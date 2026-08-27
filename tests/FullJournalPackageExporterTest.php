<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use APP\plugins\importexport\fullJournalTransfer\FullJournalPackageExporter;
use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class FullJournalPackageExporterTest extends TestCase
{
    public function testItCreatesThePackageConsumedByThePublicCli(): void
    {
        $stagingDirectories = $this->stagingDirectories();
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
            $this->assertSame($stagingDirectories, $this->stagingDirectories());
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

    public function testItCleansTheStagingDirectoryWhenExportFails(): void
    {
        $directory = sys_get_temp_dir() . '/full-journal-exporter-' . bin2hex(random_bytes(8));
        $filesDirectory = $directory . '/files';
        mkdir($filesDirectory, 0700, true);
        $archivePath = $directory . '/journal.tar.gz';
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML('<journal><href src="journals/1/missing.txt"/></journal>');
        $deployment = new ExportDocumentDeployment(new Journal(), null, $document);
        $stagingDirectories = $this->stagingDirectories();

        try {
            (new FullJournalPackageExporter($filesDirectory, '3.4.0.10'))->export($deployment, $archivePath);
            $this->fail('The missing referenced file was not rejected');
        } catch (\RuntimeException $exception) {
            $this->assertSame('A referenced journal file is unavailable', $exception->getMessage());
        } finally {
            $this->assertSame($stagingDirectories, $this->stagingDirectories());
            $this->assertFileDoesNotExist($archivePath);
            rmdir($filesDirectory);
            rmdir($directory);
        }
    }

    public function testItRejectsInconsistentNativeDataBeforeCreatingThePackage(): void
    {
        $directory = sys_get_temp_dir() . '/full-journal-exporter-' . bin2hex(random_bytes(8));
        $filesDirectory = $directory . '/files';
        mkdir($filesDirectory, 0700, true);
        $archivePath = $directory . '/journal.tar.gz';
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML(
            '<journal xmlns="http://pkp.sfu.ca"><native_data><issue_orders/><issues/><articles>'
            . '<article current_publication_id="21" stage="submission"><id type="internal">10</id>'
            . '<publication section_ref="ART" version="1"><id type="internal">20</id>'
            . '<title locale="en">Article</title></publication></article>'
            . '</articles></native_data></journal>'
        );
        $deployment = new ExportDocumentDeployment(new Journal(), null, $document);

        try {
            (new FullJournalPackageExporter($filesDirectory, '3.4.0.10'))->export($deployment, $archivePath);
            $this->fail('The inconsistent native data was not rejected');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Unknown current publication reference', $exception->getMessage());
            $this->assertFileDoesNotExist($archivePath);
        } finally {
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
            rmdir($filesDirectory);
            rmdir($directory);
        }
    }

    public function testItRejectsAnUnknownWorkflowUserBeforeCreatingThePackage(): void
    {
        $directory = sys_get_temp_dir() . '/full-journal-exporter-' . bin2hex(random_bytes(8));
        $filesDirectory = $directory . '/files';
        mkdir($filesDirectory, 0700, true);
        $archivePath = $directory . '/journal.tar.gz';
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML(
            '<journal xmlns="http://pkp.sfu.ca"><users><user_groups/><users>'
            . '<user source_ref="1"/></users></users><native_data><issue_orders/><issues/><articles>'
            . '<article current_publication_id="20" stage="submission"><id type="internal">10</id>'
            . '<publication section_ref="ART" version="1"><id type="internal">20</id>'
            . '<title locale="en">Article</title></publication></article>'
            . '</articles></native_data><workflow_history><stage_assignments>'
            . '<stage_assignment user_ref="2"/></stage_assignments></workflow_history></journal>'
        );
        $deployment = new ExportDocumentDeployment(new Journal(), null, $document);

        try {
            (new FullJournalPackageExporter($filesDirectory, '3.4.0.10'))->export($deployment, $archivePath);
            $this->fail('The unknown workflow user was not rejected');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Unknown workflow user reference: 2', $exception->getMessage());
            $this->assertFileDoesNotExist($archivePath);
        } finally {
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
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

    private function stagingDirectories(): array
    {
        $directories = glob(sys_get_temp_dir() . '/full-journal-export-*', GLOB_ONLYDIR);
        sort($directories);
        return $directories;
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
