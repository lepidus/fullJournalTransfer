<?php

// Use the real PKP classes, but replace the database with a private in-memory SQLite fixture.
if (!function_exists('import')) {
    $ojs = getenv('FJT_TEST_OJS_PATH') ?: dirname(__DIR__, 5);
    define('BASE_SYS_DIR', $ojs);
    require $ojs . '/lib/pkp/includes/functions.inc.php';
    import('lib.pkp.classes.db.DAO');
}
require_once dirname(__DIR__, 2) . '/classes/FullJournalMetricsDAO.inc.php';

class StreamedMetricsCursorTest extends \PHPUnit\Framework\TestCase
{
    private $pdo;
    private $originalCapsule;

    protected function setUp(): void
    {
        $property = new ReflectionProperty(\Illuminate\Database\Capsule\Manager::class, 'instance');
        $property->setAccessible(true);
        $this->originalCapsule = $property->getValue();
        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $this->pdo = $capsule->getConnection()->getPdo();
        $this->pdo->exec('CREATE TABLE metrics (context_id INTEGER, assoc_type INTEGER, assoc_id INTEGER,'
            . ' day TEXT, country_id TEXT, region TEXT, city TEXT, file_type INTEGER, load_id TEXT,'
            . ' metric INTEGER, metric_type TEXT)');
        $insert = $this->pdo->prepare('INSERT INTO metrics VALUES (?, 256, 123, ?, NULL, NULL, NULL, NULL, ?, 7, ?)');
        foreach ([1, 1, 2] as $contextId) {
            $insert->execute([$contextId, '20261028', 'fixture', 'ojs::counter']);
        }
    }

    protected function tearDown(): void
    {
        if ($this->originalCapsule) {
            $this->originalCapsule->setAsGlobal();
        } else {
            $property = new ReflectionProperty(\Illuminate\Database\Capsule\Manager::class, 'instance');
            $property->setAccessible(true);
            $property->setValue(null, null);
        }
    }

    public function testStreamsOnlyTheRequestedContextAndPreservesDuplicates(): void
    {
        $result = (new FullJournalMetricsDAO(false))->withExportRows(1, function ($rows) {
            $this->assertInstanceOf(Generator::class, $rows);
            return iterator_to_array($rows);
        });
        $this->assertCount(2, $result);
        $this->assertSame($result[0], $result[1]);
        $this->assertSame('fixture', $result[0]['load_id']);
        $this->assertNull($result[0]['country_id']);
    }

    public function testEmptyContextYieldsNoRows(): void
    {
        $result = (new FullJournalMetricsDAO(false))->withExportRows(999, function ($rows) {
            return iterator_to_array($rows);
        });
        $this->assertSame([], $result);
    }

    public function testFailingConsumerClosesTheCursor(): void
    {
        try {
            (new FullJournalMetricsDAO(false))->withExportRows(1, function ($rows) {
                foreach ($rows as $row) {
                    throw new RuntimeException('Consumer failed.');
                }
            });
            $this->fail('Consumer exception must propagate.');
        } catch (RuntimeException $error) {
            $this->assertSame('Consumer failed.', $error->getMessage());
            // Keep the exception alive: its trace may retain references to the generator.
            $this->assertSame(3, (int) $this->pdo->query('SELECT count(*) FROM metrics')->fetchColumn());
        }
    }
}
