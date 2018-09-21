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

use Symfony\Component\Routing\CompiledRoute;
use Symfony\Component\Routing\Route as SymfonyRoute;
use TYPO3\CMS\Core\Routing\Aspect\Applicable;

/**
 * TYPO3's route is built on top of Symfony's route with the functionality
 * of "Aspects" built on top of a route
 */
class Route extends SymfonyRoute
{
    /**
     * @var CompiledRoute|null
     */
    protected $compiled;

    /**
     * @var Applicable[]
     */
    protected $aspects = [];

    public function __construct(
        string $path,
        array $defaults = [],
        array $requirements = [],
        array $options = [],
        ?string $host = '',
        $schemes = [],
        $methods = [],
        ?string $condition = '',
        array $aspects = []
    ) {
        parent::__construct($path, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);
        $this->setAspects($aspects);
    }

    /**
     * @return array
     * @todo '_arguments' are added implicitly, make it explicit in enhancers
     */
    public function getArguments(): array
    {
        return $this->getDefault('_arguments') ?? [];
    }

    /**
     * Returns all aspects.
     *
     * @return array The aspects
     */
    public function getAspects(): array
    {
        return $this->aspects;
    }

    /**
     * Sets the aspects and removes existing ones.
     *
     * This method implements a fluent interface.
     *
     * @param array $aspects The aspects
     *
     * @return $this
     */
    public function setAspects(array $aspects)
    {
        $this->aspects = [];
        return $this->addAspects($aspects);
    }

    /**
     * Adds aspects to the existing maps.
     *
     * This method implements a fluent interface.
     *
     * @param array $aspects The aspects
     *
     * @return $this
     */
    public function addAspects(array $aspects)
    {
        foreach ($aspects as $key => $aspect) {
            $this->aspects[$key] = $aspect;
        }
        $this->compiled = null;
        return $this;
    }

    /**
     * Returns the aspect for the given key.
     *
     * @param string $key The key
     *
     * @return Applicable|null The regex or null when not given
     */
    public function getAspect($key)
    {
        return $this->aspects[$key] ?? null;
    }

    /**
     * Checks if an aspect is set for the given key.
     *
     * @param string $key A variable name
     *
     * @return bool true if a aspect is specified, false otherwise
     */
    public function hasAspect($key)
    {
        return array_key_exists($key, $this->aspects);
    }

    /**
     * Sets a mapper for the given key.
     *
     * @param string $key   The key
     * @param Applicable $aspect
     *
     * @return $this
     */
    public function setMapper($key, Applicable $aspect)
    {
        $this->aspects[$key] = $aspect;
        $this->compiled = null;
        return $this;
    }

    /**
     * @param string $className
     * @param string[] $variableNames
     * @return Applicable[]
     */
    public function filterAspects(string $className, array $variableNames = []): array
    {
        $aspects = $this->aspects;
        if (!empty($variableNames)) {
            $aspects = array_filter(
                $this->aspects,
                function (string $variableName) use ($variableNames) {
                    return in_array($variableName, $variableNames, true);
                },
                ARRAY_FILTER_USE_KEY
            );
        }
        return array_filter(
            $aspects,
            function (Applicable $aspect) use ($className) {
                return is_a($aspect, $className)
                    || in_array($className, class_uses($aspect), true);
            }
        );
    }
}
