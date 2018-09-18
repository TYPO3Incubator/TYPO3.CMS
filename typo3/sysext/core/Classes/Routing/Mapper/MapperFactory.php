<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Mapper;

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

class MapperFactory extends AbstractMapperFactory
{
    /**
     * @return string[]
     */
    public function builds(): array
    {
        $classNames = [
            SlugMapper::class,
            StaticValueMapper::class,
        ];
        // remove namespace prefix
        return array_merge(
            $classNames,
            array_map(
                [$this, 'removeNamespace'],
                $classNames
            )
        );
    }

    /**
     * @param string $name
     * @param array $settings
     * @return Mappable
     */
    public function build(string $name, array $settings): Mappable
    {
        if (!in_array($name, $this->builds(), true)) {
            throw new \LogicException(
                sprintf('Cannot build handler %s', $name),
                1537277122
            );
        }
        return call_user_func(
            [$this, 'build' . $this->removeNamespace($name)],
            $settings
        );
    }

    /**
     * @param array $settings
     * @return SlugMapper
     */
    protected function buildSlugMapper(array $settings): SlugMapper
    {
        $tableName = $settings['tableName'] ?? null;
        $fieldName = $settings['fieldName'] ?? null;

        if (!is_string($tableName)) {
            throw new \LogicException('Slug table name must be string', 1537277133);
        }
        if (!is_string($fieldName)) {
            throw new \LogicException('Slug field name must be string', 1537277134);
        }

        return new SlugMapper($tableName, $fieldName);
    }

    /**
     * @param array $settings
     * @return StaticValueMapper
     */
    protected function buildStaticValueMapper(array $settings): StaticValueMapper
    {
        $map = $settings['map'] ?? null;
        $locale = $settings['locale'] ?? null;

        if (!array($map)) {
            throw new \LogicException('Static value map must be array', 1537277143);
        }
        if (!is_string($locale ?? '')) {
            throw new \LogicException('Static value locale must be string', 1537277144);
        }

        return new StaticValueMapper($map, $locale);
    }

    /**
     * @param string $className
     * @return string
     */
    protected function removeNamespace(string $className): string
    {
        $namespaceLength = strlen(__NAMESPACE__ . '\\');
        if (strpos($className, __NAMESPACE__ . '\\') === 0) {
            return substr($className, $namespaceLength);
        }
        return $className;
    }
}
