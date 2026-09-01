<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\core\Application;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use DOMXPath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JournalFilterTest extends TestCase
{
    public function testItPreservesLocalesEnabledExclusivelyForFormsOrSubmissions(): void
    {
        $source = new Journal();
        $source->setPath('source-journal');
        $source->setSequence(1);
        $source->setPrimaryLocale('en');
        $source->setData('name', ['en' => 'Source Journal']);
        $source->setData('contactName', 'Editorial Team');
        $source->setData('contactEmail', 'editor@example.com');
        $source->setData('supportedLocales', ['en']);
        $source->setData('supportedFormLocales', ['es', 'en']);
        $source->setData('supportedSubmissionLocales', ['pt_BR', 'en']);

        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');

        $this->assertSame(
            ['en', 'es', 'pt_BR'],
            array_map(
                static fn ($node): string => $node->getAttribute('code'),
                iterator_to_array($xpath->query('/pkp:journal/pkp:locales/pkp:locale'))
            )
        );

        $destination = new Journal();
        $site = Application::get()->getRequest()->getSite();
        $originalLocales = $site->getSupportedLocales();
        $site->setSupportedLocales(['en', 'es', 'pt_BR']);
        try {
            (new FullJournalImportExportDeployment($destination, null))->importContextData($document->documentElement);
        } finally {
            $site->setSupportedLocales($originalLocales);
        }

        $this->assertSame(['en'], $destination->getData('supportedLocales'));
        $this->assertSame(['es', 'en'], $destination->getData('supportedFormLocales'));
        $this->assertSame(['pt_BR', 'en'], $destination->getData('supportedSubmissionLocales'));
    }

    public function testItIntersectsEachLocaleListWithTheDestinationSite(): void
    {
        $source = new Journal();
        $source->setPath('source-journal');
        $source->setSequence(1);
        $source->setPrimaryLocale('en');
        $source->setData('name', ['en' => 'Source Journal', 'es' => 'Revista', 'pt_BR' => 'Revista']);
        $source->setData('description', ['en' => 'Accepted', 'es' => 'Rejected']);
        $source->setData('contactName', 'Editorial Team');
        $source->setData('contactEmail', 'editor@example.com');
        $source->setData('supportedLocales', ['en', 'es', 'pt_BR']);
        $source->setData('supportedFormLocales', ['es', 'en']);
        $source->setData('supportedSubmissionLocales', ['pt_BR', 'en']);
        $source->setData('submissionChecklist', ['en' => 'Accepted', 'es' => 'Rejected']);
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $destination = new Journal();
        $site = Application::get()->getRequest()->getSite();
        $originalLocales = $site->getSupportedLocales();
        $site->setSupportedLocales(['en']);
        try {
            (new FullJournalImportExportDeployment($destination, null))->importContextData($document->documentElement);
        } finally {
            $site->setSupportedLocales($originalLocales);
        }

        $this->assertSame(['en'], $destination->getData('supportedLocales'));
        $this->assertSame(['en'], $destination->getData('supportedFormLocales'));
        $this->assertSame(['en'], $destination->getData('supportedSubmissionLocales'));
        $this->assertSame(['en' => 'Accepted'], $destination->getData('submissionChecklist'));
        $this->assertSame(['en' => 'Accepted'], $destination->getData('description'));
    }

    public function testItTransfersEssentialTypedSettingsWithoutSecretsOrAutomaticDeposits(): void
    {
        $source = new Journal();
        $source->setPath('settings-journal');
        $source->setSequence(1);
        $source->setPrimaryLocale('en');
        $source->setData('supportedLocales', ['en']);
        $source->setData('supportedFormLocales', ['en']);
        $source->setData('supportedSubmissionLocales', ['en']);
        $source->setData('name', ['en' => 'Settings Journal']);
        $source->setData('contactName', 'Editorial Team');
        $source->setData('contactEmail', 'editor@example.com');
        $source->setData('authorGuidelines', ['en' => 'Guidelines']);
        $source->setData('copyrightNotice', ['en' => 'Copyright notice']);
        $source->setData('itemsPerPage', 25);
        $source->setData('enableDois', true);
        $source->setData('enabledDoiTypes', ['publication', 'issue']);
        $source->setData('doiPrefix', '10.1234');
        $source->setData('doiSuffixType', 'default');
        $source->setData('doiVersioning', true);
        $source->setData('doiCreationTime', 'copyEditCreationTime');
        $source->setData('publishingMode', Journal::PUBLISHING_MODE_OPEN);
        $source->setData('agencies', 'require');
        $source->setData('disableSubmissions', false);
        $source->setData('supportPhone', null);
        $source->setData('automaticDoiDeposit', true);
        $source->setData('registrationAgency', 'dataciteplugin');
        $source->setData('apiKey', 'must-not-be-exported');

        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $destination = new Journal();
        (new FullJournalImportExportDeployment($destination, null))->importContextData($document->documentElement);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');

        $this->assertSame('Guidelines', $destination->getData('authorGuidelines', 'en'));
        $this->assertSame('Copyright notice', $destination->getData('copyrightNotice', 'en'));
        $this->assertSame(25, $destination->getData('itemsPerPage'));
        $this->assertTrue($destination->getData('enableDois'));
        $this->assertSame(['publication', 'issue'], $destination->getData('enabledDoiTypes'));
        $this->assertSame('10.1234', $destination->getData('doiPrefix'));
        $this->assertSame('default', $destination->getData('doiSuffixType'));
        $this->assertTrue($destination->getData('doiVersioning'));
        $this->assertSame('copyEditCreationTime', $destination->getData('doiCreationTime'));
        $this->assertSame(Journal::PUBLISHING_MODE_OPEN, $destination->getData('publishingMode'));
        $this->assertSame('require', $destination->getData('agencies'));
        $this->assertFalse($destination->getData('disableSubmissions'));
        $this->assertNull($destination->getData('supportPhone'));
        $this->assertSame(0, $xpath->query('//pkp:setting[@name="automaticDoiDeposit"]')->length);
        $this->assertSame(0, $xpath->query('//pkp:setting[@name="registrationAgency"]')->length);
        $this->assertSame(0, $xpath->query('//pkp:setting[@name="apiKey"]')->length);
    }

    public function testItTransfersLocalesAndLocalizedSubmissionChecklist(): void
    {
        $source = new Journal();
        $source->setPath('source-journal');
        $source->setSequence(9);
        $source->setEnabled(true);
        $source->setPrimaryLocale('en');
        $source->setData('name', ['en' => 'Source Journal', 'fr_CA' => 'Alternate Source Journal']);
        $source->setData('acronym', ['en' => 'SJ']);
        $source->setData('contactName', 'Editorial Team');
        $source->setData('contactEmail', 'editor@example.com');
        $source->setData('supportedLocales', ['en', 'fr_CA']);
        $source->setData('supportedFormLocales', ['fr_CA', 'en']);
        $source->setData('supportedSubmissionLocales', ['en']);
        $source->setData('submissionChecklist', [
            'en' => '<ul><li>First item</li><li>Second item</li></ul>',
            'fr_CA' => '<ul><li>Alternate first item</li></ul>',
        ]);

        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $destination = new Journal();
        (new FullJournalImportExportDeployment($destination, null))->importContextData($document->documentElement);

        $this->assertSame('source-journal', $destination->getPath());
        $this->assertSame(9.0, $destination->getSequence());
        $this->assertFalse($destination->getEnabled());
        $this->assertSame('en', $destination->getPrimaryLocale());
        $this->assertSame('Source Journal', $destination->getData('name', 'en'));
        $this->assertSame('Alternate Source Journal', $destination->getData('name', 'fr_CA'));
        $this->assertSame('SJ', $destination->getData('acronym', 'en'));
        $this->assertSame('Editorial Team', $destination->getData('contactName'));
        $this->assertSame('editor@example.com', $destination->getData('contactEmail'));
        $this->assertSame(['en', 'fr_CA'], $destination->getData('supportedLocales'));
        $this->assertSame(['fr_CA', 'en'], $destination->getData('supportedFormLocales'));
        $this->assertSame(['en'], $destination->getData('supportedSubmissionLocales'));
        $this->assertSame([
            'en' => '<ul><li>First item</li><li>Second item</li></ul>',
            'fr_CA' => '<ul><li>Alternate first item</li></ul>',
        ], $destination->getData('submissionChecklist'));
    }

    public function testItSkipsChecklistLocalesOutsideSupportedFormLocales(): void
    {
        $source = new Journal();
        $source->setPath('source-journal');
        $source->setSequence(1);
        $source->setPrimaryLocale('en');
        $source->setData('name', ['en' => 'Journal']);
        $source->setData('contactName', 'Contact');
        $source->setData('contactEmail', 'contact@example.com');
        $source->setData('supportedLocales', ['en', 'es_ES']);
        $source->setData('supportedFormLocales', ['en']);
        $source->setData('supportedSubmissionLocales', ['en']);
        $source->setData('submissionChecklist', [
            'es_ES' => '<ul><li>Unsupported locale</li></ul>',
        ]);

        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();

        $checklistNodes = $document->getElementsByTagNameNS('http://pkp.sfu.ca', 'submission_checklist');
        $this->assertSame(0, $checklistNodes->length);
        $this->assertTrue($document->schemaValidate(dirname(__DIR__) . '/fullJournal.xsd'));
    }

    public function testItRejectsDuplicatedLocaleCodes(): void
    {
        $source = $this->minimalJournal();
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $locales = $document->getElementsByTagNameNS('http://pkp.sfu.ca', 'locales')->item(0);
        $locale = $document->getElementsByTagNameNS('http://pkp.sfu.ca', 'locale')->item(0);
        $locales->appendChild($locale->cloneNode(true));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicated locale: en');

        (new FullJournalImportExportDeployment(new Journal(), null))
            ->importContextData($document->documentElement);
    }

    public function testItRejectsAnOrderDeclaredForADisabledLocale(): void
    {
        $source = $this->minimalJournal();
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $locale = $document->getElementsByTagNameNS('http://pkp.sfu.ca', 'locale')->item(0);
        $locale->setAttribute('enabled_for_forms', 'false');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A disabled form locale must not declare form_order');

        (new FullJournalImportExportDeployment(new Journal(), null))
            ->importContextData($document->documentElement);
    }

    public function testItRejectsAnInvalidLocaleBooleanAttribute(): void
    {
        $source = $this->minimalJournal();
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $locale = $document->getElementsByTagNameNS('http://pkp.sfu.ca', 'locale')->item(0);
        $locale->setAttribute('enabled_for_ui', '1');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale boolean attribute: enabled_for_ui');

        (new FullJournalImportExportDeployment(new Journal(), null))
            ->importContextData($document->documentElement);
    }

    private function minimalJournal(): Journal
    {
        $journal = new Journal();
        $journal->setPath('minimal-journal');
        $journal->setSequence(1);
        $journal->setPrimaryLocale('en');
        $journal->setData('supportedLocales', ['en']);
        $journal->setData('supportedFormLocales', ['en']);
        $journal->setData('supportedSubmissionLocales', ['en']);
        $journal->setData('name', ['en' => 'Minimal Journal']);
        $journal->setData('contactName', 'Editorial Team');
        $journal->setData('contactEmail', 'editor@example.com');
        return $journal;
    }
}
