<?php

import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.ReviewAssignmentNativeXmlFilter');

class ReviewAssignmentNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    private $reviewAssignment;

    protected function getSymbolicFilterGroup()
    {
        return 'review-assignment=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return ReviewAssignmentNativeXmlFilter::class;
    }

    public function createReviewAssignment()
    {
        $reviewAssignment = new ReviewAssignment();
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

        return $reviewAssignment;
    }

    public function createReviewAssignmentAttributes($node)
    {
        $node->setAttribute('id', 26);
        $node->setAttribute('submission_id', 13);
        $node->setAttribute('review_form_id', 2);
        $node->setAttribute('review_round_id', 6);
        $node->setAttribute('stage_id', 3);
        $node->setAttribute('quality', 5);
        $node->setAttribute('recommendation', 2);
        $node->setAttribute('round', 1);
        $node->setAttribute('review_method', 2);
        $node->setAttribute('reviewer', 'jjanssen');
        $node->setAttribute('competing_interests', 'test interest');
    }

    public function createReviewAssignmentDateNodes($doc, $deployment, $parentNode)
    {
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_rated',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-10-31 21:52:08.000'))
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_reminded',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-10-30 21:52:08.000'))
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_assigned',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-10-29 21:52:08.000'))
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_notified',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-10-28 21:52:08.000'))
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_confirmed',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-10-27 21:52:08.000'))
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_completed',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-10-26 21:52:08.000'))
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_acknowledged',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-10-25 21:52:08.000'))
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_due',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-10-24 21:52:08.000'))
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_response_due',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-10-23 21:52:08.000'))
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'last_modified',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2023-10-22 21:52:08.000'))
        ));
    }

    public function createReviewAssignmentBooleanNodes($doc, $deployment, $parentNode)
    {
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'declined',
            'false'
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'cancelled',
            'false'
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'reminder_was_automatic',
            'false'
        ));
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'unconsidered',
            'false'
        ));
    }

    public function testAddDates()
    {
        $reviewAssignmentExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewAssignmentExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedReviewAssignmentNode = $doc->createElementNS($deployment->getNamespace(), 'review_assignment');
        $this->createReviewAssignmentDateNodes($doc, $deployment, $expectedReviewAssignmentNode);

        $reviewAssignmentNode = $doc->createElementNS($deployment->getNamespace(), 'review_assignment');
        $reviewAssignment = $this->createReviewAssignment();
        $reviewAssignmentExportFilter->addDates($doc, $reviewAssignmentNode, $reviewAssignment);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedReviewAssignmentNode),
            $doc->saveXML($reviewAssignmentNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateReviewAssignmentNode()
    {
        $reviewAssignmentExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewAssignmentExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedReviewAssignmentNode = $doc->createElementNS($deployment->getNamespace(), 'review_assignment');
        $this->createReviewAssignmentAttributes($expectedReviewAssignmentNode);
        $this->createReviewAssignmentDateNodes($doc, $deployment, $expectedReviewAssignmentNode);
        $this->createReviewAssignmentBooleanNodes($doc, $deployment, $expectedReviewAssignmentNode);

        $reviewAssignment = $this->createReviewAssignment();

        $reviewAssignmentNode = $reviewAssignmentExportFilter->createReviewAssignmentNode($doc, $reviewAssignment);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedReviewAssignmentNode),
            $doc->saveXML($reviewAssignmentNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteReviewAssignmentXml()
    {
        $reviewAssignmentExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewAssignmentExportFilter->getDeployment();

        $reviewAssignment = $this->createReviewAssignment();
        $reviewAssignments = [$reviewAssignment];

        $doc = $reviewAssignmentExportFilter->execute($reviewAssignments);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('reviewAssignment.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
