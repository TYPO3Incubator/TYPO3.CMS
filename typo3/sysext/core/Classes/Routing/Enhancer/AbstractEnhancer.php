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
use TYPO3\CMS\Core\Routing\Aspect\Applicable;
use TYPO3\CMS\Core\Routing\PageRouteArguments;
use TYPO3\CMS\Core\Routing\Route;

abstract class AbstractEnhancer
{
    /**
     * @var VariableProcessor
     */
    protected $variableProcessor;

    /**
     * @param RouteCollection $collection
     * @return void
     */
    abstract public function enhance(RouteCollection $collection): void;

    /**
     * @param Route $route
     * @param array $results
     * @param array $remainingQueryParameters
     * @return PageRouteArguments
     */
    abstract public function buildRouteArguments(Route $route, array $results, array $remainingQueryParameters = []): PageRouteArguments;

    /**
     * @param Route $route
     * @param Applicable[] $aspects
     * @param string|null $namespace
     */
    protected function applyRouteAspects(Route $route, array $aspects, string $namespace = null)
    {
        if (empty($aspects)) {
            return;
        }
        $aspects = $this->getVariableProcessor()
            ->deflateKeys($aspects, $namespace, $route->getArguments());
        $route->setAspects($aspects);
    }

    /**
     * @return VariableProcessor
     */
    protected function getVariableProcessor(): VariableProcessor
    {
        if (isset($this->variableProcessor)) {
            return $this->variableProcessor;
        }
        return $this->variableProcessor = new VariableProcessor();
    }
}
