<?php

import('classes.submission.Submission');
import('classes.workflow.EditorDecisionActionsManager');
import('lib.pkp.classes.submission.reviewRound.ReviewRound');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.ExtendedArticleNativeXmlFilter');

class ExtendedArticleNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'extended-article=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return ExtendedArticleNativeXmlFilter::class;
    }

    protected function getMockedDAOs()
    {
        return [
            'UserDAO', 'UserGroupDAO',
            'StageAssignmentDAO', 'EditDecisionDAO',
            'ReviewRoundDAO', 'ReviewAssignmentDAO',
            'ReviewFormResponseDAO'
        ];
    }

    private function registerMockUserDAO($username)
    {
        $mockDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        $user = $mockDAO->newDataObject();
        $user->setUsername($username);

        $mockDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($user));

        DAORegistry::registerDAO('UserDAO', $mockDAO);
    }

    private function registerMockUserGroupDAO()
    {
        $mockDAO = $this->getMockBuilder(UserGroupDAO::class)
            ->setMethods(['getById', 'userAssignmentExists'])
            ->getMock();

        $userGroup = $mockDAO->newDataObject();
        $userGroup->setId(734);
        $userGroup->setName('External Reviewer', 'en_US');

        $mockDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($userGroup));

        $mockDAO->expects($this->any())
            ->method('userAssignmentExists')
            ->will($this->returnValue(true));

        DAORegistry::registerDAO('UserGroupDAO', $mockDAO);
    }

    private function registerMockStageAssignmentDAO($stageAssignment)
    {
        $mockDAO = $this->getMockBuilder(StageAssignmentDAO::class)
            ->setMethods(['getBySubmissionAndStageId'])
            ->getMock();

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['next'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('next')
            ->will($this->onConsecutiveCalls($stageAssignment, null));

        $mockDAO->expects($this->any())
            ->method('getBySubmissionAndStageId')
            ->will($this->returnValue($mockResult));

        DAORegistry::registerDAO('StageAssignmentDAO', $mockDAO);
    }

    private function registerMockEditorDecisionDAO()
    {
        $mockDAO = $this->getMockBuilder(EditDecisionDAO::class)
            ->setMethods(['getEditorDecisions'])
            ->getMock();

        $editorDecision = [
            'editDecisionId' => 123,
            'reviewRoundId' => 0,
            'stageId' => 1,
            'round' => 0,
            'editorId' => 784,
            'decision' => 8,
            'dateDecided' => '2015-03-04 13:39:11'
        ];

        $mockDAO->expects($this->any())
            ->method('getEditorDecisions')
            ->will($this->returnValue([$editorDecision]));

        DAORegistry::registerDAO('EditDecisionDAO', $mockDAO);
    }

    private function registerMockReviewRoundDAO()
    {
        $mockDAO = $this->getMockBuilder(ReviewRoundDAO::class)
            ->setMethods(['getBySubmissionId'])
            ->getMock();

        $reviewRound = $mockDAO->newDataObject();
        $reviewRound->setStageId(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
        $reviewRound->setRound(1);
        $reviewRound->setStatus(REVIEW_ROUND_STATUS_REVIEWS_COMPLETED);

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['next'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('next')
            ->will($this->onConsecutiveCalls($reviewRound, null));

        $mockDAO->expects($this->any())
            ->method('getBySubmissionId')
            ->will($this->returnValue($mockResult));

        DAORegistry::registerDAO('ReviewRoundDAO', $mockDAO);
    }

    private function registerMockReviewAssignmentDAO()
    {
        $mockDAO = $this->getMockBuilder(ReviewAssignmentDAO::class)
            ->setMethods(['getByReviewRoundId'])
            ->getMock();

        $reviewAssignment = $mockDAO->newDataObject();
        $reviewAssignment->setReviewFormId(2);
        $reviewAssignment->setRecommendation(SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT);
        $reviewAssignment->setQuality(SUBMISSION_REVIEWER_RATING_VERY_GOOD);
        $reviewAssignment->setReviewMethod(SUBMISSION_REVIEW_METHOD_OPEN);
        $reviewAssignment->setCompetingInterests('There is no competing interest');
        $reviewAssignment->setDeclined(false);
        $reviewAssignment->setCancelled(false);
        $reviewAssignment->setReminderWasAutomatic(false);
        $reviewAssignment->setUnconsidered(REVIEW_ASSIGNMENT_NOT_UNCONSIDERED);
        $reviewAssignment->setDateRated('2023-10-31 21:52:08.000');
        $reviewAssignment->setDateReminded('2023-10-30 21:52:08.000');
        $reviewAssignment->setDateAssigned('2023-10-29 21:52:08.000');
        $reviewAssignment->setDateNotified('2023-10-28 21:52:08.000');
        $reviewAssignment->setDateConfirmed('2023-10-27 21:52:08.000');
        $reviewAssignment->setDateCompleted('2023-10-26 21:52:08.000');
        $reviewAssignment->setDateAcknowledged('2023-10-25 21:52:08.000');
        $reviewAssignment->setDateDue('2023-10-24 21:52:08.000');
        $reviewAssignment->setDateResponseDue('2023-10-23 21:52:08.000');
        $reviewAssignment->setLastModified('2023-10-22 21:52:08.000');

        $mockDAO->expects($this->any())
            ->method('getByReviewRoundId')
            ->will($this->returnValue(array($reviewAssignment)));

        DAORegistry::registerDAO('ReviewAssignmentDAO', $mockDAO);
    }

    private function registerMockReviewFormResponseDAO()
    {
        $mockDAO = $this->getMockBuilder(ReviewFormResponseDAO::class)
            ->setMethods(['getReviewReviewFormResponseValues'])
            ->getMock();

        $responses = [];

        $response = $mockDAO->newDataObject();
        $response->setReviewFormElementId(14);
        $response->setResponseType('string');
        $response->setValue('Reviewer response');
        $responses[] = $response;

        $response = $mockDAO->newDataObject();
        $response->setReviewFormElementId(15);
        $response->setResponseType('int');
        $response->setValue(2);
        $responses[] = $response;

        $response = $mockDAO->newDataObject();
        $response->setReviewFormElementId(16);
        $response->setResponseType('object');
        $response->setValue([1, 3, 6]);
        $responses[] = $response;

        $mockDAO->expects($this->any())
            ->method('getReviewReviewFormResponseValues')
            ->will($this->returnValue($responses));

        DAORegistry::registerDAO('ReviewFormResponseDAO', $mockDAO);
    }

    public function testStagesNodeCreation()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $submission = new Submission();
        $this->registerMockStageAssignmentDAO(null);

        $expectedArticleNode = $this->doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $expectedArticleNode->appendChild($stageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage'));
        $stageNode->setAttribute('path', WORKFLOW_STAGE_PATH_SUBMISSION);
        $expectedArticleNode->appendChild($stageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage'));
        $stageNode->setAttribute('path', WORKFLOW_STAGE_PATH_INTERNAL_REVIEW);
        $expectedArticleNode->appendChild($stageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage'));
        $stageNode->setAttribute('path', WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW);
        $expectedArticleNode->appendChild($stageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage'));
        $stageNode->setAttribute('path', WORKFLOW_STAGE_PATH_EDITING);
        $expectedArticleNode->appendChild($stageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage'));
        $stageNode->setAttribute('path', WORKFLOW_STAGE_PATH_PRODUCTION);

        $articleNode = $this->doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $articleExportFilter->createStageNodes($this->doc, $articleNode, $submission);

        $this->assertXmlStringEqualsXmlString(
            $this->doc->saveXML($expectedArticleNode),
            $this->doc->saveXML($articleNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testAddingParticipants()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $context = new Journal();
        $context->setPrimaryLocale('en_US');
        $deployment->setContext($context);

        $stageAssignment = new StageAssignment();
        $stageAssignment->setId(563);
        $stageAssignment->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
        $stageAssignment->getRecommendOnly(0);
        $stageAssignment->getCanChangeMetadata(0);

        $this->registerMockUserDAO('editor');
        $this->registerMockUserGroupDAO();
        $this->registerMockStageAssignmentDAO($stageAssignment);

        $expectedStageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $expectedStageNode->appendChild($participantNode = $this->doc->createElementNS(
            $deployment->getNamespace(),
            'participant'
        ));
        $participantNode->setAttribute('user', 'editor');
        $participantNode->setAttribute('user_group_ref', 'External Reviewer');
        $participantNode->setAttribute('recommend_only', 0);
        $participantNode->setAttribute('can_change_metadata', 0);

        $submission = new Submission();
        $stageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $articleExportFilter->addParticipants($this->doc, $stageNode, $submission, WORKFLOW_STAGE_ID_SUBMISSION);

        $this->assertXmlStringEqualsXmlString(
            $this->doc->saveXML($expectedStageNode),
            $this->doc->saveXML($stageNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testAddingEditorDecision()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $this->registerMockUserDAO('editor');
        $this->registerMockUserGroupDAO();
        $this->registerMockEditorDecisionDAO();

        $expectedStageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $decisionNode = $this->doc->createElementNS($deployment->getNamespace(), 'decision');
        $decisionNode->setAttribute('round', 0);
        $decisionNode->setAttribute('review_round_id', 0);
        $decisionNode->setAttribute('editor', 'editor');
        $decisionNode->setAttribute('decision', SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW);
        $decisionNode->setAttribute('date_decided', '2015-03-04 13:39:11');
        $expectedStageNode->appendChild($decisionNode);

        $submission = new Submission();
        $stageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $articleExportFilter->addEditorDecisions($this->doc, $stageNode, $submission, WORKFLOW_STAGE_ID_SUBMISSION);

        $this->assertXmlStringEqualsXmlString(
            $this->doc->saveXML($expectedStageNode),
            $this->doc->saveXML($stageNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testAddingReviewRounds()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $this->registerMockReviewRoundDAO();

        $expectedStageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $roundNode = $this->doc->createElementNS($deployment->getNamespace(), 'round');
        $roundNode->setAttribute('round', 1);
        $roundNode->setAttribute('status', REVIEW_ROUND_STATUS_REVIEWS_COMPLETED);
        $expectedStageNode->appendChild($roundNode);

        $submission = new Submission();
        $stageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $articleExportFilter->addReviewRounds($this->doc, $stageNode, $submission, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);

        $this->assertXmlStringEqualsXmlString(
            $this->doc->saveXML($expectedStageNode),
            $this->doc->saveXML($stageNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testAddingReviewAssignments()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $this->registerMockUserDAO('reviewer');
        $this->registerMockReviewAssignmentDAO();

        $expectedRoundNode = $this->doc->createElementNS($deployment->getNamespace(), 'round');
        $assignmentNode = $this->doc->createElementNS($deployment->getNamespace(), 'review_assignment');
        $assignmentNode->setAttribute('reviewer', 'reviewer');
        $assignmentNode->setAttribute('recommendation', SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT);
        $assignmentNode->setAttribute('quality', SUBMISSION_REVIEWER_RATING_VERY_GOOD);
        $assignmentNode->setAttribute('method', SUBMISSION_REVIEW_METHOD_OPEN);
        $assignmentNode->setAttribute('unconsidered', REVIEW_ASSIGNMENT_NOT_UNCONSIDERED);
        $assignmentNode->setAttribute('competing_interests', 'There is no competing interest');
        $assignmentNode->setAttribute('declined', 0);
        $assignmentNode->setAttribute('cancelled', 0);
        $assignmentNode->setAttribute('was_automatic', 0);
        $assignmentNode->setAttribute('date_rated', '2023-10-31 21:52:08.000');
        $assignmentNode->setAttribute('date_reminded', '2023-10-30 21:52:08.000');
        $assignmentNode->setAttribute('date_assigned', '2023-10-29 21:52:08.000');
        $assignmentNode->setAttribute('date_notified', '2023-10-28 21:52:08.000');
        $assignmentNode->setAttribute('date_confirmed', '2023-10-27 21:52:08.000');
        $assignmentNode->setAttribute('date_completed', '2023-10-26 21:52:08.000');
        $assignmentNode->setAttribute('date_acknowledged', '2023-10-25 21:52:08.000');
        $assignmentNode->setAttribute('date_due', '2023-10-24 21:52:08.000');
        $assignmentNode->setAttribute('date_response_due', '2023-10-23 21:52:08.000');
        $assignmentNode->setAttribute('last_modified', '2023-10-22 21:52:08.000');
        $expectedRoundNode->appendChild($assignmentNode);

        $reviewRound = new ReviewRound();
        $roundNode = $this->doc->createElementNS($deployment->getNamespace(), 'round');
        $articleExportFilter->addReviewAssignments($this->doc, $roundNode, $reviewRound);

        $this->assertXmlStringEqualsXmlString(
            $this->doc->saveXML($expectedRoundNode),
            $this->doc->saveXML($roundNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testAddingReviewFormResponses()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $this->registerMockReviewFormResponseDAO();

        $expectedAssignmentNode = $this->doc->createElementNS($deployment->getNamespace(), 'review_assignment');
        $expectedAssignmentNode->appendChild($responseNode = $this->doc->createElementNS(
            $deployment->getNamespace(),
            'response',
            htmlspecialchars('Reviewer response', ENT_COMPAT, 'UTF-8')
        ));
        $responseNode->setAttribute('form_element_id', 14);
        $expectedAssignmentNode->appendChild($responseNode = $this->doc->createElementNS(
            $deployment->getNamespace(),
            'response',
            intval(2)
        ));
        $responseNode->setAttribute('form_element_id', 15);
        $expectedAssignmentNode->appendChild($responseNode = $this->doc->createElementNS(
            $deployment->getNamespace(),
            'response',
            join(':', [1,3,6])
        ));
        $responseNode->setAttribute('form_element_id', 16);

        $reviewAssignment = new ReviewAssignment();
        $assignmentNode = $this->doc->createElementNS($deployment->getNamespace(), 'review_assignment');
        $articleExportFilter->addReviewFormResponses($this->doc, $assignmentNode, $reviewAssignment);

        $this->assertXmlStringEqualsXmlString(
            $this->doc->saveXML($expectedAssignmentNode),
            $this->doc->saveXML($assignmentNode),
            "actual xml is equal to expected xml"
        );
    }
}
