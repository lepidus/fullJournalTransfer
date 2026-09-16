<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\policy;

use InvalidArgumentException;

class JournalLocalePolicy
{
    public function resolve(
        array $supportedLocales,
        array $supportedFormLocales,
        array $supportedSubmissionLocales,
        string $primaryLocale,
        array $availableLocales
    ): array {
        if (!in_array($primaryLocale, $availableLocales, true)) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.journalPrimaryLocaleUnavailable',
                [
                    'primaryLocale' => $primaryLocale,
                ]
            ));
        }

        $supportedLocales = $this->intersect($supportedLocales, $availableLocales);
        $supportedFormLocales = $this->intersect($supportedFormLocales, $availableLocales);
        $supportedSubmissionLocales = $this->intersect($supportedSubmissionLocales, $availableLocales);

        if (!in_array($primaryLocale, $supportedLocales, true)) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.primaryLocaleNotSupported'));
        }
        if ($supportedFormLocales === []) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.noJournalFormLocaleAvailableDestinationOjs'));
        }
        if ($supportedSubmissionLocales === []) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.noJournalSubmissionLocaleAvailableDestinationOjs'));
        }

        return compact('supportedLocales', 'supportedFormLocales', 'supportedSubmissionLocales');
    }

    private function intersect(array $locales, array $availableLocales): array
    {
        return array_values(array_filter(
            $locales,
            static fn (string $locale): bool => in_array($locale, $availableLocales, true)
        ));
    }
}
