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
        $source->setPrimaryLocale('pt_BR');
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

        $this->assertSame('pt_BR', $destination->getPrimaryLocale());
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
        $source->setPrimaryLocale('pt_BR');
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
