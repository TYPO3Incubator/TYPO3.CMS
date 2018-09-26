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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Routing\Traits\SiteLanguageAwareTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PersistedPatternMapper implements Mappable, StaticMappable
{
    use SiteLanguageAwareTrait;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var string
     */
    protected $routeFieldPattern;

    /**
     * @var string
     */
    protected $routeFieldResult;

    /**
     * @var string
     */
    protected $valueFieldName = 'uid';

    /**
     * @var PersistenceDelegate
     */
    protected $persistenceDelegate;

    /**
     * @param string $tableName
     * @param string $routeFieldPattern
     * @param string $routeFieldResult
     */
    public function __construct(
        string $tableName,
        string $routeFieldPattern,
        string $routeFieldResult
    ) {
        $this->tableName = $tableName;
        $this->routeFieldPattern = $routeFieldPattern;
        $this->routeFieldResult = $routeFieldResult;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            'tableName' => $this->tableName,
            'routeFieldPattern' => $this->routeFieldPattern,
            'routeFieldResult' => $this->routeFieldResult,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);
        $this->tableName = $data['tableName'] ?? '';
        $this->routeFieldPattern = $data['routeFieldPattern'] ?? '';
        $this->routeFieldResult = $data['routeFieldResult'] ?? '';
    }

    /**
     * @param string $value
     * @return string|null
     */
    public function generate(string $value): ?string
    {
        $result = $this->getPersistenceDelegate()->generate([
            $this->valueFieldName => $value
        ]);
        $result = $this->createRouteResult($result);
        return $result;
    }

    /**
     * @param string $value
     * @return string|null
     */
    public function resolve(string $value): ?string
    {
        if (!preg_match('#' . $this->routeFieldPattern . '#', $value, $matches)) {
            return null;
        }
        $values = $this->filterNamesKeys($matches);
        $result = $this->getPersistenceDelegate()->resolve($values);
        $result = $result[$this->valueFieldName] ?? null;
        return $result ? (string)$result : null;
    }

    /**
     * @param array|null $result
     * @return null|string
     */
    protected function createRouteResult(?array $result): ?string
    {
        if ($result === null) {
            return $result;
        }
        if (!preg_match_all('#\{(?P<fieldName>[^}]+)\}#', $this->routeFieldResult, $matches)) {
            throw new \LogicException(
                'Not substitute candidates found route field result',
                1537962752
            );
        }
        $substitutes = [];
        foreach ($matches['fieldName'] as $fieldName) {
            $routeFieldName = '{' . $fieldName . '}';
            $substitutes[$routeFieldName] = ($result[$fieldName] ?? null) ?: 'empty';
        }
        return str_replace(
            array_keys($substitutes),
            array_values($substitutes),
            $this->routeFieldResult
        );
    }

    /**
     * @param array $array
     * @return array
     */
    protected function filterNamesKeys(array $array): array
    {
        return array_filter(
            $array,
            function ($key) {
                return !is_numeric($key);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return PersistenceDelegate
     */
    protected function getPersistenceDelegate(): PersistenceDelegate
    {
        if ($this->persistenceDelegate !== null) {
            return $this->persistenceDelegate;
        }
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->tableName)
            ->from($this->tableName);
        // @todo Restrictions (Hidden? Workspace?)

        $resolveModifier = function (QueryBuilder $queryBuilder, array $values) {
            return $queryBuilder->select($this->valueFieldName)->where(
                ...$this->createFieldConstraints($queryBuilder, $values)
            );
        };
        $generateModifier = function (QueryBuilder $queryBuilder, array $values) {
            return $queryBuilder->select('*')->where(
                ...$this->createFieldConstraints($queryBuilder, $values)
            );
        };

        return $this->persistenceDelegate = new PersistenceDelegate(
            $queryBuilder,
            $resolveModifier,
            $generateModifier
        );
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array $values
     * @return array
     */
    protected function createFieldConstraints(QueryBuilder $queryBuilder, array $values): array
    {
        $constraints = [];
        foreach ($values as $fieldName => $fieldValue) {
            $constraints[] = $queryBuilder->expr()->eq(
                $fieldName,
                $queryBuilder->createNamedParameter($fieldValue,
                    \PDO::PARAM_STR)
            );
        }
        return $constraints;
    }
}
