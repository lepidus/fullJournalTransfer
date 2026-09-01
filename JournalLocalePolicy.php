<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use InvalidArgumentException;

class JournalLocalePolicy
{
    /**
     * @param list<string> $supportedLocales
     * @param list<string> $supportedFormLocales
     * @param list<string> $supportedSubmissionLocales
     * @param list<string> $availableLocales
     *
     * @return array{supportedLocales: list<string>, supportedFormLocales: list<string>, supportedSubmissionLocales: list<string>}
     */
    public function resolve(
        array $supportedLocales,
        array $supportedFormLocales,
        array $supportedSubmissionLocales,
        string $primaryLocale,
        array $availableLocales
    ): array {
        if (!in_array($primaryLocale, $availableLocales, true)) {
            throw new InvalidArgumentException(sprintf(
                'The journal primary locale (%s) is not available in the destination OJS.',
                $primaryLocale
            ));
        }

        $supportedLocales = $this->intersect($supportedLocales, $availableLocales);
        $supportedFormLocales = $this->intersect($supportedFormLocales, $availableLocales);
        $supportedSubmissionLocales = $this->intersect($supportedSubmissionLocales, $availableLocales);

        if (!in_array($primaryLocale, $supportedLocales, true)) {
            throw new InvalidArgumentException('The primary locale must be included in the supported locales');
        }
        if ($supportedFormLocales === []) {
            throw new InvalidArgumentException('No journal form locale is available in the destination OJS.');
        }
        if ($supportedSubmissionLocales === []) {
            throw new InvalidArgumentException('No journal submission locale is available in the destination OJS.');
        }

        return compact('supportedLocales', 'supportedFormLocales', 'supportedSubmissionLocales');
    }

    /** @param list<string> $locales @param list<string> $availableLocales @return list<string> */
    private function intersect(array $locales, array $availableLocales): array
    {
        return array_values(array_filter(
            $locales,
            static fn (string $locale): bool => in_array($locale, $availableLocales, true)
        ));
    }
}
