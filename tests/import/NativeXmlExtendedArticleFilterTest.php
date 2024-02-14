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
        return ['UserDAO', 'UserGroupDAO'];
    }

    protected function getAffectedTables()
    {
        return ['review_rounds', 'review_assignments', 'edit_decisions', 'stage_assignments', 'user_group_stage'];
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

    private function registerMockUserGroupDAO()
    {
        $mockDAO = $this->getMockBuilder(UserGroupDAO::class)
            ->setMethods(['getByContextId'])
            ->getMock();

        $userGroup = $mockDAO->newDataObject();
        $userGroup->setId(734);
        $userGroup->setName('External Reviewer', 'en_US');

        $contextId = 562;
        $userId = 489;
        $mockDAO->assignGroupToStage($contextId, $userGroup->getId(), 3);

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$userGroup]));

        $mockDAO->expects($this->any())
            ->method('getByContextId')
            ->will($this->returnValue($mockResult));

        DAORegistry::registerDAO('UserGroupDAO', $mockDAO);
    }

    public function testParseStageAssignment()
    {
        $articleImportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleImportFilter->getDeployment();

        $this->registerMockUserDAO();
        $this->registerMockUserGroupDAO();

        $submission = new Submission();
        $submission->setId(129);
        $submission->setContextId(562);
        $deployment->setSubmission($submission);

        $expectedStageAssignmentData = [
            'submissionId' => $submission->getId(),
            'userId' => 489,
            'userGroupId' => 734,
            'recommendOnly' => 0,
            'canChangeMetadata' => 0,
        ];

        $doc = $this->getSampleXml('articles.xml');
        $stageAssignmentNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'stage_assignment'
        );

        $stageAssignment = $articleImportFilter->parseStageAssignment($stageAssignmentNodeList->item(0), $submission);
        $expectedStageAssignmentData['id'] = $stageAssignment->getId();
        $this->assertEquals($expectedStageAssignmentData, $stageAssignment->_data);

        $insertedStageAssignment = DAORegistry::getDAO('StageAssignmentDAO')
            ->getById($stageAssignment->getId());
        $expectedStageAssignmentData['dateAssigned'] = $insertedStageAssignment->getDateAssigned();
        $expectedStageAssignmentData['stageId'] = $insertedStageAssignment->getStageId();
        $this->assertEquals($expectedStageAssignmentData, $insertedStageAssignment->_data);
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
