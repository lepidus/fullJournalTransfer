<?php

import('classes.journal.Journal');
import('lib.pkp.classes.announcement.Announcement');
import('plugins.importexport.fullJournalTransfer.filter.AnnouncementNativeXmlFilter');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');

class AnnouncementNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    private $announcementExportFilter;

    protected function setUp(): void
    {
        parent::setUp();

        $announcementType = $this->createTestAnnouncementType();
        $this->setUpAnnouncementTypeMockDAO($announcementType);

        $context = $this->createTestContext();
        $this->announcementExportFilter = $this->getNativeImportExportFilter($context);
    }

    protected function getNativeImportExportFilterClass()
    {
        return AnnouncementNativeXmlFilter::class;
    }

    protected function getSymbolicFilterGroup()
    {
        return 'announcement=>native-xml';
    }

    protected function getMockedDAOs()
    {
        return ['AnnouncementTypeDAO'];
    }

    private function setUpAnnouncementTypeMockDAO($announcementType)
    {
        $announcementTypeDAO = $this->getMockBuilder(AnnouncementTypeDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        $announcementTypeDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($announcementType));

        DAORegistry::registerDAO('AnnouncementTypeDAO', $announcementTypeDAO);
    }

    private function createTestAnnouncement($data)
    {
        $announcement = new Announcement();
        $announcement->setAllData($data);
        return $announcement;
    }

    private function createTestAnnouncementType()
    {
        $announcementType = new AnnouncementType();
        $announcementType->setName('Test Announcement Type', 'en_US');
        return $announcementType;
    }

    private function createTestContext()
    {
        $context = Application::getContextDAO()->newDataObject();
        $context->setId(99);
        $context->setPrimaryLocale('en_US');
        return $context;
    }

    public function testAddDateElements()
    {
        $deployment = $this->announcementExportFilter->getDeployment();

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
        $this->announcementExportFilter->addDates($doc, $actualAnnouncementNode, $announcement);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedAnnouncementNode),
            $doc->saveXML($actualAnnouncementNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateAnnouncementNode()
    {
        $deployment = $this->announcementExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedAnnouncementNode = $doc->createElementNS($deployment->getNamespace(), 'announcement');
        $expectedAnnouncementNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', 21));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');

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

        $this->announcementExportFilter->createLocalizedNodes(
            $doc,
            $expectedAnnouncementNode,
            'title',
            ['en_US' => 'Test Announcement']
        );
        $this->announcementExportFilter->createLocalizedNodes(
            $doc,
            $expectedAnnouncementNode,
            'description_short',
            ['en_US' => '<p>Announcement for test</p>']
        );
        $this->announcementExportFilter->createLocalizedNodes(
            $doc,
            $expectedAnnouncementNode,
            'description',
            ['en_US' => '<p>A announcement created for test purpose</p>']
        );

        $expectedAnnouncementNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'announcement_type_ref',
            htmlspecialchars('Test Announcement Type', ENT_COMPAT, 'UTF-8')
        ));

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

        $actualAnnouncementNode = $this->announcementExportFilter->createAnnouncementNode($doc, $announcement);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedAnnouncementNode),
            $doc->saveXML($actualAnnouncementNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteAnnouncementXml()
    {
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

        $doc = $this->announcementExportFilter->process($announcements, true);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('announcement.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
