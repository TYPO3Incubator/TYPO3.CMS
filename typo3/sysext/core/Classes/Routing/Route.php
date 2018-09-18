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
use TYPO3\CMS\Core\Routing\Mapper\Mappable;

/**
 * TYPO3's route is built on top of Symfony's route with the functionality
 * of "Mappers" built on top of a route
 */
class Route extends \Symfony\Component\Routing\Route
{
    protected $path = '/';
    protected $host = '';
    protected $schemes = [];
    protected $methods = [];
    protected $defaults = [];
    protected $requirements = [];
    protected $options = [];
    protected $condition = '';

    /**
     * @var CompiledRoute|null
     */
    protected $compiled;

    /**
     * @var Mappable[]
     */
    protected $mappers = [];

    public function __construct(
        string $path,
        array $defaults = [],
        array $requirements = [],
        array $options = [],
        ?string $host = '',
        $schemes = [],
        $methods = [],
        ?string $condition = '',
        array $mappers = []
    ) {
        parent::__construct($path, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);
        $this->mappers = $mappers;
    }

    /**
     * Returns all mappers.
     *
     * @return array The mappers
     */
    public function getMappers(): array
    {
        return $this->mappers;
    }

    /**
     * Sets the mappers and removes existing ones.
     *
     * This method implements a fluent interface.
     *
     * @param array $mappers The mappers
     *
     * @return $this
     */
    public function setMappers(array $mappers)
    {
        $this->mappers = [];
        return $this->addMappers($mappers);
    }

    /**
     * Adds mappers to the existing maps.
     *
     * This method implements a fluent interface.
     *
     * @param array $mappers The mappers
     *
     * @return $this
     */
    public function addMappers(array $mappers)
    {
        foreach ($mappers as $key => $mapper) {
            $this->mappers[$key] = $mapper;
        }
        $this->compiled = null;

        return $this;
    }

    /**
     * Returns the mapper for the given key.
     *
     * @param string $key The key
     *
     * @return string|null The regex or null when not given
     */
    public function getMapper($key)
    {
        return $this->mappers[$key] ?? null;
    }

    /**
     * Checks if a mapper is set for the given key.
     *
     * @param string $key A variable name
     *
     * @return bool true if a mapper is specified, false otherwise
     */
    public function hasMapper($key)
    {
        return array_key_exists($key, $this->mappers);
    }

    /**
     * Sets a mapper for the given key.
     *
     * @param string $key   The key
     * @param Mappable $mapper
     *
     * @return $this
     */
    public function setMapper($key, Mappable $mapper)
    {
        $this->mappers[$key] = $mapper;
        $this->compiled = null;
        return $this;
    }
}
