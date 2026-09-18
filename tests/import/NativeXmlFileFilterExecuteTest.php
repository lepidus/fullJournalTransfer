<?php

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlExtendedArticleFilter');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlWorkflowFileFilter');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportDeployment');
import('lib.pkp.classes.filter.FilterDAO');
import('lib.pkp.classes.submission.SubmissionFile');
import('classes.journal.Journal');
import('classes.submission.Submission');

class NativeXmlFileFilterExecuteTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider fileImports
     */
    public function testExecuteFileImport($workflow, $skipped)
    {
        $group = new FilterGroup();
        $schema = $workflow
            ? 'plugins/importexport/fullJournalTransfer/fullJournal.xsd'
            : 'plugins/importexport/native/native.xsd';
        $group->setInputType('xml::schema(' . $schema . ')');
        // Reproduce existing installations, where both file groups declare a single object.
        $group->setOutputType('class::lib.pkp.classes.submission.SubmissionFile');
        $fileFilter = $this->getMockBuilder(
            $workflow ? NativeXmlWorkflowFileFilter::class : NativeXmlArticleFileFilter::class
        )->setConstructorArgs([$group])->onlyMethods(['handleElement'])->getMock();

        $element = $workflow ? 'workflow_file' : 'submission_file';
        $document = new DOMDocument();
        $document->loadXML('<' . $element . ' xmlns="http://pkp.sfu.ca" id="1" file_id="1" stage="submission">'
            . '<name locale="en_US">file.txt</name>'
            . '<file id="1" filesize="1" extension="txt">'
            . '<embed encoding="base64" mime_type="text/plain">eA==</embed></file>'
            . '</' . $element . '>');
        $this->assertTrue($document->schemaValidate($schema));

        $submissionFile = $skipped ? null : new SubmissionFile();
        $fileFilter->expects($this->once())->method('handleElement')->willReturn($submissionFile);
        $deployment = new FullJournalImportExportDeployment(new Journal());

        if ($workflow) {
            $fileFilter->setDeployment($deployment);
            $result = $fileFilter->execute($document);
        } else {
            $filterDAO = $this->getMockBuilder(FilterDAO::class)
                ->disableOriginalConstructor()->onlyMethods(['getObjectsByGroup'])->getMock();
            $filterDAO->expects($this->once())->method('getObjectsByGroup')
                ->with('native-xml=>SubmissionFile')->willReturn([$fileFilter]);
            $daos = & DAORegistry::getDAOs();
            $originalDAOs = $daos;
            DAORegistry::registerDAO('FilterDAO', $filterDAO);
            try {
                $articleGroup = new FilterGroup();
                $articleGroup->setInputType($group->getInputType());
                $articleGroup->setOutputType('class::classes.submission.Submission[]');
                $articleFilter = new NativeXmlExtendedArticleFilter($articleGroup);
                $articleFilter->setDeployment($deployment);
                $result = $articleFilter->parseSubmissionFile($document->documentElement, new Submission());
            } finally {
                $daos = $originalDAOs;
            }
        }

        $this->assertSame($skipped ? [] : [$submissionFile], $result);
    }

    public function fileImports()
    {
        return [
            'submission file' => [false, false],
            'skipped submission file' => [false, true],
            'workflow file' => [true, false],
            'skipped workflow file' => [true, true],
        ];
    }
}
