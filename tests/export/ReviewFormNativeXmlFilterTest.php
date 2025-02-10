<?php

import('lib.pkp.classes.reviewForm.ReviewForm');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.ReviewFormNativeXmlFilter');

class ReviewFormNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpReviewFormElementMockDAO();
    }

    protected function getSymbolicFilterGroup()
    {
        return 'review-form=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return ReviewFormNativeXmlFilter::class;
    }

    protected function getMockedDAOs()
    {
        return ['ReviewFormElementDAO'];
    }

    private function setUpReviewFormElementMockDAO()
    {
        $reviewFormElement = DAORegistry::getDAO('ReviewFormElementDAO')->newDataObject();
        $reviewFormElement->setId(68);
        $reviewFormElement->setSequence(1);
        $reviewFormElement->setElementType(REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD);
        $reviewFormElement->setRequired(1);
        $reviewFormElement->setIncluded(1);
        $reviewFormElement->setQuestion('<p>What is your pet name?</p>', 'en_US');
        $reviewFormElement->setDescription('<p>A review form element for test purpose</p>', 'en_US');
        $reviewFormElements = [$reviewFormElement];

        $daoResultFactory = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->setConstructorArgs([])
            ->disableOriginalConstructor()
            ->getMock();

        $daoResultFactory->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue($reviewFormElements));

        $reviewFormElementDAO = $this->getMockBuilder(ReviewFormElementDAO::class)
            ->setMethods(['getByReviewFormId'])
            ->getMock();

        $reviewFormElementDAO->expects($this->any())
            ->method('getByReviewFormId')
            ->will($this->returnValue($daoResultFactory));

        DAORegistry::registerDAO('ReviewFormElementDAO', $reviewFormElementDAO);
    }

    public function createReviewFormElementsNode($doc, $deployment, $importExportFilter)
    {
        $reviewFormElementsNode = $doc->createElementNS($deployment->getNamespace(), 'review_form_elements');
        $reviewFormElementsNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $reviewFormElementsNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());
        $reviewFormElementNode = $doc->createElementNS($deployment->getNamespace(), 'review_form_element');
        $reviewFormElementNode->setAttribute('id', 68);
        $reviewFormElementNode->setAttribute('seq', 1);
        $reviewFormElementNode->setAttribute('element_type', 1);
        $reviewFormElementNode->setAttribute('required', 1);
        $reviewFormElementNode->setAttribute('included', 1);
        $importExportFilter->createLocalizedNodes(
            $doc,
            $reviewFormElementNode,
            'question',
            ['en_US' => '<p>What is your pet name?</p>']
        );
        $importExportFilter->createLocalizedNodes(
            $doc,
            $reviewFormElementNode,
            'description',
            ['en_US' => '<p>A review form element for test purpose</p>']
        );
        $reviewFormElementsNode->appendChild($reviewFormElementNode);

        return $reviewFormElementsNode;
    }

    public function testAddReviewFormElements()
    {
        $reviewFormExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewFormExportFilter->getDeployment();

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedReviewFormNode = $doc->createElementNS($deployment->getNamespace(), 'review_form');
        $reviewFormElementsNode = $this->createReviewFormElementsNode($doc, $deployment, $reviewFormExportFilter);
        $expectedReviewFormNode->appendChild($reviewFormElementsNode);

        $reviewForm = new ReviewForm();
        $reviewForm->setId(73);

        $actualReviewFormNode = $doc->createElementNS($deployment->getNamespace(), 'review_form');
        $reviewFormExportFilter->addReviewFormElements($doc, $actualReviewFormNode, $reviewForm);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedReviewFormNode),
            $doc->saveXML($actualReviewFormNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateReviewFormNode()
    {
        $reviewFormExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewFormExportFilter->getDeployment();

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedReviewFormNode = $doc->createElementNS($deployment->getNamespace(), 'review_form');
        $expectedReviewFormNode->setAttribute('id', 98);
        $expectedReviewFormNode->setAttribute('seq', 1);
        $expectedReviewFormNode->setAttribute('is_active', 1);
        $reviewFormExportFilter->createLocalizedNodes(
            $doc,
            $expectedReviewFormNode,
            'title',
            ['en_US' => 'Test Review Form']
        );
        $reviewFormExportFilter->createLocalizedNodes(
            $doc,
            $expectedReviewFormNode,
            'description',
            ['en_US' => '<p>A review form for test purpose</p>']
        );
        $reviewFormElementsNode = $this->createReviewFormElementsNode($doc, $deployment, $reviewFormExportFilter);
        $expectedReviewFormNode->appendChild($reviewFormElementsNode);

        $reviewForm = new ReviewForm();
        $reviewForm->_data = [
            'id' => 98,
            'assocId' => 12,
            'assocType' => ASSOC_TYPE_JOURNAL,
            'sequence' => 1,
            'active' => 1,
            'title' => ['en_US' => 'Test Review Form'],
            'description' => ['en_US' => '<p>A review form for test purpose</p>']
        ];

        $actualReviewFormNode = $reviewFormExportFilter->createReviewFormNode($doc, $reviewForm);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedReviewFormNode),
            $doc->saveXML($actualReviewFormNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteReviewFormXml()
    {
        $reviewFormExportFilter = $this->getNativeImportExportFilter();

        $reviewForm = new ReviewForm();
        $reviewForm->_data = [
            'id' => 98,
            'assocId' => 12,
            'assocType' => ASSOC_TYPE_JOURNAL,
            'sequence' => 1,
            'active' => 1,
            'title' => ['en_US' => 'Test Review Form'],
            'description' => ['en_US' => '<p>A review form for test purpose</p>']
        ];
        $reviewForms = [$reviewForm];

        $doc = $reviewFormExportFilter->execute($reviewForms);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('reviewForm.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
