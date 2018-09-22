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
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Core\Routing\Traits\AspectsAwareTrait;
use TYPO3\CMS\Extbase\Reflection\ClassSchema;

/**
 * Resolves a static list (like page.typeNum) against a file pattern. Usually added on the very last part
 * of
 * $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName]['controllers'][$controllerName] = ['actions' => \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $actionsList)];
 *
 * a typical configuration looks like this:
 * type: ExtbasePluginEnhancer
 * vendor: 'GeorgRinger'
 * extension: 'News'
 * plugin: 'Pi1'
 * routes:
 *   - { routePath: '/blog/{page}', '_controller': 'News::list' }
 *   - { routePath: '/blog/{slug}', '_controller': 'News::detail' }
 * requirements:
 *   page: { regexp: '[0-9]+'}
 *   slug: { regexp: '.*', resolver: 'SlugResolver', tableName: 'tx_news_domain_model_news', fieldName: 'path_segment' }
 */
class ExtbasePluginEnhancer extends PluginEnhancer
{
    use AspectsAwareTrait;

    /**
     * @var array
     */
    protected $routesOfPlugin;

    public function __construct(array $configuration)
    {
        parent::__construct($configuration);
        $extensionName = $this->configuration['extension'];
        $pluginName = $this->configuration['plugin'];
        $extensionName = str_replace(' ', '', ucwords(str_replace('_', ' ', $extensionName)));
        $pluginSignature = strtolower($extensionName . '_' . $pluginName);
        $this->namespace = 'tx_' . $pluginSignature;
        $this->routesOfPlugin = $this->configuration['routes'] ?? [];
        return;
        // we should do this somewhere else.
        $controllersActions = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName]['controllers'];
        $routesOfPlugin = [];
        foreach ($controllersActions as $controllerName => $actions) {
            $controllerName = ucfirst($controllerName);
            $className = $this->configuration['vendor'] . '\\' . $extensionName . '\\Controller\\' . ucfirst($controllerName) . 'Controller';
            foreach ($actions['actions'] as $action) {
                $methodName = $action . 'Action';
                $cls = new ClassSchema($className);
                $identifier = ucfirst($controllerName) . '::' . $action;
                $routesOfPlugin[$identifier] = [
                    '_controller' => $controllerName . '::' . $action,
                    'routePath' => '/' . strtolower($controllerName) . '-' . strtolower($action)
                ];
                foreach ($cls->getMethod($methodName)['params'] as $argumentName => $argument) {
                    $routesOfPlugin[$identifier]['routePath'][$argument['position']] = [
                        'name' => $argumentName,
                        'type' => $argument['type'],
                        'optional' => $argument['optional'],
                        'nullable' => $argument['nullable'],
                        'defaultValue' => $argument['defaultValue']
                    ];
                }
            }
        }
        $this->map = $map;
    }

    /**
     * Used when a URL is matched.
     * @param RouteCollection $collection
     */
    public function enhance(RouteCollection $collection): void
    {
        $i = 0;
        /** @var Route $defaultPageRoute */
        $defaultPageRoute = $collection->get('default');
        foreach ($this->routesOfPlugin as $routeDefinition) {
            $route = $this->getVariant($defaultPageRoute, $routeDefinition);
            $collection->add($this->namespace . '_' . $i++, $route);
        }
    }

    protected function getVariant(Route $defaultPageRoute, array $routeDefinition): Route
    {
        $arguments = $routeDefinition['_arguments'] ?? [];
        unset($routeDefinition['_arguments']);

        $namespacedRequirements = $this->getNamespacedRequirements();
        $routePath = $this->modifyRoutePath($routeDefinition['routePath']);
        $routePath = $this->getVariableProcessor()->deflateRoutePath($routePath, $arguments, $this->namespace);
        unset($routeDefinition['routePath']);
        $defaults = array_merge_recursive($defaultPageRoute->getDefaults(), $routeDefinition);
        $options = array_merge($defaultPageRoute->getOptions(), ['_enhancer' => $this, 'utf8' => true, '_arguments' => $arguments]);
        $route = new Route(rtrim($defaultPageRoute->getPath(), '/') . '/' . ltrim($routePath, '/'), $defaults, [], $options);
        $this->applyRouteAspects($route, $this->aspects ?? [], $this->namespace);
        if ($namespacedRequirements) {
            $compiledRoute = $route->compile();
            $variables = $compiledRoute->getPathVariables();
            $variables = array_flip($variables);
            $requirements = array_filter($namespacedRequirements, function($key) use ($variables) {
                return isset($variables[$key]);
            }, ARRAY_FILTER_USE_KEY);
            if (!empty($requirements)) {
                $route->addRequirements($requirements);
            }
        }
        return $route;
    }

    public function addRoutesThatMeetTheRequirements(RouteCollection $collection, array $originalParameters)
    {
        if (!is_array($originalParameters[$this->namespace] ?? null)) {
            return;
        }
        // apply default controller and action names if not set in parameters
        if (!$this->hasControllerActionValues($originalParameters[$this->namespace])
            && !empty($this->configuration['defaultController'])
        ) {
            $this->applyControllerActionValues(
                $this->configuration['defaultController'],
                $originalParameters[$this->namespace]
            );
        }

        $i = 0;
        /** @var Route $defaultPageRoute */
        $defaultPageRoute = $collection->get('default');
        foreach ($this->routesOfPlugin as $routeDefinition) {
            $variant = $this->getVariant($defaultPageRoute, $routeDefinition);
            // The enhancer tells us: This given route does not match the parameters
            if (!$this->verifyRequiredParameters($variant, $originalParameters)) {
                continue;
            }
            $parameters = $originalParameters;
            unset($parameters[$this->namespace]['action']);
            unset($parameters[$this->namespace]['controller']);
            $compiledRoute = $variant->compile();
            $flattenedParameters = $this->getVariableProcessor()->deflateParameters($parameters, $variant->getArguments(), $this->namespace);
            $variables = array_flip($compiledRoute->getPathVariables());
            $mergedParams = array_replace($variant->getDefaults(), $flattenedParameters);
            // all params must be given, otherwise we exclude this variant
            if ($diff = array_diff_key($variables, $mergedParams)) {
                continue;
            }
            $variant->addOptions(['flattenedParameters' => $flattenedParameters]);
            $collection->add($this->namespace . '_' . $i++, $variant);
        }
    }

    /**
     * A route has matched the controller/action combination, so ensure that these properties
     * are set to tx_blogexample_pi1[controller] and tx_blogexample_pi1[action].
     *
     * @param array $parameters Actual parameter payload to be used
     * @param array $internals Internal instructions (_route, _controller, ...)
     * @return array
     */
    public function inflateParameters(array $parameters, array $internals = []): array
    {
        $parameters = $this->getVariableProcessor()
            ->inflateParameters($parameters, [], $this->namespace);
        // Invalid if there is no controller given, so this enhancers does not do anything
        if (empty($internals['_controller'] ?? null)) {
            return $parameters;
        }
        $this->applyControllerActionValues(
            $internals['_controller'],
            $parameters[$this->namespace]
        );
        return $parameters;
    }

    /**
     * Check if controller+action combination matches
     *
     * @param Route $route
     * @param array $parameters
     * @return bool
     */
    protected function verifyRequiredParameters(Route $route, array $parameters) {
        if (!is_array($parameters[$this->namespace])) {
            return false;
        }
        if (!$route->hasDefault('_controller')) {
            return false;
        }
        $controller = $route->getDefault('_controller');
        list($controllerName, $actionName) = explode('::', $controller);
        if ($controllerName !== $parameters[$this->namespace]['controller']) {
            return false;
        }
        if ($actionName !== $parameters[$this->namespace]['action']) {
            return false;
        }
        return true;
    }

    protected function hasControllerActionValues(array $target): bool
    {
        return (!empty($target['controller']) && !empty($target['action']));
    }

    protected function applyControllerActionValues(string $controllerActionValue, array &$target)
    {
        if (strpos($controllerActionValue, '::') === false) {
            return;
        }
        list($controllerName, $actionName) = explode('::', $controllerActionValue, 2);
        $target['controller'] = $controllerName;
        $target['action'] = $actionName;
    }
}
