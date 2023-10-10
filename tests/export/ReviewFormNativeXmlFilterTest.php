<?php

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

        import('lib.pkp.classes.reviewForm.ReviewForm');
        $reviewForm = new ReviewForm();
        $reviewForm->_data = [
            'assocId' => 12,
            'assocType' => ASSOC_TYPE_JOURNAL,
            'sequence' => 1,
            'active' => 1,
            'description' => ['en_US' => '<p>A review form for test purpose</p>'],
            'title' => ['en_US' => 'Test Review Form']
        ];

        $actualReviewFormNode = $reviewFormExportFilter->createReviewFormNode($doc, $reviewForm);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedReviewFormNode),
            $doc->saveXML($actualReviewFormNode),
            "actual xml is equal to expected xml"
        );
    }
}
