<?php

import('lib.pkp.tests.PKPTestCase');
import('classes.journal.Journal');
import('plugins.importexport.fullJournalTransfer.classes.FullJournalMetricsDAO');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportDeployment');
import('plugins.importexport.fullJournalTransfer.filter.export.JournalNativeXmlFilter');

class MetricsXmlExportTest extends PKPTestCase
{
    protected function getMockedRegistryKeys()
    {
        return ['daos'];
    }

    private function exportMetrics(array $rows)
    {
        $dao = $this->getMockBuilder(FullJournalMetricsDAO::class)
            ->onlyMethods(['getByContextId'])
            ->getMock();
        $dao->expects($this->once())->method('getByContextId')->with(7)->willReturn($rows);
        DAORegistry::registerDAO('FullJournalMetricsDAO', $dao);

        $journal = new Journal();
        $journal->setId(7);
        $group = new FilterGroup();
        $group->setInputType('class::classes.journal.Journal');
        $group->setOutputType('xml::schema(plugins/importexport/fullJournalTransfer/fullJournal.xsd)');
        $filter = new JournalNativeXmlFilter($group);
        $filter->setDeployment(new FullJournalImportExportDeployment($journal));
        $doc = new DOMDocument('1.0', 'utf-8');
        $root = $doc->createElementNS($filter->getDeployment()->getNamespace(), 'journal');
        $filter->addMetrics($doc, $root, $journal);
        $doc->appendChild($root);
        return $doc;
    }

    private function metric()
    {
        return [
            'assoc_type' => 256, 'assoc_id' => 94, 'day' => '20240101', 'metric' => 0,
            'metric_type' => 'ojs::counter', 'load_id' => "uso & \"teste\" <2024>\r\n\t.log",
            'country_id' => 'BR', 'region' => '27', 'city' => 'São Paulo & <Centro>', 'file_type' => 1,
        ];
    }

    public function testPreservesAttributesNamespacesAndOrderAfterSerialization()
    {
        $first = $this->metric();
        $second = array_replace($first, [
            'assoc_id' => 95, 'day' => null, 'country_id' => null, 'region' => '0',
            'city' => '', 'file_type' => 0,
        ]);
        $doc = $this->exportMetrics([$first, $second]);
        $roundTrip = new DOMDocument();
        $this->assertTrue($roundTrip->loadXML($doc->saveXML()));
        $nodes = $roundTrip->getElementsByTagNameNS('http://pkp.sfu.ca', 'metric');
        $this->assertSame(2, $nodes->length);
        foreach ($first as $field => $value) {
            $this->assertSame((string) $value, $nodes->item(0)->getAttribute($field));
        }
        $this->assertSame('95', $nodes->item(1)->getAttribute('assoc_id'));
        $this->assertSame('', $nodes->item(1)->getAttribute('day'));
        foreach (['country_id', 'region', 'city', 'file_type'] as $field) {
            $this->assertFalse($nodes->item(1)->hasAttribute($field));
        }
    }

    public function testOmitsMetricsElementWhenThereAreNoRows()
    {
        $doc = $this->exportMetrics([]);
        $this->assertSame(0, $doc->getElementsByTagNameNS('http://pkp.sfu.ca', 'metrics')->length);
    }

    public function testInvalidXmlCannotSilentlyProduceAPartialMetricsBlock()
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $this->expectException(RuntimeException::class);
            $this->exportMetrics([$this->metric(), array_replace($this->metric(), ['city' => "invalid\x01"])]);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
