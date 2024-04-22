<?php

import('classes.article.Author');
import('classes.submission.Submission');
import('classes.publication.Publication');
import('lib.pkp.classes.query.Query');
import('lib.pkp.classes.note.Note');
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
            'StageAssignmentDAO', 'QueryDAO', 'NoteDAO',
            'EditDecisionDAO', 'ReviewRoundDAO', 'ReviewAssignmentDAO',
            'ReviewFormResponseDAO', 'SectionDAO'
        ];
    }

    protected function getMockedRegistryKeys()
    {
        return ['application'];
    }

    public function registerApplicationMock()
    {
        $mockApplication = $this->getMockBuilder(Application::class)
            ->setMethods(['getEnabledProducts'])
            ->getMock();

        $mockApplication->expects($this->any())
            ->method('getEnabledProducts')
            ->will($this->returnValue([]));

        Registry::set('application', $mockApplication);
    }

    private function registerMockUserDAO($email, $username = null)
    {
        $mockDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        $user = $mockDAO->newDataObject();
        $user->setEmail($email);
        $user->setUsername($username);

        $mockDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($user));

        DAORegistry::registerDAO('UserDAO', $mockDAO);
    }

    private function registerMockUserGroupDAO($userGroupName)
    {
        $mockDAO = $this->getMockBuilder(UserGroupDAO::class)
            ->setMethods(['getById', 'userAssignmentExists'])
            ->getMock();

        $userGroup = $mockDAO->newDataObject();
        $userGroup->setId(734);
        $userGroup->setName($userGroupName, 'en_US');

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

    private function registerMockQueryDAO($query)
    {
        $mockDAO = $this->getMockBuilder(QueryDAO::class)
            ->setMethods(['getByAssoc', 'getParticipantIds'])
            ->getMock();

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['next'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('next')
            ->will($this->onConsecutiveCalls($query, null));

        $mockDAO->expects($this->any())
            ->method('getByAssoc')
            ->will($this->returnValue($mockResult));

        $mockDAO->expects($this->any())
            ->method('getParticipantIds')
            ->will($this->returnValue([123]));

        DAORegistry::registerDAO('QueryDAO', $mockDAO);
    }

    private function registerMockNoteDAO($note)
    {
        $mockDAO = $this->getMockBuilder(NoteDAO::class)
            ->setMethods(['getByAssoc'])
            ->getMock();

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['next'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('next')
            ->will($this->onConsecutiveCalls($note, null));

        $mockDAO->expects($this->any())
            ->method('getByAssoc')
            ->will($this->returnValue($mockResult));

        DAORegistry::registerDAO('NoteDAO', $mockDAO);
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

    private function registerMockReviewAssignmentDAO($hasResponse = false)
    {
        $mockDAO = $this->getMockBuilder(ReviewAssignmentDAO::class)
            ->setMethods(['getByReviewRoundId'])
            ->getMock();

        $reviewAssignment = $mockDAO->newDataObject();
        $reviewAssignment->setRecommendation(SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT);
        $reviewAssignment->setQuality(SUBMISSION_REVIEWER_RATING_VERY_GOOD);
        $reviewAssignment->setReviewMethod(SUBMISSION_REVIEW_METHOD_OPEN);
        $reviewAssignment->setCompetingInterests('There is no competing interest');
        $reviewAssignment->setDeclined(false);
        $reviewAssignment->setCancelled(false);
        $reviewAssignment->setReminderWasAutomatic(false);
        $reviewAssignment->setUnconsidered(REVIEW_ASSIGNMENT_NOT_UNCONSIDERED);
        $reviewAssignment->setDateRated('2023-10-31 21:52:08');
        $reviewAssignment->setDateReminded('2023-10-30 21:52:08');
        $reviewAssignment->setDateAssigned('2023-10-29 21:52:08');
        $reviewAssignment->setDateNotified('2023-10-28 21:52:08');
        $reviewAssignment->setDateConfirmed('2023-10-27 21:52:08');
        $reviewAssignment->setDateCompleted('2023-10-26 21:52:08');
        $reviewAssignment->setDateAcknowledged('2023-10-25 21:52:08');
        $reviewAssignment->setDateDue('2023-10-24 21:52:08');
        $reviewAssignment->setDateResponseDue('2023-10-23 21:52:08');
        $reviewAssignment->setLastModified('2023-10-22 21:52:08');

        if ($hasResponse) {
            $reviewAssignment->setReviewFormId(35);
        }

        $mockDAO->expects($this->any())
            ->method('getByReviewRoundId')
            ->will($this->returnValue(array($reviewAssignment)));

        DAORegistry::registerDAO('ReviewAssignmentDAO', $mockDAO);
    }

    private function registerMockReviewFormResponseDAO()
    {
        $mockDAO = $this->getMockBuilder(ReviewFormResponseDAO::class)
            ->setMethods(['getReviewReviewFormResponseValues', 'getReviewFormResponse'])
            ->getMock();

        $responses = [
            14 => 'Reviewer response',
            15 => 2,
            16 => [1, 3, 6]
        ];

        $responseString = $mockDAO->newDataObject();
        $responseString->setReviewFormElementId(14);
        $responseString->setResponseType('string');
        $responseString->setValue('Reviewer response');

        $responseInt = $mockDAO->newDataObject();
        $responseInt->setReviewFormElementId(15);
        $responseInt->setResponseType('int');
        $responseInt->setValue(2);

        $responseObject = $mockDAO->newDataObject();
        $responseObject->setReviewFormElementId(16);
        $responseObject->setResponseType('object');
        $responseObject->setValue([1, 3, 6]);

        $mockDAO->expects($this->any())
            ->method('getReviewReviewFormResponseValues')
            ->will($this->returnValue($responses));

        $mockDAO->expects($this->any())
            ->method('getReviewFormResponse')
            ->will($this->onConsecutiveCalls($responseString, $responseInt, $responseObject));

        DAORegistry::registerDAO('ReviewFormResponseDAO', $mockDAO);
    }

    private function registerMockSectionDAO()
    {
        $mockDAO = $this->getMockBuilder(SectionDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        $section = $mockDAO->newDataObject();
        $section->getLocalizedAbbrev('ART');

        $mockDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($section));

        DAORegistry::registerDAO('SectionDAO', $mockDAO);
    }

    public function testAddingStages()
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
        $articleExportFilter->addStages($this->doc, $articleNode, $submission);

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

        $this->registerMockUserDAO('editor@email.com');
        $this->registerMockUserGroupDAO('External Reviewer');
        $this->registerMockStageAssignmentDAO($stageAssignment);

        $expectedStageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $expectedStageNode->appendChild($participantNode = $this->doc->createElementNS(
            $deployment->getNamespace(),
            'participant'
        ));
        $participantNode->setAttribute('user_email', 'editor@email.com');
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

        $this->registerMockUserDAO('editor@email.com');
        $this->registerMockUserGroupDAO('External Reviewer');
        $this->registerMockEditorDecisionDAO();

        $expectedStageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $decisionNode = $this->doc->createElementNS($deployment->getNamespace(), 'decision');
        $decisionNode->setAttribute('round', 0);
        $decisionNode->setAttribute('review_round_id', 0);
        $decisionNode->setAttribute('editor_email', 'editor@email.com');
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

    public function testAddingQueries()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $query = new Query();
        $query->setSequence(1);
        $query->setIsClosed(false);

        $note = new Note();
        $note->setUserId(123);
        $note->setDateCreated('2015-03-03 20:33:43');
        $note->setDateModified('2015-03-03 20:37:37');
        $note->setTitle('Recommendation');
        $note->setContents('<p>The recommendation regarding this submission is: Accept Submission</p>');

        $submissionFile = new SubmissionFile();
        $submissionFile->setId(79);
        $submissionFile->setData('assocId', $note->getId());
        $submissionFile->setData('assocType', ASSOC_TYPE_NOTE);
        $submissionFile->setData('fileStage', SUBMISSION_FILE_QUERY);
        $submissionFile->setData('createdAt', '2023-11-18 15:47:38');
        $submissionFile->setData('updatedAt', '2023-11-26 15:47:49');
        $submissionFile->setData('fileId', 79);
        $submissionFile->setData('genreId', 1);
        $submissionFile->setData('viewable', true);
        $submissionFile->setData('uploaderUserId', 123);
        $submissionFile->setData('name', 'dummy.pdf', 'en_US');

        $submissionFileDAO = DAORegistry::getDAO('SubmissionFileDAO');
        $submissionFileId = $submissionFileDAO->insertObject($submissionFile);

        $file = new stdClass();
        $file->fileId = 79;
        $file->path = 'journals/1/articles/1/655858c94c124.pdf';
        $file->mimetype = 'application/pdf';

        $mockSubmissionFileDAO = $this->getMockBuilder(SubmissionFileDAO::class)
            ->setMethods(['getRevisions'])
            ->getMock();
        $mockSubmissionFileDAO->expects($this->any())
            ->method('getRevisions')
            ->will($this->returnValue([$file]));

        DAORegistry::registerDAO('SubmissionFileDAO', $mockSubmissionFileDAO);

        $this->registerMockUserDAO('editor@email.com', 'editor');
        $this->registerMockQueryDAO($query);
        $this->registerMockNoteDAO($note);

        $expectedStageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $queriesNode = $this->doc->createElementNS($deployment->getNamespace(), 'queries');
        $queryNode = $this->doc->createElementNS($deployment->getNamespace(), 'query');
        $queryNode->setAttribute('seq', $query->getSequence());
        $queryNode->setAttribute('closed', (int) $query->getIsClosed());
        $queryNode->appendChild($this->createQueryParticipantsNode($deployment, ['editor@email.com']));
        $queryNode->appendChild($this->createQueryRepliesNode($deployment, [$note]));
        $queryNode->appendChild($this->createNoteFileNode($deployment, $articleExportFilter, $submissionFile, $file));
        $queriesNode->appendChild($queryNode);
        $expectedStageNode->appendChild($queriesNode);

        $submission = new Submission();
        $stageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $articleExportFilter->addQueries($this->doc, $stageNode, $submission, WORKFLOW_STAGE_ID_SUBMISSION);

        $this->assertXmlStringEqualsXmlString(
            $this->doc->saveXML($expectedStageNode),
            $this->doc->saveXML($stageNode),
            "actual xml is equal to expected xml"
        );

        $submissionFileDAO->deleteById($submissionFileId);
        DAORegistry::registerDAO('SubmissionFileDAO', $submissionFileDAO);
    }

    private function createQueryParticipantsNode($deployment, $participantEmails)
    {
        $participantsNode = $this->doc->createElementNS($deployment->getNamespace(), 'participants');

        foreach ($participantEmails as $email) {
            $participantNode = $this->doc->createElementNS(
                $deployment->getNamespace(),
                'participant',
                htmlspecialchars($email, ENT_COMPAT, 'UTF-8')
            );
            $participantsNode->appendChild($participantNode);
        }
        return $participantsNode;
    }

    private function createQueryRepliesNode($deployment, $notes)
    {
        $repliesNode = $this->doc->createElementNS($deployment->getNamespace(), 'replies');

        foreach ($notes as $note) {
            $noteNode = $this->doc->createElementNS($deployment->getNamespace(), 'note');
            $noteNode->setAttribute('user_email', 'editor@email.com');
            $noteNode->setAttribute('date_modified', $note->getDateModified());
            $noteNode->setAttribute('date_created', $note->getDateCreated());

            $titleNode = $this->doc->createElementNS(
                $deployment->getNamespace(),
                'title',
                htmlspecialchars($note->getTitle(), ENT_COMPAT, 'UTF-8')
            );
            $contentsNode = $this->doc->createElementNS(
                $deployment->getNamespace(),
                'contents',
                htmlspecialchars($note->getContents(), ENT_COMPAT, 'UTF-8')
            );

            $noteNode->appendChild($titleNode);
            $noteNode->appendChild($contentsNode);
            $repliesNode->appendChild($noteNode);
        }
        return $repliesNode;
    }

    private function createNoteFileNode($deployment, $filter, $submissionFile, $file)
    {
        $submissionFileNode = $this->doc->createElementNS($deployment->getNamespace(), 'submission_file');
        $submissionFileNode->setAttribute('id', $submissionFile->getId());
        $submissionFileNode->setAttribute('created_at', strftime('%Y-%m-%d', strtotime($submissionFile->getData('createdAt'))));
        $submissionFileNode->setAttribute('date_created', $submissionFile->getData('dateCreated'));
        $submissionFileNode->setAttribute('file_id', $submissionFile->getData('fileId'));
        $submissionFileNode->setAttribute('stage', 'query');
        $submissionFileNode->setAttribute('updated_at', strftime('%Y-%m-%d', strtotime($submissionFile->getData('updatedAt'))));
        $submissionFileNode->setAttribute('viewable', $submissionFile->getViewable() ? 'true' : 'false');
        $submissionFileNode->setAttribute('genre', 'Article Text');
        $submissionFileNode->setAttribute('uploader', 'editor');

        $filter->createLocalizedNodes($this->doc, $submissionFileNode, 'name', $submissionFile->getData('name'));

        $fileNode = $this->doc->createElementNS($deployment->getNamespace(), 'file');
        $fileNode->setAttribute('id', $file->fileId);
        $fileNode->setAttribute('filesize', '14572');
        $fileNode->setAttribute('extension', pathinfo($file->path, PATHINFO_EXTENSION));
        $fileNode->appendChild($hrefNode = $this->doc->createElementNS($deployment->getNamespace(), 'href'));
        $hrefNode->setAttribute('src', $file->path);
        $hrefNode->setAttribute('mime_type', $file->mimetype);

        $submissionFileNode->appendChild($fileNode);

        return $submissionFileNode;
    }

    public function testAddingReviewRounds()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $this->registerMockReviewRoundDAO();

        $expectedStageNode = $this->doc->createElementNS($deployment->getNamespace(), 'stage');
        $reviewRoundNode = $this->doc->createElementNS($deployment->getNamespace(), 'review_round');
        $reviewRoundNode->setAttribute('round', 1);
        $reviewRoundNode->setAttribute('status', REVIEW_ROUND_STATUS_REVIEWS_COMPLETED);
        $expectedStageNode->appendChild($reviewRoundNode);

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

        $this->registerMockUserGroupDAO('External Reviewer');
        $this->registerMockUserDAO('reviewer@email.com');
        $this->registerMockReviewAssignmentDAO();

        $expectedRoundNode = $this->doc->createElementNS($deployment->getNamespace(), 'review_round');
        $assignmentNode = $this->doc->createElementNS($deployment->getNamespace(), 'review_assignment');
        $assignmentNode->setAttribute('reviewer_email', 'reviewer@email.com');
        $assignmentNode->setAttribute('recommendation', SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT);
        $assignmentNode->setAttribute('quality', SUBMISSION_REVIEWER_RATING_VERY_GOOD);
        $assignmentNode->setAttribute('method', SUBMISSION_REVIEW_METHOD_OPEN);
        $assignmentNode->setAttribute('unconsidered', REVIEW_ASSIGNMENT_NOT_UNCONSIDERED);
        $assignmentNode->setAttribute('competing_interests', 'There is no competing interest');
        $assignmentNode->setAttribute('declined', 0);
        $assignmentNode->setAttribute('cancelled', 0);
        $assignmentNode->setAttribute('was_automatic', 0);
        $assignmentNode->setAttribute('date_rated', '2023-10-31 21:52:08');
        $assignmentNode->setAttribute('date_reminded', '2023-10-30 21:52:08');
        $assignmentNode->setAttribute('date_assigned', '2023-10-29 21:52:08');
        $assignmentNode->setAttribute('date_notified', '2023-10-28 21:52:08');
        $assignmentNode->setAttribute('date_confirmed', '2023-10-27 21:52:08');
        $assignmentNode->setAttribute('date_completed', '2023-10-26 21:52:08');
        $assignmentNode->setAttribute('date_acknowledged', '2023-10-25 21:52:08');
        $assignmentNode->setAttribute('date_due', '2023-10-24 21:52:08');
        $assignmentNode->setAttribute('date_response_due', '2023-10-23 21:52:08');
        $assignmentNode->setAttribute('last_modified', '2023-10-22 21:52:08');
        $expectedRoundNode->appendChild($assignmentNode);

        $reviewRound = new ReviewRound();
        $roundNode = $this->doc->createElementNS($deployment->getNamespace(), 'review_round');
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
        $responseNode->setAttribute('type', 'string');
        $expectedAssignmentNode->appendChild($responseNode = $this->doc->createElementNS(
            $deployment->getNamespace(),
            'response',
            intval(2)
        ));
        $responseNode->setAttribute('form_element_id', 15);
        $responseNode->setAttribute('type', 'int');
        $expectedAssignmentNode->appendChild($responseNode = $this->doc->createElementNS(
            $deployment->getNamespace(),
            'response',
            join(':', [1,3,6])
        ));
        $responseNode->setAttribute('form_element_id', 16);
        $responseNode->setAttribute('type', 'object');

        $reviewAssignment = new ReviewAssignment();
        $assignmentNode = $this->doc->createElementNS($deployment->getNamespace(), 'review_assignment');
        $articleExportFilter->addReviewFormResponses($this->doc, $assignmentNode, $reviewAssignment);

        $this->assertXmlStringEqualsXmlString(
            $this->doc->saveXML($expectedAssignmentNode),
            $this->doc->saveXML($assignmentNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testParseArticleToXML()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $context = new Journal();
        $context->setPrimaryLocale('en_US');
        $deployment->setContext($context);

        $author = new Author();
        $author->setId(679);
        $author->setSequence(1);
        $author->setGivenName('Author', 'en_US');
        $author->setEmail('test@mail.com');

        $publication = new Publication();
        $publication->setId(1023);
        $publication->setData('authors', [$author]);
        $publication->setData('status', STATUS_QUEUED);
        $publication->setData('sectionId', 531);
        $publication->setData('title', 'Test article', 'en_US');

        $submission = new Submission();
        $submission->setId(901);
        $submission->setSubmissionProgress(0);
        $submission->setData('currentPublicationId', 1023);
        $submission->setData('stageId', WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
        $submission->setData('status', STATUS_PUBLISHED);
        $submission->setData('locale', 'en_US');
        $submission->setDateSubmitted('2020-01-01');
        $submission->setData('publications', [$publication]);
        $submissions = [$submission];

        $this->registerApplicationMock();
        $this->registerMockReviewRoundDAO();
        $this->registerMockReviewAssignmentDAO(true);
        $this->registerMockReviewFormResponseDAO();
        $this->registerMockSectionDAO();

        $mockUserDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById'])
            ->getMock();
        $editorUser = $mockUserDAO->newDataObject();
        $editorUser->setEmail('editor@email.com');
        $reviewerUser = $mockUserDAO->newDataObject();
        $reviewerUser->setEmail('reviewer@email.com');
        $mockUserDAO->expects($this->any())
            ->method('getById')
            ->will($this->onConsecutiveCalls($editorUser, $editorUser, $editorUser, $editorUser, $reviewerUser, $reviewerUser, $editorUser, $reviewerUser));
        DAORegistry::registerDAO('UserDAO', $mockUserDAO);

        $mockUserGroupDAO = $this->getMockBuilder(UserGroupDAO::class)
            ->setMethods(['getById', 'userAssignmentExists'])
            ->getMock();
        $authorUserGroup = $mockUserGroupDAO->newDataObject();
        $authorUserGroup->setName('Author', 'en_US');
        $editorUserGroup = $mockUserGroupDAO->newDataObject();
        $editorUserGroup->setName('Editor', 'en_US');
        $reviewerUserGroup = $mockUserGroupDAO->newDataObject();
        $reviewerUserGroup->setName('External Reviewer', 'en_US');
        $mockUserGroupDAO->expects($this->any())
            ->method('getById')
            ->will($this->onConsecutiveCalls($authorUserGroup, $editorUserGroup, $reviewerUserGroup));
        $mockUserGroupDAO->expects($this->any())
            ->method('userAssignmentExists')
            ->will($this->returnValue(true));
        DAORegistry::registerDAO('UserGroupDAO', $mockUserGroupDAO);

        $mockStageAssignmentDAO = $this->getMockBuilder(StageAssignmentDAO::class)
            ->setMethods(['getBySubmissionAndStageId'])
            ->getMock();
        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['next'])
            ->disableOriginalConstructor()
            ->getMock();
        $editorStageAssignment = new StageAssignment();
        $editorStageAssignment->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
        $editorStageAssignment->getRecommendOnly(0);
        $editorStageAssignment->getCanChangeMetadata(0);
        $reviewerStageAssignment = new StageAssignment();
        $reviewerStageAssignment->setStageId(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
        $reviewerStageAssignment->getRecommendOnly(0);
        $reviewerStageAssignment->getCanChangeMetadata(0);
        $mockResult->expects($this->any())
            ->method('next')
            ->will($this->onConsecutiveCalls($editorStageAssignment, null, null, $reviewerStageAssignment));
        $mockStageAssignmentDAO->expects($this->any())
            ->method('getBySubmissionAndStageId')
            ->will($this->returnValue($mockResult));
        DAORegistry::registerDAO('StageAssignmentDAO', $mockStageAssignmentDAO);

        $mockEditorDecisionDAO = $this->getMockBuilder(EditDecisionDAO::class)
            ->setMethods(['getEditorDecisions'])
            ->getMock();
        $submissionStageEditorDecision = [
            'editDecisionId' => 123,
            'reviewRoundId' => 0,
            'round' => 0,
            'editorId' => 784,
            'decision' => SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW,
            'dateDecided' => '2015-03-04 13:39:11'
        ];
        $reviewStageEditorDecision = [
            'editDecisionId' => 123,
            'reviewRoundId' => 76,
            'round' => 1,
            'editorId' => 784,
            'decision' => SUBMISSION_EDITOR_DECISION_ACCEPT,
            'dateDecided' => '2015-03-10 12:00:00'
        ];
        $mockEditorDecisionDAO->expects($this->any())
            ->method('getEditorDecisions')
            ->will($this->onConsecutiveCalls([$submissionStageEditorDecision], [], [$reviewStageEditorDecision]));
        DAORegistry::registerDAO('EditDecisionDAO', $mockEditorDecisionDAO);

        $query = new Query();
        $query->setSequence(1);
        $query->setIsClosed(false);
        $this->registerMockQueryDAO($query);

        $note = new Note();
        $note->setUserId(123);
        $note->setDateCreated('2015-03-03 20:33:43');
        $note->setDateModified('2015-03-03 20:37:37');
        $note->setTitle('Recommendation');
        $note->setContents('<p>The recommendation regarding this submission is: Accept Submission</p>');
        $this->registerMockNoteDAO($note);

        $doc = $articleExportFilter->execute($submissions);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('article.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
