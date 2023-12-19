<?php

import('lib.pkp.classes.submission.reviewRound.ReviewRound');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.ReviewRoundNativeXmlFilter');

class ReviewRoundNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'review-round=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return ReviewRoundNativeXmlFilter::class;
    }

    public function testCreateReviewRoundNode()
    {
        $reviewRoundExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewRoundExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedReviewRoundNode = $doc->createElementNS($deployment->getNamespace(), 'review_round');
        $expectedReviewRoundNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', 13));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');
        $expectedReviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'submission_id',
            16
        ));
        $expectedReviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'stage',
            htmlspecialchars('externalReview', ENT_COMPAT, 'UTF-8')
        ));
        $expectedReviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'round',
            1
        ));
        $expectedReviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'status',
            1
        ));

        $reviewRound = new ReviewRound();
        $reviewRound->setId(13);
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
        $reviewRound->setId(13);
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