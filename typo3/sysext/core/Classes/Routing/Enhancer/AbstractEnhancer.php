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

use TYPO3\CMS\Core\Routing\Aspect\Applicable;
use TYPO3\CMS\Core\Routing\Aspect\MappableProcessor;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappable;
use TYPO3\CMS\Core\Routing\Route;

abstract class AbstractEnhancer implements Enhancable
{
    /**
     * @var VariableProcessor
     */
    protected $variableProcessor;

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
     * Asserts that possible amount of items in all static and countable mappers
     * (such as StaticRangeMapper) is limited to 10000 in order to avoid
     * brute-force scenarios and the risk of cache-flooding.
     *
     * @param Route $route
     * @param array $variableNames
     */
    protected function assertMaximumStaticMappableAmount(Route $route, array $variableNames = [])
    {
        $mappers = $route->filterAspects(
            [StaticMappable::class, \Countable::class],
            $variableNames
        );
        if (empty($mappers)) {
            return;
        }

        $multipliers = array_map('count', $mappers);
        $product = array_product($multipliers);
        if ($product > 10000) {
            throw new \LogicException(
                'Possible range of all mappers is larger than 10000 items',
                1537696772
            );
        }
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
