<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\ArchiveManager;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportPlugin;
use APP\plugins\importexport\native\NativeImportExportPlugin;
use PHPUnit\Framework\TestCase;

class FullJournalImportExportPluginTest extends TestCase
{
    public function testEntrypointLoadsNativeOjs34Plugin(): void
    {
        $plugin = require dirname(__DIR__) . '/index.php';

        $this->assertInstanceOf(FullJournalImportExportPlugin::class, $plugin);
        $this->assertInstanceOf(NativeImportExportPlugin::class, $plugin);
        $this->assertSame('FullJournalImportExportPlugin', $plugin->getName());
    }

    public function testDeploymentUsesNativeOjsSubmissionNodes(): void
    {
        $plugin = new FullJournalImportExportPlugin();
        $deployment = $plugin->getAppSpecificDeployment(new Journal(), null);

        $this->assertInstanceOf(FullJournalImportExportDeployment::class, $deployment);
        $this->assertSame('article', $deployment->getSubmissionNodeName());
        $this->assertSame('articles', $deployment->getSubmissionsNodeName());
        $this->assertSame('article_galley', $deployment->getRepresentationNodeName());
    }

    public function testDeploymentCompensatesCreatedFilesWhenNativeImportFails(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'full-journal-created-');
        $this->assertNotFalse($file);
        $deployment = new FailingFullJournalImportExportDeployment(new Journal(), null, $file);

        $deployment->import('unused-filter', '<journal/>');

        $this->assertFileDoesNotExist($file);
    }

    public function testDeploymentKeepsCreatedFilesWhenNativeImportSucceeds(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'full-journal-created-');
        $this->assertNotFalse($file);
        $deployment = new SuccessfulFullJournalImportExportDeployment(new Journal(), null, $file);

        try {
            $deployment->import('unused-filter', '<journal/>');

            $this->assertFileExists($file);
        } finally {
            if (is_string($file) && file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testDeploymentCompensatesCreatedFilesWhenNativeImportThrows(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'full-journal-created-');
        $this->assertNotFalse($file);
        $deployment = new ThrowingFullJournalImportExportDeployment(new Journal(), null, $file);

        try {
            $deployment->import('unused-filter', '<journal/>');
            $this->fail('The native import exception was not propagated');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Native import failed', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($file);
    }

    public function testPackageImportUsesValidatedStagingAndClearsItsPath(): void
    {
        $directory = sys_get_temp_dir() . '/full-journal-staging-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        file_put_contents($directory . '/journal.xml', '<journal/>');
        $deployment = new CapturingFullJournalImportExportDeployment(new Journal(), null);

        try {
            $result = $deployment->importPackage(
                '/unused/archive.tar.gz',
                '3.4.0.10',
                'native-xml=>journal',
                new ValidatedStagingArchiveManager($directory)
            );

            $this->assertTrue($result);
            $this->assertSame('native-xml=>journal', $deployment->rootFilter);
            $this->assertSame('<journal/>', $deployment->journalXml);
            $this->assertSame($directory, $deployment->importPathDuringImport);
            $this->assertSame('', $deployment->getImportPath());
        } finally {
            unlink($directory . '/journal.xml');
            rmdir($directory);
        }
    }
}

class FailingFullJournalImportExportDeployment extends FullJournalImportExportDeployment
{
    private string $createdFile;

    public function __construct($context, $user, string $createdFile)
    {
        parent::__construct($context, $user);
        $this->createdFile = $createdFile;
    }

    protected function runNativeImport($rootFilter, $importXml): void
    {
        $this->recordCreatedFile($this->createdFile);
    }

    public function isProcessFailed()
    {
        return true;
    }
}

class SuccessfulFullJournalImportExportDeployment extends FullJournalImportExportDeployment
{
    private string $createdFile;

    public function __construct($context, $user, string $createdFile)
    {
        parent::__construct($context, $user);
        $this->createdFile = $createdFile;
    }

    protected function runNativeImport($rootFilter, $importXml): void
    {
        $this->recordCreatedFile($this->createdFile);
    }

    public function isProcessFailed()
    {
        return false;
    }
}

class CapturingFullJournalImportExportDeployment extends FullJournalImportExportDeployment
{
    public string $rootFilter = '';
    public string $journalXml = '';
    public string $importPathDuringImport = '';

    protected function runNativeImport($rootFilter, $importXml): void
    {
        $this->rootFilter = $rootFilter;
        $this->journalXml = $importXml;
        $this->importPathDuringImport = $this->getImportPath();
    }

    public function isProcessFailed()
    {
        return false;
    }
}

class ThrowingFullJournalImportExportDeployment extends FullJournalImportExportDeployment
{
    private string $createdFile;

    public function __construct($context, $user, string $createdFile)
    {
        parent::__construct($context, $user);
        $this->createdFile = $createdFile;
    }

    protected function runNativeImport($rootFilter, $importXml): void
    {
        $this->recordCreatedFile($this->createdFile);
        throw new \RuntimeException('Native import failed');
    }
}

class ValidatedStagingArchiveManager extends ArchiveManager
{
    private string $stagingPath;

    public function __construct(string $stagingPath)
    {
        $this->stagingPath = $stagingPath;
    }

    public function withExtractedPackage(string $archivePath, string $applicationVersion, callable $importer)
    {
        return $importer($this->stagingPath);
    }
}
