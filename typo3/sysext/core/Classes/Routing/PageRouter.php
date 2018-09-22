<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Doctrine\DBAL\Connection;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendWorkspaceRestriction;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\Aspect\MappableProcessor;
use TYPO3\CMS\Core\Routing\Enhancer\AbstractEnhancer;
use TYPO3\CMS\Core\Routing\Enhancer\ExtbasePluginEnhancer;
use TYPO3\CMS\Core\Routing\Enhancer\PageTypeEnhancer;
use TYPO3\CMS\Core\Routing\Enhancer\PluginEnhancer;
use TYPO3\CMS\Core\Routing\Aspect\AbstractAspectFactory;
use TYPO3\CMS\Core\Routing\Aspect\Mappable;
use TYPO3\CMS\Core\Routing\Aspect\AspectFactory;
use TYPO3\CMS\Core\Routing\Traits\AspectsAwareTrait;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Page Router looking up the slug of the page path.
 *
 * This is done via the "Route Candidate" pattern.
 *
 * Example:
 * - /about-us/team/management/
 *
 * will look for all pages that have
 * - /about-us
 * - /about-us/
 * - /about-us/team
 * - /about-us/team/
 * - /about-us/team/management
 * - /about-us/team/management/
 *
 * And create route candidates for that.
 *
 * Please note: PageRouter does not restrict the HTTP method or is bound to any domain constraints,
 * as the SiteMatcher has done that already.
 *
 * The concept of the PageRouter is to *resolve*, and not build URIs. On top, it is a facade to hide the
 * dependency to symfony and to not expose its logic.

 * @internal This API is not public yet and might change in the future, until TYPO3 v9 or TYPO3 v10.
 */
class PageRouter
{
    /**
     * @var Site
     */
    protected $site;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var string[]
     */
    protected $aspectFactoryClassNames = [];

    /**
     * PageRouter constructor.
     * @param Site $site
     * @param array $configuration
     */
    public function __construct(Site $site, array $configuration)
    {
        $this->site = $site;
        $this->configuration = $configuration;

        // @todo Move to factory collection/registry
        $this->aspectFactoryClassNames = [
            AspectFactory::class
        ];
    }

    /**
     * Matches against a request.
     *
     * @param ServerRequestInterface $request
     * @param SiteLanguage $language
     * @param RouteResult $result
     * @return RouteResult|null
     */
    public function matchRequest(ServerRequestInterface $request, SiteLanguage $language, RouteResult $result): ?RouteResult
    {
        $slugCandidates = $this->getCandidateSlugsFromRoutePath($result->getTail());
        if (empty($slugCandidates)) {
            return null;
        }

        // @todo Match page -> Route, then match Enhancers ("page takes precendence")

        $pageCandidates = $this->getPagesFromDatabaseForCandidates($slugCandidates, $this->site, $language->getLanguageId());
        // Stop if there are no candidates
        if (empty($pageCandidates)) {
            return null;
        }

        $fullCollection = new RouteCollection();
        $factories = $this->buildAspectFactories($language);

        foreach ($pageCandidates ?? [] as $page) {
            $pageIdForDefaultLanguage = (int)($page['l10n_parent'] ?: $page['uid']);
            $pagePath = $page['slug'];
            $pageCollection = new RouteCollection();
            $defaultRouteForPage = new Route(
                $pagePath,
                [],
                ['tail' => '.*'],
                ['utf8' => true, '_page' => $page]
            );
            $pageCollection->add('default', $defaultRouteForPage);

            foreach ($this->getSuitableEnhancersForPage($pageIdForDefaultLanguage, $factories) as $enhancer) {
                $enhancer->enhance($pageCollection);
            }

            $pageCollection->addNamePrefix('page_' . $page['uid'] . '_');
            $fullCollection->addCollection($pageCollection);
        }

        $mappableProcessor = new MappableProcessor();
        $context = new RequestContext('/', $request->getMethod(), $request->getUri()->getHost());
        $matcher = new PageUriMatcher($fullCollection, $context, $mappableProcessor);
        try {
            $result = $matcher->match('/' . trim($result->getTail(), '/'));
            /** @var Route $matchedRoute */
            $matchedRoute = $fullCollection->get($result['_route']);
            return $this->buildRouteResult($request, $this->site, $language, $matchedRoute, $result);
        } catch (ResourceNotFoundException $e) {
            // do nothing
        }
        return new RouteResult($request->getUri(), $this->site, $language);
    }

    /**
     * @param int $pageId
     * @param SiteLanguage $language
     * @param array $parameters
     * @param string $fragment
     * @param string $type
     * @return UriInterface
     */
    public function generate(int $pageId, SiteLanguage $language, array $parameters = [], string $fragment = '', string $type = ''): ?UriInterface
    {
        $originalParameters = $parameters;
        $collection = new RouteCollection();
        $page = GeneralUtility::makeInstance(PageRepository::class)->getPage($pageId, true);
        $pagePath = ltrim($page['slug'], '/');
        $defaultRouteForPage = new Route(
            '/' . $pagePath,
            [],
            [],
            ['utf8' => true, '_page' => $page]
        );
        $collection->add('default', $defaultRouteForPage);

        // @todo: this should be built in a way that cHash is not generated for incoming links
        // because this is built inside this very method.
        unset($originalParameters['cHash']);
        $factories = $this->buildAspectFactories($language);
        foreach ($this->getSuitableEnhancersForPage($pageId, $factories) as $enhancer) {
            $enhancer->addRoutesThatMeetTheRequirements($collection, $originalParameters);
        }

        $scheme = $language->getBase()->getScheme();
        $mappableProcessor = new MappableProcessor();
        $context = new RequestContext(
            // page segment (slug & enhanced part) is supposed to start with '/'
            rtrim($language->getBase()->getPath(), '/'),
            'GET',
            $language->getBase()->getHost(),
            $scheme ?: 'http',
            $scheme === 'http' ? $language->getBase()->getPort() ?? 80 : 80,
            $scheme === 'https' ? $language->getBase()->getPort() ?? 443 : 443
        );
        $generator = new UrlGenerator($collection, $context);
        $allRoutes = $collection->all();
        $allRoutes = array_reverse($allRoutes, true);
        $matchedRoute = null;
        $routeArguments = null;
        $uri = null;
        /**
         * @var string $routeName
         * @var Route $route
         */
        foreach ($allRoutes as $routeName => $route) {
            try {
                $parameters = $originalParameters;
                if ($route->hasOption('deflatedParameters')) {
                    $parameters = $route->getOption('deflatedParameters');
                }
                $mappableProcessor->generate($route, $parameters);
                $urlAsString = $generator->generate($routeName, $parameters, $type);
                $uri = new Uri($urlAsString);
                /** @var Route $matchedRoute */
                $matchedRoute = $collection->get($routeName);
                parse_str($uri->getQuery() ?? '', $remainingQueryParameters);
                $routeArguments = $this->buildRouteArguments($route, $parameters, $remainingQueryParameters);
                break;
            } catch (MissingMandatoryParametersException $e) {
                // no match
            }
        }

        if ($routeArguments && $routeArguments->areDirty()) {
            // for generating URLs this should(!) never happen
            // if it does happen, generator logic has flaws
            throw new \LogicException('Route arguments are dirty', 1537613247);
        }

        if ($matchedRoute && $routeArguments && $uri instanceof UriInterface && $uri->getQuery()) {
            $signer = new PageRouteSigner();
            $cacheHash = $signer->getCacheHash($pageId, $routeArguments);

            if (!empty($cacheHash)) {
                $queryArguments = $routeArguments->getQueryArguments();
                $queryArguments['cHash'] = $cacheHash;
                $uri = $uri->withQuery(http_build_query($queryArguments, '', '&', PHP_QUERY_RFC3986));
            }

            // @todo Check remaining query parameters again, e.g. when cHash was provided already
        }
        if ($uri instanceof UriInterface) {
            $uri = $uri->withFragment($fragment);
        }
        return $uri;
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $routePathTail
     * @param Site $site
     * @param SiteLanguage $language
     * @return RouteResult|null
     * @deprecated Probably not required any more, correct?
     */
    public function matchRoute(ServerRequestInterface $request, string $routePathTail, Site $site, SiteLanguage $language): ?RouteResult
    {
        $slugCandidates = $this->getCandidateSlugsFromRoutePath($routePathTail);
        if (empty($slugCandidates)) {
            return null;
        }
        $pageCandidates = $this->getPagesFromDatabaseForCandidates($slugCandidates, $site, $language->getLanguageId());
        // Stop if there are no candidates
        if (empty($pageCandidates)) {
            return null;
        }

        $collection = new RouteCollection();
        foreach ($pageCandidates ?? [] as $page) {
            $path = $page['slug'];
            $route = new Route(
                $path . '{tail}',
                ['tail' => ''],
                ['tail' => '.*'],
                ['utf8' => true, '_page' => $page]
            );
            $collection->add('page_' . $page['uid'], $route);
        }

        $mappableProcessor = new MappableProcessor();
        $context = new RequestContext('/', $request->getMethod(), $request->getUri()->getHost());
        $matcher = new PageUriMatcher($collection, $context, $mappableProcessor);
        try {
            $result = $matcher->match('/' . ltrim($routePathTail, '/'));
            /** @var Route $matchedRoute */
            $matchedRoute = $collection->get($result['_route']);
            return $this->buildRouteResult($request, $site, $language, $matchedRoute, $result);
        } catch (ResourceNotFoundException $e) {
            // do nothing
        }
        return new RouteResult($request->getUri(), $site, $language);
    }

    /**
     * Check for records in the database which matches one of the slug candidates.
     *
     * @param array $slugCandidates
     * @param Site $site
     * @param int $languageId
     * @return array
     */
    protected function getPagesFromDatabaseForCandidates(array $slugCandidates, Site $site, int $languageId): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(FrontendWorkspaceRestriction::class));

        $statement = $queryBuilder
            ->select('uid', 'l10n_parent', 'pid', 'slug')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageId, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->in(
                    'slug',
                    $queryBuilder->createNamedParameter(
                        $slugCandidates,
                        Connection::PARAM_STR_ARRAY
                    )
                )
            )
            // Exact match will be first, that's important
            ->orderBy('slug', 'desc')
            ->execute();

        $pages = [];
        $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);
        while ($row = $statement->fetch()) {
            $pageIdInDefaultLanguage = (int)($languageId > 0 ? $row['l10n_parent'] : $row['uid']);
            if ($siteMatcher->matchByPageId($pageIdInDefaultLanguage)->getRootPageId() === $site->getRootPageId()) {
                $pages[] = $row;
            }
        }
        return $pages;
    }

    /**
     * @param int $pageId
     * @param array $factories
     * @return \Generator|AbstractEnhancer[]
     */
    protected function getSuitableEnhancersForPage(int $pageId, array $factories): \Generator
    {
        foreach ($this->configuration['routingEnhancers'] as $enhancerConfiguration) {
            // Check if there is a restriction to page Ids.
            if (is_array($enhancerConfiguration['limitToPages'] ?? null) && !in_array($pageId, $enhancerConfiguration['limitToPages'])) {
                continue;
            }
            $enhancer = null;
            switch ($enhancerConfiguration['type']) {
                case 'PageTypeEnhancer':
                    $enhancer = new PageTypeEnhancer($enhancerConfiguration);
                    break;
                case 'PluginEnhancer':
                    $enhancer = new PluginEnhancer($enhancerConfiguration);
                    break;
                case 'ExtbasePluginEnhancer':
                    $enhancer = new ExtbasePluginEnhancer($enhancerConfiguration);
            }
            if (!empty($enhancerConfiguration['aspects']) && in_array(AspectsAwareTrait::class, class_uses($enhancer), true)) {
                $aspects = $this->buildMappers($enhancerConfiguration['aspects'], $factories);
                $enhancer->setAspects($aspects);
            }
            if ($enhancer !== null) {
                yield $enhancer;
            }
        }
    }

    /**
     * @param array $aspects
     * @param AbstractAspectFactory[] $factories
     * @return Mappable[]
     */
    protected function buildMappers(array $aspects, array $factories): array
    {
        return array_map(
            function ($settings) use ($factories) {
                $type = (string)($settings['type'] ?? '');

                if (empty($type)) {
                    throw new \LogicException(
                        'Mapper type cannot be empty',
                        1537298184
                    );
                }

                return $this->findAspectFactory($type, $factories)->build($settings);
            },
            $aspects
        );
    }

    /**
     * @param SiteLanguage $language
     * @return AbstractAspectFactory[]
     */
    protected function buildAspectFactories(SiteLanguage $language)
    {
        return array_map(
            function (string $className) use ($language) {
                return new $className($this->site, $language);
            },
            $this->aspectFactoryClassNames
        );
    }

    /**
     * @param string $name
     * @param AbstractAspectFactory[] $factories
     * @return AbstractAspectFactory
     */
    protected function findAspectFactory(string $name, array $factories): AbstractAspectFactory
    {
        $factories = array_filter(
            $factories,
            function (AbstractAspectFactory $factory) use ($name) {
                return in_array($name, $factory->builds(), true);
            }
        );
        if (empty($factories)) {
            throw new \LogicException(
                sprintf('No mapper factories found for %s', $name),
                1537298185
            );
        }
        if (count($factories) > 1) {
            throw new \LogicException(
                sprintf('Multiple mapper factories found for %s', $name),
                1537298185
            );
        }
        return $factories[0];
    }

    /**
     * Returns possible URL parts for a string like /home/about-us/offices/
     * to return
     * /home/about-us/offices.json
     * /home/about-us/offices/
     * /home/about-us/offices
     * /home/about-us/
     * /home/about-us
     * /home/
     * /home
     *
     * @param string $routePath
     * @return array
     */
    protected function getCandidateSlugsFromRoutePath(string $routePath): array
    {
        $candidatePathParts = [];
        $pathParts = GeneralUtility::trimExplode('/', $routePath, true);
        if (empty($pathParts)) {
            return ['/'];
        }
        // Check if the last part contains a ".", then split it
        $lastPart = array_pop($pathParts);
        if (strpos($lastPart, '.') !== false) {
            $pathParts = array_merge($pathParts, explode('.', $lastPart));
        } else {
            $pathParts[] = $lastPart;
        }

        while (!empty($pathParts)) {
            $prefix = '/' . implode('/', $pathParts);
            $candidatePathParts[] = $prefix . '/';
            $candidatePathParts[] = $prefix;
            array_pop($pathParts);
        }
        return $candidatePathParts;
    }

    /**
     * @param ServerRequestInterface $request
     * @param Site $site
     * @param SiteLanguage|null $language
     * @param Route|null $route
     * @param array $results
     * @return RouteResult
     */
    protected function buildRouteResult(ServerRequestInterface $request, Site $site, SiteLanguage $language = null, Route $route = null, array $results = []): RouteResult
    {
        $data = [];
        if (!empty($route)) {
            // internal values, like _route, _controller, etc.
            $data['internals'] = $this->filterInternalParameters(
                $route,
                $results
            );
            // page route arguments separated by static and dynamic values
            $data['pageRouteArguments'] = $this->buildRouteArguments(
                $route,
                $results,
                $request->getQueryParams()
            );
            // page record the route has been applied for
            if ($route->hasOption('_page')) {
                $data['page'] = $route->getOption('_page');
            }
        }
        $tail = $results['tail'] ?? '';
        return new RouteResult($request->getUri(), $site, $language, $tail, $data);
    }

    /**
     * @param Route $route
     * @param array $results
     * @param array $remainingQueryParameters
     * @return PageRouteArguments
     */
    protected function buildRouteArguments(Route $route, array $results, array $remainingQueryParameters = []): PageRouteArguments
    {
        $enhancer = $route->getEnhancer();
        if ($enhancer === null) {
            $routeArguments = $this->filterProcessedParameters($route, $results);
            return (new PageRouteArguments($routeArguments))
                ->withQueryArguments($remainingQueryParameters);
        }
        if ($enhancer !== null) {
            $arguments = $enhancer->buildRouteArguments($route, $results, $remainingQueryParameters);
        }
        return $arguments;
    }

    protected function filterProcessedParameters(Route $route, $results): array
    {
        // determine those parameters that have been processed
        $parameters = array_intersect_key(
            $results,
            array_flip($route->compile()->getPathVariables())
        );
        return $parameters;
    }

    protected function filterInternalParameters(Route $route, $results): array
    {
        // strip those parameters that have not been processed
        $internals = array_diff_key(
            $results,
            array_flip($route->compile()->getPathVariables())
        );
        return $internals;
    }
}
