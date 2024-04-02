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
        return ['UserDAO', 'UserGroupDAO', 'StageAssignmentDAO', 'EditDecisionDAO', 'ReviewRoundDAO'];
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
}
