<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlJournalFilter');
import('plugins.importexport.fullJournalTransfer.classes.FullJournalMetricsDAO');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportDeployment');
import('classes.journal.Journal');

class NativeXmlMetricsImportTest extends \PHPUnit\Framework\TestCase
{
    private $originalCapsule;
    private $originalDAOs;
    private $connection;
    private $dao;
    private $lookups;

    protected function setUp(): void
    {
        // All writes stay in a disposable database, never in the installed journal.
        $property = new ReflectionProperty(Capsule::class, 'instance');
        $property->setAccessible(true);
        $this->originalCapsule = $property->getValue();
        $this->originalDAOs = DAORegistry::getDAOs();
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $this->connection = Capsule::connection();
        $this->connection->statement('CREATE TABLE metrics (
            load_id TEXT NOT NULL, context_id INTEGER NOT NULL, pkp_section_id INTEGER,
            assoc_object_type INTEGER, assoc_object_id INTEGER, submission_id INTEGER,
            representation_id INTEGER, assoc_type INTEGER NOT NULL, assoc_id INTEGER NOT NULL,
            day TEXT, month TEXT, file_type INTEGER, country_id TEXT, region TEXT, city TEXT,
            metric_type TEXT NOT NULL, metric INTEGER NOT NULL CHECK (metric <= 2147483647)
        )');
        $this->lookups = [];
        $this->dao = $this->getMockBuilder(FullJournalMetricsDAO::class)
            ->onlyMethods(['foreignKeyLookup'])->getMock();
        $this->dao->method('foreignKeyLookup')->willReturnCallback(function ($type, $id) {
            $this->lookups[] = [$type, $id];
            return [42, 7, ASSOC_TYPE_ISSUE, 9, 12, null];
        });
        DAORegistry::registerDAO('MetricsDAO', $this->dao);
        DAORegistry::registerDAO('FullJournalMetricsDAO', $this->dao);
        $this->connection->enableQueryLog();
    }

    protected function tearDown(): void
    {
        $this->connection->disconnect();
        $this->originalCapsule->setAsGlobal();
        $daos = & DAORegistry::getDAOs();
        $daos = $this->originalDAOs;
    }

    public function testImportsFullAndPartialBatchesWithoutRepeatedLookups()
    {
        $this->importMetrics(array_fill(0, 501, $this->metric()));
        $queries = $this->connection->getQueryLog();

        $this->assertSame(501, $this->connection->table('metrics')->count());
        $this->assertSame(1002, (int) $this->connection->table('metrics')->sum('metric'));
        $this->assertCount(2, $queries, '501 metrics should need only two INSERT statements.');
        $this->assertCount(2, $this->lookups, 'Resolve each association once per bounded batch.');
    }

    public function testRemapsAllSupportedAssociations()
    {
        $types = [ASSOC_TYPE_SUBMISSION_FILE, ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER,
            ASSOC_TYPE_REPRESENTATION, ASSOC_TYPE_SUBMISSION, Application::getContextAssocType(),
            ASSOC_TYPE_ISSUE_GALLEY, ASSOC_TYPE_ISSUE];
        $records = [];
        foreach ($types as $type) {
            $records[] = array_replace($this->metric(), ['assoc_type' => $type]);
        }
        $this->importMetrics($records);
        $this->assertSame(
            [102, 102, 103, 104, 42, 105, 106],
            array_map('intval', $this->connection->table('metrics')->pluck('assoc_id')->all())
        );
    }

    public function testInvalidRecordDoesNotDiscardValidNeighbours()
    {
        $deployment = $this->importMetrics([
            $this->metric(),
            array_replace($this->metric(), ['day' => 'invalid']),
            array_replace($this->metric(), ['metric' => 3]),
        ]);
        $this->assertSame(2, $this->connection->table('metrics')->count());
        $this->assertSame(5, (int) $this->connection->table('metrics')->sum('metric'));
        $this->assertCount(1, $deployment->getProcessedObjectsWarnings(ASSOC_TYPE_JOURNAL)[42]);
    }

    public function testDatabaseFailureKeepsValidRowsWithoutDuplicatesInsideTransaction()
    {
        $this->connection->beginTransaction();
        $deployment = $this->importMetrics([
            $this->metric(),
            array_replace($this->metric(), ['metric' => '2147483648']),
            array_replace($this->metric(), ['metric' => 3]),
        ]);
        $this->assertSame(2, $this->connection->table('metrics')->count());
        $this->assertSame(5, (int) $this->connection->table('metrics')->sum('metric'));
        $this->assertCount(1, $deployment->getProcessedObjectsWarnings(ASSOC_TYPE_JOURNAL)[42]);
        $this->assertSame(1, $this->connection->transactionLevel());
        $this->connection->rollBack();
        $this->assertSame(0, $this->connection->table('metrics')->count());
    }

    public function testEmptyMetricsDoNotWriteToDatabase()
    {
        $this->importMetrics([]);
        $this->assertSame([], $this->connection->getQueryLog());
        $this->assertSame([], $this->lookups);
    }

    /** @dataProvider recordVariants */
    public function testBatchPreservesNativeNormalizationAndValidation($changes, $omit)
    {
        $record = array_replace($this->metric(), $changes);
        foreach ($omit as $key) {
            unset($record[$key]);
        }
        $nativeErrors = [];
        try {
            $this->dao->insertRecord($record);
        } catch (Exception $e) {
            $nativeErrors[] = $e->getMessage();
        }
        $expected = $this->connection->table('metrics')->get()->all();
        $this->connection->table('metrics')->delete();
        $batchErrors = [];
        $this->dao->insertRecords([$record], function ($e) use (&$batchErrors) {
            $batchErrors[] = $e->getMessage();
        });
        $this->assertEquals($expected, $this->connection->table('metrics')->get()->all());
        $this->assertSame($nativeErrors, $batchErrors);
    }

    public function recordVariants()
    {
        return [
            'all dimensions' => [[], []],
            'optional dimensions absent' => [[], ['file_type', 'country_id', 'region', 'city']],
            'optional dimensions empty' => [['file_type' => '', 'country_id' => '', 'region' => '', 'city' => ''], []],
            'optional dimensions null' => [
                ['file_type' => null, 'country_id' => null, 'region' => null, 'city' => null], [],
            ],
            'monthly' => [['month' => '202401'], ['day']],
            'matching month' => [['month' => '202401'], []],
            'numeric strings' => [['metric' => '3.7', 'file_type' => '1'], []],
            'zero' => [['metric' => 0], []],
            'negative' => [['metric' => -1], []],
            'bound text' => [['city' => "L'Aquila", 'load_id' => "x'); DELETE FROM metrics; --"], []],
            'missing load' => [[], ['load_id']],
            'missing association type' => [[], ['assoc_type']],
            'missing association id' => [[], ['assoc_id']],
            'missing metric type' => [[], ['metric_type']],
            'null association' => [['assoc_id' => null], []],
            'invalid date' => [['day' => 'invalid'], []],
            'invalid month' => [['month' => 'invalid'], ['day']],
            'mismatched month' => [['month' => '202402'], []],
            'missing time' => [[], ['day']],
            'missing metric' => [[], ['metric']],
            'invalid metric' => [['metric' => 'invalid'], []],
        ];
    }

    public function testMissingMappedAssociationWarnsAndContinues()
    {
        $deployment = $this->importMetrics([
            array_replace($this->metric(), ['assoc_type' => 999999]),
            $this->metric(),
        ]);
        $this->assertSame(1, $this->connection->table('metrics')->count());
        $this->assertCount(1, $deployment->getProcessedObjectsWarnings(ASSOC_TYPE_JOURNAL)[42]);
    }

    public function testFailedLookupDoesNotDiscardOtherAssociations()
    {
        $dao = $this->getMockBuilder(FullJournalMetricsDAO::class)
            ->onlyMethods(['foreignKeyLookup'])->getMock();
        $dao->method('foreignKeyLookup')->willReturnCallback(function ($type, $id) {
            if ($id === 95) {
                throw new Exception('Cannot load record: invalid submission file id.');
            }
            return [42, null, null, null, null, null];
        });
        $errors = [];
        $dao->insertRecords([
            $this->metric(), array_replace($this->metric(), ['assoc_id' => 95]), $this->metric(),
        ], function ($e) use (&$errors) {
            $errors[] = $e->getMessage();
        });
        $this->assertSame(2, $this->connection->table('metrics')->count());
        $this->assertSame(['Cannot load record: invalid submission file id.'], $errors);
    }

    public function testNonTransactionalTableUsesSingleWritesAndStillCachesAssociations()
    {
        $dao = $this->getMockBuilder(FullJournalMetricsDAO::class)
            ->onlyMethods(['foreignKeyLookup', 'supportsBatchTransactions'])->getMock();
        $dao->expects($this->once())->method('foreignKeyLookup')->willReturn([42, null, null, null, null, null]);
        $dao->method('supportsBatchTransactions')->willReturn(false);
        $errors = [];
        $dao->insertRecords([
            $this->metric(), array_replace($this->metric(), ['metric' => '2147483648']), $this->metric(),
        ], function ($e) use (&$errors) {
            $errors[] = $e;
        });
        $this->assertCount(2, $this->connection->getQueryLog());
        $this->assertSame(2, $this->connection->table('metrics')->count());
        $this->assertCount(1, $errors);
    }

    /** @dataProvider infrastructureErrors */
    public function testInfrastructureFailureIsNotRetriedAsInvalidData($sqlState)
    {
        $dao = $this->getMockBuilder(FullJournalMetricsDAO::class)
            ->onlyMethods(['foreignKeyLookup', 'update'])->getMock();
        $dao->method('foreignKeyLookup')->willReturn([42, null, null, null, null, null]);
        $cause = new PDOException('Simulated infrastructure failure');
        $cause->errorInfo = [$sqlState, 0, $cause->getMessage()];
        $error = new \Illuminate\Database\QueryException('INSERT INTO metrics ...', [], $cause);
        // QueryException normally inherits the SQLSTATE from the driver's exception code.
        $code = new ReflectionProperty(Exception::class, 'code');
        $code->setAccessible(true);
        $code->setValue($error, $sqlState);
        $dao->expects($this->once())->method('update')->willThrowException($error);
        $this->expectException(\Illuminate\Database\QueryException::class);
        $dao->insertRecords([$this->metric(), $this->metric()], function () {
            $this->fail('Infrastructure failure must propagate, not become a skipped metric.');
        });
    }

    public function infrastructureErrors()
    {
        return ['connection' => ['08006'], 'deadlock' => ['40001'], 'missing table' => ['42S02']];
    }

    private function metric()
    {
        return ['assoc_type' => ASSOC_TYPE_SUBMISSION_FILE, 'assoc_id' => 94,
            'day' => '20240101', 'country_id' => 'BR', 'region' => '27', 'city' => 'São Paulo',
            'file_type' => 1, 'metric' => 2, 'metric_type' => 'ojs::counter',
            'load_id' => 'usage_events_20240101.log'];
    }

    private function importMetrics(array $records)
    {
        $group = new FilterGroup();
        $group->setInputType('xml::schema(plugins/importexport/fullJournalTransfer/fullJournal.xsd)');
        $group->setOutputType('class::classes.journal.Journal[]');
        $filter = new NativeXmlJournalFilter($group);
        $journal = new Journal();
        $journal->setId(42);
        $deployment = new FullJournalImportExportDeployment($journal);
        $deployment->setSubmissionFileDBId(94, 102);
        $deployment->setRepresentationDBId(94, 103);
        $deployment->setSubmissionDBId(94, 104);
        $deployment->setIssueGalleyDBId(94, 105);
        $deployment->setIssueDBId(94, 106);
        $filter->setDeployment($deployment);
        $document = new DOMDocument();
        $root = $document->appendChild($document->createElement('metrics'));
        foreach ($records as $record) {
            $node = $root->appendChild($document->createElement('metric'));
            foreach ($record as $key => $value) {
                $node->setAttribute($key, (string) $value);
            }
        }
        ob_start();
        try {
            $filter->parseMetrics($root, $journal);
        } finally {
            ob_end_clean();
        }
        return $deployment;
    }
}
