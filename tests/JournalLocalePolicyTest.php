<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\plugins\importexport\fullJournalTransfer\JournalLocalePolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JournalLocalePolicyTest extends TestCase
{
    public function testItIntersectsTheThreeListsIndependentlyAndPreservesOrder(): void
    {
        $locales = (new JournalLocalePolicy())->resolve(
            ['en', 'es', 'pt_BR'],
            ['es', 'en'],
            ['pt_BR', 'en'],
            'en',
            ['en', 'pt_BR']
        );

        $this->assertSame(['en', 'pt_BR'], $locales['supportedLocales']);
        $this->assertSame(['en'], $locales['supportedFormLocales']);
        $this->assertSame(['pt_BR', 'en'], $locales['supportedSubmissionLocales']);
    }

    public function testItRejectsAnUnavailablePrimaryLocale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The journal primary locale (pt_BR) is not available in the destination OJS.');

        (new JournalLocalePolicy())->resolve(['pt_BR'], ['pt_BR'], ['pt_BR'], 'pt_BR', ['en']);
    }

    public function testItRejectsAnEmptyEffectiveFormLocaleList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No journal form locale is available in the destination OJS.');

        (new JournalLocalePolicy())->resolve(['en'], ['es'], ['en'], 'en', ['en']);
    }

    public function testItRejectsAnEmptyEffectiveSubmissionLocaleList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No journal submission locale is available in the destination OJS.');

        (new JournalLocalePolicy())->resolve(['en'], ['en'], ['es'], 'en', ['en']);
    }

}
