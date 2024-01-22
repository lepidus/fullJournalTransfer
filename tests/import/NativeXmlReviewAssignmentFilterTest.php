<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlReviewAssignmentFilter');

class NativeXmlReviewAssignmentFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>review-assignment';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlReviewAssignmentFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['review_assignments'];
    }

    public function testHandleReviewAssignmentElement()
    {
        $reviewAssignmentImportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewAssignmentImportFilter->getDeployment();
        $deployment->setSubmissionDBId(13, 87);

        $doc = $this->getSampleXml('reviewAssignment.xml');

        $importedObjects = $reviewAssignmentImportFilter->execute($doc);
        $reviewAssignment = array_shift($importedObjects);
        $this->assertInstanceOf(ReviewAssignment::class, $reviewAssignment);

        $expectedReviewAssignmentData = [
            'submissionId' => 87,
            'reviewerId' => 7,
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
        ];

        $reviewAssignmentId = array_pop($reviewAssignment->_data);
        $this->assertEquals($expectedReviewAssignmentData, $reviewAssignment->_data);

        $reviewAssignmentDAO = DAORegistry::getDAO('ReviewAssignmentDAO');
        $insertedReviewAssignment = $reviewAssignmentDAO->getById($reviewAssignmentId);
        $expectedReviewAssignmentData = array_merge($expectedReviewAssignmentData, [
            'id' => $reviewAssignmentId,
            'reviewerFullName' => 'Julie Janssen',
            'reviewRoundId' => 0,
        ]);
        $this->assertEquals($expectedReviewAssignmentData, $insertedReviewAssignment->_data);
    }
}
