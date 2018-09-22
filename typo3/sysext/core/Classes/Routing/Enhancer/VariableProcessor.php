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

    /**
     * @var array
     */
    protected $hashes = [];

    /**
     * @param string $value
     * @return string
     */
    protected function createHash(string $value): string
    {
        if (strlen($value) < 32 && !preg_match('#[^\w]#', $value)) {
            return $value;
        }
        $hash = md5($value);
        $this->hashes[$hash] = $value;
        return $hash;
    }

    /**
     * @param string $hash
     * @return string
     */
    protected function resolveHash(string $hash): string
    {
        if (strlen($hash) < 32) {
            return $hash;
        }
        if (!isset($this->hashes[$hash])) {
            throw new \LogicException(
                'Hash not resolvable',
                1537633463
            );
        }
        return $this->hashes[$hash];
    }

    /**
     * @param string $routePath
     * @param string|null $namespace
     * @param array $arguments
     * @return string
     */
    public function deflateRoutePath(string $routePath, string $namespace = null, array $arguments = []): string
    {
        if (!preg_match_all(static::VARIABLE_PATTERN, $routePath, $matches)) {
            return $routePath;
        }

        $search = array_values($matches[0]);
        $replace = array_map(
            function (string $name) {
                return '{' . $name . '}';
            },
            $this->deflateValues($matches['name'], $namespace, $arguments)
        );

        return str_replace($search, $replace, $routePath);
    }

    /**
     * @param string $routePath
     * @param string|null $namespace
     * @param array $arguments
     * @return string
     */
    public function inflateRoutePath(string $routePath, string $namespace = null, array $arguments = []): string
    {
        if (!preg_match_all(static::VARIABLE_PATTERN, $routePath, $matches)) {
            return $routePath;
        }

        $search = array_values($matches[0]);
        $replace = array_map(
            function (string $name) {
                return '{' . $name . '}';
            },
            $this->inflateValues($matches['name'], $namespace, $arguments)
        );

        return str_replace($search, $replace, $routePath);
    }

    /**
     * Deflates (flattens) route/request parameters for a given namespace.
     *
     * @param array $parameters
     * @param string $namespace
     * @param array $arguments
     * @return array
     */
    public function deflateNamespaceParameters(array $parameters, string $namespace, array $arguments = []): array
    {
        if (empty($namespace) || empty($parameters[$namespace])) {
            return $parameters;
        }
        // prefix items of namespace parameters and apply argument mapping
        $namespaceParameters = $this->deflateKeys($parameters[$namespace], $namespace, $arguments, false);
        // deflate those array items
        $namespaceParameters = $this->deflateArray($namespaceParameters);
        unset($parameters[$namespace]);
        // merge with remaining array items
        return array_merge($parameters, $namespaceParameters);
    }

    /**
     * Inflates (unflattens) route/request parameters.
     *
     * @param array $parameters
     * @param string $namespace
     * @param array $arguments
     * @return array
     */
    public function inflateNamespaceParameters(array $parameters, string $namespace, array $arguments = []): array
    {
        if (empty($namespace) || empty($parameters)) {
            return $parameters;
        }
        $parameters = $this->inflateArray($parameters);
        // apply argument mapping on items of inflated namespace parameters
        if (!empty($parameters[$namespace]) && !empty($arguments)) {
            $parameters[$namespace] = $this->inflateKeys($parameters[$namespace], null, $arguments, false);
        }
        return $parameters;
    }

    /**
     * Deflates keys names on the first level, now recursion into sub-arrays.
     * Can be used to adjust key names of route requirements, mappers, etc.
     *
     * @param array $items
     * @param string|null $namespace
     * @param array $arguments
     * @param bool $hash = true
     * @return array
     */
    public function deflateKeys(array $items, string $namespace = null, array $arguments = [], bool $hash = true): array
    {
        if (empty($items) || empty($arguments) && empty($namespace)) {
            return $items;
        }
        $keys = $this->deflateValues(array_keys($items), $namespace, $arguments, $hash);
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
     * @param string|null $namespace
     * @param array $arguments
     * @param bool $hash = true
     * @return array
     */
    public function inflateKeys(array $items, string $namespace = null, array $arguments = [], bool $hash = true): array
    {
        if (empty($items) || empty($arguments) && empty($namespace)) {
            return $items;
        }
        $keys = $this->inflateValues(array_keys($items), $namespace, $arguments, $hash);
        return array_combine(
            $keys,
            array_values($items)
        );
    }

    /**
     * Deflates plain values.
     *
     * @param array $values
     * @param null $namespace
     * @param array $arguments
     * @param bool bool $hash
     * @return array
     */
    public function deflateValues(array $values, $namespace = null, array $arguments = [], bool $hash = true): array
    {
        if (empty($values) || empty($arguments) && empty($namespace)) {
            return $values;
        }
        $namespacePrefix = $namespace ? $namespace . static::LEVEL_DELIMITER : '';
        return array_map(
            function (string $value) use ($arguments, $namespacePrefix, $hash) {
                $value = $arguments[$value] ?? $value;
                $value = $namespacePrefix . $value;
                if (!$hash) {
                    return $value;
                }
                return $this->createHash($value);
            },
            $values
        );
    }

    /**
     * Inflates plain values.
     *
     * @param array $values
     * @param null $namespace
     * @param array $arguments
     * @param bool $hash
     * @return array
     */
    public function inflateValues(array $values, $namespace = null, array $arguments = [], bool $hash = true): array
    {
        if (empty($values) || empty($arguments) && empty($namespace)) {
            return $values;
        }
        $namespacePrefix = $namespace ? $namespace . static::LEVEL_DELIMITER : '';
        return array_map(
            function (string $value) use ($arguments, $namespacePrefix, $hash) {
                if ($hash) {
                    $value = $this->resolveHash($value);
                }
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
    protected function deflateArray(array $array, string $prefix = ''): array
    {
        $delimiter = static::LEVEL_DELIMITER;
        if ($prefix !== '' && substr($prefix, -strlen($delimiter)) !== $delimiter) {
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
                $deflatedKey = $this->createHash($prefix . $key);
                $result[$deflatedKey] = $value;
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
            $inflatedKey = $this->resolveHash($key);
            $steps = explode(static::LEVEL_DELIMITER, $inflatedKey);
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
