<?php

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlAnnouncementFilter');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');

class NativeXmlAnnouncementFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>announcement';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlAnnouncementFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['announcements', 'announcement_settings', 'announcement_types', 'announcement_type_settings'];
    }

    protected function getContextData()
    {
        return ['id' => 12];
    }

    private function insertTestAnnouncementType()
    {
        $announcementTypeDAO = DAORegistry::getDAO('AnnouncementTypeDAO');
        $announcementType = $announcementTypeDAO->newDataObject();
        $announcementType->setAssocType($this->context->getAssocType());
        $announcementType->setAssocId($this->context->getId());
        $announcementType->setName('Test Announcement Type', 'en_US');
        $announcementTypeId = $announcementTypeDAO->insertObject($announcementType);
        return $announcementTypeId;
    }

    public function testHandleAnnouncementElement()
    {
        $announcementTypeId = $this->insertTestAnnouncementType();

        $announcementImportFilter = $this->getNativeImportExportFilter();
        $deployment = $announcementImportFilter->getDeployment();
        $announcementDAO = DAORegistry::getDAO('AnnouncementDAO');

        $expectedAnnouncementData = [
            'assocId' => 12,
            'assocType' => ASSOC_TYPE_JOURNAL,
            'typeId' => $announcementTypeId,
            'dateExpire' => '2023-02-01',
            'datePosted' => '2023-01-01 12:00:00',
            'title' => [
                'en_US' => 'Test Announcement'
            ],
            'descriptionShort' => [
                'en_US' => '<p>Announcement for test</p>'
            ],
            'description' => [
                'en_US' => '<p>A announcement created for test purpose</p>'
            ]
        ];

        $doc = $this->getSampleXml('announcement.xml');

        $announcementNode = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'announcement');

        $announcement = $announcementImportFilter->handleElement($announcementNode->item(0));
        $announcementId = array_pop($announcement->_data);

        $this->assertEquals($expectedAnnouncementData, $announcement->_data);

        $insertedAnnouncement = $announcementDAO->getById($announcementId);
        $expectedAnnouncementData['id'] = $announcementId;

        $this->assertEquals($expectedAnnouncementData, $insertedAnnouncement->_data);
    }
}
