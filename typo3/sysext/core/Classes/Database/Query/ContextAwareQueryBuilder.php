<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\Database\Query;

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

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\RecordRestrictionInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Object oriented approach to building SQL queries.
 *
 * This is an advanced query over the simple Doctrine DBAL / TYPO3 QueryBuilder by taking into account the
 * context - that is next to enableFields the proper fetching of resolved langauge and workspace records, if given.
 *
 *
 * For this to work, the concept of map/reduce is applied. Example:
 *
 * - Fetch all records that match live and workspace ID
 * - Fetch all records that match the target language + the fallback records
 *
 * Then filter out the criteria that match based on enableFields + "overlays".
 */
class ContextAwareQueryBuilder extends QueryBuilder
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var SelectIdentifierCollection
     */
    private $selectIdentifierCollection;

    /**
     * @var TableIdentifier
     */
    private $tableIdentifier;

    /**
     * Initializes a new QueryBuilder.
     *
     * @param Connection $connection The DBAL Connection.
     * @param Context $context
     * @param QueryRestrictionContainerInterface $restrictionContainer
     * @param \Doctrine\DBAL\Query\QueryBuilder $concreteQueryBuilder
     * @param array $additionalRestrictions
     */
    public function __construct(
        Connection $connection,
        Context $context,
        QueryRestrictionContainerInterface $restrictionContainer = null,
        \Doctrine\DBAL\Query\QueryBuilder $concreteQueryBuilder = null,
        array $additionalRestrictions = null
    ) {
        parent::__construct($connection, $restrictionContainer, $concreteQueryBuilder, $additionalRestrictions);
        $this->context = $context;
    }

    public function select(string ...$selects): QueryBuilder
    {
        $this->selectIdentifierCollection = SelectIdentifierCollection::fromExpressions(...$selects);
        return parent::select(...$selects);
    }

    public function from(string $from, string $alias = null): QueryBuilder
    {
        $this->tableIdentifier = TableIdentifier::create($from, $alias);
        return parent::from($from, $alias);
    }

    /**
     * Executes this query using the bound parameters and their types.
     *
     * @return \Doctrine\DBAL\Driver\Statement|ContextAwareStatement|int
     */
    public function execute()
    {
        if ($this->getType() !== \Doctrine\DBAL\Query\QueryBuilder::SELECT) {
            return parent::execute();
        }
        if (!$this->context->hasAspect('workspace') || !$this->context->hasAspect('language')) {
            return parent::execute();
        }

        $subqueryBuilder = GeneralUtility::makeInstance(QueryBuilder::class, $this->connection);

        $this->prepareWorkspaceSubquery($subqueryBuilder);

        $this->concreteQueryBuilder->resetQueryPart('from');
        $this->concreteQueryBuilder->from(
            sprintf('(%s)', $subqueryBuilder->getSQL()), 
            $this->quoteIdentifier($this->tableIdentifier->getAlias() ?? $this->tableIdentifier->getTableName())
        );

        foreach ($subqueryBuilder->getParameters() as $key => $value) {
            // @todo Throw exception if parameter is already set
            $this->concreteQueryBuilder->setParameter(
                $key, 
                $value, 
                $subqueryBuilder->getParameterType($key)
            );
        }

        return $this->concreteQueryBuilder->execute();
    }

    private function prepareWorkspaceSubquery(QueryBuilder $queryBuilder)
    {
        $tableName = $this->tableIdentifier->getTableName();
        $workspaceId = (int) $this->context->getAspect('workspace')->getId();
        $fieldNames = [];

        // @todo Support any literal
        foreach ($this->selectIdentifierCollection as $selectIdentifier) {
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

                $fieldNames[] = '_ORIG_uid';
                $fieldNames[] = '_ORIG_pid';
            } else {
                $fieldNames[] = $selectIdentifier->getFieldName();
            }
        }

        foreach ($fieldNames as $fieldName) {
            if ($fieldName === 'uid') {
                $queryBuilder->addSelectLiteral(
                    sprintf(
                        'COALESCE(%s,%s) AS %s',
                        $queryBuilder->quoteIdentifier('original.uid'),
                        $queryBuilder->quoteIdentifier($tableName . '.uid'),
                        $queryBuilder->quoteIdentifier('uid')
                    )
                );
            } elseif ($fieldName === 'pid') {
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

        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        $queryBuilder
            ->from($tableName)
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
                            ':_' . md5('workspaceLive')
                        )
                    ),
                    $queryBuilder->expr()->eq(
                        'version.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            $workspaceId,
                            \PDO::PARAM_INT,
                            ':_' . md5('workspaceContext')
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
                            ':_' . md5('workspaceContext')
                        )
                    ),
                    $queryBuilder->expr()->in(
                        'original.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            [0, $workspaceId],
                            Connection::PARAM_INT_ARRAY,
                            ':_' . md5('workspaceIdentifiers')
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
                            ':_' . md5('workspaceLive')
                        )
                    ),
                    $queryBuilder->expr()->eq(
                        $tableName . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            $workspaceId,
                            \PDO::PARAM_INT,
                            ':_' . md5('workspaceContext')
                        )
                    ),
                    $queryBuilder->expr()->in(
                        'placeholder.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            [0, $workspaceId],
                            Connection::PARAM_INT_ARRAY,
                            ':_' . md5('workspaceIdentifiers')
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
                                ':_' . md5('workspaceLive')
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
                                ':_' . md5('workspaceContext')
                            )
                        ),
                        $queryBuilder->expr()->notIn(
                            $tableName . '.t3ver_state',
                            $queryBuilder->createNamedParameter(
                                [1, 3],
                                Connection::PARAM_INT_ARRAY,
                                ':_' . md5('workspaceStates')
                            )
                        )
                    )
                )
            );
    }

    /**
     * Deep clone of the QueryBuilder
     * @see \Doctrine\DBAL\Query\QueryBuilder::__clone()
     */
    public function __clone()
    {
        parent::__clone();;
        $this->context = clone $this->context;
    }
}
