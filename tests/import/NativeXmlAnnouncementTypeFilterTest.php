<?php

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlAnnouncementTypeFilter');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');

class NativeXmlAnnouncementTypeFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>announcement-type';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlAnnouncementTypeFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['announcement_types', 'announcement_type_settings'];
    }

    protected function getContextData()
    {
        return ['id' => 12];
    }

    public function testHandleAnnouncementTypeElement()
    {
        $announcementTypeImportFilter = $this->getNativeImportExportFilter();
        $deployment = $announcementTypeImportFilter->getDeployment();
        $announcementTypeDAO = DAORegistry::getDAO('AnnouncementTypeDAO');

        $expectedAnnouncementTypeData = [
            'assocId' => 12,
            'assocType' => ASSOC_TYPE_JOURNAL,
            'name' => [
                'en_US' => 'Test Announcement Type'
            ]
        ];

        $doc = $this->getSampleXml('announcementType.xml');

        $announcementTypeNode = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'announcement_type');

        $announcementType = $announcementTypeImportFilter->handleElement($announcementTypeNode->item(0));
        $announcementTypeId = array_pop($announcementType->_data);

        $this->assertEquals($expectedAnnouncementTypeData, $announcementType->_data);

        $insertedAnnouncementType = $announcementTypeDAO->getById($announcementTypeId);
        $expectedAnnouncementTypeData['id'] = $announcementTypeId;

        $this->assertEquals($expectedAnnouncementTypeData, $insertedAnnouncementType->_data);
    }
}
