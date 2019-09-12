<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Tests\Functional\Database;

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

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\FetchMode;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Database\Query\Restriction\ContextRestrictionContainer;
use TYPO3\CMS\Core\Tests\Functional\DataHandling\AbstractDataHandlerActionTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet;

/**
 * Test case
 */
class ContextAwareQueryTest extends AbstractDataHandlerActionTestCase
{
    private const VALUE_WorkspaceId = 1;
    private const VALUE_PageId = 89;
    private const VALUE_PageIdTarget = 90;
    private const VALUE_PageIdSuperfluous = 91;
    private const VALUE_PageIdMoveAround = 92;
    private const VALUE_ContentIdFirst = 297;
    private const VALUE_ContentIdSecond = 298;
    private const VALUE_ContentIdThird = 299;

    private const TABLE_Page = 'pages';
    private const TABLE_Content = 'tt_content';

    /**
     * @var string
     */
    protected $scenarioDataSetDirectory = '';

    /**
     * @var array
     */
    protected $coreExtensionsToLoad = ['workspaces'];

    // protected $testExtensionsToLoad = ['typo3conf/ext/data_handler_tools'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importScenario();
        // $this->createScenario();
    }

    private function importScenario()
    {
        $this->scenarioDataSetDirectory = 'typo3/sysext/core/Tests/Functional/Database/Fixtures/DataSet/';
        $this->importScenarioDataSet('WorkspaceQueryScenario');
    }

    /**
     * @todo Remove once scenario is okay...
     */
    private function createScenario()
    {
        $this->scenarioDataSetDirectory = 'typo3/sysext/workspaces/Tests/Functional/DataHandling/Regular/DataSet/';
        $this->importScenarioDataSet('LiveDefaultPages');
        $this->importScenarioDataSet('LiveDefaultElements');

        $this->importScenarioDataSet('LivePageFreeModeElements');
        $this->importScenarioDataSet('VersionDefaultElements');
        $this->importScenarioDataSet('ReferenceIndex');

        $this->setWorkspaceId(self::VALUE_WorkspaceId);
        // create content
        $newTableIds = $this->actionService->createNewRecord(self::TABLE_Content, self::VALUE_PageId, ['header' => 'Testing #1']);
        $this->recordIds['newContentIdFirst'] = $newTableIds[self::TABLE_Content][0];
        // modify content
        $this->actionService->modifyRecord(self::TABLE_Content, self::VALUE_ContentIdFirst, ['header' => 'Testing #1']);
        // delete content
        $this->actionService->deleteRecord(self::TABLE_Content, self::VALUE_ContentIdSecond);
        // move content
        $this->actionService->moveRecord(self::TABLE_Content, self::VALUE_ContentIdThird, self::VALUE_PageIdTarget);
        // create page
        $newTableIds = $this->actionService->createNewRecord(self::TABLE_Page, self::VALUE_PageId, ['title' => 'Testing #1', 'hidden' => 0]);
        $this->recordIds['newPageId'] = $newTableIds[self::TABLE_Page][0];
        // modify page
        $this->actionService->modifyRecord(self::TABLE_Page, self::VALUE_PageId, ['title' => 'Testing #1']);
        // delete page
        $this->actionService->deleteRecord(self::TABLE_Page, self::VALUE_PageIdSuperfluous);
        // move page
        $this->actionService->moveRecord(self::TABLE_Page, self::VALUE_PageIdMoveAround, self::VALUE_PageIdTarget);
        // create content on created page
        $newTableIds = $this->actionService->createNewRecord(self::TABLE_Content, $this->recordIds['newPageId'], ['header' => 'Testing #2']);
        $this->recordIds['newContentIdSecond'] = $newTableIds[self::TABLE_Content][0];

        $dataSet = DataSet::read(__DIR__ . '/Fixtures/DataSet/WorkspaceQueryScenario.csv');
        $exportService = \OliverHader\DataHandlerTools\Service\ExportService::getInstance();
        $exportService->setExportPath(ORIGINAL_ROOT);
        $exportService->setFields($dataSet);
        $exportService->reExport($dataSet->getTableNames(), $this, 'WorkspaceQueryScenario');
    }

    /**
     * @param string $tableName
     * @param string $dataSetFile
     *
     * @test
     */
    public function liveWorkspaceRecordsAreRetrieved(string $tableName = 'tt_content', string $dataSetFile = __DIR__ . '/Fixtures/DataSet/LiveWorkspaceResultScenario.csv')
    {
        $context = clone GeneralUtility::makeInstance(Context::class);
        $context->setAspect('workspace', GeneralUtility::makeInstance(WorkspaceAspect::class, 0));

        $queryBuilder = $this->getConnectionPool()->getConnectionForTable($tableName)->createContextAwareQueryBuilder($context);
        $statement = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->execute();

        $dataSet = DataSet::read($dataSetFile);
        $records = $this->retrieveRecordsUsingFetchAll($statement, true, FetchMode::ASSOCIATIVE);
        $this->assertDataSetEntries($dataSet, $tableName, $records);
    }

    /**
     * @param string $tableName
     * @param string $dataSetFile
     *
     * @test
     */
    public function draftWorkspaceRecordsAreRetrieved(string $tableName = 'tt_content', string $dataSetFile = __DIR__ . '/Fixtures/DataSet/DraftWorkspaceResultScenario.csv')
    {
        $context = clone GeneralUtility::makeInstance(Context::class);
        $context->setAspect('workspace', GeneralUtility::makeInstance(WorkspaceAspect::class, 1));

        $queryBuilder = $this->getConnectionPool()->getConnectionForTable($tableName)->createContextAwareQueryBuilder($context);
        $statement = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->execute();

        $dataSet = DataSet::read($dataSetFile);
        $records = $this->retrieveRecordsUsingFetchAll($statement, true, FetchMode::ASSOCIATIVE);
        $this->assertDataSetEntries($dataSet, $tableName, $records);
    }

    private function retrieveRecordsUsingFetch(ResultStatement $statement, bool $hasUidField = true, int $fetchMode = null): array
    {
        $allRecords = [];
        while ($record = $statement->fetch($fetchMode)) {
            $allRecords[] = $record;
        }
        if ($hasUidField) {
            return $this->applyRecordIndex($allRecords);
        }
        return $allRecords;
    }

    private function retrieveRecordsUsingFetchAll(ResultStatement $statement, bool $hasUidField = true, int $fetchMode = null): array
    {
        $allRecords = $statement->fetchAll($fetchMode);
        if ($hasUidField) {
            return $this->applyRecordIndex($allRecords);
        }
        return $allRecords;
    }

    private function applyRecordIndex(array $records): array
    {
        $values = array_values($records);
        $keys = array_map(
            function(array $record) {
                return $record['uid'] ?? $record->uid ?? $record[0] ?? null;
            },
            $values
        );
        return array_combine($keys, $values);
    }

    /**
     * @param DataSet $dataSet
     * @param string $tableName
     * @param array $records
     *
     * @todo Add as separate method to testing-framework
     */
    private function assertDataSetEntries(DataSet $dataSet, string $tableName, array $records)
    {
        $failMessages = [];
        $hasUidField = ($dataSet->getIdIndex($tableName) !== null);
        foreach ($dataSet->getElements($tableName) as $assertion) {
            $result = $this->assertInRecords($assertion, $records);
            if ($result === false) {
                if ($hasUidField && empty($records[$assertion['uid']])) {
                    $failMessages[] = 'Record "' . $tableName . ':' . $assertion['uid'] . '" not found in database';
                    continue;
                }
                $recordIdentifier = $tableName . ($hasUidField ? ':' . $assertion['uid'] : '');
                $additionalInformation = ($hasUidField ? $this->renderRecords($assertion, $records[$assertion['uid']]) : $this->arrayToString($assertion));
                $failMessages[] = 'Assertion in data-set failed for "' . $recordIdentifier . '":' . LF . $additionalInformation;
                // Unset failed asserted record
                if ($hasUidField) {
                    unset($records[$assertion['uid']]);
                }
            } else {
                // Unset asserted record
                unset($records[$result]);
                // Increase assertion counter
                $this->assertTrue($result !== false);
            }
        }
        if (!empty($records)) {
            foreach ($records as $record) {
                $recordIdentifier = $tableName . ':' . $record['uid'];
                $emptyAssertion = array_fill_keys($dataSet->getFields($tableName), '[none]');
                $reducedRecord = array_intersect_key($record, $emptyAssertion);
                $additionalInformation = ($hasUidField ? $this->renderRecords($emptyAssertion, $reducedRecord) : $this->arrayToString($reducedRecord));
                $failMessages[] = 'Not asserted record found for "' . $recordIdentifier . '":' . LF . $additionalInformation;
            }
        }

        if (!empty($failMessages)) {
            $this->fail(implode(LF, $failMessages));
        }
    }
}
