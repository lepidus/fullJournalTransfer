<?php

if (!function_exists('import')) {
    $ojs = getenv('FJT_TEST_OJS_PATH') ?: dirname(__DIR__, 5);
    define('BASE_SYS_DIR', $ojs);
    require $ojs . '/lib/pkp/includes/functions.inc.php';
}
import('lib.pkp.classes.db.DAO');
import('lib.pkp.plugins.importexport.native.PKPNativeImportExportDeployment');
require_once dirname(__DIR__, 2) . '/filter/export/JournalNativeXmlFilter.inc.php';

class JournalUserInterestsTest extends \PHPUnit\Framework\TestCase
{
    private function filter(): JournalNativeXmlFilter
    {
        $filter = (new ReflectionClass(JournalNativeXmlFilter::class))->newInstanceWithoutConstructor();
        $deployment = (new ReflectionClass(PKPNativeImportExportDeployment::class))->newInstanceWithoutConstructor();
        $filter->setDeployment($deployment);
        return $filter;
    }

    /**
     * @dataProvider interestCases
     */
    public function testPreservesDeduplicationAndFirstSpelling($input, $expected): void
    {
        $document = new DOMDocument();
        $root = $document->appendChild($document->createElementNS('http://pkp.sfu.ca', 'p:PKPUsers'));
        // More than one user exercises traversal while earlier text nodes are changed.
        for ($i = 0; $i < 3; $i++) {
            $user = $root->appendChild($document->createElementNS('http://pkp.sfu.ca', 'p:user'));
            $node = $user->appendChild($document->createElementNS('http://pkp.sfu.ca', 'p:review_interests'));
            $node->appendChild($document->createTextNode($input));
        }
        $this->normalize($this->filter(), $document);
        foreach ($document->getElementsByTagNameNS('http://pkp.sfu.ca', 'review_interests') as $node) {
            $this->assertSame($expected, $node->textContent);
        }
    }

    public function interestCases(): array
    {
        return [
            ['Educação, educacao, Ciência, ciência, História', 'Educação, Ciência, História'],
            ['A & B, a & b, Métodos <aplicados>, metodos <aplicados>, O\'Connor', 'A & B, Métodos <aplicados>, O\'Connor'],
            ['Educação,  Educação , educacao', 'Educação, Educação '],
            ['', ''],
        ];
    }

    public function testLeavesUnrelatedNamespacesAndMultipleInterestNodesUnchanged(): void
    {
        $document = new DOMDocument();
        $document->loadXML('<PKPUsers xmlns="http://pkp.sfu.ca" xmlns:other="urn:other">'
            . '<user><review_interests>A, a</review_interests><review_interests>B, b</review_interests></user>'
            . '<user><username>unchanged</username></user>'
            . '<other:user><other:review_interests>C, c</other:review_interests></other:user></PKPUsers>');
        $expected = $document->C14N();
        $this->normalize($this->filter(), $document);
        $this->assertSame($expected, $document->C14N());
    }

    public function testRepeatedNormalizationRetainsTheSameResults(): void
    {
        $filter = $this->filter();
        foreach (['Educação', 'EDUCAÇÃO', 'educacao', 'Ciência', 'EDUCAÇÃO'] as $term) {
            $expected = $term === 'Ciência' ? 'ciencia' : 'educacao';
            $this->assertSame($expected, $filter->removeAccents($term));
        }
    }

    private function normalize(JournalNativeXmlFilter $filter, DOMDocument $document): void
    {
        $method = new ReflectionMethod($filter, 'removeDuplicatedInterests');
        $method->setAccessible(true);
        $method->invoke($filter, $document);
    }
}
