<?php

require_once dirname(__DIR__, 2) . '/classes/StreamedJournalExport.inc.php';

class StreamedJournalExportTest extends \PHPUnit\Framework\TestCase
{
    private $path;
    private $schema;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'fjt-xml-test-');
        $schema = defined('BASE_SYS_DIR')
            ? BASE_SYS_DIR . '/plugins/importexport/fullJournalTransfer/fullJournal.xsd'
            : dirname(__DIR__, 2) . '/fullJournal.xsd';
        $this->schema = getenv('FJT_TEST_SCHEMA') ?: $schema;
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    private function document(): DOMDocument
    {
        $document = new DOMDocument('1.0', 'utf-8');
        $document->loadXML('<journal xmlns="http://pkp.sfu.ca"><email_signature>A &amp; B</email_signature>'
            . '<supported_locales>pt_BR</supported_locales><submission_checklist locale="pt_BR"/></journal>');
        return $document;
    }

    private function metric(): array
    {
        return [
            'assoc_type' => 256, 'assoc_id' => 123, 'day' => '20261028',
            'metric' => 7, 'metric_type' => 'ojs::counter', 'load_id' => 'A & "B" <C>',
            'country_id' => 'BR', 'region' => 'AM', 'city' => 'São Gabriel', 'file_type' => 1,
        ];
    }

    public function testPreservesMetadataAndAllMetricRows(): void
    {
        $row = $this->metric();
        $rows = (function () use ($row) {
            yield $row;
            yield $row; // Identical records must not be deduplicated.
        })();
        $writer = new StreamedJournalExport();
        $writer->write($this->document(), $rows, $this->path);
        $writer->validate($this->path, $this->schema);

        $actual = new DOMDocument();
        $actual->load($this->path);
        $metrics = $actual->getElementsByTagNameNS('http://pkp.sfu.ca', 'metric');
        $this->assertSame(2, $metrics->length);
        foreach ($row as $key => $value) {
            $this->assertSame((string) $value, $metrics->item(0)->getAttribute($key));
        }
        $this->assertSame('A & B', $actual->getElementsByTagName('email_signature')->item(0)->textContent);
    }

    public function testEmptyMetricsPreserveTheDocument(): void
    {
        $writer = new StreamedJournalExport();
        $expected = $this->document();
        $writer->write($expected, [], $this->path);
        $writer->validate($this->path, $this->schema);
        $actual = new DOMDocument();
        $actual->load($this->path);
        $this->assertSame($expected->C14N(), $actual->C14N());
    }

    public function testValidatesLargeTextNodesProducedByExport(): void
    {
        $document = $this->document();
        $document->getElementsByTagName('email_signature')->item(0)->nodeValue = str_repeat('x', 11 * 1024 * 1024);
        $writer = new StreamedJournalExport();
        $writer->write($document, [], $this->path);
        // A valid generated text node above libxml's default 10 MiB limit must still validate.
        $this->expectNotToPerformAssertions();
        $writer->validate($this->path, $this->schema);
    }

    public function testRejectsInvalidMetricCharactersInsteadOfChangingData(): void
    {
        $row = $this->metric();
        $row['load_id'] = "invalid\x01";
        $this->expectException(RuntimeException::class);
        (new StreamedJournalExport())->write($this->document(), [$row], $this->path);
    }

    public function testCleansTemporaryMetricsOnFailure(): void
    {
        $directory = sys_get_temp_dir() . '/fjt-failure-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $path = $directory . '/journal.xml';
        $row = $this->metric();
        $row['load_id'] = "invalid\x01";
        try {
            try {
                (new StreamedJournalExport())->write($this->document(), (function () use ($row) {
                    yield $row;
                })(), $path);
                $this->fail('Invalid metric should abort the export.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('Invalid XML character', $error->getMessage());
            }
            $this->assertSame([], glob($directory . '/fjt-metrics-*'));
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    public function testRejectsInvalidFinalSchema(): void
    {
        $row = $this->metric();
        $row['metric'] = 'invalid-integer';
        $writer = new StreamedJournalExport();
        $writer->write($this->document(), [$row], $this->path);
        $this->expectException(RuntimeException::class);
        $writer->validate($this->path, $this->schema);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLargeMetricSetDoesNotGrowResidentMemoryWithTheDocument(): void
    {
        if (!is_readable('/proc/self/status')) {
            $this->markTestSkipped('RSS measurement requires Linux /proc.');
        }
        $row = $this->metric();
        $rows = (function () use ($row) {
            for ($i = 0; $i < 200000; $i++) {
                $row['assoc_id'] = $i;
                $row['load_id'] = 'fixture-' . $i;
                yield $row;
            }
        })();
        preg_match('/^VmHWM:\s+(\d+)/m', file_get_contents('/proc/self/status'), $before);
        $writer = new StreamedJournalExport();
        $writer->write($this->document(), $rows, $this->path);
        $writer->validate($this->path, $this->schema);
        preg_match('/^VmHWM:\s+(\d+)/m', file_get_contents('/proc/self/status'), $after);
        $growthKiB = (int) $after[1] - (int) $before[1];
        $this->assertLessThan(32768, $growthKiB, 'Export and validation must not materialize metrics in DOM.');
        $this->assertGreaterThan(10000000, filesize($this->path));
    }
}
