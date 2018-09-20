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
        $this->namespace = 'tx_' . strtolower($extensionName) . '_' . strtolower($pluginName);
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
    public function enhance(RouteCollection $collection)
    {
        $i = 0;
        $defaultPageRoute = $collection->get('default');
        foreach ($this->routesOfPlugin as $routeDefinition) {
            $routePath = $this->getNamespacedRoutePath($routeDefinition['routePath']);
            $routePath = $this->modifyRoutePath($routePath);
            unset($routeDefinition['routePath']);
            $defaults = array_merge_recursive($defaultPageRoute->getDefaults(), $routeDefinition);
            $options = ['enhancer' => $this, 'utf8' => true];
            $route = new Route(rtrim($defaultPageRoute->getPath(), '/') . '/' . ltrim($routePath, '/'), $defaults, [], $options);
            $route->setAspects($this->aspects ?? []);
            if ($this->configuration['requirements']) {
                $route->addRequirements($this->getNamespacedRequirements());
            }
            $collection->add($this->namespace . '_' . $i++, $route);
        }
    }

    /**
     * A route has matched the controller/action combination, so ensure that these properties
     * are set to tx_blogexample_pi1[controller] and tx_blogexample_pi1[action].
     * @param $parameters
     * @return array
     */
    public function unflattenParameters(array $parameters): array
    {
        // Invalid if there is no controller given, so this enhancers does not do anything
        if (empty($parameters['_controller'] ?? null)) {
            return $parameters;
        }
        list($controllerName, $actionName) = explode('::', $parameters['_controller']);
        $parameters[$this->namespace]['controller'] = $controllerName;
        $parameters[$this->namespace]['action'] = $actionName;
        $parameters = parent::unflattenParameters($parameters);
        // Now put the "blog_title" back to "news" parameter
        foreach ($parameters['_arguments'] ?? [] as $argumentName => $placeholderName) {
            if (isset($parameters[$this->namespace][$placeholderName])) {
                $parameters[$this->namespace][$argumentName] = $parameters[$this->namespace][$placeholderName];
                unset($parameters[$this->namespace][$placeholderName]);
            }
        }
        return $parameters;
    }

    public function flattenParameters(array $parameters): array
    {
        // First put the "news" parameter to the placeholder name
        foreach ($parameters['_arguments'] ?? [] as $argumentName => $placeholderName) {
            if (isset($parameters[$this->namespace][$argumentName])) {
                $parameters[$this->namespace][$placeholderName] = $parameters[$this->namespace][$argumentName];
                unset($parameters[$this->namespace][$argumentName]);
            }
        }
        $parameters = parent::flattenParameters($parameters);

        return $parameters;
    }


    public function verifyRequiredParameters(Route $route, array $parameters) {
        if (empty($parameters['_controller'] ?? null)) {
            return false;
        }
        return true;
    }

}
