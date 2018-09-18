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
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendWorkspaceRestriction;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\Enhancer\ExtbasePluginEnhancer;
use TYPO3\CMS\Core\Routing\Enhancer\PageTypeEnhancer;
use TYPO3\CMS\Core\Routing\Enhancer\PluginEnhancer;
use TYPO3\CMS\Core\Routing\Mapper\AbstractMapperFactory;
use TYPO3\CMS\Core\Routing\Mapper\Mappable;
use TYPO3\CMS\Core\Routing\Mapper\MapperFactory;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
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
     * @var SiteInterface
     */
    protected $site;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var AbstractMapperFactory[]
     */
    protected $mapperFactories = [];

    /**
     * PageRouter constructor.
     * @param SiteInterface $site
     * @param array $configuration
     */
    public function __construct(SiteInterface $site, array $configuration)
    {
        $this->site = $site;
        $this->configuration = $configuration;

        // @todo Move to factory collection/registry
        $this->mapperFactories = [
            new MapperFactory(),
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

            foreach ($this->getSuitableEnhancersForPage($pageIdForDefaultLanguage) as $enhancer) {
                $enhancer->enhance($pageCollection);
            }

            $pageCollection->addNamePrefix('page_' . $page['uid'] . '_');
            $fullCollection->addCollection($pageCollection);
        }

        $context = new RequestContext('/', $request->getMethod(), $request->getUri()->getHost());
        $matcher = new UrlMatcher($fullCollection, $context);
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

        foreach ($this->getSuitableEnhancersForPage($pageId) as $enhancer) {
            $enhancer->addVariants($collection);
        }

        $context = new RequestContext(
            $language->getBase()->getPath(),
            'GET',
            $language->getBase()->getHost(),
            $language->getBase()->getScheme() ?? ''
        );
        $generator = new UrlGenerator($collection, $context);
        $generator->setStrictRequirements(true);
        $parameters['_fragment'] = $fragment;
        $allRoutes = $collection->all();
        $allRoutes = array_reverse($allRoutes, true);
        foreach ($allRoutes as $routeName => $route) {
            try {
                $result = $generator->generate($routeName, $parameters, $type);
                break;
            } catch (MissingMandatoryParametersException $e) {
            }
        }
        $uri = new Uri($result);
        if ($uri->getQuery()) {
            $queryParams = [];
            parse_str($uri->getQuery(), $queryParams);
            foreach ($this->getSuitableEnhancersForPage($pageId) as $enhancer) {
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
     * @param SiteInterface $site
     * @param SiteLanguage $language
     * @return RouteResult|null
     */
    public function matchRoute(ServerRequestInterface $request, string $routePathTail, SiteInterface $site, SiteLanguage $language): ?RouteResult
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
        $matcher = new UrlMatcher($collection, $context);
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
     * @param SiteInterface $site
     * @param int $languageId
     * @return array
     */
    protected function getPagesFromDatabaseForCandidates(array $slugCandidates, SiteInterface $site, int $languageId): array
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

    protected function getSuitableEnhancersForPage(int $pageId): \Generator
    {
        foreach ($this->configuration['routingEnhancers'] as $enhancerConfiguration) {
            $mappers = $this->buildMappers($enhancerConfiguration['mappings'] ?? []);
            // @todo: Check if there is a restriction to page Ids.
            switch ($enhancerConfiguration['type']) {
                case 'PageTypeEnhancer':
                    yield new PageTypeEnhancer($enhancerConfiguration, $mappers);
                    break;
                case 'PluginEnhancer':
                    yield new PluginEnhancer($enhancerConfiguration, $mappers);
                    break;
                case 'ExtbasePluginEnhancer':
                    yield new ExtbasePluginEnhancer($enhancerConfiguration, $mappers);
            }
        }
    }

    /**
     * @param array $mappings
     * @return Mappable[]
     */
    protected function buildMappers(array $mappings): array
    {
        return array_map([$this, 'buildMapper'], $mappings);
    }

    /**
     * @param array $settings
     * @return Mappable
     */
    protected function buildMapper(array $settings): Mappable
    {
        $type = (string)($settings['type'] ?? '');

        if (empty($type)) {
            throw new \LogicException(
                'Mapper type cannot be empty',
                1537298184
            );
        }

        return $this->findMapperFactory($type)->build($type, $settings);
    }

    /**
     * @param string $name
     * @return AbstractMapperFactory
     */
    protected function findMapperFactory(string $name): AbstractMapperFactory
    {
        $factories = array_filter(
            $this->mapperFactories,
            function (AbstractMapperFactory $factory) use ($name) {
                return in_array($name, $factory->builds(), true);
            }
        );

        if (count($factories) === 1) {
            return $factories[0];
        }
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
