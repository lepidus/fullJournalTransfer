<?php

import('classes.submission.Submission');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.ExtendedArticleNativeXmlFilter');

class ExtendedArticleNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'extended-article=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return ExtendedArticleNativeXmlFilter::class;
    }

    public function testCreateStagesNode()
    {
        $articleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $articleExportFilter->getDeployment();

        $submission = new Submission();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedArticleNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $expectedArticleNode->appendChild($stageNode = $doc->createElementNS($deployment->getNamespace(), 'stage'));
        $stageNode->setAttribute('path', WORKFLOW_STAGE_PATH_SUBMISSION);
        $expectedArticleNode->appendChild($stageNode = $doc->createElementNS($deployment->getNamespace(), 'stage'));
        $stageNode->setAttribute('path', WORKFLOW_STAGE_PATH_INTERNAL_REVIEW);
        $expectedArticleNode->appendChild($stageNode = $doc->createElementNS($deployment->getNamespace(), 'stage'));
        $stageNode->setAttribute('path', WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW);
        $expectedArticleNode->appendChild($stageNode = $doc->createElementNS($deployment->getNamespace(), 'stage'));
        $stageNode->setAttribute('path', WORKFLOW_STAGE_PATH_EDITING);
        $expectedArticleNode->appendChild($stageNode = $doc->createElementNS($deployment->getNamespace(), 'stage'));
        $stageNode->setAttribute('path', WORKFLOW_STAGE_PATH_PRODUCTION);

        $articleNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $articleExportFilter->createStageNodes($doc, $articleNode, $submission);
        $this->assertEquals($expectedArticleNode, $articleNode);
    }
}
