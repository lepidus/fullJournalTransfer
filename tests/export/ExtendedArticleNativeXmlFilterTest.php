<?php

import('classes.submission.Submission');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.ExtendedArticleNativeXmlFilter');

class ExtendedArticleNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMockReviewRound();
        $this->registerMockReviewAssignment();
    }

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
        return ['ReviewRoundDAO', 'ReviewAssignmentDAO'];
    }

    private function registerMockReviewRound()
    {
        $mockDAO = $this->getMockBuilder(ReviewRoundDAO::class)
            ->setMethods(['getBySubmissionId'])
            ->getMock();

        $reviewRound = $mockDAO->newDataObject();
        $reviewRound->setId(13);
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

    private function registerMockReviewAssignment()
    {
        $mockDAO = $this->getMockBuilder(ReviewAssignmentDAO::class)
            ->setMethods(['getBySubmissionId'])
            ->getMock();

        $reviewAssignment = $mockDAO->newDataObject();
        $reviewAssignment->setId(26);
        $reviewAssignment->setReviewerId(7);
        $reviewAssignment->setReviewFormId(2);
        $reviewAssignment->setSubmissionId(13);
        $reviewAssignment->setReviewRoundId(6);
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
            ->method('getBySubmissionId')
            ->will($this->returnValue(array($reviewAssignment)));

        DAORegistry::registerDAO('ReviewAssignmentDAO', $mockDAO);
    }

    public function testAddReviewRounds()
    {
        $fullJournalArticleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $fullJournalArticleExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'article');

        $expectedSubmissionNode->appendChild($reviewRoundsNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'review_rounds'
        ));
        $reviewRoundsNode->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $reviewRoundsNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );
        $reviewRoundsNode->appendChild($reviewRoundNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'review_round'
        ));
        $reviewRoundNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', 13));
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

        $actualSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'article');
        $submission = new Submission();
        $submission->setId(143);

        $fullJournalArticleExportFilter->addReviewRounds($doc, $actualSubmissionNode, $submission);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedSubmissionNode),
            $doc->saveXML($actualSubmissionNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testAddReviewAssignments()
    {
        $fullJournalArticleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $fullJournalArticleExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'article');

        $reviewAssignmentFilterTest = new ReviewAssignmentNativeXmlFilterTest();
        $reviewAssignmentsNode = $doc->createElementNS($deployment->getNamespace(), 'review_assignments');
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
        $reviewAssignmentFilterTest->createReviewAssignmentAttributes($reviewAssignmentNode);
        $reviewAssignmentFilterTest->createReviewAssignmentDateNodes($doc, $deployment, $reviewAssignmentNode);
        $reviewAssignmentFilterTest->createReviewAssignmentBooleanNodes($doc, $deployment, $reviewAssignmentNode);
        $expectedSubmissionNode->appendChild($reviewAssignmentsNode);

        $actualSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'article');
        $submission = new Submission();
        $submission->setId(143);

        $fullJournalArticleExportFilter->addReviewAssignments($doc, $actualSubmissionNode, $submission);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedSubmissionNode),
            $doc->saveXML($actualSubmissionNode),
            "actual xml is equal to expected xml"
        );
    }
}
