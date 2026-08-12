<?php

import('lib.pkp.tests.PKPTestCase');

class FullJournalContractFixturesTest extends PKPTestCase
{
    public function testWorkflowFixtureCoversAcceptedTransferContracts()
    {
        $article = $this->loadFixture('article-contracts.xml');
        $xpath = $this->createXpath($article);

        $this->assertSame(
            '1024',
            $article->documentElement->getAttribute('current_publication_id')
        );
        $this->assertCount(2, $xpath->query('/pkp:extended_article/pkp:publication'));
        $this->assertCount(2, $xpath->query('//pkp:review_round'));
        $this->assertGreaterThanOrEqual(3, $xpath->query('//pkp:decision')->length);
        $this->assertSame(1, $xpath->query('//pkp:query//pkp:workflow_file/pkp:file/pkp:href')->length);
    }

    public function testJournalFixtureCoversUnpublishedContentAndMetricDimensions()
    {
        $journal = $this->loadFixture('journal.xml');
        $xpath = $this->createXpath($journal);

        $this->assertSame(1, $xpath->query('//pkp:extended_issue[@published="0"]')->length);
        $this->assertSame(1, $xpath->query('/pkp:journal/pkp:extended_articles')->length);

        $metric = $xpath->query('/pkp:journal/pkp:metrics/pkp:geo_metrics/pkp:geo_metric')->item(0);
        $this->assertInstanceOf(DOMElement::class, $metric);
        $this->assertSame('BR', $metric->getAttribute('country'));
        $this->assertSame('27', $metric->getAttribute('region'));
        $this->assertSame('daily', $metric->getAttribute('granularity'));
    }

    private function loadFixture($filename)
    {
        $document = new DOMDocument();
        $loaded = $document->load(__DIR__ . '/samples/' . $filename);
        $this->assertTrue($loaded, sprintf('Fixture %s must be valid XML', $filename));

        return $document;
    }

    private function createXpath(DOMDocument $document)
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');

        return $xpath;
    }
}
