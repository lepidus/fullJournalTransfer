<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.classes.services.PKPSchemaService');
import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');
import('lib.pkp.plugins.importexport.users.PKPUserImportExportDeployment');

class NativeXmlJournalFilter extends NativeImportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML journal import');
        parent::__construct($filterGroup);
    }

    public function getSingularElementName()
    {
        return 'journal';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlJournalFilter';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $contextDAO = Application::get()->getContextDAO();

        $journal = $contextDAO->newDataObject();
        $journal->setSequence((int) $node->getAttribute('seq'));
        $journal->setPath($node->getAttribute('url_path'));
        $journal->setPrimaryLocale($node->getAttribute('primary_locale'));
        $journal->setEnabled((bool) $node->getAttribute('enabled'));
        $journal->setData('copyrightYearBasis', $node->getAttribute('copyright_year_basis'));
        $journal->setData('defaultReviewMode', (int) $node->getAttribute('default_review_mode'));
        $journal->setData('disableSubmissions', (bool) $node->getAttribute('disable_submissions') === 'true' ? true : false);
        $journal->setData('enableOai', (bool) $node->getAttribute('enable_oai'));
        $journal->setData('itemsPerPage', (int) $node->getAttribute('items_per_page'));
        $journal->setData('keywords', $node->getAttribute('keywords'));
        $journal->setData('membershipFee', (int) $node->getAttribute('membership_fee'));
        $journal->setData('numPageLinks', (int) $node->getAttribute('num_page_links'));
        $journal->setData('numWeeksPerResponse', (int) $node->getAttribute('num_weeks_per_response'));
        $journal->setData('numWeeksPerReview', (int) $node->getAttribute('num_weeks_per_review'));
        $journal->setData('publicationFee', (int) $node->getAttribute('publication_fee'));
        $journal->setData('purchaseArticleFee', (int) $node->getAttribute('purchase_article_fee'));
        $journal->setData('themePluginPath', $this->validateActiveTheme($node));
        $contextDAO->insertObject($journal);

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                $this->handleChildElement($n, $journal);
                if ($n->tagName === 'supported_locales') {
                    $this->installDefaultGenres($journal);
                }
            }
        }
        $contextDAO->updateObject($journal);

        return $journal;
    }

    private function validateActiveTheme($node)
    {
        $plugin = PluginRegistry::loadPlugin('themes', $node->getAttribute('theme_plugin_path'));
        if ($plugin) {
            return $node->getAttribute('theme_plugin_path');
        }
        return 'default';
    }

    public function installDefaultGenres($journal)
    {
        $genreDao = \DAORegistry::getDAO('GenreDAO');
        $genreDao->installDefaults($journal->getId(), $journal->getData('supportedLocales'));
    }

    public function handleChildElement($node, $journal)
    {
        $deployment = $this->getDeployment();
        $deployment->setContext($journal);

        $simpleNodeMapping = $this->getSimpleJournalNodeMapping();
        $localizedNodeMapping = $this->getLocalizedJournalNodeMapping();
        $localesNodeMapping = $this->getLocalesJournalNodeMapping();

        $propName = $this->snakeToCamel($node->tagName);
        if (in_array($node->tagName, $simpleNodeMapping)) {
            $journal->setData($propName, $node->textContent);
        }
        if (in_array($node->tagName, $localizedNodeMapping)) {
            list($locale, $value) = $this->parseLocalizedContent($node);
            if (empty($locale)) {
                $locale = $journal->getPrimaryLocale();
            }
            $journal->setData($propName, $value, $locale);
        }
        if (in_array($node->tagName, $localesNodeMapping)) {
            $locales = preg_split('/:/', $node->textContent);
            $journal->setData($propName, $locales);
        }
        if ($node->tagName == 'submission_checklist') {
            list($locale, $items) = $this->parseSubmissionChecklist($node);
            $journal->setData($propName, $items, $locale);
        }

        if (is_a($node, 'DOMElement')) {
            if ($node->tagName == 'plugins') {
                $this->parsePlugins($node);
            }
            if ($node->tagName == 'navigation_menu_items') {
                $this->parseNavigationMenuItems($node, $journal);
            }
            if ($node->tagName == 'navigation_menus') {
                $this->parseNavigationMenus($node, $journal);
            }
            if ($node->tagName == 'PKPUsers') {
                $this->parseUsers($node, $journal);
            }
            if ($node->tagName == 'sections') {
                $this->parseSections($node, $journal);
            }
            if ($node->tagName == 'extended_issues') {
                $this->parseIssues($node, $journal);
            }
            if ($node->tagName == 'extended_issue') {
                $this->parseIssue($node, $journal);
            }
            if ($node->tagName == 'extended_articles') {
                $this->parseArticles($node, $journal);
            }
        }
    }

    public function parseSubmissionChecklist($node)
    {
        $locale = $node->getAttribute('locale');
        $items = [];
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                $items[] = [
                    'order' => $n->getAttribute('order'),
                    'content' => $n->textContent
                ];
            }
        }
        return [$locale, $items];
    }

    public function parsePlugins($node)
    {
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName === 'plugin') {
                $this->parsePlugin($n);
            }
        }
    }

    public function parsePlugin($node)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>plugin');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $pluginDoc = new DOMDocument();
        $pluginDoc->appendChild($pluginDoc->importNode($node, true));
        return $importFilter->execute($pluginDoc);
    }

    public function parseNavigationMenuItems($node)
    {
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName === 'navigation_menu_item') {
                $this->parseNavigationMenuItem($n);
            }
        }
    }

    public function parseNavigationMenuItem($node)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>navigation-menu-item');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $navigationMenuItemDoc = new DOMDocument();
        $navigationMenuItemDoc->appendChild($navigationMenuItemDoc->importNode($node, true));
        return $importFilter->execute($navigationMenuItemDoc);
    }

    public function parseNavigationMenus($node)
    {
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName === 'navigation_menu') {
                $this->parseNavigationMenu($n);
            }
        }
    }

    public function parseNavigationMenu($node)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>navigation-menu');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $navigationMenuDoc = new DOMDocument();
        $navigationMenuDoc->appendChild($navigationMenuDoc->importNode($node, true));
        return $importFilter->execute($navigationMenuDoc);
    }

    public function parseUsers($node, $journal)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $userFilters = $filterDao->getObjectsByGroup('user-xml=>user');

        assert(count($userFilters) == 1);
        $filter = array_shift($userFilters);
        $filter->setDeployment(new PKPUserImportExportDeployment($journal, null));
        $usersDoc = new DOMDocument('1.0');
        $usersDoc->preserveWhiteSpace = false;
        $usersDoc->formatOutput = true;
        $usersDoc->appendChild($usersDoc->importNode($node, true));
        $usersXml = $usersDoc->saveXML();
        return $filter->execute($usersXml);
    }

    public function parseSections($node, $journal)
    {
        $deployment = $this->getDeployment();
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName  === 'section') {
                $this->parseSection($n, $journal);
            }
        }
    }

    public function parseSection($node, $journal)
    {
        $deployment = $this->getDeployment();

        $sectionDAO = DAORegistry::getDAO('SectionDAO');
        $section = $sectionDAO->newDataObject();
        $section->setContextId($journal->getId());
        $section->setReviewFormId($node->getAttribute('review_form_id'));
        $section->setSequence($node->getAttribute('seq'));
        $section->setEditorRestricted($node->getAttribute('editor_restricted'));
        $section->setMetaIndexed($node->getAttribute('meta_indexed'));
        $section->setMetaReviewed($node->getAttribute('meta_reviewed'));
        $section->setAbstractsNotRequired($node->getAttribute('abstracts_not_required'));
        $section->setHideAuthor($node->getAttribute('hide_author'));
        $section->setHideTitle($node->getAttribute('hide_title'));
        $section->setAbstractWordCount($node->getAttribute('abstract_word_count'));

        $unknownNodes = array();
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                switch ($n->tagName) {
                    case 'id':
                        $advice = $n->getAttribute('advice');
                        assert(!$advice || $advice == 'ignore');
                        break;
                    case 'abbrev':
                        list($locale, $value) = $this->parseLocalizedContent($n);
                        if (empty($locale)) {
                            $locale = $journal->getPrimaryLocale();
                        }
                        $section->setAbbrev($value, $locale);
                        break;
                    case 'policy':
                        list($locale, $value) = $this->parseLocalizedContent($n);
                        if (empty($locale)) {
                            $locale = $context->getPrimaryLocale();
                        }
                        $section->setPolicy($value, $locale);
                        break;
                    case 'title':
                        list($locale, $value) = $this->parseLocalizedContent($n);
                        if (empty($locale)) {
                            $locale = $context->getPrimaryLocale();
                        }
                        $section->setTitle($value, $locale);
                        break;
                    default:
                        $unknownNodes[] = $n->tagName;
                }
            }
        }

        $sectionId = $sectionDAO->insertObject($section);
        if (count($unknownNodes)) {
            foreach ($unknownNodes as $tagName) {
                $deployment->addWarning(
                    ASSOC_TYPE_SECTION,
                    $sectionId,
                    __('plugins.importexport.common.error.unknownElement', ['param' => $tagName])
                );
            }
        }
        $deployment->addProcessedObjectId(ASSOC_TYPE_SECTION, $sectionId);
    }

    public function parseIssues($node, $journal)
    {
        $deployment = $this->getDeployment();
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName  === 'extended_issue') {
                $this->parseIssue($n, $journal);
            }
        }
    }

    public function parseIssue($node, $journal)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>extended-issue');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $issueDoc = new DOMDocument();
        $issueDoc->appendChild($issueDoc->importNode($node, true));
        $importedObjects = $importFilter->execute($issueDoc);
        return $importedObjects;
    }

    public function parseArticles($node, $journal)
    {
        $deployment = $this->getDeployment();
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName  === 'extended_article') {
                $this->parseArticle($n, $journal);
            }
        }
    }

    public function parseArticle($node, $journal)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>extended-article');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $articleDoc = new DOMDocument();
        $articleDoc->appendChild($articleDoc->importNode($node, true));
        return $importFilter->execute($articleDoc);
    }

    private function getSimpleJournalNodeMapping()
    {
        return [
            'email_signature',
            'contact_email',
            'contact_name',
            'contact_phone',
            'mailing_address',
            'online_issn',
            'print_issn',
            'publisher_institution',
            'support_email',
            'support_name',
            'support_phone'
        ];
    }

    private function getLocalizedJournalNodeMapping()
    {
        return [
            'acronym',
            'author_information',
            'clockss_license',
            'librarian_information',
            'lockss_license',
            'name',
            'open_access_policy',
            'privacy_statement',
            'reader_information',
            'abbreviation',
            'about',
            'contact_affiliation',
            'description',
            'editorial_team',
        ];
    }

    private function getLocalesJournalNodeMapping()
    {
        return [
            'supported_locales',
            'supported_form_locales',
            'supported_submission_locales',
        ];
    }

    private function snakeToCamel($text)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $text))));
    }
}
