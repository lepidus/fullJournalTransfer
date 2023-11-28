<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');
import('lib.pkp.classes.services.PKPSchemaService');

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

        $this->createJournalDirectories($journal);

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
        }
    }

    public function parseSubmissionChecklist($element)
    {
        $locale = $element->getAttribute('locale');
        $items = [];
        for ($n = $element->firstChild; $n !== null; $n = $n->nextSibling) {
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

    public function parsePlugin($n)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>plugin');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $pluginDoc = new DOMDocument();
        $pluginDoc->appendChild($pluginDoc->importNode($n, true));
        return $importFilter->execute($pluginDoc);
    }

    public function createJournalDirectories($journal)
    {
        $contextService = Services::get('context');

        import('lib.pkp.classes.file.FileManager');
        $fileManager = new \FileManager();
        foreach ($contextService->installFileDirs as $dir) {
            dump(sprintf($dir, $contextService->contextsFileDirName, $journal->getId()));
            $fileManager->mkdir(sprintf($dir, $contextService->contextsFileDirName, $journal->getId()));
        }
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
