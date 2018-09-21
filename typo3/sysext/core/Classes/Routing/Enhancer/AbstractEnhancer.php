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
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Core\Routing\Traits\AspectsAwareTrait;

abstract class AbstractEnhancer
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
    public function applyRouteAspects(Route $route, array $aspects, string $namespace = null)
    {
        if (empty($aspects)) {
            return;
        }
        $arguments = $route->getDefault('_arguments') ?? [];
        $aspects = $this->getVariableProcessor()
            ->deflateKeys($aspects, $arguments, $namespace);
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
