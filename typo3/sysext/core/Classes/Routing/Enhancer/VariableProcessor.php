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


class VariableProcessor
{
    protected const LEVEL_DELIMITER = '__';
    protected const VARIABLE_PATTERN = '#\{(?P<name>[^}]+)\}#';

    public function deflateRoutePath(string $routePath, array $arguments = [], string $namespace = null): string
    {
        if (!preg_match_all(static::VARIABLE_PATTERN, $routePath, $matches)) {
            return $routePath;
        }

        $search = array_values($matches[0]);
        $replace = array_map(
            function (string $name) {
                return '{' . $name . '}';
            },
            $this->deflateValues($matches['name'], $arguments, $namespace)
        );

        return str_replace($search, $replace, $routePath);
    }

    public function inflateRoutePath(string $routePath, array $arguments = [], string $namespace = null): string
    {
        if (!preg_match_all(static::VARIABLE_PATTERN, $routePath, $matches)) {
            return $routePath;
        }

        $search = array_values($matches[0]);
        $replace = array_map(
            function (string $name) {
                return '{' . $name . '}';
            },
            $this->inflateValues($matches['name'], $arguments, $namespace)
        );

        return str_replace($search, $replace, $routePath);
    }

    /**
     * Deflates (flattens) route/request parameters.
     *
     * @param array $values
     * @param array $arguments
     * @param string|null $namespace
     * @return array
     */
    public function deflateParameters(array $values, array $arguments = [], string $namespace = null): array
    {
        if (empty($values) || empty($arguments) && empty($namespace)) {
            return $values;
        }
        // deflate candidates and prefix namespace
        if (!empty($namespace) && !empty($values[$namespace])) {
            $candidates = $this->deflateKeys($values[$namespace], $arguments);
            unset($values[$namespace]);
            $candidates = $this->deflateArray($candidates, $namespace);
        }
        $values = $this->deflateKeys($values, $arguments);
        return array_merge($values, $candidates ?? []);
    }

    /**
     * Inflates (unflattens) route/request parameters.
     *
     * @param array $values
     * @param array $arguments
     * @param string|null $namespace
     * @return array
     */
    public function inflateParameters(array $values, array $arguments = [], string $namespace = null): array
    {
        if (empty($values) || empty($arguments) && empty($namespace)) {
            return $values;
        }
        if (!empty($namespace)) {
            $candidates = $this->filterArray($values, $namespace);
            $values = array_diff_key($values, $candidates);
            $candidates = $this->inflateKeys($candidates, $arguments, $namespace);
            $candidates = [$namespace => $this->inflateArray($candidates)];
        }
        $values = $this->inflateKeys($values, $arguments);
        return array_merge($values, $candidates ?? []);
    }

    /**
     * Deflates keys names on the first level, now recursion into sub-arrays.
     * Can be used to adjust key names of route requirements, mappers, etc.
     *
     * @param array $items
     * @param array $arguments
     * @param string|null $namespace
     * @return array
     */
    public function deflateKeys(array $items, array $arguments = [], string $namespace = null): array
    {
        if (empty($items) || empty($arguments) && empty($namespace)) {
            return $items;
        }
        $keys = $this->deflateValues(array_keys($items), $arguments, $namespace);
        return array_combine(
            $keys,
            array_values($items)
        );
    }

    /**
     * Inflates keys names on the first level, now recursion into sub-arrays.
     * Can be used to adjust key names of route requirements, mappers, etc.
     *
     * @param array $items
     * @param array $arguments
     * @param string|null $namespace
     * @return array
     */
    public function inflateKeys(array $items, array $arguments = [], string $namespace = null): array
    {
        if (empty($items) || empty($arguments) && empty($namespace)) {
            return $items;
        }
        $keys = $this->inflateValues(array_keys($items), $arguments, $namespace);
        return array_combine(
            $keys,
            array_values($items)
        );
    }

    /**
     * Deflates plain values.
     *
     * @param array $values
     * @param array $arguments
     * @param null $namespace
     * @return array
     */
    public function deflateValues(array $values, array $arguments = [], $namespace = null): array
    {
        if (empty($values) || empty($arguments) && empty($namespace)) {
            return $values;
        }
        $namespacePrefix = $namespace ? $namespace . static::LEVEL_DELIMITER : '';
        return array_map(
            function (string $value) use ($arguments, $namespacePrefix) {
                $value = $arguments[$value] ?? $value;
                return $namespacePrefix . $value;
            },
            $values
        );
    }

    /**
     * Inflates plain values.
     *
     * @param array $values
     * @param array $arguments
     * @param null $namespace
     * @return array
     */
    public function inflateValues(array $values, array $arguments = [], $namespace = null): array
    {
        if (empty($values) || empty($arguments) && empty($namespace)) {
            return $values;
        }
        $namespacePrefix = $namespace ? $namespace . static::LEVEL_DELIMITER : '';
        return array_map(
            function (string $value) use ($arguments, $namespacePrefix) {
                if (!empty($namespacePrefix) && strpos($value, $namespacePrefix) === 0) {
                    $value = substr($value, strlen($namespacePrefix));
                }
                $index = array_search($value, $arguments);
                return $index !== false ? $index : $value;
            },
            $values
        );
    }

    /**
     * Filters array items having an according namespace prefix.
     *
     * @param array $array
     * @param string $namespace
     * @return array
     */
    protected function filterArray(array $array, string $namespace): array
    {
        if (empty($namespace)) {
            return $array;
        }
        $namespacePrefix = $namespace . static::LEVEL_DELIMITER;
        return array_filter(
            $array,
            function (string $key) use ($namespacePrefix) {
                return strpos($key, $namespacePrefix) === 0;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Deflates (flattens) array having nested structures.
     *
     * @param array $array
     * @param string $prefix
     * @return array
     */
    protected function deflateArray(array $array, string $prefix): array
    {
        if ($prefix !== '' && substr($prefix, -2) !== static::LEVEL_DELIMITER) {
            $prefix .= static::LEVEL_DELIMITER;
        }

        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge(
                    $result,
                    $this->deflateArray(
                        $value,
                        $prefix . $key . static::LEVEL_DELIMITER
                    )
                );
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    /**
     * Inflates (unflattens) an array into nested structures.
     *
     * @param array $array
     * @return array
     */
    protected function inflateArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $steps = explode(static::LEVEL_DELIMITER, $key);
            $pointer = &$result;
            foreach ($steps as $step) {
                $pointer = &$pointer[$step];
            }
            $pointer = $value;
            unset($pointer);
        }
        return $result;
    }
}
