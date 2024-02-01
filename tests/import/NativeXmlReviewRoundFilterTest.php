<?php

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlReviewRoundFilter');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');

class NativeXmlReviewRoundFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>review-round';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlReviewRoundFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['review_rounds', 'review_assignments'];
    }

    public function testHandleReviewRoundElement()
    {
        $reviewRoundImportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewRoundImportFilter->getDeployment();

        $submission = new Submission();
        $submission->setId(129);
        $deployment->setSubmission($submission);

        $expectedReviewRoundData = [
            'submissionId' => $submission->getId(),
            'stageId' => 3,
            'round' => 1,
            'status' => 1
        ];

        $doc = $this->getSampleXml('reviewRound.xml');

        $importedObjects = $reviewRoundImportFilter->execute($doc);
        $reviewRound = array_shift($importedObjects);
        $reviewRoundId = array_pop($reviewRound->_data);
        $this->assertEquals($expectedReviewRoundData, $reviewRound->_data);

        $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
        $insertedReviewRound = $reviewRoundDAO->getById($reviewRoundId);
        $expectedReviewRoundData['id'] = $reviewRoundId;
        $this->assertEquals($expectedReviewRoundData, $insertedReviewRound->_data);
    }

    public function testParseReviewAssignments()
    {
        $reviewRoundImportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewRoundImportFilter->getDeployment();

        $reviewerId = 7;

        $submission = new Submission();
        $submission->setId(87);
        $deployment->setSubmission($submission);

        $reviewRound = new ReviewRound();
        $reviewRound->setId(43);
        $deployment->setReviewRound($reviewRound);

        $expectedReviewAssignmentData = [
            'submissionId' => $submission->getId(),
            'reviewerId' => $reviewerId,
            'competingInterests' => 'test interest',
            'recommendation' => '2',
            'dateAssigned' => '2023-10-29 21:52:08',
            'dateNotified' => '2023-10-28 21:52:08',
            'dateConfirmed' => '2023-10-27 21:52:08',
            'dateCompleted' => '2023-10-26 21:52:08',
            'dateAcknowledged' => '2023-10-25 21:52:08',
            'dateDue' => '2023-10-24 21:52:08',
            'dateResponseDue' => '2023-10-23 21:52:08',
            'lastModified' => '2023-10-22 21:52:08',
            'declined' => 0,
            'cancelled' => 0,
            'quality' => 5,
            'dateRated' => '2023-10-31 21:52:08',
            'dateReminded' => '2023-10-30 21:52:08',
            'reminderWasAutomatic' => 0,
            'round' => 1,
            'reviewMethod' => 2,
            'stageId' => 3,
            'unconsidered' => 0,
            'reviewerFullName' => 'Julie Janssen',
            'reviewRoundId' => $reviewRound->getId(),
        ];

        $doc = $this->getSampleXml('reviewRound.xml') ;
        $reviewAssignmentsNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'review_assignments'
        );

        $reviewRoundImportFilter->parseReviewAssignments($reviewAssignmentsNodeList->item(0), $reviewRound);

        $reviewAssignmentDAO = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignment = $reviewAssignmentDAO->getReviewAssignment($reviewRound->getId(), $reviewerId);
        $expectedReviewAssignmentData['id'] = $reviewAssignment->getId();

        $this->assertEquals($expectedReviewAssignmentData, $reviewAssignment->_data);
    }
}
