<?php

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlJournalFilter');
import('classes.journal.Journal');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportDeployment');
import('lib.pkp.classes.file.FileManager');
import('classes.core.Services');

class NativeXmlJournalFilterProcessTest extends \PHPUnit\Framework\TestCase
{
    public function testProcessSingleJournalDocument()
    {
        $filterGroup = new FilterGroup();
        $filterGroup->setInputType('xml::schema(plugins/importexport/fullJournalTransfer/fullJournal.xsd)');
        $filterGroup->setOutputType('class::classes.journal.Journal');

        // Isolate persistence while exercising the real NativeImportFilter dispatch.
        $filter = $this->getMockBuilder(NativeXmlJournalFilter::class)
            ->setConstructorArgs([$filterGroup])
            ->onlyMethods(['handleElement'])
            ->getMock();

        $document = new DOMDocument();
        $document->loadXML('<journal xmlns="http://pkp.sfu.ca"/>');
        $journal = new Journal();

        $filter->expects($this->once())
            ->method('handleElement')
            ->with($this->identicalTo($document->documentElement))
            ->willReturn($journal);

        $this->assertSame([$journal], $filter->process($document));
    }

    public function testCreateJournalDirectoriesWithMissingParents()
    {
        $filterGroup = new FilterGroup();
        $filterGroup->setInputType('xml::schema(plugins/importexport/fullJournalTransfer/fullJournal.xsd)');
        $filterGroup->setOutputType('class::classes.journal.Journal');
        $filter = new NativeXmlJournalFilter($filterGroup);
        $journal = new Journal();
        $journal->setId(42);
        $deployment = new FullJournalImportExportDeployment($journal);

        $contextService = Services::get('context');
        $tempDir = sys_get_temp_dir() . '/full-journal-dirs-' . bin2hex(random_bytes(8));
        mkdir($tempDir);
        $originalDirs = $contextService->installFileDirs;
        $contextService->installFileDirs = [
            $tempDir . '/files/%s/%d',
            $tempDir . '/files/%s/%d/articles',
            $tempDir . '/files/%s/%d/issues',
            $tempDir . '/public/%s/%d',
        ];

        try {
            $filter->createJournalDirs($journal, $deployment);
            foreach ($contextService->installFileDirs as $dir) {
                $this->assertDirectoryExists(sprintf($dir, $contextService->contextsFileDirName, $journal->getId()));
            }

            // Repeating directory preparation must preserve existing content.
            $existingFile = $tempDir . '/public/journals/42/existing.txt';
            file_put_contents($existingFile, 'preserved');
            $filter->createJournalDirs($journal, $deployment);
            $this->assertSame('preserved', file_get_contents($existingFile));
        } finally {
            $contextService->installFileDirs = $originalDirs;
            (new FileManager())->rmtree($tempDir);
        }
    }
}
