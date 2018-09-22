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

class SlugMapper implements Mappable, StaticMappable
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
     * @var PersistenceDelegate
     */
    protected $persistenceDelegate;

    /**
     * @param string $tableName
     * @param string $routeFieldName
     * @param string $valueFieldName
     */
    public function __construct(string $tableName, string $routeFieldName, string $valueFieldName)
    {
        $this->tableName = $tableName;
        $this->routeFieldName = $routeFieldName;
        $this->valueFieldName = $valueFieldName;
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
    }

    /**
     * @param string $value
     * @return string|null
     */
    public function generate(string $value): ?string
    {
        $result = $this->getPersistenceDelegate()->generate($value);
        // @todo UrlGenerator does not allow variables containing slashes
        $result = trim($result, '/');
        return $result;
    }

    /**
     * @param string $value
     * @return string|null
     */
    public function resolve(string $value): ?string
    {
        $value = '/' . ltrim($value, '/');
        return $this->getPersistenceDelegate()->resolve($value);
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

        $resolveModifier = function(QueryBuilder $queryBuilder, string $value) {
            return $queryBuilder->select($this->valueFieldName)->where(
                $queryBuilder->expr()->eq(
                    $this->routeFieldName,
                    $queryBuilder->createNamedParameter($value, \PDO::PARAM_STR)
                )
            );
        };
        $generateModifier = function(QueryBuilder $queryBuilder, string $value) {
            return $queryBuilder->select($this->routeFieldName)->where(
                $queryBuilder->expr()->eq(
                    $this->valueFieldName,
                    $queryBuilder->createNamedParameter($value, \PDO::PARAM_STR)
                )
            );
        };

        return $this->persistenceDelegate = new PersistenceDelegate(
            $queryBuilder,
            $resolveModifier,
            $generateModifier
        );
    }
}
