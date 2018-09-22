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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;

class PageRouteSigner
{
    /**
     * @var CacheHashCalculator
     */
    protected $calculator;

    /**
     * @param CacheHashCalculator|null $calculator
     */
    public function __construct(CacheHashCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? GeneralUtility::makeInstance(CacheHashCalculator::class);
    }

    public function get(int $pageId, PageRouteArguments $arguments): string
    {
        return md5(serialize([
            'cacheHash' => $this->getCacheHash($pageId, $arguments),
            'staticRouteHash' => $this->getStaticRouteHash($pageId, $arguments),
        ]));
    }

    public function getCacheHash(int $pageId, PageRouteArguments $arguments): string
    {
        if (empty($arguments->getDynamicArguments())) {
            return '';
        }

        return $this->calculator->calculateCacheHash(
            $this->getCacheHashParameters($pageId, $arguments)
        );
    }

    public function getCacheHashParameters(int $pageId, PageRouteArguments $arguments): array
    {
        if (empty($arguments->getDynamicArguments())) {
            return [];
        }

        $hashParameters = $arguments->getDynamicArguments();
        $hashParameters['id'] = $pageId;
        $uri = http_build_query($hashParameters, '', '&', PHP_QUERY_RFC3986);
        return $this->calculator->getRelevantParameters($uri);
    }

    public function getStaticRouteHash(int $pageId, PageRouteArguments $arguments): string
    {
        return md5(serialize([
            'pageId' => $pageId,
            'staticArguments' => $arguments->getStaticArguments(),
            // no encryption key, since it SHALL NOT be used externally
        ]));
    }
}
