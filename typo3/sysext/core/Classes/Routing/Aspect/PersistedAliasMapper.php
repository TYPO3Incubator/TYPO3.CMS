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

class PersistedAliasMapper implements Mappable, StaticMappable
{
    use SiteLanguageAwareTrait;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var string
     */
    protected $routeFieldName;

    /**
     * @var string
     */
    protected $valueFieldName;

    /**
     * @var string
     */
    protected $routeValuePrefix;

    /**
     * @var PersistenceDelegate
     */
    protected $persistenceDelegate;

    /**
     * @param string $tableName
     * @param string $routeFieldName
     * @param string $valueFieldName
     * @param string $routeValuePrefix
     */
    public function __construct(string $tableName, string $routeFieldName, string $valueFieldName, string $routeValuePrefix = '')
    {
        $this->tableName = $tableName;
        $this->routeFieldName = $routeFieldName;
        $this->valueFieldName = $valueFieldName;
        $this->routeValuePrefix = $routeValuePrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            'tableName' => $this->tableName,
            'routeFieldName' => $this->routeFieldName,
            'valueFieldName' => $this->valueFieldName,
            'routeValuePrefix' => $this->routeValuePrefix,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);
        $this->tableName = $data['tableName'] ?? '';
        $this->routeFieldName = $data['routeFieldName'] ?? '';
        $this->valueFieldName = $data['valueFieldName'] ?? '';
        $this->routeValuePrefix = $data['routeValuePrefix'] ?? '';
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
        $result = $this->purgeRouteValuePrefix(
            $result[$this->routeFieldName] ?? null
        );
        return $result ? (string)$result : null;
    }

    /**
     * @param string $value
     * @return string|null
     */
    public function resolve(string $value): ?string
    {
        $value = $this->routeValuePrefix . $this->purgeRouteValuePrefix($value);
        $result = $this->getPersistenceDelegate()->resolve([
            $this->routeFieldName => $value
        ]);
        $result = $result[$this->valueFieldName] ?? null;
        return $result ? (string)$result : null;
    }

    /**
     * @param string|null $value
     * @return string
     */
    protected function purgeRouteValuePrefix(?string $value): ?string
    {
        if (empty($this->routeValuePrefix) || $value === null) {
            return $value;
        }
        return ltrim($value, $this->routeValuePrefix);
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

        $resolveModifier = function(QueryBuilder $queryBuilder, array $values) {
            return $queryBuilder->select($this->valueFieldName)->where(
                ...$this->createFieldConstraints($queryBuilder, $values)
            );
        };
        $generateModifier = function(QueryBuilder $queryBuilder, array $values) {
            return $queryBuilder->select($this->routeFieldName)->where(
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
                $queryBuilder->createNamedParameter($fieldValue, \PDO::PARAM_STR)
            );
        }
        return $constraints;
    }
}
