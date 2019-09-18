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

use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\SelectIdentifierCollection;
use TYPO3\CMS\Core\Database\Query\TableIdentifier;
use TYPO3\CMS\Core\Database\Query\VersionMap;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LanguageAspectView
{
    private const PARAMETER_PREFIX = ':_';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LanguageAspect
     */
    private $languageAspect;

    /**
     * Initializes a new LanguageAspectView.
     * 
     * @param Connection $connection
     * @param WorkspaceAspect $workspaceAspect
     */
    public function __construct(Connection $connection, LanguageAspect $languageAspect)
    {
        $this->connection = $connection;
        $this->languageAspect = $languageAspect;
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

        $queryBuilder = $this->getQueryBuilder()
            ->from($tableName);

        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        $this->project($tableName, $selectIdentifiers, $queryBuilder);

        if (!isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])
            || !isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])
        ) {
            return $queryBuilder;
        }

        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
        $translationParent = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];

        if ($this->languageAspect->getContentId() > 0) {
            switch ($this->languageAspect->getOverlayType()) {
                case LanguageAspect::OVERLAYS_OFF:
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->in(
                            $tableName . '.' . $languageField,
                            $queryBuilder->createNamedParameter(
                                [-1, $this->languageAspect->getContentId()],
                                Connection::PARAM_INT_ARRAY,
                                self::PARAMETER_PREFIX . md5('languageIdentifiers')
                            )
                        ),
                        $queryBuilder->expr()->eq(
                            $tableName . '.' . $translationParent,
                            $queryBuilder->createNamedParameter(
                                0,
                                \PDO::PARAM_INT,
                                self::PARAMETER_PREFIX . md5('languageDefault')
                            )
                        )
                    );
                    break;
                case LanguageAspect::OVERLAYS_MIXED:
                    $builder->leftJoin(
                        $tableName,
                        $tableName,
                        'overlay',
                        (string) $builder->expr()->eq(
                            $tableName . '.uid',
                            $builder->quoteIdentifier('overlay.' . $translationParent)
                        )
                    )->andWhere(
                        $queryBuilder->expr()->orX(
                            $queryBuilder->expr()->andX(
                                $queryBuilder->expr()->neq(
                                    $tableName . '.' . $translationParent,
                                    $queryBuilder->createNamedParameter(
                                        0,
                                        \PDO::PARAM_INT,
                                        self::PARAMETER_PREFIX . md5('languageDefault')
                                    )
                                ),
                                $queryBuilder->expr()->eq(
                                    $tableName . '.' . $languageField,
                                    $queryBuilder->createNamedParameter(
                                        $this->languageAspect->getContentId(),
                                        \PDO::PARAM_INT,
                                        self::PARAMETER_PREFIX . md5('languageIdentifier')
                                    )
                                )
                            ),
                            $queryBuilder->expr()->in(
                                $tableName . '.' . $languageField,
                                $queryBuilder->createNamedParameter(
                                    [-1, 0],
                                    Connection::PARAM_INT_ARRAY,
                                    self::PARAMETER_PREFIX . md5('languageIdentifiers')
                                )
                            )
                        ),
                        $builder->expr()->isNull(
                            'overlay.uid'
                        )
                    );
                    break;
                case LanguageAspect::OVERLAYS_ON:
                    $queryBuilder->orWhere(
                        $queryBuilder->expr()->eq(
                            $tableName . '.' . $languageField,
                            $queryBuilder->createNamedParameter(
                                -1,
                                \PDO::PARAM_INT,
                                self::PARAMETER_PREFIX . md5('languageAll')
                            )
                        ),
                        $queryBuilder->expr()->andX(
                            $queryBuilder->expr()->eq(
                                $tableName . '.' . $languageField,
                                $builder->createNamedParameter(
                                    $this->languageAspect->getContentId(),
                                    \PDO::PARAM_INT,
                                    self::PARAMETER_PREFIX . md5('languageIdentifier')
                                )
                            ),
                            $queryBuilder->expr()->neq(
                                $tableName . '.' . $translationParent,
                                $builder->createNamedParameter(
                                    0,
                                    \PDO::PARAM_INT,
                                    self::PARAMETER_PREFIX . md5('languageDefault')
                                )
                            )
                        )
                    );
                    $languages[] = 0;
                    break;
                case LanguageAspect::OVERLAYS_ON_WITH_FLOATING:
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->in(
                            $tableName . '.' . $languageField,
                            $queryBuilder->createNamedParameter(
                                [-1, $this->languageAspect->getContentId()],
                                Connection::PARAM_INT_ARRAY,
                                self::PARAMETER_PREFIX . md5('languageIdentifiers')
                            )
                        )
                    );
                    break;
            }
        } elseif ($this->languageAspect->getContentId() === 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    $tableName . '.' . $languageField,
                    $queryBuilder->createNamedParameter(
                        [-1, 0],
                        Connection::PARAM_INT_ARRAY,
                        self::PARAMETER_PREFIX . md5('languageIdentifiers')
                    )
                )
            );
        }

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
            } else {
                $fieldNames[] = $selectIdentifier->getFieldName();
            }
        }

        foreach ($fieldNames as $fieldName) {
            $queryBuilder->addSelect($tableName . '.' . $fieldName);
        }

        return $queryBuilder;
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(QueryBuilder::class, $this->connection);
    }
}
