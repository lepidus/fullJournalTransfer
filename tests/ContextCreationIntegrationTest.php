<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\core\Application;
use APP\file\PublicFileManager;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\ArchiveManager;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use APP\plugins\importexport\fullJournalTransfer\PackageIntegrity;
use APP\plugins\importexport\fullJournalTransfer\PackageManifest;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\facades\Locale;
use PKP\tests\DatabaseTestCase;

class ContextCreationIntegrationTest extends DatabaseTestCase
{
    private ?Journal $createdContext = null;

    protected function getAffectedTables()
    {
        return [];
    }

    protected function tearDown(): void
    {
        if ($this->createdContext && $this->createdContext->getId()) {
            $publicFileManager = new PublicFileManager();
            $publicFileManager->rmtree(
                $publicFileManager->getContextFilesPath((int) $this->createdContext->getId())
            );
            Application::get()->getContextDAO()->deleteObject($this->createdContext);
        }
        parent::tearDown();
    }

    public function testItCreatesTheImportedContextDisabledAndRejectsAConflictingPath(): void
    {
        $source = new Journal();
        $source->setPath('created-journal-' . bin2hex(random_bytes(4)));
        $source->setSequence(3);
        $source->setEnabled(true);
        $source->setPrimaryLocale('en');
        $source->setData('supportedLocales', ['en']);
        $source->setData('supportedFormLocales', ['en']);
        $source->setData('supportedSubmissionLocales', ['en']);
        $source->setData('submissionChecklist', ['en' => '<ul><li>Ready</li></ul>']);
        $source->setData('name', ['en' => 'Created Journal']);
        $source->setData('contactName', 'Editorial Team');
        $source->setData('contactEmail', 'editor@example.com');
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();

        $deployment = new FullJournalImportExportDeployment($source, null);
        $created = $deployment->createContextData($document->documentElement);
        $this->createdContext = $created;

        $this->assertGreaterThan(0, (int) $created->getId());
        $this->assertFalse($created->getEnabled());
        $this->assertSame('Created Journal', $created->getData('name', 'en'));
        $publicFileManager = new PublicFileManager();
        $this->assertDirectoryExists($publicFileManager->getContextFilesPath((int) $created->getId()));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A context with this path already exists');

        $deployment->createContextData($document->documentElement);
    }

    public function testPackageImportRollsBackTheContextWhenPersistedIntegrityDoesNotMatch(): void
    {
        $path = 'rejected-integrity-' . bin2hex(random_bytes(4));
        $source = new Journal();
        $source->setPath($path);
        $source->setSequence(1);
        $source->setPrimaryLocale('en');
        $source->setData('supportedLocales', ['en']);
        $source->setData('supportedFormLocales', ['en']);
        $source->setData('supportedSubmissionLocales', ['en']);
        $source->setData('name', ['en' => 'Integrity Journal']);
        $source->setData('contactName', 'Editorial Team');
        $source->setData('contactEmail', 'editor@example.com');
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $directory = sys_get_temp_dir() . '/full-journal-integrity-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        file_put_contents($directory . '/journal.xml', $document->saveXML());
        $result = null;

        try {
            $result = (new MismatchedIntegrityDeployment(new Journal(), null))->importPackage(
                '/unused/archive.tar.gz',
                '3.4.0.10',
                'full-journal-xml=>journal',
                new IntegrityStagingArchiveManager($directory, $document)
            );
        } finally {
            unlink($directory . '/journal.xml');
            rmdir($directory);
        }

        $this->assertFalse($result);
        $this->assertNull(Application::get()->getContextDAO()->getByPath($path));
    }

    public function testPackageImportAcceptsMatchingPersistedIntegrity(): void
    {
        Locale::registerPath(dirname(__DIR__) . '/locale');
        $path = 'accepted-integrity-' . bin2hex(random_bytes(4));
        $source = new Journal();
        $source->setPath($path);
        $source->setSequence(1);
        $source->setPrimaryLocale('en');
        $source->setData('supportedLocales', ['en']);
        $source->setData('supportedFormLocales', ['en']);
        $source->setData('supportedSubmissionLocales', ['en']);
        $source->setData('name', ['en' => 'Integrity Journal']);
        $source->setData('contactName', 'Editorial Team');
        $source->setData('contactEmail', 'editor@example.com');
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $directory = sys_get_temp_dir() . '/full-journal-integrity-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        file_put_contents($directory . '/journal.xml', $document->saveXML());
        $progress = [];

        try {
            $result = (new FullJournalImportExportDeployment(new Journal(), null))->importPackage(
                '/unused/archive.tar.gz',
                '3.4.0.10',
                'full-journal-xml=>journal',
                new IntegrityStagingArchiveManager($directory, $document),
                static function (string $message) use (&$progress): void {
                    $progress[] = $message;
                }
            );
            $this->createdContext = Application::get()->getContextDAO()->getByPath($path);
        } finally {
            unlink($directory . '/journal.xml');
            rmdir($directory);
        }

        $this->assertTrue($result);
        $this->assertInstanceOf(Journal::class, $this->createdContext);
        $this->assertSame([
            'Importing journal data...',
            __('plugins.importexport.fullJournal.integrityValidated', ['count' => 29]),
        ], $progress);
    }

    /**
     * @dataProvider invalidDestinationLocaleProvider
     */
    public function testItRejectsInvalidDestinationLocalesBeforeCreatingTheContext(
        string $primaryLocale,
        array $supportedLocales,
        array $supportedFormLocales,
        array $supportedSubmissionLocales,
        array $destinationLocales,
        string $message
    ): void {
        $path = 'rejected-locales-' . bin2hex(random_bytes(4));
        $source = new Journal();
        $source->setPath($path);
        $source->setSequence(1);
        $source->setPrimaryLocale($primaryLocale);
        $source->setData('supportedLocales', $supportedLocales);
        $source->setData('supportedFormLocales', $supportedFormLocales);
        $source->setData('supportedSubmissionLocales', $supportedSubmissionLocales);
        $source->setData('name', [$primaryLocale => 'Rejected Journal']);
        $source->setData('contactName', 'Editorial Team');
        $source->setData('contactEmail', 'editor@example.com');
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $site = Application::get()->getRequest()->getSite();
        $originalLocales = $site->getSupportedLocales();
        $site->setSupportedLocales($destinationLocales);
        $exception = null;
        try {
            (new FullJournalImportExportDeployment(new Journal(), null))
                ->createContextData($document->documentElement);
        } catch (InvalidArgumentException $caught) {
            $exception = $caught;
        } finally {
            $site->setSupportedLocales($originalLocales);
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
        $this->assertNull(Application::get()->getContextDAO()->getByPath($path));
    }

    public function invalidDestinationLocaleProvider(): array
    {
        return [
            'primary locale unavailable' => [
                'pt_BR',
                ['pt_BR'],
                ['pt_BR'],
                ['pt_BR'],
                ['en'],
                'The journal primary locale (pt_BR) is not available in the destination OJS.',
            ],
            'form locales unavailable' => [
                'en',
                ['en'],
                ['es'],
                ['en'],
                ['en'],
                'No journal form locale is available in the destination OJS.',
            ],
            'submission locales unavailable' => [
                'en',
                ['en'],
                ['en'],
                ['es'],
                ['en'],
                'No journal submission locale is available in the destination OJS.',
            ],
        ];
    }

    /**
     * @dataProvider invalidContextSettingProvider
     */
    public function testItRejectsInvalidContextSettingsBeforeCreatingTheContext(
        string $name,
        string $type,
        string $value,
        string $message
    ): void {
        $path = 'rejected-setting-' . bin2hex(random_bytes(4));
        $source = new Journal();
        $source->setPath($path);
        $source->setSequence(1);
        $source->setPrimaryLocale('en');
        $source->setData('supportedLocales', ['en']);
        $source->setData('supportedFormLocales', ['en']);
        $source->setData('supportedSubmissionLocales', ['en']);
        $source->setData('name', ['en' => 'Rejected Journal']);
        $source->setData('contactName', 'Editorial Team');
        $source->setData('contactEmail', 'editor@example.com');
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $settings = $document->getElementsByTagNameNS('http://pkp.sfu.ca', 'context_settings')->item(0);
        $this->assertInstanceOf(DOMElement::class, $settings);
        $setting = $document->createElementNS('http://pkp.sfu.ca', 'setting');
        $setting->setAttribute('name', $name);
        $setting->setAttribute('type', $type);
        $setting->appendChild($document->createTextNode($value));
        $settings->appendChild($setting);
        $exception = null;
        try {
            (new FullJournalImportExportDeployment(new Journal(), null))
                ->createContextData($document->documentElement);
        } catch (InvalidArgumentException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
        $this->assertNull(Application::get()->getContextDAO()->getByPath($path));
    }

    public function invalidContextSettingProvider(): array
    {
        return [
            'unknown setting' => [
                'automaticDoiDeposit',
                'boolean',
                'true',
                'Context setting is not allowed: automaticDoiDeposit',
            ],
            'wrong type' => [
                'itemsPerPage',
                'string',
                '25',
                'Invalid type for context setting: itemsPerPage',
            ],
            'invalid enum' => [
                'doiSuffixType',
                'string',
                'serialized',
                'Invalid value for context setting: doiSuffixType',
            ],
            'invalid list payload' => [
                'enabledDoiTypes',
                'string-list',
                'a:1:{i:0;s:11:"publication";}',
                'Invalid JSON context setting: enabledDoiTypes',
            ],
            'subscription publishing mode' => [
                'publishingMode',
                'integer',
                (string) Journal::PUBLISHING_MODE_SUBSCRIPTION,
                'Subscription publishing mode is not supported because subscriptions are not transferred.',
            ],
        ];
    }
}

class MismatchedIntegrityDeployment extends FullJournalImportExportDeployment
{
    protected function getImportedIntegrityCounts(): array
    {
        $counts = parent::getImportedIntegrityCounts();
        $counts['submissions']++;
        return $counts;
    }
}

class IntegrityStagingArchiveManager extends ArchiveManager
{
    private string $stagingPath;
    private PackageManifest $manifest;

    public function __construct(string $stagingPath, DOMDocument $journal)
    {
        $this->stagingPath = $stagingPath;
        $integrity = '<integrity>';
        foreach (PackageIntegrity::countDocument($journal) as $name => $count) {
            $integrity .= '<entity name="' . $name . '" count="' . $count . '"/>';
        }
        $integrity .= '</integrity>';
        $this->manifest = PackageManifest::fromXml(
            '<full_journal_package application="ojs" application_version="3.4.0.10" '
                . 'format_version="1.1" created_at="2026-09-03T00:00:00+00:00">'
                . $integrity . '<files><file path="journal.xml" size="1" checksum="'
                . str_repeat('a', 64) . '"/></files></full_journal_package>',
            '3.4.0.10'
        );
    }

    public function withExtractedPackage(
        string $archivePath,
        string $applicationVersion,
        callable $importer,
        ?callable $progress = null
    ) {
        return $importer($this->stagingPath, $this->manifest);
    }
}
