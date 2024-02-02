<?php

import('lib.pkp.classes.submission.reviewRound.ReviewRound');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.ReviewRoundNativeXmlFilter');

class ReviewRoundNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMockReviewAssignment();
    }

    protected function getSymbolicFilterGroup()
    {
        return 'review-round=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return ReviewRoundNativeXmlFilter::class;
    }

    protected function getMockedDAOs()
    {
        return ['ReviewAssignmentDAO'];
    }

    private function registerMockReviewAssignment()
    {
        $mockDAO = $this->getMockBuilder(ReviewAssignmentDAO::class)
            ->setMethods(['getByReviewRoundId'])
            ->getMock();

        $reviewAssignment = $mockDAO->newDataObject();
        $reviewAssignment->setId(26);
        $reviewAssignment->setReviewerId(7);
        $reviewAssignment->setReviewFormId(2);
        $reviewAssignment->setSubmissionId(16);
        $reviewAssignment->setReviewRoundId(563);
        $reviewAssignment->setStageId(3);
        $reviewAssignment->setRecommendation(2);
        $reviewAssignment->setQuality(5);
        $reviewAssignment->setRound(1);
        $reviewAssignment->setReviewMethod(2);
        $reviewAssignment->setCompetingInterests('test interest');

        $reviewAssignment->setDeclined(false);
        $reviewAssignment->setCancelled(false);
        $reviewAssignment->setReminderWasAutomatic(false);
        $reviewAssignment->setUnconsidered(false);

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

    public function createReviewRoundNode($doc, $deployment)
    {
        $reviewRoundNode = $doc->createElementNS($deployment->getNamespace(), 'review_round');
        $reviewRoundNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', 563));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');
        $reviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'submission_id',
            16
        ));
        $reviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'stage',
            htmlspecialchars('externalReview', ENT_COMPAT, 'UTF-8')
        ));
        $reviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'round',
            1
        ));
        $reviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'status',
            1
        ));

        return $reviewRoundNode;
    }

    private function createReviewAssignmentsNode($doc, $parentNode, $deployment)
    {
        $parentNode->appendChild($reviewAssignmentsNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'review_assignments'
        ));
        $reviewAssignmentsNode->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $reviewAssignmentsNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );

        $reviewAssignmentsNode->appendChild($reviewAssignmentNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'review_assignment'
        ));
        $reviewAssignmentExportFilterTest = new ReviewAssignmentNativeXmlFilterTest();
        $reviewAssignmentExportFilterTest->createReviewAssignmentAttributes($reviewAssignmentNode);
        $reviewAssignmentExportFilterTest->createReviewAssignmentDateNodes($doc, $deployment, $reviewAssignmentNode);
        $reviewAssignmentExportFilterTest->createReviewAssignmentBooleanNodes($doc, $deployment, $reviewAssignmentNode);
    }

    public function testAddReviewAssignments()
    {
        $reviewRoundExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewRoundExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $reviewRound = new ReviewRound();
        $reviewRound->setId(563);
        $reviewRound->setSubmissionId(16);
        $reviewRound->setStageId(3);
        $reviewRound->setRound(1);
        $reviewRound->setStatus(1);

        $expectedReviewRoundNode = $doc->createElementNS($deployment->getNamespace(), 'review_round');
        $this->createReviewAssignmentsNode($doc, $expectedReviewRoundNode, $deployment);

        $actualReviewRoundNode = $doc->createElementNS($deployment->getNamespace(), 'review_round');
        $reviewRoundExportFilter->addReviewAssignments($doc, $actualReviewRoundNode, $reviewRound);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedReviewRoundNode),
            $doc->saveXML($actualReviewRoundNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateReviewRoundNode()
    {
        $reviewRoundExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewRoundExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedReviewRoundNode = $this->createReviewRoundNode($doc, $deployment);
        $this->createReviewAssignmentsNode($doc, $expectedReviewRoundNode, $deployment);

        $reviewRound = new ReviewRound();
        $reviewRound->setId(563);
        $reviewRound->setSubmissionId(16);
        $reviewRound->setStageId(3);
        $reviewRound->setRound(1);
        $reviewRound->setStatus(1);

        $reviewRoundNode = $reviewRoundExportFilter->createReviewRoundNode($doc, $reviewRound);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedReviewRoundNode),
            $doc->saveXML($reviewRoundNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteReviewRoundXml()
    {
        $reviewRoundExportFilter = $this->getNativeImportExportFilter();

        $reviewRound = new ReviewRound();
        $reviewRound->setId(563);
        $reviewRound->setSubmissionId(16);
        $reviewRound->setStageId(3);
        $reviewRound->setRound(1);
        $reviewRound->setStatus(1);
        $reviewRounds = [$reviewRound];

        $doc = $reviewRoundExportFilter->execute($reviewRounds);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('reviewRound.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
