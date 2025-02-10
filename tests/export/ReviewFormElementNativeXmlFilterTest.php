<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.ReviewFormElementNativeXmlFilter');

class ReviewFormElementNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'review-form-element=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return ReviewFormElementNativeXmlFilter::class;
    }

    private function createPossibleResponsesNode($doc, $deployment, $locale, $possibleResponses)
    {
        $possibleResponsesNode = $doc->createElementNS($deployment->getNamespace(), 'possible_responses');
        $possibleResponsesNode->setAttribute('locale', $locale);
        foreach ($possibleResponses as $possibleResponse) {
            $possibleResponsesNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'possible_response',
                htmlspecialchars($possibleResponse, ENT_COMPAT, 'UTF-8')
            ));
        }

        return $possibleResponsesNode;
    }

    public function testAddPossibleResponsesNode()
    {
        $reviewFormElementExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewFormElementExportFilter->getDeployment();

        $possibleResponses = [
            'en_US' => ['option 1','option 2', 'option 3']
        ];

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedReviewFormElementNode = $doc->createElementNS($deployment->getNamespace(), 'review_form_element');
        $possibleResponsesNode = $this->createPossibleResponsesNode(
            $doc,
            $deployment,
            'en_US',
            $possibleResponses['en_US']
        );
        $expectedReviewFormElementNode->appendChild($possibleResponsesNode);

        $actualReviewFormElementNode = $doc->createElementNS($deployment->getNamespace(), 'review_form_element');
        $reviewFormElementExportFilter->addPossibleResponsesNode(
            $doc,
            $actualReviewFormElementNode,
            $possibleResponses
        );

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($actualReviewFormElementNode),
            $doc->saveXML($expectedReviewFormElementNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateReviewFormElementNode()
    {
        $reviewFormElementExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewFormElementExportFilter->getDeployment();

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedReviewFormElementNode = $doc->createElementNS($deployment->getNamespace(), 'review_form_element');
        $expectedReviewFormElementNode->setAttribute('id', 68);
        $expectedReviewFormElementNode->setAttribute('seq', 1);
        $expectedReviewFormElementNode->setAttribute('element_type', 1);
        $expectedReviewFormElementNode->setAttribute('required', 1);
        $expectedReviewFormElementNode->setAttribute('included', 1);
        $reviewFormElementExportFilter->createLocalizedNodes(
            $doc,
            $expectedReviewFormElementNode,
            'question',
            ['en_US' => '<p>What is your pet name?</p>']
        );
        $reviewFormElementExportFilter->createLocalizedNodes(
            $doc,
            $expectedReviewFormElementNode,
            'description',
            ['en_US' => '<p>A review form element for test purpose</p>']
        );

        $reviewFormElement = DAORegistry::getDAO('ReviewFormElementDAO')->newDataObject();
        $reviewFormElement->setId(68);
        $reviewFormElement->setSequence(1);
        $reviewFormElement->setElementType(REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD);
        $reviewFormElement->setRequired(1);
        $reviewFormElement->setIncluded(1);
        $reviewFormElement->setQuestion('<p>What is your pet name?</p>', 'en_US');
        $reviewFormElement->setDescription('<p>A review form element for test purpose</p>', 'en_US');

        $actualReviewFormElementNode = $reviewFormElementExportFilter->createReviewFormElementNode(
            $doc,
            $reviewFormElement
        );

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedReviewFormElementNode),
            $doc->saveXML($actualReviewFormElementNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteReviewFormElementXml()
    {
        $reviewFormElementExportFilter = $this->getNativeImportExportFilter();

        $reviewFormElement = DAORegistry::getDAO('ReviewFormElementDAO')->newDataObject();
        $reviewFormElement->setId(68);
        $reviewFormElement->setSequence(1);
        $reviewFormElement->setElementType(REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS);
        $reviewFormElement->setRequired(1);
        $reviewFormElement->setIncluded(1);
        $reviewFormElement->setQuestion('<p>How are you?</p>', 'en_US');
        $reviewFormElement->setDescription('<p>A review form element for test purpose</p>', 'en_US');
        $reviewFormElement->setPossibleResponses(['Fine','Same as always', 'Bad'], 'en_US');
        $reviewFormElements = [$reviewFormElement];

        $doc = $reviewFormElementExportFilter->execute($reviewFormElements);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('reviewFormElement.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
