<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlReviewFormElementFilter');

class NativeXmlReviewFormElementFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>review-form-element';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlReviewFormElementFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['review_form_elements', 'review_form_element_settings'];
    }

    protected function getContextData()
    {
        return ['id' => 12];
    }

    public function testHandleReviewFormElementElement()
    {
        $reviewFormElementImportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewFormElementImportFilter->getDeployment();
        $reviewFormElementDAO = DAORegistry::getDAO('ReviewFormElementDAO');
        $reviewForm = $deployment->getReviewForm();

        $reviewForm = DAORegistry::getDAO('ReviewFormDAO')->newDataObject();
        $reviewForm->setId(52);
        $deployment->setReviewForm($reviewForm);
        $reviewFormElementImportFilter->setDeployment($deployment);

        $expectedReviewFormElementData = [
            'reviewFormId' => $reviewForm->getId(),
            'sequence' => 1,
            'reviewFormElementType' => 5,
            'required' => 1,
            'included' => 1,
            'question' => [
                'en_US' => '<p>How are you?</p>'
            ],
            'description' => [
                'en_US' => '<p>A review form element for test purpose</p>'
            ],
            'possibleResponses' => [
                'en_US' => ['Fine', 'Same as always', 'Bad']
            ],
        ];

        $doc = $this->getSampleXml('reviewFormElement.xml');

        $reviewFormElementNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'review_form_element');

        $reviewFormElement = $reviewFormElementImportFilter->handleElement($reviewFormElementNodeList->item(0));
        $reviewFormElementId = array_pop($reviewFormElement->_data);
        $this->assertEquals($expectedReviewFormElementData, $reviewFormElement->_data);

        $insertedReviewFormElement = $reviewFormElementDAO->getById($reviewFormElementId);
        $expectedReviewFormElementData['id'] = $reviewFormElementId;
        $this->assertEquals($expectedReviewFormElementData, $insertedReviewFormElement->_data);
    }
}
