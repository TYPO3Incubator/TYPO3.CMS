<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Enhancer;

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

use Symfony\Component\Routing\RouteCollection;
use TYPO3\CMS\Core\Routing\Aspect\Mappable;

/**
 * Resolves a static list (like page.typeNum) against a file pattern. Usually added on the very last part
 * of the URL.
 *
 * type: PageTypeEnhancer
 *   routePath: '{type}'
 *   requirements:
 *     type: '.json|.html'
 */
class PageTypeEnhancer
{
    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var Mappable[]
     */
    protected $mappers;

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Used when a URL is matched.
     * @param RouteCollection $collection
     */
    public function enhance(RouteCollection $collection)
    {
        foreach ($collection->all() as $routeName => $existingRoute) {
            $variant = clone $existingRoute;
            $variant->setPath(rtrim($variant->getPath(), '/') . $this->configuration['routePath']);
            $variant->addDefaults(['type' => 0]);
            $variant->addRequirements($this->configuration['requirements'] ?? ['type' => '.*']);
            $collection->add($routeName . '_typeNum_' . spl_object_hash($variant), $variant);
        }
    }

    public function addRoutesThatMeetTheRequirements(RouteCollection $collection, array $parameters)
    {
        if (!$parameters['type']) {
            return;
        }

    }


    public function flattenParameters(array $parameters): array
    {
        return $parameters;
    }

    public function unflattenParameters(array $parameters): array
    {
        return $parameters;
    }
}
