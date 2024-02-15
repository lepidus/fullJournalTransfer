<?php

import('classes.submission.Submission');
import('classes.workflow.EditorDecisionActionsManager');
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
        return ['UserDAO', 'UserGroupDAO', 'ReviewRoundDAO', 'EditDecisionDAO', 'StageAssignmentDAO'];
    }

    private function registerMockStageAssignmentDAO()
    {
        $mockDAO = $this->getMockBuilder(StageAssignmentDAO::class)
            ->setMethods(['getBySubmissionAndStageId'])
            ->getMock();

        $stageAssignment = $mockDAO->newDataObject();
        $stageAssignment->setId(563);
        $stageAssignment->setStageId(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
        $stageAssignment->getRecommendOnly(0);
        $stageAssignment->getCanChangeMetadata(0);

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

    private function registerMockReviewRoundDAO()
    {
        $mockDAO = $this->getMockBuilder(ReviewRoundDAO::class)
            ->setMethods(['getBySubmissionId'])
            ->getMock();

        $reviewRound = $mockDAO->newDataObject();
        $reviewRound->setId(563);
        $reviewRound->setSubmissionId(16);
        $reviewRound->setStageId(3);
        $reviewRound->setRound(1);
        $reviewRound->setStatus(1);
        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$reviewRound]));

        $mockDAO->expects($this->any())
            ->method('getBySubmissionId')
            ->will($this->returnValue($mockResult));

        DAORegistry::registerDAO('ReviewRoundDAO', $mockDAO);
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
            ->setMethods(['getById'])
            ->getMock();

        $userGroup = $mockDAO->newDataObject();
        $userGroup->setId(734);
        $userGroup->setName('External Reviewer', 'en_US');

        $mockDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($userGroup));

        DAORegistry::registerDAO('UserGroupDAO', $mockDAO);
    }

    public function createRootNode($doc, $deployment, $tagName)
    {
        $rootNode = $doc->createElementNS($deployment->getNamespace(), $tagName);
        $rootNode->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $rootNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );

        return $rootNode;
    }

    public function testAddStageAssignment()
    {
        $extendedArticleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $extendedArticleExportFilter->getDeployment();


        $context = new Journal();
        $context->setPrimaryLocale('en_US');
        $deployment->setContext($context);

        $this->registerMockStageAssignmentDAO();
        $this->registerMockUserDAO('testuser');
        $this->registerMockUserGroupDAO();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $expectedSubmissionNode->appendChild($stageAssignmentNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'stage_assignment'
        ));
        $stageAssignmentNode->setAttribute('user', 'testuser');
        $stageAssignmentNode->setAttribute('user_group_ref', 'External Reviewer');
        $stageAssignmentNode->setAttribute('stage', 'externalReview');
        $stageAssignmentNode->setAttribute('recommend_only', 0);
        $stageAssignmentNode->setAttribute('can_change_metadata', 0);

        $submissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');

        $submission = new Submission();
        $submission->setId(143);

        $extendedArticleExportFilter->addStageAssignments($doc, $submissionNode, $submission);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedSubmissionNode),
            $doc->saveXML($submissionNode),
            "actual xml is equal to expected xml"
        );
    }

    public function createEditorDecisionsNode($doc, $deployment)
    {
        $editorDecisionsNode = $doc->createElementNS($deployment->getNamespace(), 'editor_decisions');
        $editorDecisionsNode->appendChild($editorDecisionNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'editor_decision'
        ));

        $editorDecisionNode->setAttribute('submission_id', 143);
        $editorDecisionNode->setAttribute('round', 0);
        $editorDecisionNode->setAttribute('review_round_id', 0);
        $editorDecisionNode->setAttribute('stage_id', 1);
        $editorDecisionNode->setAttribute('decision', SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW);

        $editorDecisionNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'editor',
            htmlspecialchars('journaleditor', ENT_COMPAT, 'UTF-8')
        ));
        $editorDecisionNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_decided',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2015-03-04 13:39:11'))
        ));

        return $editorDecisionsNode;
    }

    public function testAddReviewRounds()
    {
        $extendedArticleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $extendedArticleExportFilter->getDeployment();

        $reviewRoundNativeXmlFilterTest = new ReviewRoundNativeXmlFilterTest();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');

        $expectedSubmissionNode->appendChild($reviewRoundsNode = $this->createRootNode(
            $doc,
            $deployment,
            'review_rounds'
        ));
        $reviewRoundsNode->appendChild(
            $reviewRoundNode = $reviewRoundNativeXmlFilterTest->createReviewRoundNode($doc, $deployment)
        );
        $reviewRoundNode->appendChild($this->createRootNode($doc, $deployment, 'review_assignments'));

        $actualSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $submission = new Submission();
        $submission->setId(143);
        $this->registerMockReviewRoundDAO();

        $extendedArticleExportFilter->addReviewRounds($doc, $actualSubmissionNode, $submission);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedSubmissionNode),
            $doc->saveXML($actualSubmissionNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testAddEditorDecisions()
    {
        $extendedArticleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $extendedArticleExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $submission = new Submission();
        $submission->setId(143);

        $this->registerMockEditorDecisionDAO();
        $this->registerMockUserDAO('journaleditor');

        $expectedSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $expectedSubmissionNode->appendChild($this->createEditorDecisionsNode($doc, $deployment));

        $actualSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $extendedArticleExportFilter->addEditorDecisions(
            $doc,
            $actualSubmissionNode,
            $submission
        );

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedSubmissionNode),
            $doc->saveXML($actualSubmissionNode),
            "actual xml is equal to expected xml"
        );
    }
}
