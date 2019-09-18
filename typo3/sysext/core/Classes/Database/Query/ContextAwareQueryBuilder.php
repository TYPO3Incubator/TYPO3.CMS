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
use TYPO3\CMS\Core\Database\Query\Context\LanguageAspectView;
use TYPO3\CMS\Core\Database\Query\Context\WorkspaceAspectView;
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

        if (!$this->context->hasAspect('workspace') && !$this->context->hasAspect('language')) {
            return parent::execute();
        }

        $aspectViewQueries = [];

        if ($this->context->hasAspect('workspace')) {
            $workspaceAspectView = new WorkspaceAspectView($this->connection, $this->context->getAspect('workspace'));
            $aspectViewQueries[] = $workspaceAspectView->buildQuery($this->tableIdentifier, $this->selectIdentifierCollection);
        }

        if ($this->context->hasAspect('language')) {
            $languageAspectView = new LanguageAspectView($this->connection, $this->context->getAspect('language'));
            $aspectViewQueries[] = $languageAspectView->buildQuery($this->tableIdentifier, $this->selectIdentifierCollection);
        }

        $innerQuery = end($aspectViewQueries);

        while ($outerQuery = prev($aspectViewQueries)) {
            $innerQuery = $this->mergeInlineView($outerQuery, $innerQuery);
        }

        $this->mergeInlineView($this, $innerQuery);

        return $this->concreteQueryBuilder->execute();
    }

    /**
     * Deep clone of the QueryBuilder
     * @see \Doctrine\DBAL\Query\QueryBuilder::__clone()
     */
    public function __clone()
    {
        parent::__clone();
        $this->context = clone $this->context;
    }

    private function mergeInlineView(QueryBuilder $outerQueryBuilder, QueryBuilder $innerQueryBuilder): QueryBuilder
    {
        $outerQueryBuilder->add(
            'from',
            [
                [
                    'table' => sprintf('(%s)', $innerQueryBuilder->getSQL()), 
                    'alias' => $this->quoteIdentifier(
                        $this->tableIdentifier->getAlias() ?? $this->tableIdentifier->getTableName()
                    )
                ]
            ],
            false
        );

        foreach ($innerQueryBuilder->getParameters() as $key => $value) {
            // @todo Throw exception if parameter is already set
            $outerQueryBuilder->setParameter(
                $key,
                $value,
                $innerQueryBuilder->getParameterType($key)
            );
        }

        return $outerQueryBuilder;
    }
}
