<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use APP\file\PublicFileManager;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\JournalLocalePolicy;
use APP\plugins\importexport\fullJournalTransfer\JournalSettingsPolicy;
use APP\plugins\importexport\fullJournalTransfer\ThemeSettingsTransfer;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\ThemePlugin;
use PKP\site\Site;
use RuntimeException;

class NativeXmlJournalFilter extends NativeImportFilter
{
    private const NAMESPACE = 'http://pkp.sfu.ca';
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
        $primaryLocale = $node->getAttribute('primary_locale');
        $journal->setPrimaryLocale($primaryLocale);
        $supportedLocales = [];
        $supportedFormLocales = [];
        $supportedSubmissionLocales = [];
        $seenLocales = [];
        foreach ($node->getElementsByTagNameNS(self::NAMESPACE, 'locale') as $localeNode) {
            $locale = $localeNode->getAttribute('code');
            if (isset($seenLocales[$locale])) {
                throw new InvalidArgumentException('Duplicated locale: ' . $locale);
            }
            $seenLocales[$locale] = true;
            if ($this->readBooleanAttribute($localeNode, 'enabled_for_ui')) {
                $supportedLocales[] = $locale;
            }
            if ($this->readBooleanAttribute($localeNode, 'enabled_for_forms')) {
                $formOrder = $this->readOrderAttribute($localeNode, 'form_order');
                if (isset($supportedFormLocales[$formOrder])) {
                    throw new InvalidArgumentException(sprintf(
                        'Duplicated form_order "%d" for locales "%s" and "%s"%s',
                        $formOrder,
                        $supportedFormLocales[$formOrder],
                        $locale,
                        $this->line($localeNode)
                    ));
                }
                $supportedFormLocales[$formOrder] = $locale;
            } elseif ($localeNode->hasAttribute('form_order')) {
                throw new InvalidArgumentException(sprintf(
                    'Locale "%s" is disabled for forms but declares form_order "%s"%s',
                    $locale,
                    $localeNode->getAttribute('form_order'),
                    $this->line($localeNode)
                ));
            }
            if ($this->readBooleanAttribute($localeNode, 'enabled_for_submissions')) {
                $submissionOrder = $this->readOrderAttribute($localeNode, 'submission_order');
                if (isset($supportedSubmissionLocales[$submissionOrder])) {
                    throw new InvalidArgumentException(sprintf(
                        'Duplicated submission_order "%d" for locales "%s" and "%s"%s',
                        $submissionOrder,
                        $supportedSubmissionLocales[$submissionOrder],
                        $locale,
                        $this->line($localeNode)
                    ));
                }
                $supportedSubmissionLocales[$submissionOrder] = $locale;
            } elseif ($localeNode->hasAttribute('submission_order')) {
                throw new InvalidArgumentException(sprintf(
                    'Locale "%s" is disabled for submissions but declares submission_order "%s"%s',
                    $locale,
                    $localeNode->getAttribute('submission_order'),
                    $this->line($localeNode)
                ));
            }
        }
        ksort($supportedFormLocales);
        ksort($supportedSubmissionLocales);
        $site = Application::get()->getRequest()->getSite();
        if (!$site instanceof Site) {
            throw new InvalidArgumentException('The destination OJS site could not be loaded');
        }
        $locales = (new JournalLocalePolicy())->resolve(
            $supportedLocales,
            array_values($supportedFormLocales),
            array_values($supportedSubmissionLocales),
            $primaryLocale,
            $site->getSupportedLocales()
        );
        $journal->setData('supportedLocales', $locales['supportedLocales']);
        $journal->setData('supportedFormLocales', $locales['supportedFormLocales']);
        $journal->setData('supportedSubmissionLocales', $locales['supportedSubmissionLocales']);

        $checklist = [];
        $checklistNode = $this->optionalChild($node, 'submission_checklist');
        foreach ($checklistNode?->childNodes ?? [] as $contentNode) {
            if (!$contentNode instanceof DOMElement || $contentNode->localName !== 'content') {
                continue;
            }
            $locale = $contentNode->getAttribute('locale');
            if (in_array($locale, $locales['supportedFormLocales'], true)) {
                $checklist[$locale] = $contentNode->textContent;
            }
        }
        $journal->setData('submissionChecklist', $checklist);
        $acceptedLocales = array_values(array_unique(array_merge(...array_values($locales))));
        $this->importSettings($node, $journal, $acceptedLocales);
        $themeNode = $this->optionalChild($node, 'theme');
        if ($themeNode) {
            $theme = $this->getImportedTheme($themeNode);
            $journal->setData('themePluginPath', $theme->getDirName());
        }
        (new JournalSettingsPolicy())->validateJournal($journal);
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
            $publicFileManager = new PublicFileManager();
            $publicFilesPath = $publicFileManager->getContextFilesPath($contextId);
            $publicFilesPathExisted = file_exists($publicFilesPath);
            if (!$publicFileManager->mkdirtree($publicFilesPath) || !is_dir($publicFilesPath)) {
                throw new RuntimeException('The imported context public files directory could not be created');
            }
            if (!$publicFilesPathExisted) {
                $absolutePublicFilesPath = realpath($publicFilesPath);
                if ($absolutePublicFilesPath === false) {
                    throw new RuntimeException('The imported context public files directory could not be resolved');
                }
                $deployment->recordCreatedDirectory($absolutePublicFilesPath);
            }
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
            $deployment->validateImportedIntegrity();
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

    /** @param list<string> $acceptedLocales */
    private function importSettings(DOMElement $node, Journal $journal, array $acceptedLocales): void
    {
        $policy = new JournalSettingsPolicy();
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
                throw new InvalidArgumentException(sprintf(
                    'Duplicated context setting "%s" for locale "%s"%s',
                    $property,
                    $locale,
                    $this->line($settingNode)
                ));
            }
            $seen[$key] = true;
            $definition = $policy->definition($property);
            $type = $settingNode->getAttribute('type');
            if ($locale === '') {
                if ($definition['localized']) {
                    throw new InvalidArgumentException('Context setting must be localized: ' . $property);
                }
                $journal->setData($property, $policy->decode($property, $type, $settingNode->textContent));
                continue;
            }
            if (!$definition['localized']) {
                throw new InvalidArgumentException('Localized context setting is not allowed: ' . $property);
            }
            if (!in_array($locale, $acceptedLocales, true)) {
                continue;
            }
            $journal->setData($property, $policy->decode($property, $type, $settingNode->textContent), $locale);
        }
    }

    private function readBooleanAttribute(DOMElement $node, string $name): bool
    {
        $value = $node->getAttribute($name);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s value "%s" for locale "%s"%s; expected "true" or "false"',
                $name,
                $value,
                $node->getAttribute('code'),
                $this->line($node)
            ));
        }
        return $value === 'true';
    }

    private function readOrderAttribute(DOMElement $node, string $name): int
    {
        $value = $node->getAttribute($name);
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s value "%s" for locale "%s"%s; expected a positive integer',
                $name,
                $value,
                $node->getAttribute('code'),
                $this->line($node)
            ));
        }
        return (int) $value;
    }

    private function line(DOMElement $node): string
    {
        return $node->getLineNo() > 0 ? ' at line ' . $node->getLineNo() : '';
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
