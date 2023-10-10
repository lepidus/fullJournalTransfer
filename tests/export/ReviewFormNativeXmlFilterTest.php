<?php

import('lib.pkp.classes.reviewForm.ReviewForm');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.ReviewFormNativeXmlFilter');

class ReviewFormNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'review-form=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return ReviewFormNativeXmlFilter::class;
    }

    public function testCreateReviewFormNode()
    {
        $reviewFormExportFilter = $this->getNativeImportExportFilter();
        $deployment = $reviewFormExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedReviewFormNode = $doc->createElementNS($deployment->getNamespace(), 'review_form');
        $expectedReviewFormNode->setAttribute('seq', 1);
        $expectedReviewFormNode->setAttribute('is_active', 1);
        $reviewFormExportFilter->createLocalizedNodes(
            $doc,
            $expectedReviewFormNode,
            'title',
            ['en_US' => 'Test Review Form']
        );
        $reviewFormExportFilter->createLocalizedNodes(
            $doc,
            $expectedReviewFormNode,
            'description',
            ['en_US' => '<p>A review form for test purpose</p>']
        );

        $reviewForm = new ReviewForm();
        $reviewForm->_data = [
            'assocId' => 12,
            'assocType' => ASSOC_TYPE_JOURNAL,
            'sequence' => 1,
            'active' => 1,
            'title' => ['en_US' => 'Test Review Form'],
            'description' => ['en_US' => '<p>A review form for test purpose</p>']
        ];

        $actualReviewFormNode = $reviewFormExportFilter->createReviewFormNode($doc, $reviewForm);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedReviewFormNode),
            $doc->saveXML($actualReviewFormNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteReviewFormXml()
    {
        $reviewFormExportFilter = $this->getNativeImportExportFilter();

        $reviewForm = new ReviewForm();
        $reviewForm->_data = [
            'assocId' => 12,
            'assocType' => ASSOC_TYPE_JOURNAL,
            'sequence' => 1,
            'active' => 1,
            'title' => ['en_US' => 'Test Review Form'],
            'description' => ['en_US' => '<p>A review form for test purpose</p>']
        ];
        $reviewForms = [$reviewForm];

        $doc = $reviewFormExportFilter->process($reviewForms);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('reviewForm.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
