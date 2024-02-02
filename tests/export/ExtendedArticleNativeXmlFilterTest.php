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
        return ['ReviewRoundDAO'];
    }

    private function registerMockReviewRound()
    {
        $mockDAO = $this->getMockBuilder(ReviewRoundDAO::class)
            ->setMethods(['getBySubmissionId'])
            ->getMock();

        $reviewRound = $mockDAO->newDataObject();
        $reviewRound->setId(563);
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

    public function testAddReviewRounds()
    {
        $fullJournalArticleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $fullJournalArticleExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');

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
        $reviewRoundNode->appendChild($reviewAssignmentsNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'review_assignments',
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

        $actualSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $submission = new Submission();
        $submission->setId(143);

        $fullJournalArticleExportFilter->addReviewRounds($doc, $actualSubmissionNode, $submission);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedSubmissionNode),
            $doc->saveXML($actualSubmissionNode),
            "actual xml is equal to expected xml"
        );
    }
}
