<?php

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlJournalFilter');
import('classes.journal.Journal');

class NativeXmlJournalFilterProcessTest extends \PHPUnit\Framework\TestCase
{
    public function testProcessSingleJournalDocument()
    {
        $filterGroup = new FilterGroup();
        $filterGroup->setInputType('xml::schema(plugins/importexport/fullJournalTransfer/fullJournal.xsd)');
        $filterGroup->setOutputType('class::classes.journal.Journal');

        // Isolate persistence while exercising the real NativeImportFilter dispatch.
        $filter = $this->getMockBuilder(NativeXmlJournalFilter::class)
            ->setConstructorArgs([$filterGroup])
            ->onlyMethods(['handleElement'])
            ->getMock();

        $document = new DOMDocument();
        $document->loadXML('<journal xmlns="http://pkp.sfu.ca"/>');
        $journal = new Journal();

        $filter->expects($this->once())
            ->method('handleElement')
            ->with($this->identicalTo($document->documentElement))
            ->willReturn($journal);

        $this->assertSame([$journal], $filter->process($document));
    }
}
