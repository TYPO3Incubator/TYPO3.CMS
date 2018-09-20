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
    protected $apectFactoryClassNames = [];

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
        $this->apectFactoryClassNames = [
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
                ['page' => $page],
                ['tail' => '.*'],
                ['utf8' => true]
            );
            $pageCollection->add('default', $defaultRouteForPage);

            foreach ($this->getSuitableEnhancersForPage($pageIdForDefaultLanguage, $factories) as $enhancer) {
                $enhancer->enhance($pageCollection);
            }

            $pageCollection->addNamePrefix('page_' . $page['uid'] . '_');
            $fullCollection->addCollection($pageCollection);
        }

        $context = new RequestContext('/', $request->getMethod(), $request->getUri()->getHost());
        $matcher = new PageUriMatcher($fullCollection, $context);
        try {
            $result = $matcher->match('/' . trim($result->getTail(), '/'));
            $matchedRoute = $fullCollection->get($result['_route']);
            if ($matchedRoute->hasOption('enhancer')) {
                $enhancer = $matchedRoute->getOption('enhancer');
                if (method_exists($enhancer, 'unflattenParameters')) {
                    $result = $enhancer->unflattenParameters($result);
                }
            }
            return new RouteResult($request->getUri(), $this->site, $language, $result['tail'] ?? '', $result);
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
    public function generate(int $pageId, SiteLanguage $language, array $parameters = [], string $fragment = '', string $type = ''): UriInterface
    {
        $originalParameters = $parameters;
        $collection = new RouteCollection();
        $page = GeneralUtility::makeInstance(PageRepository::class)->getPage($pageId, true);
        $pagePath = ltrim($page['slug'], '/');
        $defaultRouteForPage = new Route(
            '/' . $pagePath,
            ['page' => $page],
            [],
            ['utf8' => true]
        );
        $collection->add('default', $defaultRouteForPage);

        $factories = $this->buildAspectFactories($language);
        foreach ($this->getSuitableEnhancersForPage($pageId, $factories) as $enhancer) {
            $enhancer->enhance($collection);
        }

        $filteredRoutes = new RouteCollection();
        foreach ($collection->all() as $routeName => $route) {
            #$parameters = $originalParameters;
            $compiledRoute = $route->compile();

            if ($route->hasOption('enhancer')) {
                $enhancer = $route->getOption('enhancer');
                $parameters = $enhancer->flattenParameters($parameters);
                if (!$enhancer->verifyRequiredParameters($route, $parameters)) {
                    continue;
                }
            }
            $variables = array_flip($compiledRoute->getPathVariables());
            $mergedParams = array_replace($route->getDefaults(), $parameters);

            // all params must be given, otherwise we exclude this possibility
            if ($diff = array_diff_key($variables, $mergedParams)) {
                continue;
            }
            $filteredRoutes->add($routeName, $route);
        }

        if (isset($parameters['tx_felogin_pi1'])) {
            var_dump($filteredRoutes->all());
            var_dump($parameters);
            exit;
        }


        $context = new RequestContext(
            $language->getBase()->getPath(),
            'GET',
            $language->getBase()->getHost(),
            $language->getBase()->getScheme() ?? ''
        );
        $parameters['_fragment'] = $fragment;
        $generator = new UrlGenerator($filteredRoutes, $context);
        $generator->setStrictRequirements(true);
        $allRoutes = $filteredRoutes->all();
        $allRoutes = array_reverse($allRoutes, true);
        $matchedRoute = null;
        foreach ($allRoutes as $routeName => $route) {
            try {
                $result = $generator->generate($routeName, $parameters, $type);
                $matchedRoute = $collection->get($routeName);
                break;
            } catch (MissingMandatoryParametersException $e) {
                // no match
            }
        }
        $uri = new Uri($result);
        if ($matchedRoute && $uri->getQuery()) {
            $queryParams = [];
            parse_str($uri->getQuery(), $queryParams);
            if ($matchedRoute->hasOption('enhancer')) {
                $enhancer = $matchedRoute->getOption('enhancer');
                if (method_exists($enhancer, 'unflattenParameters')) {
                    $queryParams = $enhancer->unflattenParameters($queryParams);
                }
            }
            $cacheHashCalculator = new CacheHashCalculator();
            $uri = $uri->withQuery(http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986));
        }
        return $uri;
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $routePathTail
     * @param Site $site
     * @param SiteLanguage $language
     * @return RouteResult|null
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
                ['page' => $page, 'tail' => ''],
                ['tail' => '.*'],
                ['utf8' => true]
            );
            $collection->add('page_' . $page['uid'], $route);
        }

        $context = new RequestContext('/', $request->getMethod(), $request->getUri()->getHost());
        $matcher = new PageUriMatcher($collection, $context);
        try {
            $result = $matcher->match('/' . ltrim($routePathTail, '/'));
            unset($result['_route']);
            return new RouteResult($request->getUri(), $site, $language, $result['tail'], $result);
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

    protected function getSuitableEnhancersForPage(int $pageId, array $factories): \Generator
    {
        foreach ($this->configuration['routingEnhancers'] as $enhancerConfiguration) {
            // Check if there is a restriction to page Ids.
            if (is_array($enhancerConfiguration['limitToPages']) && !in_array($pageId, $enhancerConfiguration['limitToPages'])) {
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

                return $this->findApectFactory($type, $factories)->build($settings);
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
            $this->apectFactoryClassNames
        );
    }

    /**
     * @param string $name
     * @param AbstractAspectFactory[] $factories
     * @return AbstractAspectFactory
     */
    protected function findApectFactory(string $name, array $factories): AbstractAspectFactory
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
}
