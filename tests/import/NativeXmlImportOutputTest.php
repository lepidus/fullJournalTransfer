<?php

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlJournalFilter');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlAnnouncementFilter');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlReviewFormFilter');
import('classes.journal.Journal');
import('lib.pkp.classes.announcement.Announcement');
import('lib.pkp.classes.reviewForm.ReviewForm');

class NativeXmlImportOutputTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider importFilters
     */
    public function testExecuteReturnsImportedObjects($filterClass, $objectClass, $symbolic, $sample, $legacy)
    {
        $config = new DOMDocument();
        $config->load(__DIR__ . '/../../filter/filterConfig.xml');
        $xpath = new DOMXPath($config);
        $definition = $xpath->query('//filterGroup[@symbolic="' . $symbolic . '"]')->item(0);
        $group = new FilterGroup();
        $group->setInputType($definition->getAttribute('inputType'));
        $outputType = $definition->getAttribute('outputType');
        $group->setOutputType($legacy ? str_replace('[]', '', $outputType) : $outputType);

        $filter = $this->getMockBuilder($filterClass)
            ->setConstructorArgs([$group])->onlyMethods(['handleElement'])->getMock();
        $object = new $objectClass();
        $filter->expects($this->once())->method('handleElement')->willReturn($object);
        $document = new DOMDocument();
        $document->load(__DIR__ . '/../samples/' . $sample);
        if ($objectClass !== 'Journal') {
            $node = $document->documentElement->getElementsByTagName('*')->item(0);
            $document = new DOMDocument();
            $document->appendChild($document->importNode($node, true));
        }

        $this->assertSame([$object], $filter->execute($document));
    }

    public function importFilters()
    {
        $filters = [
            [NativeXmlJournalFilter::class, 'Journal', 'native-xml=>journal', 'journal.xml'],
            [NativeXmlAnnouncementFilter::class, 'Announcement', 'native-xml=>announcement', 'announcement.xml'],
            [NativeXmlReviewFormFilter::class, 'ReviewForm', 'native-xml=>review-form', 'reviewForm.xml'],
        ];
        foreach ($filters as $filter) {
            yield $filter[1] . ' configured group' => array_merge($filter, [false]);
            yield $filter[1] . ' existing group' => array_merge($filter, [true]);
        }
    }
}
