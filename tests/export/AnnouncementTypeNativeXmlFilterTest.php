<?php

import('lib.pkp.classes.announcement.AnnouncementType');
import('plugins.importexport.fullJournalTransfer.filter.export.AnnouncementTypeNativeXmlFilter');
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
        $announcementTypeExportFilter = $this->getNativeImportExportFilter();
        $deployment = $announcementTypeExportFilter->getDeployment();

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedAnnouncementTypeNode = $doc->createElementNS($deployment->getNamespace(), 'announcement_type');
        $announcementTypeExportFilter->createLocalizedNodes($doc, $expectedAnnouncementTypeNode, 'name', ['en_US' => 'Test Announcement Type']);

        $announcementType = new AnnouncementType();
        $announcementType->setAssocId(12);
        $announcementType->setAssocType(ASSOC_TYPE_JOURNAL);
        $announcementType->setName('Test Announcement Type', 'en_US');

        $announcementTypeNode = $announcementTypeExportFilter->createAnnouncementTypeNode($doc, $announcementType);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedAnnouncementTypeNode),
            $doc->saveXML($announcementTypeNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteAnnouncementTypeXml()
    {
        $announcementTypeExportFilter = $this->getNativeImportExportFilter();

        $announcementType = new AnnouncementType();
        $announcementType->setAssocId(12);
        $announcementType->setAssocType(ASSOC_TYPE_JOURNAL);
        $announcementType->setName('Test Announcement Type', 'en_US');
        $announcementTypes = [$announcementType];

        $doc = $announcementTypeExportFilter->process($announcementTypes);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('announcementType.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
