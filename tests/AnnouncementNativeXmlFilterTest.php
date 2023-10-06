<?php

import('classes.journal.Journal');
import('lib.pkp.classes.announcement.Announcement');
import('plugins.importexport.fullJournalTransfer.filter.AnnouncementNativeXmlFilter');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');

class AnnouncementNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getNativeImportExportFilterClass()
    {
        return AnnouncementNativeXmlFilter::class;
    }

    protected function getSymbolicFilterGroup()
    {
        return 'announcement=>native-xml';
    }

    private function createTestAnnouncement($data)
    {
        $announcement = new Announcement();
        $announcement->setAllData($data);
        return $announcement;
    }

    public function testAddDateElements()
    {
        $nativeImportExportFilter = $this->getNativeImportExportFilter();
        $deployment = $nativeImportExportFilter->getDeployment();

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
        $nativeImportExportFilter->addDates($doc, $actualAnnouncementNode, $announcement);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedAnnouncementNode),
            $doc->saveXML($actualAnnouncementNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateAnnouncementNode()
    {
        $nativeImportExportFilter = $this->getNativeImportExportFilter();
        $deployment = $nativeImportExportFilter->getDeployment();

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
        $nativeImportExportFilter->createLocalizedNodes($doc, $expectedAnnouncementNode, 'title', ['en_US' => 'Test Announcement']);
        $nativeImportExportFilter->createLocalizedNodes($doc, $expectedAnnouncementNode, 'description_short', ['en_US' => '<p>Announcement for test</p>']);
        $nativeImportExportFilter->createLocalizedNodes($doc, $expectedAnnouncementNode, 'description', ['en_US' => '<p>A announcement created for test purpose</p>']);

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

        $actualAnnouncementNode = $nativeImportExportFilter->createAnnouncementNode($doc, $announcement);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedAnnouncementNode),
            $doc->saveXML($actualAnnouncementNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteAnnouncementXml()
    {
        $nativeImportExportFilter = $this->getNativeImportExportFilter();
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

        $doc = $nativeImportExportFilter->process($announcements, true);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('announcement.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
