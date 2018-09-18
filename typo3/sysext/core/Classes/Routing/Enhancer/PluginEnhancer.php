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
use TYPO3\CMS\Core\Routing\Mapper\Mappable;
use TYPO3\CMS\Core\Routing\Route;

/**
 * Used for plugins like EXT:felogin.
 *
 * - type: PluginEnhancer
 *   routePath: '/{controller}/{action}/'
 *   requirements:
 *     controller: '[A-z]*'
 *     action: '[A-z]*'
 *   namespace: "tx_blogexample_pi1"
 */
class PluginEnhancer
{
    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var Mappable[]
     */
    protected $mappers;

    /**
     * @var string
     */
    protected $namespace;

    public function __construct(array $configuration, array $mappers)
    {
        $this->configuration = $configuration;
        $this->mappers = $mappers;
        $this->namespace = $this->configuration['namespace'] ?? '';
    }

    /**
     * Used when a URL is matched.
     * @param RouteCollection $collection
     */
    public function addVariants(RouteCollection $collection)
    {
        $routePath = $this->getNamespacedRoutePath();
        $defaultPageRoute = $collection->get('default');
        $variant = clone $defaultPageRoute;
        $variant->setPath(rtrim($variant->getPath(), '/') . '/' . ltrim($routePath, '/'));
        $variant->addOptions(['enhancer' => $this]);
        if ($this->configuration['requirements']) {
            $variant->addRequirements($this->getNamespacedRequirements());
        }
        $collection->add('enhancer_' . $this->namespace . spl_object_hash($variant), $variant);
    }

    /**
     * If the route enhancers contains non-default parameters, they NEED to be cloned.
     *
     * @param Route $route
     * @return Route
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
        $routePath = $this->configuration['routePath'];
        $routePath = str_replace('{', '{' . $this->namespace . '_', $routePath);
        return $routePath;
    }

    protected function getNamespacedRequirements()
    {
        $requirements = [];
        foreach ($this->configuration['requirements'] as $name => $value) {
            $requirements[$this->namespace . '_' . $name] = $value;
        }
        return $requirements;
    }

    public function flattenParameters(array $parameters)
    {
        if (empty($this->namespace)) {
            return $parameters;
        }
        if (isset($parameters[$this->namespace])) {
            $newParameters = [];
            foreach ($parameters as $name => $v) {
                if ($name === $this->namespace) {
                    if (is_array($v)) {
                        foreach ($v as $k2 => $v2) {
                            $newParameters[$this->namespace . '_' . $k2] = $v2;
                        }
                    } else {
                        $newParameters[$this->namespace . '_' . $name] = $v;
                    }
                    continue;
                }
                $newParameters[$name] = $v;
            }
            return $newParameters;
        }
        return $parameters;
    }

    public function unflattenParameters($parameters)
    {
        if (empty($this->namespace)) {
            return $parameters;
        }
        $newParameters = [];
        foreach ($parameters as $name => $v) {
            if ($name !== $this->namespace && strpos($name, $this->namespace) === 0) {
                $name = substr($name, strlen($this->namespace)+1);
                $newParameters[$this->namespace][$name] = $v;
                continue;
            }
            $newParameters[$name] = $v;
        }
        return $newParameters;
    }
}
