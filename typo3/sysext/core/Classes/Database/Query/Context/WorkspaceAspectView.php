<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\Database\Query\Context;

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

use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\SelectIdentifierCollection;
use TYPO3\CMS\Core\Database\Query\TableIdentifier;
use TYPO3\CMS\Core\Database\Query\VersionMap;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class WorkspaceAspectView
{
    private const PARAMETER_PREFIX = ':_';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var WorkspaceAspect
     */
    private $workspaceAspect;

    /**
     * Initializes a new WorkspaceAspectView.
     * 
     * @param Connection $connection
     * @param WorkspaceAspect $workspaceAspect
     */
    public function __construct(Connection $connection, WorkspaceAspect $workspaceAspect)
    {
        $this->connection = $connection;
        $this->workspaceAspect = $workspaceAspect;
    }

    /**
     * Builds a query for the view.
     * 
     * @param TableIdentifier $tableIdentifier
     * @param SelectIdentifierCollection $selectIdentifiers
     */
    public function buildQuery(TableIdentifier $tableIdentifier, SelectIdentifierCollection $selectIdentifiers): QueryBuilder
    {
        $tableName = $tableIdentifier->getTableName();
        $workspaceId = (int) $this->workspaceAspect->getId();
        
        $queryBuilder = $this->getQueryBuilder()
            ->from($tableName);

        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        $this->project($tableName, $selectIdentifiers, $queryBuilder);

        if (!isset($GLOBALS['TCA'][$tableName]['ctrl']['versioningWS'])) {
            return $queryBuilder;
        }

        $queryBuilder
            ->leftJoin(
                $tableName,
                $tableName,
                'version',
                (string) $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq(
                        $tableName . '.uid',
                        $queryBuilder->quoteIdentifier('version.t3ver_oid')
                    ),
                    $queryBuilder->expr()->eq(
                        $tableName . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            0,
                            \PDO::PARAM_INT,
                            self::PARAMETER_PREFIX . md5('workspaceLive')
                        )
                    ),
                    $queryBuilder->expr()->eq(
                        'version.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            $workspaceId,
                            \PDO::PARAM_INT,
                            self::PARAMETER_PREFIX . md5('workspaceContext')
                        )
                    )
                )
            )
            ->leftJoin(
                $tableName,
                $tableName,
                'original',
                (string) $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq(
                        $tableName . '.t3ver_oid',
                        $queryBuilder->quoteIdentifier('original.uid')
                    ),
                    $queryBuilder->expr()->eq(
                        $tableName . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            $workspaceId,
                            \PDO::PARAM_INT,
                            self::PARAMETER_PREFIX . md5('workspaceContext')
                        )
                    ),
                    $queryBuilder->expr()->in(
                        'original.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            [0, $workspaceId],
                            Connection::PARAM_INT_ARRAY,
                            self::PARAMETER_PREFIX . md5('workspaceIdentifiers')
                        )
                    )
                )
            )
            ->leftJoin(
                $tableName,
                $tableName,
                'placeholder',
                (string) $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq(
                        $tableName . '.t3ver_oid',
                        $queryBuilder->quoteIdentifier('placeholder.t3ver_move_id')
                    ),
                    $queryBuilder->expr()->neq(
                        'placeholder.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            0,
                            \PDO::PARAM_INT,
                            self::PARAMETER_PREFIX . md5('workspaceLive')
                        )
                    ),
                    $queryBuilder->expr()->eq(
                        $tableName . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            $workspaceId,
                            \PDO::PARAM_INT,
                            self::PARAMETER_PREFIX . md5('workspaceContext')
                        )
                    ),
                    $queryBuilder->expr()->in(
                        'placeholder.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            [0, $workspaceId],
                            Connection::PARAM_INT_ARRAY,
                            self::PARAMETER_PREFIX . md5('workspaceIdentifiers')
                        )
                    )
                )
            )
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq(
                            $tableName . '.t3ver_wsid',
                            $queryBuilder->createNamedParameter(
                                0,
                                \PDO::PARAM_INT,
                                self::PARAMETER_PREFIX . md5('workspaceLive')
                            )
                        ),
                        $queryBuilder->expr()->isNull(
                            'version.uid'
                        )
                    ),
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq(
                            $tableName . '.t3ver_wsid',
                            $queryBuilder->createNamedParameter(
                                $workspaceId,
                                \PDO::PARAM_INT,
                                self::PARAMETER_PREFIX . md5('workspaceContext')
                            )
                        ),
                        $queryBuilder->expr()->notIn(
                            $tableName . '.t3ver_state',
                            $queryBuilder->createNamedParameter(
                                [1, 3],
                                Connection::PARAM_INT_ARRAY,
                                self::PARAMETER_PREFIX . md5('workspaceStates')
                            )
                        )
                    )
                )
            );

        return $queryBuilder;
    }

    private function project(string $tableName, SelectIdentifierCollection $selectIdentifiers, QueryBuilder $queryBuilder): QueryBuilder
    {
        $fieldNames = [];

        foreach ($selectIdentifiers as $selectIdentifier) {
            if ($selectIdentifier->getTableName() !== null 
                && $selectIdentifier->getTableName() !== $tableName
            ) {
                continue;
            }

            if ($selectIdentifier->getFieldName() === '*') {
                $columns = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getConnectionForTable($tableName)
                    ->getSchemaManager()
                    ->listTableDetails($tableName)
                    ->getColumns();
                
                foreach ($columns as $column) {
                    $fieldNames[] = $column->getName();
                }

                if (isset($GLOBALS['TCA'][$tableName]['ctrl']['versioningWS'])) {
                    $fieldNames[] = '_ORIG_uid';
                    $fieldNames[] = '_ORIG_pid';
                }
            } else {
                $fieldNames[] = $selectIdentifier->getFieldName();
            }
        }

        foreach ($fieldNames as $fieldName) {
            if ($fieldName === 'uid' && isset($GLOBALS['TCA'][$tableName]['ctrl']['versioningWS'])) {
                $queryBuilder->addSelectLiteral(
                    sprintf(
                        'COALESCE(%s,%s) AS %s',
                        $queryBuilder->quoteIdentifier('original.uid'),
                        $queryBuilder->quoteIdentifier($tableName . '.uid'),
                        $queryBuilder->quoteIdentifier('uid')
                    )
                );
            } elseif ($fieldName === 'pid' && isset($GLOBALS['TCA'][$tableName]['ctrl']['versioningWS'])) {
                $queryBuilder->addSelectLiteral(
                    sprintf(
                        'COALESCE(%s,%s,%s) AS %s',
                        $queryBuilder->quoteIdentifier('placeholder.pid'),
                        $queryBuilder->quoteIdentifier('original.pid'),
                        $queryBuilder->quoteIdentifier($tableName . '.pid'),
                        $queryBuilder->quoteIdentifier('pid')
                    )
                );
            } elseif ($fieldName === '_ORIG_uid') {
                $queryBuilder->addSelectLiteral(
                    sprintf(
                        'CASE WHEN %s IS NULL THEN NULL ELSE %s END AS %s',
                        $queryBuilder->quoteIdentifier('original.uid'),
                        $queryBuilder->quoteIdentifier($tableName . '.uid'),
                        $queryBuilder->quoteIdentifier('_ORIG_uid')
                    )
                );
            } elseif ($fieldName === '_ORIG_pid') {
                $queryBuilder->addSelectLiteral(
                    sprintf(
                        'CASE WHEN %s IS NULL THEN NULL ELSE %s END AS %s',
                        $queryBuilder->quoteIdentifier('original.pid'),
                        $queryBuilder->quoteIdentifier($tableName . '.pid'),
                        $queryBuilder->quoteIdentifier('_ORIG_pid')
                    )
                );
            } else {
                $queryBuilder->addSelect($tableName . '.' . $fieldName);
            }
        }

        return $queryBuilder;
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(QueryBuilder::class, $this->connection);
    }
}
