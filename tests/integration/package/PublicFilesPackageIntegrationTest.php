<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\integration\package;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use APP\plugins\importexport\fullJournalTransfer\package\FullJournalPackageExporter;
use APP\plugins\importexport\fullJournalTransfer\publicFiles\PublicFilesTransfer;
use APP\plugins\importexport\fullJournalTransfer\tests\support\LoadsPluginLocale;
use DOMDocument;
use DOMElement;
use PKP\config\Config;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\install\Installer;
use PKP\tests\DatabaseTestCase;
use RuntimeException;

class PublicFilesPackageIntegrationTest extends DatabaseTestCase
{
    use LoadsPluginLocale;

    private array $contexts = [];
    private array $archives = [];

    protected function getAffectedTables()
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $installer = (new \ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
        if (!$installer->installFilterConfig(dirname(__DIR__, 3) . '/filter/filterConfig.xml')) {
            throw new RuntimeException('Public files filter configuration could not be installed');
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->contexts) as $context) {
            Repo::issue()->deleteByContextId((int) $context->getId());
            Repo::section()->deleteByContextId((int) $context->getId());
            DAORegistry::getDAO('GenreDAO')->deleteByContextId((int) $context->getId());
            (new PublicFileManager())->rmtree((new PublicFileManager())->getContextFilesPath($context->getId()));
            Application::get()->getContextDAO()->deleteObject($context);
        }
        foreach ($this->archives as $archive) {
            if (is_file($archive)) {
                unlink($archive);
            }
        }
        parent::tearDown();
    }

    public function testItRoundTripsPublicFilesAppearanceAndIssueCoverThroughTheRealPackage(): void
    {
        $source = $this->sourceJournal();
        $sourcePath = (new PublicFileManager())->getContextFilesPath($source->getId());
        $issue = Repo::issue()->newDataObject();
        $issue->setJournalId($source->getId());
        $issue->setVolume(1);
        $issue->setNumber('1');
        $issue->setYear(2026);
        $issue->setPublished(1);
        $issue->setDatePublished('2026-09-01 12:00:00');
        $issue->setAccessStatus(1);
        $issue->setShowVolume(1);
        $issue->setShowNumber(1);
        $issue->setShowYear(1);
        $issue->setShowTitle(1);
        $issue->setTitle('Synthetic issue', 'en');
        $issue->setCoverImage('image.png', 'en');
        $issue->setCoverImageAltText('Issue cover', 'en');
        Repo::issue()->add($issue);
        $archive = $this->exportAndReleasePath($source);
        $deployment = new FullJournalImportExportDeployment(new Journal(), null);

        $result = $deployment->importPackage($archive, 'full-journal-xml=>journal');
        $created = $deployment->getContext();
        if ($created->getId()) {
            $this->contexts[] = $created;
        }
        $this->assertTrue(
            $result,
            json_encode($deployment->getProcessedObjectsErrors(PKPApplication::ASSOC_TYPE_NONE), JSON_UNESCAPED_UNICODE)
        );
        $destinationPath = (new PublicFileManager())->getContextFilesPath($created->getId());
        $destinationUrl = $this->url($created);
        $saved = Application::get()->getContextDAO()->getById($created->getId());
        $this->assertNotSame($source->getId(), $saved->getId());
        foreach (['pageHeaderLogoImage', 'homepageImage', 'favicon', 'journalThumbnail', 'styleSheet'] as $name) {
            $this->assertEquals($source->getData($name), $saved->getData($name));
        }
        $this->assertSame(
            file_get_contents($sourcePath . '/image.png'),
            file_get_contents($destinationPath . '/image.png')
        );
        $this->assertSame(
            str_replace($this->url($source) . '/', $this->url($saved) . '/', file_get_contents($sourcePath . '/extensionless-logo')),
            file_get_contents($destinationPath . '/extensionless-logo')
        );
        $this->assertSame(
            file_get_contents($sourcePath . '/assets/font.woff2'),
            file_get_contents($destinationPath . '/assets/font.woff2')
        );
        $this->assertSame(
            'a{background:url(' . $destinationUrl . '/image.png)}',
            file_get_contents($destinationPath . '/style.css')
        );
        $this->assertSame(
            '<img src="' . $destinationUrl . '/image.png">',
            file_get_contents($destinationPath . '/assets/page.html')
        );
        $this->assertSame('<img src="' . $destinationUrl . '/image.png">', $saved->getData('description', 'en'));
        $this->assertFalse($saved->getEnabled());
        $importedIssue = Repo::issue()->getCollector()->filterByContextIds([$created->getId()])->getMany()->first();
        $this->assertNotNull($importedIssue);
        $this->assertSame('Issue cover', $importedIssue->getCoverImageAltText('en'));
        $this->assertSame(
            file_get_contents($sourcePath . '/image.png'),
            file_get_contents($destinationPath . '/' . $importedIssue->getCoverImage('en'))
        );
    }

    public function testItCompensatesPublicFilesAfterALatePackageImportFailure(): void
    {
        $source = $this->sourceJournal();
        $archive = $this->exportAndReleasePath($source);
        $deployment = new LatePublicFilesFailureDeployment(new Journal(), null);
        $result = $deployment->importPackage($archive, 'full-journal-xml=>journal');
        if ($deployment->getContext()->getId()) {
            $this->contexts[] = $deployment->getContext();
        }
        $this->assertFalse($result);
        $this->assertNotEmpty($deployment->createdFiles);
        foreach ($deployment->createdFiles as $file) {
            $this->assertFileDoesNotExist($file);
        }
        $this->assertDirectoryDoesNotExist((new PublicFileManager())->getContextFilesPath(
            $deployment->getContext()->getId()
        ));
        $this->assertNull(Application::get()->getContextDAO()->getByPath($deployment->getContext()->getPath()));
        $this->assertFileExists((new PublicFileManager())->getContextFilesPath($source->getId()) . '/image.png');
    }

    public function testItStillImportsPackagesWithoutTheOptionalPublicFilesSection(): void
    {
        $source = $this->sourceJournal();
        $archive = $this->exportAndReleasePath($source, true);
        $deployment = new FullJournalImportExportDeployment(new Journal(), null);
        $result = $deployment->importPackage($archive, 'full-journal-xml=>journal');
        $created = $deployment->getContext();
        if ($created->getId()) {
            $this->contexts[] = $created;
        }
        $this->assertTrue(
            $result,
            json_encode($deployment->getProcessedObjectsErrors(PKPApplication::ASSOC_TYPE_NONE), JSON_UNESCAPED_UNICODE)
        );
        $this->assertNull($created->getData('pageHeaderLogoImage'));
        $this->assertFileDoesNotExist((new PublicFileManager())->getContextFilesPath($created->getId()) . '/image.png');
    }

    private function sourceJournal(): Journal
    {
        $journal = new Journal();
        $journal->setPath('pubfiles-' . bin2hex(random_bytes(6)));
        $journal->setPrimaryLocale('en');
        $journal->setEnabled(false);
        $journal->setSequence(1);
        foreach (['supportedLocales', 'supportedFormLocales', 'supportedSubmissionLocales'] as $name) {
            $journal->setData($name, ['en']);
        }
        $journal->setData('name', ['en' => 'Synthetic Public Files Journal']);
        $journal->setData('contactName', 'Editorial Team');
        $journal->setData('contactEmail', 'editor@example.com');
        Application::get()->getContextDAO()->insertObject($journal);
        $this->contexts[] = $journal;
        $path = (new PublicFileManager())->getContextFilesPath($journal->getId());
        mkdir($path . '/assets', 0755, true);
        file_put_contents($path . '/image.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));
        file_put_contents($path . '/assets/font.woff2', 'synthetic font bytes');
        file_put_contents(
            $path . '/extensionless-logo',
            '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><image href="' . $this->url($journal) . '/image.png"/></svg>'
        );
        file_put_contents($path . '/style.css', 'a{background:url(' . $this->url($journal) . '/image.png)}');
        file_put_contents($path . '/assets/page.html', '<img src="' . $this->url($journal) . '/image.png">');
        foreach (['pageHeaderLogoImage', 'homepageImage', 'favicon', 'journalThumbnail'] as $name) {
            $journal->setData($name, ['en' => ['uploadName' => 'image.png', 'altText' => 'Synthetic image',
                'width' => 1, 'height' => 1, 'dateUploaded' => '2026-09-30 12:00:00']]);
        }
        $journal->setData('styleSheet', ['uploadName' => 'style.css', 'dateUploaded' => '2026-09-30 12:00:00']);
        $journal->setData('description', ['en' => '<img src="' . $this->url($journal) . '/image.png">']);
        Application::get()->getContextDAO()->updateObject($journal);
        return $journal;
    }

    private function url(Journal $journal): string
    {
        return Application::get()->getRequest()->getBaseUrl() . '/'
            . (new PublicFilesTransfer())->publicPath(
                (new PublicFileManager())->getContextFilesPath($journal->getId())
            );
    }

    private function exportAndReleasePath(Journal $source, bool $legacy = false): string
    {
        $archive = sys_get_temp_dir() . '/public-package-' . bin2hex(random_bytes(8)) . '.tar.gz';
        $this->archives[] = $archive;
        $deployment = $legacy
            ? new LegacyPublicFilesExportDeployment($source, null)
            : new FullJournalImportExportDeployment($source, null);
        (new FullJournalPackageExporter((string) Config::getVar('files', 'files_dir')))->export($deployment, $archive);
        // Simulate a different installation without deleting the source fixture or its files.
        $source->setPath($source->getPath() . '-source');
        Application::get()->getContextDAO()->updateObject($source);
        return $archive;
    }
}

class LatePublicFilesFailureDeployment extends FullJournalImportExportDeployment
{
    public array $createdFiles = [];

    public function recordCreatedFile(string $path): void
    {
        $this->createdFiles[] = $path;
        parent::recordCreatedFile($path);
    }

    public function importMetrics(DOMElement $metricsNode): void
    {
        throw new RuntimeException('Synthetic failure after restoring public files');
    }
}

class LegacyPublicFilesExportDeployment extends FullJournalImportExportDeployment
{
    public function exportContextData(): DOMDocument
    {
        $document = parent::exportContextData();
        $node = $document->getElementsByTagNameNS('http://pkp.sfu.ca', 'public_files')->item(0);
        $node->parentNode->removeChild($node);
        return $document;
    }
}
