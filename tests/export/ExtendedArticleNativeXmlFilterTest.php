<?php

import('classes.submission.Submission');
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
        return ['UserDAO', 'UserGroupDAO', 'StageAssignmentDAO'];
    }

    private function registerMockStageAssignmentDAO()
    {
        $mockDAO = $this->getMockBuilder(StageAssignmentDAO::class)
            ->setMethods(['getBySubmissionAndStageId'])
            ->getMock();

        $stageAssignment = $mockDAO->newDataObject();
        $stageAssignment->setId(563);
        $stageAssignment->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
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

    public function testStagesNodeCreation()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $submission = new Submission();

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
        $this->assertEquals($expectedArticleNode, $articleNode);
    }

    public function testParticipantNodeCreation()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $context = new Journal();
        $context->setPrimaryLocale('en_US');
        $deployment->setContext($context);

        $this->registerMockUserDAO('editor');
        $this->registerMockUserGroupDAO();
        $this->registerMockStageAssignmentDAO();

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
        $articleExportFilter->createParticipantNode($this->doc, $stageNode, $submission, WORKFLOW_STAGE_ID_SUBMISSION);

        $this->assertEquals($expectedStageNode, $stageNode);
    }
}
