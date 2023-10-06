<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportDeployment');
import('plugins.importexport.fullJournalTransfer.filter.NativeXmlAnnouncementFilter');

class NativeXmlAnnouncementFilterTest extends DatabaseTestCase
{
    protected function getAffectedTables()
    {
        return ['announcements', 'announcement_settings'];
    }

    private function getNativeImportFilter()
    {
        $context = new Journal();
        $context->setId(12);

        $filterGroupDAO = DAORegistry::getDAO('FilterGroupDAO');
        $nativeImportGroup = $filterGroupDAO->getObjectBySymbolic('native-xml=>announcement');
        $nativeImportFilter = new NativeXmlAnnouncementFilter($nativeImportGroup);
        $nativeImportFilter->setDeployment(new FullJournalImportExportDeployment($context, null));

        return $nativeImportFilter;
    }

    public function testHandlerAnnouncementElement()
    {
        $announcementImportFilter = $this->getNativeImportFilter();
        $deployment = $announcementImportFilter->getDeployment();

        $fileContent = file_get_contents(__DIR__ . '/fixtures/announcement.xml');
        $doc = new DOMDocument('1.0');
        $doc->loadXML($fileContent);

        $announcementNode = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'announcement');

        $announcement = $announcementImportFilter->handleElement($announcementNode->item(0));

        $this->assertEquals([
            'assocId' => 12,
            'assocType' => ASSOC_TYPE_JOURNAL,
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
        ], $announcement->_data);
    }
}
