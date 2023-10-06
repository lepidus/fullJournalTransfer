<?php

import('plugins.importexport.fullJournalTransfer.filter.AnnouncementTypeNativeXmlFilter');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');

class AnnouncementTypeNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'announcement-type=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return AnnouncementTypeNativeXmlFilter::class;
    }

    public function testCreateAnnouncementTypeNode()
    {
        $nativeImportExportFilter = $this->getNativeImportExportFilter();
        $deployment = $nativeImportExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedAnnouncementTypeNode = $doc->createElementNS($deployment->getNamespace(), 'announcementType');
        $nativeImportExportFilter->createLocalizedNodes($doc, $expectedAnnouncementTypeNode, 'name', ['en_US' => 'Test Announcement Type']);

        $announcementType = new AnnouncementType();
        $announcementType->setAssocId(12);
        $announcementType->setAssocType(ASSOC_TYPE_JOURNAL);
        $announcementType->setName('Test Announcement Type', 'en_US');

        $announcementTypeNode = $nativeImportExportFilter->createAnnouncementTypeNode($doc, $announcementType);
    }
}
