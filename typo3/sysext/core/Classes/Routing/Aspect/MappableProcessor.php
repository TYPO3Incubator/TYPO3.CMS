<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Aspect;

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

use Symfony\Component\Routing\Route as SymfonyRoute;
use TYPO3\CMS\Core\Routing\Route as CoreRoute;

class MappableProcessor
{
    /**
     * @param SymfonyRoute $route
     * @param array $attributes
     * @return bool
     */
    public function resolve(SymfonyRoute $route, array &$attributes): bool
    {
        if (!$route instanceof CoreRoute) {
            return true;
        }
        $mappers = $this->fetchMappers($route, $attributes);
        if (empty($mappers)) {
            return true;
        }

        $values = [];
        foreach ($mappers as $variableName => $mapper) {
            $value = $mapper->resolve(
                (string)($attributes[$variableName] ?? '')
            );
            if ($value !== null) {
                $values[$variableName] = $value;
            }
        }

        if (count($mappers) !== count($values)) {
            return false;
        }

        $attributes = array_merge($attributes, $values);
        return true;
    }

    /**
     * @param SymfonyRoute $route
     * @param array $attributes
     * @return bool
     */
    public function generate(SymfonyRoute $route, array &$attributes): bool
    {
        if (!$route instanceof CoreRoute) {
            return true;
        }
        $mappers = $this->fetchMappers($route, $attributes);
        if (empty($mappers)) {
            return true;
        }

        $values = [];
        foreach ($mappers as $variableName => $mapper) {
            $value = $mapper->generate(
                (string)($attributes[$variableName] ?? '')
            );
            if ($value !== null) {
                $values[$variableName] = $value;
            }
        }

        if (count($mappers) !== count($values)) {
            return false;
        }

        $attributes = array_merge($attributes, $values);
        return true;
    }

    /**
     * @param CoreRoute $route
     * @param array $attributes
     * @param string $type
     * @return Mappable[]
     */
    protected function fetchMappers(CoreRoute $route, array $attributes, string $type = Mappable::class): array
    {
        if (empty($attributes)) {
            return [];
        }
        return $route->filterAspects([$type], array_keys($attributes));
    }
}
