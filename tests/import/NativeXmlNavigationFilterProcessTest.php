<?php

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlNavigationMenuFilter');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlNavigationMenuItemFilter');
import('lib.pkp.classes.navigationMenu.NavigationMenu');
import('lib.pkp.classes.navigationMenu.NavigationMenuItem');

class NativeXmlNavigationFilterProcessTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider navigationDocuments
     */
    public function testProcessNavigationDocument($filterClass, $objectClass, $sample, $singular)
    {
        $filterGroup = new FilterGroup();
        $filterGroup->setInputType('xml::schema(plugins/importexport/fullJournalTransfer/fullJournal.xsd)');
        $filterGroup->setOutputType('class::lib.pkp.classes.navigationMenu.' . $objectClass . '[]');
        $filter = $this->getMockBuilder($filterClass)
            ->setConstructorArgs([$filterGroup])
            ->onlyMethods(['handleElement'])
            ->getMock();

        $document = new DOMDocument();
        $document->load(__DIR__ . '/../samples/' . $sample);
        $node = $document->documentElement->getElementsByTagName('*')->item(0);
        if ($singular) {
            $document = new DOMDocument();
            $node = $document->importNode($node, true);
            $document->appendChild($node);
        }
        $object = new $objectClass();

        $filter->expects($this->once())
            ->method('handleElement')
            ->with($this->identicalTo($node))
            ->willReturn($object);

        $this->assertSame([$object], $filter->process($document));
    }

    public function navigationDocuments()
    {
        return [
            'single item' => [
                NativeXmlNavigationMenuItemFilter::class, 'NavigationMenuItem', 'navigationMenuItem.xml', true
            ],
            'item collection' => [
                NativeXmlNavigationMenuItemFilter::class, 'NavigationMenuItem', 'navigationMenuItem.xml', false
            ],
            'single menu' => [NativeXmlNavigationMenuFilter::class, 'NavigationMenu', 'navigationMenu.xml', true],
            'menu collection' => [NativeXmlNavigationMenuFilter::class, 'NavigationMenu', 'navigationMenu.xml', false],
        ];
    }
}
