<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\integration\journal;

use APP\core\Application;
use APP\file\PublicFileManager;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use DOMElement;
use InvalidArgumentException;
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

    public function testItRejectsInvalidDestinationLocalesBeforeCreatingTheContext(): void
    {
        foreach ($this->invalidDestinationLocaleProvider() as $case) {
            $this->assertInvalidDestinationLocalesBeforeCreatingTheContext(...$case);
        }
    }

    private function assertInvalidDestinationLocalesBeforeCreatingTheContext(
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

    public function testItRejectsInvalidContextSettingsBeforeCreatingTheContext(): void
    {
        foreach ($this->invalidContextSettingProvider() as $case) {
            $this->assertInvalidContextSettingsBeforeCreatingTheContext(...$case);
        }
    }

    private function assertInvalidContextSettingsBeforeCreatingTheContext(
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
