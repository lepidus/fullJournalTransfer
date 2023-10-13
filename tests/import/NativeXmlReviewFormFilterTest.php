<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlReviewFormFilter');

class NativeXmlReviewFormFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>review-form';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlReviewFormFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['review_forms', 'review_form_settings'];
    }

    protected function getContextData()
    {
        return ['id' => 12];
    }

    public function testHandleReviewFormElement()
    {
        $reviewFormImportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewFormImportFilter->getDeployment();
        $reviewFormDAO = DAORegistry::getDAO('ReviewFormDAO');

        $expectedReviewFormData = [
            'assocId' => $this->context->getId(),
            'assocType' => ASSOC_TYPE_JOURNAL,
            'active' => 1,
            'sequence' => 1.0,
            'title' => [
                'en_US' => 'Test Review Form'
            ],
            'description' => [
                'en_US' => '<p>A review form for test purpose</p>'
            ]
        ];

        $doc = $this->getSampleXml('reviewForm.xml');

        $reviewFormNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'review_form');

        $reviewForm = $reviewFormImportFilter->handleElement($reviewFormNodeList->item(0));
        $reviewFormId = array_pop($reviewForm->_data);
        $this->assertEquals($expectedReviewFormData, $reviewForm->_data);

        $insertedReviewForm = $reviewFormDAO->getById($reviewFormId, $this->context->getAssocType(), $this->context->getId());
        $expectedReviewFormData['id'] = $reviewFormId;
        $expectedReviewFormData['completeCount'] = 0;
        $expectedReviewFormData['incompleteCount'] = 0;
        $this->assertEquals($expectedReviewFormData, $insertedReviewForm->_data);
    }
}
