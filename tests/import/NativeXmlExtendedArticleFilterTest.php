<?php

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
        return ['review_form_responses'];
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

        for ($i = 0 ; $i < $responseNodeList->length ; $i++) {
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
}
