<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\ThemeSettingsTransfer;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\ThemePlugin;

class NativeXmlJournalFilter extends NativeImportFilter
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

    public function hydrate(DOMElement $node, Journal $journal): void
    {
        $path = $node->getAttribute('url_path');
        if (preg_match('/^[a-zA-Z0-9]+(?:[-_][a-zA-Z0-9]+)*$/', $path) !== 1) {
            throw new InvalidArgumentException('The context path is invalid');
        }
        $sequence = $node->getAttribute('sequence');
        if (!is_numeric($sequence)) {
            throw new InvalidArgumentException('The context sequence is invalid');
        }
        $journal->setPath($path);
        $journal->setSequence((float) $sequence);
        $journal->setEnabled(false);
        $journal->setPrimaryLocale($node->getAttribute('primary_locale'));
        $supportedLocales = [];
        $supportedFormLocales = [];
        $supportedSubmissionLocales = [];
        foreach ($node->getElementsByTagNameNS(self::NAMESPACE, 'locale') as $localeNode) {
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
        foreach ($node->getElementsByTagNameNS(self::NAMESPACE, 'content') as $contentNode) {
            $checklist[$contentNode->getAttribute('locale')] = $contentNode->textContent;
        }
        $journal->setData('submissionChecklist', $checklist);
        $this->importSettings($node, $journal);
        $themeNode = $this->optionalChild($node, 'theme');
        if ($themeNode) {
            $theme = $this->getImportedTheme($themeNode);
            $journal->setData('themePluginPath', $theme->getDirName());
        }
        $this->validateRequiredSettings($journal);
    }

    public function handleElement($node)
    {
        return DB::transaction(function () use ($node): Journal {
            $contextDao = Application::get()->getContextDAO();
            $journal = $contextDao->newDataObject();
            $this->hydrate($node, $journal);
            if ($contextDao->getByPath($journal->getPath())) {
                throw new InvalidArgumentException('A context with this path already exists');
            }
            $contextId = $contextDao->insertObject($journal);
            $createdJournal = $contextDao->getById($contextId);
            if (!$createdJournal instanceof Journal) {
                throw new InvalidArgumentException('The imported context could not be persisted');
            }
            $deployment = $this->getDeployment();
            $deployment->setContext($createdJournal);
            $themeNode = $this->optionalChild($node, 'theme');
            if ($themeNode) {
                $this->importThemeOptions($themeNode, $createdJournal);
            }
            $operations = [
                'users' => 'importUsers',
                'reference_data' => 'importReferenceData',
                'native_data' => 'importNativeData',
                'workflow_history' => 'importWorkflow',
                'metrics' => 'importMetrics',
            ];
            foreach ($operations as $element => $method) {
                $child = $this->optionalChild($node, $element);
                if ($child) {
                    $deployment->{$method}($child);
                }
            }
            return $createdJournal;
        });
    }

    public function getPluralElementName()
    {
        return 'journals';
    }

    public function getSingularElementName()
    {
        return 'journal';
    }

    private function importSettings(DOMElement $node, Journal $journal): void
    {
        $containers = $node->getElementsByTagNameNS(self::NAMESPACE, 'context_settings');
        if ($containers->length !== 1) {
            throw new InvalidArgumentException('Expected exactly one context_settings element');
        }
        $seen = [];
        foreach ($containers->item(0)->childNodes as $settingNode) {
            if (!$settingNode instanceof DOMElement || $settingNode->localName !== 'setting') {
                continue;
            }
            $property = $settingNode->getAttribute('name');
            $locale = trim($settingNode->getAttribute('locale'));
            $key = $property . ':' . $locale;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicated context setting: ' . $property);
            }
            $seen[$key] = true;
            if ($locale === '') {
                if (!isset(self::SCALAR_SETTINGS[$property])) {
                    throw new InvalidArgumentException('Context setting is not allowed: ' . $property);
                }
                $journal->setData($property, $this->decodeSetting(
                    $settingNode->textContent,
                    self::SCALAR_SETTINGS[$property]
                ));
                continue;
            }
            if (!in_array($property, self::LOCALIZED_SETTINGS, true)) {
                throw new InvalidArgumentException('Localized context setting is not allowed: ' . $property);
            }
            $journal->setData($property, $settingNode->textContent, $locale);
        }
    }

    private function getImportedTheme(DOMElement $node): ThemePlugin
    {
        $pluginPath = $node->getAttribute('plugin_path');
        $theme = (new ThemeSettingsTransfer())->findInstalledThemeOrDefault($pluginPath);
        if ($theme->getDirName() === $pluginPath && $node->getAttribute('plugin_name') !== $theme->getName()) {
            throw new InvalidArgumentException('The selected theme identity does not match the installed plugin');
        }
        return $theme;
    }

    private function importThemeOptions(DOMElement $node, Journal $journal): void
    {
        $theme = $this->getImportedTheme($node);
        $theme->init();
        if ($theme->getDirName() !== $node->getAttribute('plugin_path')) {
            $theme->updateSetting((int) $journal->getId(), 'enabled', true, 'bool');
            return;
        }
        $options = [];
        foreach ($node->childNodes as $optionNode) {
            if (!$optionNode instanceof DOMElement || $optionNode->localName !== 'option') {
                continue;
            }
            $name = $optionNode->getAttribute('name');
            if (array_key_exists($name, $options)) {
                throw new InvalidArgumentException('Duplicated theme option: ' . $name);
            }
            if ($theme->getOptionConfig($name) === false) {
                throw new InvalidArgumentException('Theme option is not supported: ' . $name);
            }
            try {
                $value = json_decode($optionNode->textContent, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('Theme option contains invalid JSON: ' . $name, 0, $exception);
            }
            if ($value === null) {
                throw new InvalidArgumentException('Theme option value must not be null: ' . $name);
            }
            $options[$name] = $value;
        }
        $errors = $theme->validateOptions(
            array_merge($options, ['themePluginPath' => $theme->getDirName()]),
            $theme->getDirName(),
            (int) $journal->getId(),
            Application::get()->getRequest()
        );
        if ($errors !== []) {
            throw new InvalidArgumentException('The imported theme options are invalid');
        }
        $theme->updateSetting((int) $journal->getId(), 'enabled', true, 'bool');
        foreach ($options as $name => $value) {
            $theme->saveOption($name, $value, (int) $journal->getId());
        }
    }

    private function optionalChild(DOMElement $parent, string $name): ?DOMElement
    {
        $matches = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $matches[] = $child;
            }
        }
        if (count($matches) > 1) {
            throw new InvalidArgumentException('Expected at most one ' . $name . ' element');
        }
        return $matches[0] ?? null;
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
        $email = $journal->getData('contactEmail');
        if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The context must have a valid contact email');
        }
    }
}
