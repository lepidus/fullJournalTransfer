<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlExtendedArticleFilter');

class NativeXmlExtendedArticleFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>extended-article';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlExtendedArticleFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['review_rounds', 'review_assignments'];
    }

    public function testParseReviewRounds()
    {
        $articleImportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleImportFilter->getDeployment();

        $submission = new Submission();
        $submission->setId(129);
        $deployment->setSubmission($submission);

        $expectedReviewRoundData = [
            'submissionId' => $submission->getId(),
            'stageId' => 3,
            'round' => 1,
            'status' => 1
        ];

        $doc = $this->getSampleXml('articles.xml');
        $reviewRoundsNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'review_rounds'
        );

        $articleImportFilter->parseReviewRounds($reviewRoundsNodeList->item(0), $submission);
        $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRounds = $reviewRoundDAO->getBySubmissionId($submission->getId())->toArray();
        $reviewRound = array_shift($reviewRounds);
        $expectedReviewRoundData['id'] = $reviewRound->getId();

        $this->assertEquals($expectedReviewRoundData, $reviewRound->_data);
    }
}
