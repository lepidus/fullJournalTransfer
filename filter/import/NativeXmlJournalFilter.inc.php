<?php

/**
 * Copyright (c) 2014-2023 Lepidus Tecnologia
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
        $journal->setData('themePluginPath', $node->getAttribute('theme_plugin_path'));
        $contextDAO->insertObject($journal);

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                $this->handleChildElement($n, $journal);
            }
        }
        $contextDAO->updateObject($journal);

        return $journal;
    }

    public function handleChildElement($n, $journal)
    {
        $deployment = $this->getDeployment();
        $deployment->setContext($journal);

        $simpleNodeMapping = $this->getSimpleJournalNodeMapping();
        $localizedNodeMapping = $this->getLocalizedJournalNodeMapping();
        $localesNodeMapping = $this->getLocalesJournalNodeMapping();

        $propName = $this->snakeToCamel($n->tagName);
        if (in_array($n->tagName, $simpleNodeMapping)) {
            $journal->setData($propName, $n->textContent);
        }
        if (in_array($n->tagName, $localizedNodeMapping)) {
            list($locale, $value) = $this->parseLocalizedContent($n);
            if (empty($locale)) {
                $locale = $journal->getPrimaryLocale();
            }
            $journal->setData($propName, $value, $locale);
        }
        if (in_array($n->tagName, $localesNodeMapping)) {
            $locales = preg_split('/:/', $n->textContent);
            $journal->setData($propName, $locales);
        }
        if ($n->tagName == 'submission_checklist') {
            list($locale, $items) = $this->parseSubmissionChecklist($n);
            $journal->setData($propName, $items, $locale);
        }

        if (is_a($n, 'DOMElement')) {
            if ($n->tagName == 'plugins') {
                $this->parsePlugins($n);
            }
            if ($n->tagName == 'navigation_menu_items') {
                $this->parseNavigationMenuItems($n, $journal);
            }
            if ($n->tagName == 'navigation_menus') {
                $this->parseNavigationMenus($n, $journal);
            }
            if ($n->tagName == 'PKPUsers') {
                $this->parseUsers($n, $journal);
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
