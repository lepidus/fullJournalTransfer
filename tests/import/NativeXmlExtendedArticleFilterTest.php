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
        return ['review_form_responses', 'review_assignments'];
    }

    protected function getMockedDAOs()
    {
        return ['UserDAO', 'ReviewAssignmentDAO'];
    }

    public function testParseResponses()
    {
        $articleImportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleImportFilter->getDeployment();
        $deployment->setReviewFormElementDBId(14, 41);
        $deployment->setReviewFormElementDBId(15, 42);
        $deployment->setReviewFormElementDBId(16, 43);

        $doc = $this->getSampleXml('article.xml');
        $responseNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'response');

        $reviewAssignment = new ReviewAssignment();
        $reviewAssignment->setId(81);

        for ($i = 0; $i < $responseNodeList->length; $i++) {
            $responseNode = $responseNodeList->item($i);
            $articleImportFilter->parseResponse($responseNode, $reviewAssignment);
        }

        $reviewFormResponseDAO = DAORegistry::getDAO('ReviewFormResponseDAO');
        $reviewFormResponses = $reviewFormResponseDAO->getReviewReviewFormResponseValues($reviewAssignment->getId());

        $expectedResponses = [
            41 => 'Reviewer response',
            42 => 2,
            43 => [1, 3, 6]
        ];

        $this->assertEquals($expectedResponses, $reviewFormResponses);
    }

    public function testParseReviewAssignment()
    {
        $articleImportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleImportFilter->getDeployment();
        $doc = $this->getSampleXml('article.xml');

        $reviewRound = new ReviewRound();
        $reviewRound->setId(23);
        $reviewRound->setRound(1);
        $reviewRound->setSubmissionId(75);
        $reviewRound->setStageId(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);

        $mockUserDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById', 'getByUsername'])
            ->getMock();

        $reviewer = $mockUserDAO->newDataObject();
        $reviewer->setId(52);
        $reviewer->setUsername('reviewer');
        $reviewer->setGivenName('reviewer', 'en_US');

        $mockUserDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($reviewer));

        $mockUserDAO->expects($this->any())
            ->method('getByUsername')
            ->will($this->returnValue($reviewer));

        DAORegistry::registerDAO('UserDAO', $mockUserDAO);

        $mockReviewAssignmentDAO = $this->getMockBuilder(ReviewAssignmentDAO::class)
            ->setMethods(['newDataObject'])
            ->getMock();

        $mockReviewAssignmentDAO->expects($this->any())
            ->method('newDataObject')
            ->will($this->returnValue(new ReviewAssignment()));

        DAORegistry::registerDAO('ReviewAssignmentDAO', $mockReviewAssignmentDAO);

        $reviewAssignmentList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'review_assignment');
        $articleImportFilter->parseReviewAssignment($reviewAssignmentList->item(0), $reviewRound);

        $reviewAssignmentDAO = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignments = $reviewAssignmentDAO->getByReviewRoundId($reviewRound->getId());
        $reviewAssignment = array_shift($reviewAssignments);
        unset($reviewAssignment->_data['id']);

        $expectedReviewAssignment = new ReviewAssignment();
        $expectedReviewAssignment->setReviewerId($reviewer->getId());
        $expectedReviewAssignment->setSubmissionId($reviewRound->getSubmissionId());
        $expectedReviewAssignment->setReviewFormId(35);
        $expectedReviewAssignment->setReviewRoundId($reviewRound->getId());
        $expectedReviewAssignment->setReviewerFullName($reviewer->getFullName());
        $expectedReviewAssignment->setRound($reviewRound->getRound());
        $expectedReviewAssignment->setStageId($reviewRound->getStageId());
        $expectedReviewAssignment->setRecommendation(SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT);
        $expectedReviewAssignment->setQuality(SUBMISSION_REVIEWER_RATING_VERY_GOOD);
        $expectedReviewAssignment->setReviewMethod(SUBMISSION_REVIEW_METHOD_OPEN);
        $expectedReviewAssignment->setCompetingInterests('There is no competing interest');
        $expectedReviewAssignment->setDeclined(0);
        $expectedReviewAssignment->setCancelled(0);
        $expectedReviewAssignment->setReminderWasAutomatic(0);
        $expectedReviewAssignment->setUnconsidered(REVIEW_ASSIGNMENT_NOT_UNCONSIDERED);
        $expectedReviewAssignment->setDateRated('2023-10-31 21:52:08');
        $expectedReviewAssignment->setDateReminded('2023-10-30 21:52:08');
        $expectedReviewAssignment->setDateAssigned('2023-10-29 21:52:08');
        $expectedReviewAssignment->setDateNotified('2023-10-28 21:52:08');
        $expectedReviewAssignment->setDateConfirmed('2023-10-27 21:52:08');
        $expectedReviewAssignment->setDateCompleted('2023-10-26 21:52:08');
        $expectedReviewAssignment->setDateAcknowledged('2023-10-25 21:52:08');
        $expectedReviewAssignment->setDateDue('2023-10-24 21:52:08');
        $expectedReviewAssignment->setDateResponseDue('2023-10-23 21:52:08');
        $expectedReviewAssignment->setLastModified('2023-10-22 21:52:08');

        $this->assertEquals($expectedReviewAssignment->_data, $reviewAssignment->_data);
    }

    public function testParseDecision()
    {
        $articleImportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleImportFilter->getDeployment();
        $doc = $this->getSampleXml('article.xml');

        $reviewRound = new ReviewRound();
        $reviewRound->setSubmissionId(78);
        $reviewRound->setStageId(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
        $reviewRound->setRound(1);

        $mockUserDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById', 'getByUsername'])
            ->getMock();

        $editor = $mockUserDAO->newDataObject();
        $editor->setId(89);
        $editor->setUsername('editor');

        $mockUserDAO->expects($this->any())
            ->method('getByUsername')
            ->will($this->returnValue($editor));

        DAORegistry::registerDAO('UserDAO', $mockUserDAO);

        $roundNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'round');
        $decisionNodeList = $roundNodeList->item(0)->getElementsByTagNameNS($deployment->getNamespace(), 'decision');
        $articleImportFilter->parseDecision(
            $decisionNodeList->item(0),
            $reviewRound
        );

        $editDecisionDAO = DAORegistry::getDAO('EditDecisionDAO');
        $decisions = $editDecisionDAO->getEditorDecisions(
            $reviewRound->getSubmissionId(),
            $reviewRound->getStageId(),
            $reviewRound->getRound()
        );

        $decision = array_shift($decisions);
        unset($decision['editDecisionId']);

        $expectedDecision = [
            'reviewRoundId' => 0,
            'stageId' => 3,
            'round' => 1,
            'editorId' => 89,
            'decision' => 1,
            'dateDecided' => '2015-03-10 12:00:00'
        ];

        $this->assertEquals($expectedDecision, $decision);
    }
}
