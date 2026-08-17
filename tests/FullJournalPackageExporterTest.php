<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use APP\plugins\importexport\fullJournalTransfer\FullJournalPackageExporter;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class FullJournalPackageExporterTest extends TestCase
{
    public function testItCreatesThePackageConsumedByThePublicCli(): void
    {
        $directory = sys_get_temp_dir() . '/full-journal-exporter-' . bin2hex(random_bytes(8));
        $filesDirectory = $directory . '/files';
        $referencedFile = 'journals/1/articles/example.txt';
        mkdir($filesDirectory . '/journals/1/articles', 0700, true);
        file_put_contents($filesDirectory . '/' . $referencedFile, 'example file');
        $archivePath = $directory . '/journal.tar.gz';
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML('<journal><href src="' . $referencedFile . '"/></journal>');
        $deployment = new ExportDocumentDeployment(new Journal(), null, $document);

        try {
            (new FullJournalPackageExporter($filesDirectory, '3.4.0.10'))->export($deployment, $archivePath);

            $entries = $this->runTar(['-tzf', $archivePath]);
            $this->assertSame([
                'manifest.xml',
                'journal.xml',
                $referencedFile,
            ], preg_split('/\R/', trim($entries)));
            $manifest = $this->runTar(['-xOzf', $archivePath, 'manifest.xml']);
            $this->assertStringContainsString('application_version="3.4.0.10"', $manifest);
            $this->assertStringContainsString('path="journal.xml"', $manifest);
            $this->assertStringContainsString('path="' . $referencedFile . '"', $manifest);
            $this->assertSame('example file', $this->runTar(['-xOzf', $archivePath, $referencedFile]));
        } finally {
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
            unlink($filesDirectory . '/' . $referencedFile);
            rmdir($filesDirectory . '/journals/1/articles');
            rmdir($filesDirectory . '/journals/1');
            rmdir($filesDirectory . '/journals');
            rmdir($filesDirectory);
            rmdir($directory);
        }
    }

    private function runTar(array $arguments): string
    {
        $process = new Process(array_merge(['/bin/tar'], $arguments));
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        return $process->getOutput();
    }
}

class ExportDocumentDeployment extends FullJournalImportExportDeployment
{
    private DOMDocument $document;

    public function __construct($context, $user, DOMDocument $document)
    {
        parent::__construct($context, $user);
        $this->document = $document;
    }

    public function exportContextData(): DOMDocument
    {
        return $this->document;
    }
}
