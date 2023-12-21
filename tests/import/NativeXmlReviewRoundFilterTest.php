<?php

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlReviewRoundFilter');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');

class NativeXmlReviewRoundFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>review-round';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlReviewRoundFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['review_rounds'];
    }

    public function testHandleReviewFormElement()
    {
        $reviewRoundImportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewRoundImportFilter->getDeployment();

        $expectedReviewRoundData = [
            'submissionId' => 16,
            'stageId' => 3,
            'round' => 1,
            'status' => 1
        ];

        $doc = $this->getSampleXml('reviewRound.xml');

        $reviewRoundNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'review_round');

        $reviewRound = $reviewRoundImportFilter->handleElement($reviewRoundNodeList->item(0));
        $reviewRoundId = array_pop($reviewRound->_data);
        $this->assertEquals($expectedReviewRoundData, $reviewRound->_data);

        $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
        $insertedReviewRound = $reviewRoundDAO->getById($reviewRoundId);
        $expectedReviewRoundData['id'] = $reviewRoundId;
        $this->assertEquals($expectedReviewRoundData, $insertedReviewRound->_data);
    }
}
