<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportPlugin;
use DOMDocument;
use InvalidArgumentException;
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

        $result = (new CliTestPlugin())->executeCLI('tools/importExport.php', $arguments);

        $this->assertTrue($result);
        $this->assertFileExists($archive);
        $process = new Process(['/bin/tar', '-tzf', $archive]);
        $process->mustRun();
        $this->assertSame("manifest.xml\njournal.xml\n", $process->getOutput());
    }

    public function testItRejectsAnExistingExportDestination(): void
    {
        $context = $this->createContext();
        $archive = $this->archivePath();
        file_put_contents($archive, 'existing');
        $arguments = ['export', $archive, $context->getPath()];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The export archive path is invalid');

        (new CliTestPlugin())->executeCLI('tools/importExport.php', $arguments);
    }

    public function testItRejectsAnUnknownJournal(): void
    {
        $arguments = ['export', $this->archivePath(), 'missing-' . bin2hex(random_bytes(4))];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The journal path does not exist');

        (new FullJournalImportExportPlugin())->executeCLI('tools/importExport.php', $arguments);
    }

    public function testItRejectsAnUnknownImportUser(): void
    {
        $arguments = ['import', $this->archivePath(), 'missing-' . bin2hex(random_bytes(4))];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The import user does not exist');

        (new FullJournalImportExportPlugin())->executeCLI('tools/importExport.php', $arguments);
    }

    public function testItReportsAnInvalidImportPackage(): void
    {
        $this->createContext();
        $user = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($user);
        $arguments = ['import', $this->archivePath(), $user->getUsername()];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The package archive must be a regular file');

        (new FullJournalImportExportPlugin())->executeCLI('tools/importExport.php', $arguments);
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
    public function getAppSpecificDeployment($context, $user)
    {
        return new CliExportDeployment($context, $user);
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
