<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Enhancers;

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


use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Used for plugins like EXT:felogin.
 *
 * - type: PluginEnhancer
 *   routePath: '/{controller}/{action}/'
 *   requirements:
 *     controller: '[A-z]*'
 *     action: '[A-z]*'
 *   namespace: "tx_blogexample_pi1"
 *
 */
class PluginEnhancer
{
    protected $configuration;

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Used when a URL is matched.
     * @param RouteCollection $collection
     */
    public function addVariants(RouteCollection $collection)
    {
        $routePath = $this->getNamespacedRoutePath();
        foreach ($collection->all() as $existingRoute) {
            $variant = clone $existingRoute;
            $variant->setPath($variant->getPath() . $routePath);
            #$variant->addDefaults(['type' => 0]);
            if ($this->configuration['requirements']) {
                $variant->addRequirements($this->getNamespacedRequirements());
            }
            $collection->add('enhancer_' . spl_object_hash($this) . spl_object_hash($existingRoute), $variant);
        }
    }

    /**
     * If the route enhancers contains non-default parameters, they NEED to be cloned.
     *
     * @param Route $route
     */
    public function enhanceDefaultRoute(Route $route)
    {
        $newPath = rtrim($route->getPath(), '/') . $this->getNamespacedRoutePath();
        $route = clone $route;
        $route->setPath($newPath);
        $route->addRequirements($this->getNamespacedRequirements());
        return $route;
    }

    protected function getNamespacedRoutePath()
    {
        $namespace = $this->configuration['namespace'];
        $routePath = $this->configuration['routePath'];
        #$routePath = str_replace(['{', '}'], ['{' . $namespace . '%5B', '%5D}'], $routePath);
        #$routePath = str_replace(['{', '}'], ['{' . $namespace . '[', ']}'], $routePath);
        $routePath = str_replace('{', '{' . $namespace . '_', $routePath);
        return $routePath;
    }

    protected function getNamespacedRequirements()
    {
        $namespace = $this->configuration['namespace'];
        $requirements = [];
        foreach ($this->configuration['requirements'] as $name => $value) {
            $requirements[$namespace . '_' . $name] = $value;
        }
        return $requirements;
    }

    public function flattenParameters(array $parameters) {
        $namespace = $this->configuration['namespace'];
        if (isset($parameters[$namespace])) {
            $newParameters = [];
            foreach ($parameters as $name => $v) {
                if ($name === $namespace) {
                    if (is_array($v)) {
                        foreach ($v as $k2 => $v2) {
                            $newParameters[$namespace . '_' . $k2] = $v2;
                        }
                    } else {
                        $newParameters[$namespace . '_' . $name] = $v;
                    }
                    continue;
                }
                $newParameters[$name] = $v;
            }
            return $newParameters;
        }
        return $parameters;
    }

    public function unflattenParameters($parameters) {
        $namespace = $this->configuration['namespace'];
        $newParameters = [];
        foreach ($parameters as $name => $v) {
            if (strpos($name, $namespace) === 0) {
                $name = substr($name, strlen($namespace)+1);
                $newParameters[$namespace][$name] = $v;
                continue;
            }
            $newParameters[$name] = $v;
        }
        return $newParameters;
    }
}
