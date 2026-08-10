<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\ContextDataTransfer;
use PHPUnit\Framework\TestCase;

class ContextDataTransferTest extends TestCase
{
    public function testItTransfersLocalesAndLocalizedSubmissionChecklist(): void
    {
        $source = new Journal();
        $source->setPath('source-journal');
        $source->setSequence(9);
        $source->setEnabled(true);
        $source->setPrimaryLocale('pt_BR');
        $source->setData('name', ['pt_BR' => 'Periódico de origem', 'en' => 'Source Journal']);
        $source->setData('acronym', ['pt_BR' => 'PO']);
        $source->setData('contactName', 'Equipe editorial');
        $source->setData('contactEmail', 'editor@example.com');
        $source->setData('supportedLocales', ['pt_BR', 'en']);
        $source->setData('supportedFormLocales', ['en', 'pt_BR']);
        $source->setData('supportedSubmissionLocales', ['pt_BR']);
        $source->setData('submissionChecklist', [
            'pt_BR' => '<ul><li>Primeiro item</li><li>Segundo item</li></ul>',
            'en' => '<ul><li>First item</li></ul>',
        ]);

        $document = (new ContextDataTransfer())->export($source);
        $destination = new Journal();
        (new ContextDataTransfer())->import($document->documentElement, $destination);

        $this->assertSame('source-journal', $destination->getPath());
        $this->assertSame(9.0, $destination->getSequence());
        $this->assertFalse($destination->getEnabled());
        $this->assertSame('pt_BR', $destination->getPrimaryLocale());
        $this->assertSame('Periódico de origem', $destination->getData('name', 'pt_BR'));
        $this->assertSame('Source Journal', $destination->getData('name', 'en'));
        $this->assertSame('PO', $destination->getData('acronym', 'pt_BR'));
        $this->assertSame('Equipe editorial', $destination->getData('contactName'));
        $this->assertSame('editor@example.com', $destination->getData('contactEmail'));
        $this->assertSame(['pt_BR', 'en'], $destination->getData('supportedLocales'));
        $this->assertSame(['en', 'pt_BR'], $destination->getData('supportedFormLocales'));
        $this->assertSame(['pt_BR'], $destination->getData('supportedSubmissionLocales'));
        $this->assertSame([
            'pt_BR' => '<ul><li>Primeiro item</li><li>Segundo item</li></ul>',
            'en' => '<ul><li>First item</li></ul>',
        ], $destination->getData('submissionChecklist'));
    }

    public function testItRejectsChecklistLocalesOutsideSupportedFormLocales(): void
    {
        $source = new Journal();
        $source->setPath('source-journal');
        $source->setSequence(1);
        $source->setPrimaryLocale('pt_BR');
        $source->setData('name', ['pt_BR' => 'Periódico']);
        $source->setData('contactName', 'Contato');
        $source->setData('contactEmail', 'contact@example.com');
        $source->setData('supportedLocales', ['pt_BR']);
        $source->setData('supportedFormLocales', ['pt_BR']);
        $source->setData('supportedSubmissionLocales', ['pt_BR']);
        $source->setData('submissionChecklist', [
            'en' => '<ul><li>Unexpected locale</li></ul>',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Checklist locale is not supported by the destination form configuration: en');

        (new ContextDataTransfer())->export($source);
    }
}
