<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.announcement.Announcement');
import('classes.journal.Journal');
import('plugins.importexport.native.NativeImportExportDeployment');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportPlugin');
import('plugins.importexport.fullJournalTransfer.filter.AnnouncementNativeXmlFilter');

class AnnouncementNativeXmlFilterTest extends PKPTestCase
{
    private function createTestAnnouncement($data)
    {
        $announcement = new Announcement();
        $announcement->setAllData($data);
        return $announcement;
    }

    private function getSampleXml($sampleFile)
    {
        $fileContent = file_get_contents(__DIR__ . '/fixtures/' . $sampleFile);
        $xml = new DOMDocument('1.0');
        $xml->loadXML($fileContent);

        return $xml->saveXML();
    }

    public function testCreateAnnouncementNode()
    {
        $filterGroupDAO = DAORegistry::getDAO('FilterGroupDAO');
        $nativeExportGroup = $filterGroupDAO->getObjectBySymbolic('announcement=>native-xml');
        $nativeExportFilter = new AnnouncementNativeXmlFilter($nativeExportGroup);
        $nativeExportFilter->setDeployment(new NativeImportExportDeployment(new Journal(), null));

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $announcement = $this->createTestAnnouncement([
            'id' => 21,
            'assocId' => 12,
            'assocType' => ASSOC_TYPE_JOURNAL,
            'typeId' => 1,
            'dateExpire' => '2023-02-01',
            'datePosted' => '2023-01-01 12:00:00.000',
            'title' => [
                'en_US' => 'Test Announcement'
            ],
            'descriptionShort' => [
                'en_US' => '<p>Announcement for test</p>'
            ],
            'description' => [
                'en_US' => '<p>A announcement created for test purpose</p>'
            ]
        ]);

        $announcementNode = $nativeExportFilter->createAnnouncementNode($doc, $announcement);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('announcementNode.xml'),
            $doc->saveXML($announcementNode),
            "actual xml is equal to expected xml"
        );
    }
}
