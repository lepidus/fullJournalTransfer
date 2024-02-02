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

    protected function getMockedDAOs()
    {
        return ['UserDAO'];
    }

    protected function getAffectedTables()
    {
        return ['review_rounds', 'review_assignments', 'edit_decisions'];
    }

    private function registerMockUserDAO()
    {
        $mockDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getByUsername'])
            ->getMock();

        $user = $mockDAO->newDataObject();
        $user->setId('489');

        $mockDAO->expects($this->any())
            ->method('getByUsername')
            ->will($this->returnValue($user));

        DAORegistry::registerDAO('UserDAO', $mockDAO);
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

    public function testParseEditorDecisions()
    {
        $articleImportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleImportFilter->getDeployment();

        $submission = new Submission();
        $submission->setId(129);
        $deployment->setSubmission($submission);

        $expectedEditorDecisionData = [
            'reviewRoundId' => 0,
            'stageId' => 1,
            'round' => 0,
            'editorId' => 489,
            'decision' => 8,
            'dateDecided' => '2015-03-04 13:39:11'
        ];

        $this->registerMockUserDAO();

        $doc = $this->getSampleXml('articles.xml');
        $editorDecisionsNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'editor_decisions'
        );

        $articleImportFilter->parseEditorDecisions($editorDecisionsNodeList->item(0), $submission);
        $editorDecisionDAO = DAORegistry::getDAO('EditDecisionDAO');
        $editorDecisions = $editorDecisionDAO->getEditorDecisions($submission->getId());
        $editorDecision = array_shift($editorDecisions);
        $expectedEditorDecisionData['editDecisionId'] = $editorDecision['editDecisionId'];

        $this->assertEquals($expectedEditorDecisionData, $editorDecision);
    }
}
