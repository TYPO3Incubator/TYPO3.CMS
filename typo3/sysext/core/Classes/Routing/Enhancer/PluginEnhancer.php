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
use TYPO3\CMS\Core\Routing\Aspect\Modifiable;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Core\Routing\Traits\AspectsAwareTrait;

/**
 * Used for plugins like EXT:felogin.
 *
 * - type: PluginEnhancer
 *   routePath: '/forgot-pw/{user-id}/{hash}/'
 *   requirements:
 *     user-id: '[A-z]*'
 *     hash: '[A-z]{0-6}'
 *   namespace: "tx_felogin_pi1"
 */
class PluginEnhancer extends AbstractEnhancer
{
    use AspectsAwareTrait;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $namespace;

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
        $this->namespace = $this->configuration['namespace'] ?? '';
    }

    /**
     * Used when a URL is matched.
     * @param RouteCollection $collection
     */
    public function enhance(RouteCollection $collection)
    {
        /** @var Route $defaultPageRoute */
        $defaultPageRoute = $collection->get('default');
        $variant = $this->getVariant($defaultPageRoute, $this->configuration);
        $collection->add('enhancer_' . $this->namespace . spl_object_hash($variant), $variant);
    }

    protected function getVariant(Route $defaultPageRoute, array $configuration): Route
    {
        $arguments = $configuration['_arguments'] ?? [];
        unset($configuration['_arguments']);

        $routePath = $this->modifyRoutePath($configuration['routePath']);
        $routePath = $this->getVariableProcessor()->deflateRoutePath($routePath, $arguments, $this->namespace);
        $variant = clone $defaultPageRoute;
        $variant->setPath(rtrim($variant->getPath(), '/') . '/' . ltrim($routePath, '/'));
        $variant->addOptions(['enhancer' => $this, '_arguments' => $arguments]);
        $this->applyRouteAspects($variant, $this->aspects ?? [], $this->namespace);
        if ($configuration['requirements']) {
            $variant->addRequirements($this->getNamespacedRequirements());
        }
        return $variant;
    }

    public function addRoutesThatMeetTheRequirements(RouteCollection $collection, array $parameters)
    {
        // No parameter for this namespace given, so this route does not fit the requirements
        if (!is_array($parameters[$this->namespace])) {
            return;
        }
        /** @var Route $defaultPageRoute */
        $defaultPageRoute = $collection->get('default');
        $variant = $this->getVariant($defaultPageRoute, $this->configuration);
        $compiledRoute = $variant->compile();
        $flattenedParameters = $this->getVariableProcessor()->deflateParameters($parameters, $variant->getArguments(), $this->namespace);
        $variables = array_flip($compiledRoute->getPathVariables());
        $mergedParams = array_replace($variant->getDefaults(), $flattenedParameters);
        // all params must be given, otherwise we exclude this variant
        if ($diff = array_diff_key($variables, $mergedParams)) {
            return;
        }
        $variant->addOptions(['flattenedParameters' => $flattenedParameters]);
        $collection->add('enhancer_' . $this->namespace . spl_object_hash($variant), $variant);
    }



    protected function modifyRoutePath(string $routePath): string
    {
        $substitutes = [];
        foreach ($this->aspects as $variableName => $aspect) {
            if (!$aspect instanceof Modifiable) {
                continue;
            }
            $value = $aspect->retrieve();
            if ($value !== null) {
                $substitutes['{' . $variableName . '}'] = $value;
            }
        }
        return str_replace(
            array_keys($substitutes),
            array_values($substitutes),
            $routePath
        );
    }

    protected function getNamespacedRequirements()
    {
        $requirements = [];
        foreach ($this->configuration['requirements'] as $name => $value) {
            $requirements[$this->namespace . '_' . $name] = $value;
        }
        return $requirements;
    }

    /**
     * @param Route $route
     * @param array $parameters
     * @return array
     */
    public function inflateParameters(Route $route, array $parameters): array
    {
        return $this->getVariableProcessor()
            ->inflateParameters($parameters, $route->getArguments(), $this->namespace);
    }
}
