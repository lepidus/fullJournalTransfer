<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportPlugin;
use DOMDocument;
use PKP\tests\DatabaseTestCase;
use Symfony\Component\Process\Process;

class FullJournalCliIntegrationTest extends DatabaseTestCase
{
    private array $contexts = [];
    private array $archives = [];

    protected function getAffectedTables()
    {
        return [];
    }

    protected function tearDown(): void
    {
        foreach ($this->archives as $archive) {
            if (is_file($archive)) {
                unlink($archive);
            }
        }
        foreach (array_reverse($this->contexts) as $context) {
            Application::get()->getContextDAO()->deleteObject($context);
        }
        parent::tearDown();
    }

    public function testItExportsACompletePackageThroughThePublicCli(): void
    {
        $context = $this->createContext();
        $archive = $this->archivePath();
        $arguments = ['export', $archive, $context->getPath()];
        $plugin = new CliTestPlugin();

        $result = $plugin->executeCLI('tools/importExport.php', $arguments);

        $this->assertTrue($result);
        $this->assertSame([
            'Exporting journal data...',
            'Copying journal files...',
            'Creating journal archive...',
            'Journal export completed',
        ], $plugin->cliOutput);
        $this->assertFileExists($archive);
        $process = new Process(['/bin/tar', '-tzf', $archive]);
        $process->mustRun();
        $this->assertSame("manifest.xml\njournal.xml\n", $process->getOutput());
    }

    public function testItDisplaysPluginUsageWithoutAnException(): void
    {
        $arguments = ['usage'];
        ob_start();
        try {
            $result = (new CliTestPlugin())->executeCLI('tools/importExport.php', $arguments);
            $output = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $this->assertTrue($result);
        $this->assertStringContainsString('FullJournalImportExportPlugin', $output);
        $this->assertStringContainsString('import', $output);
        $this->assertStringContainsString('export', $output);
    }

    public function testItRejectsAnExistingExportDestination(): void
    {
        $context = $this->createContext();
        $archive = $this->archivePath();
        file_put_contents($archive, 'existing');
        $arguments = ['export', $archive, $context->getPath()];
        $plugin = new CliTestPlugin();

        $result = $plugin->executeCLI('tools/importExport.php', $arguments);

        $this->assertFalse($result);
        $this->assertSame(['The export archive path is invalid'], $plugin->cliErrors);
    }

    public function testItRejectsAnUnknownJournal(): void
    {
        $arguments = ['export', $this->archivePath(), 'missing-' . bin2hex(random_bytes(4))];
        $plugin = new CliTestPlugin();

        $result = $plugin->executeCLI('tools/importExport.php', $arguments);

        $this->assertFalse($result);
        $this->assertSame(['The journal path does not exist'], $plugin->cliErrors);
    }

    public function testPublicCliReportsAnUnknownJournalWithoutAStackTrace(): void
    {
        $applicationRoot = dirname(INDEX_FILE_LOCATION);
        $process = new Process([
            PHP_BINARY,
            $applicationRoot . '/tools/importExport.php',
            'FullJournalImportExportPlugin',
            'export',
            $this->archivePath(),
            'missing-' . bin2hex(random_bytes(4)),
        ], $applicationRoot);

        $process->run();

        $this->assertSame(1, $process->getExitCode());
        $this->assertSame('', $process->getOutput());
        $this->assertSame("Error: The journal path does not exist\n", $process->getErrorOutput());
    }

    public function testItRejectsAnUnknownImportUser(): void
    {
        $arguments = ['import', $this->archivePath(), 'missing-' . bin2hex(random_bytes(4))];
        $plugin = new CliTestPlugin();

        $result = $plugin->executeCLI('tools/importExport.php', $arguments);

        $this->assertFalse($result);
        $this->assertSame(['The import user does not exist'], $plugin->cliErrors);
    }

    public function testItReportsAnInvalidImportPackage(): void
    {
        $this->createContext();
        $user = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($user);
        $arguments = ['import', $this->archivePath(), $user->getUsername()];
        $plugin = new CliTestPlugin();

        $result = $plugin->executeCLI('tools/importExport.php', $arguments);

        $this->assertFalse($result);
        $this->assertSame(['Validating journal package...'], $plugin->cliOutput);
        $this->assertSame(['The package archive must be a regular file'], $plugin->cliErrors);
    }

    public function testItReportsRelevantImportProgress(): void
    {
        $user = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($user);
        $arguments = ['import', $this->archivePath(), $user->getUsername()];
        $plugin = new SuccessfulImportCliTestPlugin();

        $result = $plugin->executeCLI('tools/importExport.php', $arguments);

        $this->assertTrue($result);
        $this->assertSame([
            'Validating journal package...',
            'Extracting journal package...',
            'Importing journal data...',
            'Journal import completed',
        ], $plugin->cliOutput);
    }

    private function createContext(): Journal
    {
        $context = Application::get()->getContextDAO()->newDataObject();
        $context->setPath('cli-test-' . bin2hex(random_bytes(4)));
        $context->setPrimaryLocale('en');
        $context->setEnabled(false);
        $context->setSequence(1);
        $context->setData('supportedLocales', ['en']);
        $context->setData('name', ['en' => 'CLI Test Journal']);
        Application::get()->getContextDAO()->insertObject($context);
        $this->contexts[] = $context;
        $this->setRequestContext($context);
        return $context;
    }

    private function setRequestContext(Journal $context): void
    {
        $router = new class ($context) extends \APP\core\PageRouter {
            private Journal $context;

            public function __construct(Journal $context)
            {
                $this->context = $context;
            }

            public function getContext($request, $forceReload = false): Journal
            {
                return $this->context;
            }
        };
        Application::get()->getRequest()->setRouter($router);
    }

    private function archivePath(): string
    {
        $path = sys_get_temp_dir() . '/full-journal-cli-' . bin2hex(random_bytes(8)) . '.tar.gz';
        $this->archives[] = $path;
        return $path;
    }
}

class CliTestPlugin extends FullJournalImportExportPlugin
{
    public array $cliErrors = [];
    public array $cliOutput = [];

    public function usage($scriptName)
    {
        echo $scriptName . ' FullJournalImportExportPlugin import|export';
    }

    public function getAppSpecificDeployment($context, $user)
    {
        return new CliExportDeployment($context, $user);
    }

    protected function exitWithCLIError(string $message): void
    {
        $this->cliErrors[] = $message;
    }

    protected function writeCLIOutput(string $message): void
    {
        $this->cliOutput[] = $message;
    }
}

class CliExportDeployment extends FullJournalImportExportDeployment
{
    public function exportContextData(): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML('<journal/>');
        return $document;
    }
}

class SuccessfulImportCliTestPlugin extends CliTestPlugin
{
    public function getAppSpecificDeployment($context, $user)
    {
        return new SuccessfulImportDeployment($context, $user);
    }
}

class SuccessfulImportDeployment extends FullJournalImportExportDeployment
{
    public function importPackage(
        string $archivePath,
        string $applicationVersion,
        string $rootFilter,
        ?\APP\plugins\importexport\fullJournalTransfer\ArchiveManager $archiveManager = null,
        ?callable $progress = null
    ): bool {
        if ($progress) {
            $progress('Validating journal package...');
            $progress('Extracting journal package...');
            $progress('Importing journal data...');
        }
        return true;
    }
}
