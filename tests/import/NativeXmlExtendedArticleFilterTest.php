<?php

import('classes.submission.Submission');
import('lib.pkp.classes.query.Query');
import('lib.pkp.classes.submission.reviewRound.ReviewRound');
import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment');
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
        return [
            'review_form_responses', 'review_assignments',
            'edit_decisions', 'review_rounds',
            'queries', 'query_participants', 'notes',
            'stage_assignments', 'user_group_stage'
        ];
    }

    protected function getMockedDAOs()
    {
        return ['UserDAO', 'UserGroupDAO', 'ReviewAssignmentDAO'];
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

        $deployment->setReviewFormElementDBId(14, 41);
        $deployment->setReviewFormElementDBId(15, 42);
        $deployment->setReviewFormElementDBId(16, 43);

        $reviewRound = new ReviewRound();
        $reviewRound->setId(23);
        $reviewRound->setRound(1);
        $reviewRound->setSubmissionId(75);
        $reviewRound->setStageId(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);

        $mockUserDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById', 'getUserByEmail'])
            ->getMock();

        $reviewer = $mockUserDAO->newDataObject();
        $reviewer->setId(52);
        $reviewer->setUsername('reviewer');
        $reviewer->setGivenName('reviewer', 'en_US');

        $mockUserDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($reviewer));

        $mockUserDAO->expects($this->any())
            ->method('getUserByEmail')
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

        $submission = new Submission();
        $submission->setId(78);

        $stageId = WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;

        $reviewRound = new ReviewRound();
        $reviewRound->setSubmissionId($submission->getId());
        $reviewRound->setStageId($stageId);
        $reviewRound->setRound(1);

        $mockUserDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById', 'getUserByEmail'])
            ->getMock();

        $editor = $mockUserDAO->newDataObject();
        $editor->setId(89);
        $editor->setUsername('editor');

        $mockUserDAO->expects($this->any())
            ->method('getUserByEmail')
            ->will($this->returnValue($editor));

        DAORegistry::registerDAO('UserDAO', $mockUserDAO);

        $roundNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'review_round');
        $decisionNodeList = $roundNodeList->item(0)->getElementsByTagNameNS($deployment->getNamespace(), 'decision');
        $articleImportFilter->parseDecision(
            $decisionNodeList->item(0),
            $submission,
            $stageId,
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

    public function testParseQueries()
    {
        $articleImportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleImportFilter->getDeployment();
        $doc = $this->getSampleXml('article.xml');

        $submission = new Submission();
        $submission->setId(128);

        $stageId = WORKFLOW_STAGE_ID_SUBMISSION;

        $mockUserDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById', 'getUserByEmail'])
            ->getMock();

        $editor = $mockUserDAO->newDataObject();
        $editor->setId(89);
        $editor->setUsername('editor');

        $mockUserDAO->expects($this->any())
            ->method('getUserByEmail')
            ->will($this->returnValue($editor));

        DAORegistry::registerDAO('UserDAO', $mockUserDAO);

        $stageNode = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'stage')->item(0);
        $queryNode = $stageNode->getElementsByTagNameNS($deployment->getNamespace(), 'query')->item(0);
        $parsedQueryId = $articleImportFilter->parseQuery($queryNode, $submission, $stageId);

        $queryDAO = DAORegistry::getDAO('QueryDAO');
        $resultQueries = $queryDAO->getByAssoc(ASSOC_TYPE_SUBMISSION, $submission->getId(), $stageId);

        $expectedQuery = new Query();
        $expectedQuery->setId($parsedQueryId);
        $expectedQuery->setAssocType(ASSOC_TYPE_SUBMISSION);
        $expectedQuery->setAssocId($submission->getId());
        $expectedQuery->setStageId($stageId);
        $expectedQuery->setSequence(1.0);
        $expectedQuery->setIsClosed(0);

        $expectedNote = new Note();
        $expectedNote->setUserId($editor->getId());
        $expectedNote->setDateCreated('2015-03-03 20:33:43');
        $expectedNote->setDateModified(Core::getCurrentDate());
        $expectedNote->setTitle('Recommendation');
        $expectedNote->setContents('<p>The recommendation regarding this submission is: Accept Submission</p>');
        $expectedNote->setAssocType(ASSOC_TYPE_QUERY);
        $expectedNote->setAssocId($parsedQueryId);

        $retrievedQuery = $resultQueries->toArray()[0];
        $this->assertEquals($expectedQuery, $retrievedQuery);

        $participantIds = $queryDAO->getParticipantIds($retrievedQuery->getId());
        $this->assertEquals([$editor->getId()], $participantIds);

        $replies = $retrievedQuery->getReplies();
        $retrievedNote = $replies->toArray()[0];
        $expectedNote->setId($retrievedNote->getId());
        $this->assertEquals($expectedNote, $retrievedNote);

        $submissionFileDAO = DAORegistry::getDAO('SubmissionFileDAO');
        $expectedSubmissionFile = $submissionFileDAO->newDataObject();
        $expectedSubmissionFile->setAllData([
            'submissionId' => $submission->getId(),
            'assocId' => $retrievedNote->getId(),
            'assocType' => ASSOC_TYPE_NOTE,
            'fileStage' => SUBMISSION_FILE_QUERY,
            'createdAt' => '2023-11-18',
            'updatedAt' => '2023-11-26',
            'fileId' => 1,
            'genreId' => 1,
            'viewable' => true,
            'uploaderUserId' => 123,
            'name' => [
                'en_US' => 'dummy.pdf'
            ]
        ]);
        $noteFiles = Services::get('submissionFile')->getMany([
            'assocTypes' => [ASSOC_TYPE_NOTE],
            'assocIds' => [$retrievedNote->getId()],
            'submissionIds' => [$submission->getId()],
            'fileStages' => [SUBMISSION_FILE_QUERY]
        ]);
        $retrievedSubmissionFile = $noteFiles->current();
        $expectedSubmissionFile->setId($retrievedSubmissionFile->getId());

        $revisions = $submissionFileDao->getRevisions($retrievedSubmissionFile->getId());
        $submissionFileDAO->deleteById($retrievedSubmissionFile->getId());
        foreach ($revisions as $revision) {
            Services::get('file')->delete($revision->fileId);
        }

        $this->assertEquals($expectedSubmissionFile, $retrievedSubmissionFile);
    }

    public function testParseReviewRound()
    {
        $articleImportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleImportFilter->getDeployment();
        $doc = $this->getSampleXml('article.xml');

        $deployment->setReviewFormElementDBId(14, 41);
        $deployment->setReviewFormElementDBId(15, 42);
        $deployment->setReviewFormElementDBId(16, 43);

        $submission = new Submission();
        $submission->setId(32);
        $stageId = WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;

        $mockUserDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById', 'getUserByEmail'])
            ->getMock();
        $reviewer = $mockUserDAO->newDataObject();
        $reviewer->setId(52);
        $reviewer->setUsername('reviewer');
        $reviewer->setGivenName('reviewer', 'en_US');
        $mockUserDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($reviewer));
        $mockUserDAO->expects($this->any())
            ->method('getUserByEmail')
            ->will($this->returnValue($reviewer));
        DAORegistry::registerDAO('UserDAO', $mockUserDAO);

        $mockReviewAssignmentDAO = $this->getMockBuilder(ReviewAssignmentDAO::class)
            ->setMethods(['newDataObject'])
            ->getMock();
        $mockReviewAssignmentDAO->expects($this->any())
            ->method('newDataObject')
            ->will($this->returnValue(new ReviewAssignment()));
        DAORegistry::registerDAO('ReviewAssignmentDAO', $mockReviewAssignmentDAO);

        $roundNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'review_round');
        $articleImportFilter->parseReviewRound($roundNodeList->item(0), $submission, $stageId);

        $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRounds = $reviewRoundDAO->getBySubmissionId($submission->getId(), $stageId)->toArray();
        $reviewRound = array_shift($reviewRounds);
        unset($reviewRound->_data['id']);

        $expectedReviewRound = new ReviewRound();
        $expectedReviewRound->setSubmissionId($submission->getId());
        $expectedReviewRound->setStageId($stageId);
        $expectedReviewRound->setRound(1);
        $expectedReviewRound->setStatus(REVIEW_ROUND_STATUS_REVIEWS_COMPLETED);

        $this->assertEquals($expectedReviewRound, $reviewRound);
    }

    public function testParseStageAssignment()
    {
        $articleImportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleImportFilter->getDeployment();
        $doc = $this->getSampleXml('article.xml');

        $contextId = 38;
        $submission = new Submission();
        $submission->setId(32);
        $submission->setContextId($contextId);
        $stageId = WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;

        $mockUserGroupDAO = $this->getMockBuilder(UserGroupDAO::class)
            ->setMethods(['getByContextId'])
            ->getMock();
        $userGroup = $mockUserGroupDAO->newDataObject();
        $userGroup->setId(46);
        $userGroup->setName('Editor', 'en_US');
        $mockUserGroupDAO->assignGroupToStage($submission->getContextId(), $userGroup->getId(), $stageId);
        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$userGroup]));
        $mockUserGroupDAO->expects($this->any())
            ->method('getByContextId')
            ->will($this->returnValue($mockResult));
        DAORegistry::registerDAO('UserGroupDAO', $mockUserGroupDAO);

        $mockUserDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getUserByEmail'])
            ->getMock();
        $user = $mockUserDAO->newDataObject();
        $user->setId(67);
        $mockUserDAO->expects($this->any())
            ->method('getUserByEmail')
            ->will($this->returnValue($user));
        DAORegistry::registerDAO('UserDAO', $mockUserDAO);

        $participantNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'participant');
        $articleImportFilter->parseStageAssignment($participantNodeList->item(0), $submission, $stageId);

        $stageAssignmentDAO = DAORegistry::getDAO('StageAssignmentDAO');
        $stageAssignments = $stageAssignmentDAO->getBySubmissionAndStageId($submission->getId(), $stageId);
        $stageAssignment = $stageAssignments->next();
        unset($stageAssignment->_data['id']);
        unset($stageAssignment->_data['dateAssigned']);

        $expectedStageAssignment = new StageAssignment();
        $expectedStageAssignment->setSubmissionId($submission->getId());
        $expectedStageAssignment->setStageId($stageId);
        $expectedStageAssignment->setUserGroupId(46);
        $expectedStageAssignment->setUserId(67);
        $expectedStageAssignment->setRecommendOnly(false);
        $expectedStageAssignment->setCanChangeMetadata(0);

        $this->assertEquals($expectedStageAssignment, $stageAssignment);
    }
}
