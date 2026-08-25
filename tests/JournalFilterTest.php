<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use PHPUnit\Framework\TestCase;

class JournalFilterTest extends TestCase
{
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
}
