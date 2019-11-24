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
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Tests\Functional\DataHandling\AbstractDataHandlerActionTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet;

/**
 * Test case
 */
class ContextAwareQueryTest extends AbstractDataHandlerActionTestCase
{
    /**
     * @var string
     */
    protected $scenarioDataSetDirectory = 'typo3/sysext/core/Tests/Functional/Database/Fixtures/DataSet/';

    /**
     * @var array
     */
    protected $coreExtensionsToLoad = ['workspaces'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importScenarioDataSet('WorkspaceQueryScenario');
    }

    public function resultScenarioProvider()
    {
        return [
            [0, -1, 'pages', 'LiveWorkspaceResultScenario.csv'],
            [1, -1, 'pages', 'DraftWorkspaceResultScenario.csv'],
            [0, -1, 'tt_content', 'LiveWorkspaceResultScenario.csv'],
            [1, -1, 'tt_content', 'DraftWorkspaceResultScenario.csv'],
            [1, 0, 'tt_content', 'DefaultLanguageDraftWorkspaceResultScenario.csv'],
        ];
    }

    /**
     * @param string $tableName
     * @param string $dataSetFile
     *
     * @test
     * @dataProvider resultScenarioProvider
     */
    public function shouldReturnResultScenarioInAnyOrder(int $workspaceIdentifier, int $languageIdentifier, string $tableName, string $dataSetFile)
    {
        $context = clone GeneralUtility::makeInstance(Context::class);
        $context->setAspect('workspace', GeneralUtility::makeInstance(WorkspaceAspect::class, $workspaceIdentifier));
        $context->setAspect('language', GeneralUtility::makeInstance(LanguageAspect::class, $languageIdentifier));

        $dataSet = DataSet::read($this->scenarioDataSetDirectory . $dataSetFile);

        $subject = $this->getConnectionPool()->getConnectionForTable($tableName)->createContextAwareQueryBuilder($context);

        $actual = $subject
            ->select(...$dataSet->getFields($tableName))
            ->from($tableName)
            ->execute()
            ->fetchAll(FetchMode::ASSOCIATIVE);

        $expected = $dataSet->getElements($tableName);

        $this->assertEquals($expected, $this->applyRecordIndex($actual));
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
}
