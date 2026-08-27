<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\ThemeSettingsTransfer;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class JournalNativeXmlFilter extends NativeExportFilter
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

    public function &process(&$journal)
    {
        if (!$journal instanceof Journal) {
            throw new InvalidArgumentException('Expected a journal for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $this->createJournalNode($document, $journal);
        $document->appendChild($root);
        return $document;
    }

    public function createJournalNode(DOMDocument $document, Journal $journal): DOMElement
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

        $root = $document->createElementNS(self::NAMESPACE, 'journal');
        $root->setAttribute('primary_locale', $primaryLocale);
        $path = $journal->getPath();
        if (!is_string($path) || preg_match('/^[a-zA-Z0-9]+(?:[-_][a-zA-Z0-9]+)*$/', $path) !== 1) {
            throw new InvalidArgumentException('The context path is invalid');
        }
        $root->setAttribute('url_path', $path);
        $root->setAttribute('sequence', (string) $journal->getSequence());
        $root->setAttribute('source_enabled', $journal->getEnabled() ? 'true' : 'false');

        $this->addLocales($document, $root, $supportedLocales, $supportedFormLocales, $supportedSubmissionLocales);
        $this->addSubmissionChecklist($document, $root, $journal, $supportedFormLocales);
        $this->addSettings($document, $root, $journal);
        $this->addTheme($document, $root, $journal);
        $this->validateRequiredSettings($journal);
        if ((int) $journal->getId() > 0) {
            foreach (['exportUsers', 'exportReferenceData', 'exportNativeData', 'exportWorkflow',
                'exportMetrics'] as $method) {
                $childDocument = $this->getDeployment()->{$method}();
                $root->appendChild($document->importNode($childDocument->documentElement, true));
            }
        }
        return $root;
    }

    public function addLocales(
        DOMDocument $document,
        DOMElement $root,
        array $supportedLocales,
        array $supportedFormLocales,
        array $supportedSubmissionLocales
    ): void {
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
    }

    public function addSubmissionChecklist(
        DOMDocument $document,
        DOMElement $root,
        Journal $journal,
        array $supportedFormLocales
    ): void {
        $checklist = $journal->getData('submissionChecklist') ?? [];
        if (!is_array($checklist)) {
            throw new InvalidArgumentException('Submission checklist must be localized');
        }
        if ($checklist !== []) {
            $checklistNode = $document->createElementNS(self::NAMESPACE, 'submission_checklist');
            foreach ($checklist as $locale => $items) {
                if (!in_array($locale, $supportedFormLocales, true)) {
                    continue;
                }
                if (!is_string($items)) {
                    throw new InvalidArgumentException('Submission checklist content must be localized HTML');
                }
                $contentNode = $document->createElementNS(self::NAMESPACE, 'content');
                $contentNode->setAttribute('locale', $locale);
                $contentNode->appendChild($document->createTextNode($items));
                $checklistNode->appendChild($contentNode);
            }
            if ($checklistNode->hasChildNodes()) {
                $root->appendChild($checklistNode);
            }
        }
    }

    public function addSettings(DOMDocument $document, DOMElement $root, Journal $journal): void
    {
        $settingsNode = $document->createElementNS(self::NAMESPACE, 'context_settings');
        foreach (self::SCALAR_SETTINGS as $property => $type) {
            $value = $journal->getData($property);
            if ($value === null) {
                continue;
            }
            $this->addContextSetting($document, $settingsNode, $property, $type, $value);
        }
        foreach (self::LOCALIZED_SETTINGS as $property) {
            foreach ((array) $journal->getData($property, null) as $locale => $value) {
                if ($value === null) {
                    continue;
                }
                $this->addContextSetting(
                    $document,
                    $settingsNode,
                    $property,
                    'string',
                    $value,
                    (string) $locale
                );
            }
        }
        $root->appendChild($settingsNode);
    }

    public function addTheme(DOMDocument $document, DOMElement $root, Journal $journal): void
    {
        $pluginPath = $journal->getData('themePluginPath');
        if ($pluginPath === null || $pluginPath === '') {
            return;
        }
        if (!is_string($pluginPath)) {
            throw new InvalidArgumentException('The selected theme path is invalid');
        }
        $theme = (new ThemeSettingsTransfer())->findInstalledTheme($pluginPath);
        $theme->init();
        $themeNode = $document->createElementNS(self::NAMESPACE, 'theme');
        $themeNode->setAttribute('plugin_path', $pluginPath);
        $themeNode->setAttribute('plugin_name', $theme->getName());
        if ((int) $journal->getId() > 0) {
            foreach ($theme->getOptionValues((int) $journal->getId()) as $name => $value) {
                if ($value === null) {
                    continue;
                }
                $optionNode = $document->createElementNS(self::NAMESPACE, 'option');
                $optionNode->setAttribute('name', $name);
                $optionNode->appendChild($document->createTextNode(json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                )));
                $themeNode->appendChild($optionNode);
            }
        }
        $root->appendChild($themeNode);
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

    private function addContextSetting(
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
