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

use TYPO3\CMS\Core\Utility\ArrayUtility;

class PageRouteArguments
{
    protected $allArguments;
    protected $routeArguments;
    protected $staticArguments;
    protected $dynamicArguments;

    protected $queryArguments = [];
    protected $systemArguments = []; // ?? not sure

    protected $dirty = false;

    /**
     * PageRouteArguments constructor.
     * @param array $routeArguments
     * @param array $staticArguments
     */
    public function __construct(array $routeArguments, array $staticArguments = [])
    {
        $this->routeArguments = $this->sort($routeArguments);
        $this->staticArguments = $this->sort($staticArguments);
        $this->allArguments = $this->routeArguments;
        $this->updateDynamicArguments();
    }

    /**
     * @return bool
     */
    public function areDirty(): bool
    {
        return $this->dirty;
    }

    public function get(string $name)
    {
        return $this->allArguments[$name] ?? null;
    }

    /**
     * @return array
     */
    public function getAllArguments(): array
    {
        return $this->allArguments;
    }

    /**
     * @return array
     */
    public function getRouteArguments(): array
    {
        return $this->routeArguments;
    }

    /**
     * @return array
     */
    public function getStaticArguments(): array
    {
        return $this->staticArguments;
    }

    /**
     * @return array
     */
    public function getDynamicArguments(): array
    {
        return $this->dynamicArguments;
    }

    /**
     * @return array
     */
    public function getQueryArguments(): array
    {
        return $this->queryArguments;
    }

    /**
     * @return array
     */
    public function getSystemArguments(): array
    {
        return $this->systemArguments;
    }

    /**
     * @param array $queryArguments
     * @return static
     */
    public function withQueryArguments(array $queryArguments): self
    {
        $queryArguments = $this->sort($queryArguments);
        if ($this->queryArguments === $queryArguments) {
            return $this;
        }
        // in case query arguments would override route arguments,
        // the state is considered as dirty (since it's not distinct)
        // thus, route arguments take precedence over query arguments
        $additionalQueryArguments = $this->diff($queryArguments, $this->routeArguments);
        $dirty = $additionalQueryArguments !== $queryArguments;
        // apply changes
        $target = clone $this;
        $target->dirty = $this->dirty || $dirty;
        $target->queryArguments = $queryArguments;
        $target->allArguments = array_replace_recursive($target->allArguments, $additionalQueryArguments);
        $target->updateDynamicArguments();
        return $target;
    }

    protected function updateDynamicArguments()
    {
        $this->dynamicArguments = $this->diff(
            $this->allArguments,
            $this->staticArguments
        );
    }

    /**
     * Cleans empty array recursively.
     *
     * @param array $array
     * @return array
     */
    protected function clean(array $array): array
    {
        foreach ($array as $key => &$item) {
            if (!is_array($item)) {
                continue;
            }
            if (!empty($item)) {
                $item = $this->clean($item);
            }
            if (empty($item)) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     * Sorts array keys recursively.
     *
     * @param array $array
     * @return array
     */
    protected function sort(array $array): array
    {
        $array = $this->clean($array);
        ArrayUtility::naturalKeySortRecursive($array);
        return $array;
    }

    /**
     * Removes keys that are defined in $second from $first recursively.
     *
     * @param array $first
     * @param array $second
     * @return array
     */
    protected function diff(array $first, array $second): array
    {
        return ArrayUtility::arrayDiffAssocRecursive($first, $second);
    }
}
