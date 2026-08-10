<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

class JournalXmlSupport
{
    private const NAMESPACE = 'http://pkp.sfu.ca';
    private const SCALAR_SETTINGS = [
        'contactEmail' => 'string',
        'contactName' => 'string',
        'contactPhone' => 'string',
        'mailingAddress' => 'string',
        'onlineIssn' => 'string',
        'printIssn' => 'string',
        'publisherInstitution' => 'string',
        'supportEmail' => 'string',
        'supportName' => 'string',
        'supportPhone' => 'string',
        'copyrightYearBasis' => 'string',
        'defaultReviewMode' => 'integer',
        'enableOai' => 'boolean',
        'itemsPerPage' => 'integer',
        'numPageLinks' => 'integer',
        'numWeeksPerResponse' => 'integer',
        'numWeeksPerReview' => 'integer',
    ];
    private const LOCALIZED_SETTINGS = [
        'name',
        'acronym',
        'abbreviation',
        'about',
        'authorInformation',
        'librarianInformation',
        'readerInformation',
        'privacyStatement',
        'openAccessPolicy',
        'contactAffiliation',
        'description',
        'editorialTeam',
    ];

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
        $path = $journal->getPath();
        if (!is_string($path) || preg_match('/^[a-zA-Z0-9]+(?:[-_][a-zA-Z0-9]+)*$/', $path) !== 1) {
            throw new InvalidArgumentException('The context path is invalid');
        }
        $root->setAttribute('url_path', $path);
        $root->setAttribute('sequence', (string) $journal->getSequence());
        $root->setAttribute('source_enabled', $journal->getEnabled() ? 'true' : 'false');
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

        $settingsNode = $document->createElementNS(self::NAMESPACE, 'context_settings');
        foreach (self::SCALAR_SETTINGS as $property => $type) {
            $value = $journal->getData($property);
            if ($value === null) {
                continue;
            }
            $this->appendSetting($document, $settingsNode, $property, $type, $value);
        }
        foreach (self::LOCALIZED_SETTINGS as $property) {
            foreach ((array) $journal->getData($property, null) as $locale => $value) {
                if ($value === null) {
                    continue;
                }
                $this->appendSetting($document, $settingsNode, $property, 'string', $value, (string) $locale);
            }
        }
        $root->appendChild($settingsNode);
        $this->validateRequiredSettings($journal);

        return $document;
    }

    public function import(DOMElement $root, Journal $journal): void
    {
        $path = $root->getAttribute('url_path');
        if (preg_match('/^[a-zA-Z0-9]+(?:[-_][a-zA-Z0-9]+)*$/', $path) !== 1) {
            throw new InvalidArgumentException('The context path is invalid');
        }
        $sequence = $root->getAttribute('sequence');
        if (!is_numeric($sequence)) {
            throw new InvalidArgumentException('The context sequence is invalid');
        }
        $journal->setPath($path);
        $journal->setSequence((float) $sequence);
        $journal->setEnabled(false);
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

        $settingsContainers = $root->getElementsByTagNameNS(self::NAMESPACE, 'context_settings');
        if ($settingsContainers->length !== 1) {
            throw new InvalidArgumentException('Expected exactly one context_settings element');
        }
        $seenSettings = [];
        foreach ($settingsContainers->item(0)->childNodes as $settingNode) {
            if (!$settingNode instanceof DOMElement || $settingNode->localName !== 'setting') {
                continue;
            }
            $property = $settingNode->getAttribute('name');
            $locale = trim($settingNode->getAttribute('locale'));
            $key = $property . ':' . $locale;
            if (isset($seenSettings[$key])) {
                throw new InvalidArgumentException('Duplicated context setting: ' . $property);
            }
            $seenSettings[$key] = true;
            if ($locale === '') {
                if (!isset(self::SCALAR_SETTINGS[$property])) {
                    throw new InvalidArgumentException('Context setting is not allowed: ' . $property);
                }
                $journal->setData(
                    $property,
                    $this->decodeSetting($settingNode->textContent, self::SCALAR_SETTINGS[$property])
                );
            } else {
                if (!in_array($property, self::LOCALIZED_SETTINGS, true)) {
                    throw new InvalidArgumentException('Localized context setting is not allowed: ' . $property);
                }
                $journal->setData($property, $settingNode->textContent, $locale);
            }
        }
        $this->validateRequiredSettings($journal);
    }

    public function create(DOMElement $root): Journal
    {
        $contextDao = Application::get()->getContextDAO();
        $journal = $contextDao->newDataObject();
        $this->import($root, $journal);
        if ($contextDao->getByPath($journal->getPath())) {
            throw new InvalidArgumentException('A context with this path already exists');
        }
        $contextId = $contextDao->insertObject($journal);
        $createdJournal = $contextDao->getById($contextId);
        if (!$createdJournal instanceof Journal) {
            throw new InvalidArgumentException('The imported context could not be persisted');
        }
        return $createdJournal;
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

    private function appendSetting(
        DOMDocument $document,
        DOMElement $parent,
        string $property,
        string $type,
        $value,
        ?string $locale = null
    ): void {
        $node = $document->createElementNS(self::NAMESPACE, 'setting');
        $node->setAttribute('name', $property);
        $node->setAttribute('type', $type);
        if ($locale !== null) {
            $node->setAttribute('locale', $locale);
        }
        if ($type === 'boolean') {
            $value = $value ? 'true' : 'false';
        }
        $node->appendChild($document->createTextNode((string) $value));
        $parent->appendChild($node);
    }

    private function decodeSetting(string $value, string $type)
    {
        if ($type === 'boolean') {
            if (!in_array($value, ['true', 'false'], true)) {
                throw new InvalidArgumentException('Invalid boolean context setting');
            }
            return $value === 'true';
        }
        if ($type === 'integer') {
            if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('Invalid integer context setting');
            }
            return (int) $value;
        }
        return $value;
    }

    private function validateRequiredSettings(Journal $journal): void
    {
        $names = $journal->getData('name', null);
        if (!is_array($names) || array_filter($names, 'is_string') === []) {
            throw new InvalidArgumentException('The context must have a localized name');
        }
        if (!is_string($journal->getData('contactName')) || trim($journal->getData('contactName')) === '') {
            throw new InvalidArgumentException('The context must have a contact name');
        }
        $contactEmail = $journal->getData('contactEmail');
        if (!is_string($contactEmail) || filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The context must have a valid contact email');
        }
    }
}
