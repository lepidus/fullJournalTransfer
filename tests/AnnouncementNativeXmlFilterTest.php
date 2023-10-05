<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.announcement.Announcement');
import('classes.journal.Journal');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportDeployment');
import('plugins.importexport.fullJournalTransfer.filter.AnnouncementNativeXmlFilter');

class AnnouncementNativeXmlFilterTest extends PKPTestCase
{
    private function getNativeExportFilter()
    {
        $filterGroupDAO = DAORegistry::getDAO('FilterGroupDAO');
        $nativeExportGroup = $filterGroupDAO->getObjectBySymbolic('announcement=>native-xml');
        $nativeExportFilter = new AnnouncementNativeXmlFilter($nativeExportGroup);
        $nativeExportFilter->setDeployment(new FullJournalImportExportDeployment(new Journal(), null));

        return $nativeExportFilter;
    }

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

    public function testAddDateElements()
    {
        $nativeExportFilter = $this->getNativeExportFilter();
        $deployment = $nativeExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedAnnouncementNode = $doc->createElementNS($deployment->getNamespace(), 'announcement');
        $expectedAnnouncementNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_expire',
            strftime('%Y-%m-%d', strtotime('2023-02-01'))
        ));
        $expectedAnnouncementNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_posted',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-01-01 12:00:00.000'))
        ));

        $announcement = $this->createTestAnnouncement([
            'dateExpire' => '2023-02-01',
            'datePosted' => '2023-01-01 12:00:00.000',
        ]);
        $actualAnnouncementNode = $doc->createElementNS($deployment->getNamespace(), 'announcement');
        $nativeExportFilter->addDates($doc, $actualAnnouncementNode, $announcement);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedAnnouncementNode),
            $doc->saveXML($actualAnnouncementNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateAnnouncementNode()
    {
        $nativeExportFilter = $this->getNativeExportFilter();
        $deployment = $nativeExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedAnnouncementNode = $doc->createElementNS($deployment->getNamespace(), 'announcement');
        $expectedAnnouncementNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', 21));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');

        $expectedAnnouncementNode->setAttribute('type_id', 1);
        $expectedAnnouncementNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_expire',
            strftime('%Y-%m-%d', strtotime('2023-02-01'))
        ));
        $expectedAnnouncementNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_posted',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-01-01 12:00:00.000'))
        ));
        $nativeExportFilter->createLocalizedNodes($doc, $expectedAnnouncementNode, 'title', ['en_US' => 'Test Announcement']);
        $nativeExportFilter->createLocalizedNodes($doc, $expectedAnnouncementNode, 'description_short', ['en_US' => '<p>Announcement for test</p>']);
        $nativeExportFilter->createLocalizedNodes($doc, $expectedAnnouncementNode, 'description', ['en_US' => '<p>A announcement created for test purpose</p>']);

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

        $actualAnnouncementNode = $nativeExportFilter->createAnnouncementNode($doc, $announcement);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedAnnouncementNode),
            $doc->saveXML($actualAnnouncementNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteAnnouncementXml()
    {
        $nativeExportFilter = $this->getNativeExportFilter();
        $announcements = [$this->createTestAnnouncement([
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
        ])];

        $doc = $nativeExportFilter->process($announcements, true);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('announcement.xml'),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
