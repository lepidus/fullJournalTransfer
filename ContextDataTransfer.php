<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

class ContextDataTransfer
{
    private const NAMESPACE = 'http://pkp.sfu.ca';

    public function export(Journal $journal): DOMDocument
    {
        $supportedLocales = $this->requireLocales($journal->getData('supportedLocales'), 'supportedLocales');
        $supportedFormLocales = $this->requireLocales(
            $journal->getData('supportedFormLocales'),
            'supportedFormLocales'
        );
        $supportedSubmissionLocales = $this->requireLocales(
            $journal->getData('supportedSubmissionLocales'),
            'supportedSubmissionLocales'
        );
        $primaryLocale = $journal->getPrimaryLocale();
        if (!is_string($primaryLocale) || !in_array($primaryLocale, $supportedLocales, true)) {
            throw new InvalidArgumentException('The primary locale must be included in the supported locales');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS(self::NAMESPACE, 'journal');
        $root->setAttribute('primary_locale', $primaryLocale);
        $document->appendChild($root);

        $localesNode = $document->createElementNS(self::NAMESPACE, 'locales');
        foreach ($supportedLocales as $locale) {
            $localeNode = $document->createElementNS(self::NAMESPACE, 'locale');
            $localeNode->setAttribute('code', $locale);
            $localeNode->setAttribute(
                'enabled_for_forms',
                in_array($locale, $supportedFormLocales, true) ? 'true' : 'false'
            );
            $formOrder = array_search($locale, $supportedFormLocales, true);
            if ($formOrder !== false) {
                $localeNode->setAttribute('form_order', (string) ($formOrder + 1));
            }
            $localeNode->setAttribute(
                'enabled_for_submissions',
                in_array($locale, $supportedSubmissionLocales, true) ? 'true' : 'false'
            );
            $submissionOrder = array_search($locale, $supportedSubmissionLocales, true);
            if ($submissionOrder !== false) {
                $localeNode->setAttribute('submission_order', (string) ($submissionOrder + 1));
            }
            $localesNode->appendChild($localeNode);
        }
        $root->appendChild($localesNode);

        $checklist = $journal->getData('submissionChecklist') ?? [];
        if (!is_array($checklist)) {
            throw new InvalidArgumentException('Submission checklist must be localized');
        }
        if ($checklist !== []) {
            $checklistNode = $document->createElementNS(self::NAMESPACE, 'submission_checklist');
            foreach ($checklist as $locale => $items) {
                if (!in_array($locale, $supportedFormLocales, true)) {
                    throw new InvalidArgumentException(
                        'Checklist locale is not supported by the destination form configuration: ' . $locale
                    );
                }
                if (!is_string($items)) {
                    throw new InvalidArgumentException('Submission checklist content must be localized HTML');
                }
                $contentNode = $document->createElementNS(self::NAMESPACE, 'content');
                $contentNode->setAttribute('locale', $locale);
                $contentNode->appendChild($document->createTextNode($items));
                $checklistNode->appendChild($contentNode);
            }
            $root->appendChild($checklistNode);
        }

        return $document;
    }

    public function import(DOMElement $root, Journal $journal): void
    {
        $journal->setPrimaryLocale($root->getAttribute('primary_locale'));
        $supportedLocales = [];
        $supportedFormLocales = [];
        $supportedSubmissionLocales = [];
        foreach ($root->getElementsByTagNameNS(self::NAMESPACE, 'locale') as $localeNode) {
            $locale = $localeNode->getAttribute('code');
            $supportedLocales[] = $locale;
            if ($localeNode->getAttribute('enabled_for_forms') === 'true') {
                $supportedFormLocales[(int) $localeNode->getAttribute('form_order')] = $locale;
            }
            if ($localeNode->getAttribute('enabled_for_submissions') === 'true') {
                $supportedSubmissionLocales[(int) $localeNode->getAttribute('submission_order')] = $locale;
            }
        }
        ksort($supportedFormLocales);
        ksort($supportedSubmissionLocales);
        $journal->setData('supportedLocales', $supportedLocales);
        $journal->setData('supportedFormLocales', array_values($supportedFormLocales));
        $journal->setData('supportedSubmissionLocales', array_values($supportedSubmissionLocales));

        $checklist = [];
        foreach ($root->getElementsByTagNameNS(self::NAMESPACE, 'content') as $contentNode) {
            $checklist[$contentNode->getAttribute('locale')] = $contentNode->textContent;
        }
        $journal->setData('submissionChecklist', $checklist);
    }

    private function requireLocales($locales, string $property): array
    {
        if (!is_array($locales) || $locales === []) {
            throw new InvalidArgumentException($property . ' must contain at least one locale');
        }
        foreach ($locales as $locale) {
            if (!is_string($locale) || preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale) !== 1) {
                throw new InvalidArgumentException('Invalid locale in ' . $property);
            }
        }
        return array_values(array_unique($locales));
    }
}
