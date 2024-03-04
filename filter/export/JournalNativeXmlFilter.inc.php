<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class JournalNativeXmlFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML journal export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.JournalNativeXmlFilter';
    }

    private function getJournalAttributeProps()
    {
        return [
            'urlPath',
            'enabled',
            'primaryLocale',
            'seq',
            'copyrightYearBasis',
            'defaultReviewMode',
            'enableOai',
            'itemsPerPage',
            'keywords',
            'membershipFee',
            'numPageLinks',
            'numWeeksPerResponse',
            'numWeeksPerReview',
            'publicationFee',
            'purchaseArticleFee',
            'themePluginPath'
        ];
    }

    private function getJournalOptionalProps()
    {
        return [
            'contactEmail',
            'contactName',
            'contactPhone',
            'mailingAddress',
            'onlineIssn',
            'printIssn',
            'publisherInstitution',
            'supportEmail',
            'supportName',
            'supportPhone',
        ];
    }

    private function getJournalLocalizedProps()
    {
        return [
            'acronym',
            'authorInformation',
            'clockssLicense',
            'librarianInformation',
            'lockssLicense',
            'name',
            'openAccessPolicy',
            'privacyStatement',
            'readerInformation',
            'abbreviation',
            'about',
            'contactAffiliation',
            'description',
            'editorialTeam',
        ];
    }

    public function &process(&$journal)
    {
        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        $rootNode = $this->createJournalNode($doc, $journal);
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    public function createJournalNode($doc, $journal)
    {
        $deployment = $this->getDeployment();
        $deployment->setContext($journal);

        $journalName = $journal->getName($journal->getPrimaryLocale());
        echo __('plugins.importexport.fullJournal.exportingJournal', [
            'journalName' => $journalName,
        ]) . "\n";

        $journalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');

        foreach ($this->getJournalAttributeProps() as $propName) {
            $journalNode->setAttribute(
                $this->camelCaseToSnakeCase($propName),
                $journal->getData($propName)
            );
        }
        $journalNode->setAttribute(
            'disable_submissions',
            $journal->getData('disableSubmissions') ? 'true' : 'false'
        );

        $journalNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'email_signature',
            htmlspecialchars(
                $journal->getData('emailSignature'),
                ENT_COMPAT,
                'UTF-8'
            )
        ));
        $journalNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'supported_locales',
            htmlspecialchars(join(':', $journal->getData('supportedLocales')), ENT_COMPAT, 'UTF-8')
        ));
        $journalNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'supported_form_locales',
            htmlspecialchars(join(':', $journal->getData('supportedFormLocales')), ENT_COMPAT, 'UTF-8')
        ));
        $journalNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'supported_submission_locales',
            htmlspecialchars(join(':', $journal->getData('supportedSubmissionLocales')), ENT_COMPAT, 'UTF-8')
        ));

        $this->createJournalOptionalNodes($doc, $journalNode, $journal);
        $this->createJournalLocalizedNodes($doc, $journalNode, $journal);

        foreach ($journal->getData('submissionChecklist') as $locale => $submissionChecklist) {
            $submissionChecklistNode = $doc->createElementNS($deployment->getNamespace(), 'submission_checklist');
            $submissionChecklistNode->setAttribute('locale', $locale);
            foreach ($submissionChecklist as $checklistItem) {
                $submissionChecklistNode->appendChild($node = $doc->createElementNS(
                    $deployment->getNamespace(),
                    'submission_checklist_item',
                    htmlspecialchars($checklistItem['content'], ENT_COMPAT, 'UTF-8')
                ));
                $node->setAttribute('order', $checklistItem['order']);
            }
            $journalNode->appendChild($submissionChecklistNode);
        }

        $this->addPlugins($doc, $journalNode);
        $this->addNavigationMenuItems($doc, $journalNode, $journal);
        $this->addNavigationMenus($doc, $journalNode, $journal);
        $this->addUsers($doc, $journalNode, $journal);
        $this->addGenres($doc, $journalNode, $journal);
        $this->addSections($doc, $journalNode, $journal);
        $this->addIssues($doc, $journalNode, $journal);
        $this->addArticles($doc, $journalNode, $journal);

        return $journalNode;
    }

    public function createJournalOptionalNodes($doc, $journalNode, $journal)
    {
        foreach ($this->getJournalOptionalProps() as $propName) {
            $this->createOptionalNode(
                $doc,
                $journalNode,
                $this->camelCaseToSnakeCase($propName),
                $journal->getData($propName)
            );
        }
    }

    public function createJournalLocalizedNodes($doc, $journalNode, $journal)
    {
        foreach ($this->getJournalLocalizedProps() as $propName) {
            $this->createLocalizedNodes(
                $doc,
                $journalNode,
                $this->camelCaseToSnakeCase($propName),
                $journal->getData($propName)
            );
        }
    }

    public function createSubmissionChecklistNode($doc, $parentNode, $checklist)
    {
        $deployment = $this->getDeployment();

        foreach ($checklist as $locale => $items) {
            $parentNode->appendChild($checklistNode = $doc->createElementNS(
                $deployment->getNamespace(),
                'submission_checklist'
            ));
            $checklistNode->setAttribute('locale', $locale);
            foreach ($items as $item) {
                $checklistNode->appendChild($node = $doc->createElementNS(
                    $deployment->getNamespace(),
                    'submission_checklist_item',
                    htmlspecialchars($item['content'], ENT_COMPAT, 'UTF-8')
                ));
                $node->setAttribute('order', $item['order']);
            }
        }
    }

    public function addPlugins($doc, $journalNode)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('plugin=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setDeployment($this->getDeployment());

        $plugins = PluginRegistry::loadAllPlugins();
        $pluginsDoc = $exportFilter->execute($plugins, true);
        if ($pluginsDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($pluginsDoc->documentElement, true);
            $journalNode->appendChild($clone);
        }
    }

    public function addNavigationMenuItems($doc, $journalNode, $journal)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('navigation-menu-item=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setDeployment($this->getDeployment());

        $navigationItemMenuDAO = DAORegistry::getDAO('NavigationMenuItemDAO');
        $navigationMenuItems = $navigationItemMenuDAO->getByContextId($journal->getId())->toArray();
        $navigationMenuItemsDoc = $exportFilter->execute($navigationMenuItems, true);
        if ($navigationMenuItemsDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($navigationMenuItemsDoc->documentElement, true);
            $journalNode->appendChild($clone);
        }
    }

    public function addNavigationMenus($doc, $journalNode, $journal)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('navigation-menu=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setDeployment($this->getDeployment());

        $navigationMenuDAO = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenus = $navigationMenuDAO->getByContextId($journal->getId())->toArray();
        $navigationMenusDoc = $exportFilter->execute($navigationMenus, true);
        if ($navigationMenusDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($navigationMenusDoc->documentElement, true);
            $journalNode->appendChild($clone);
        }
    }

    public function addUsers($doc, $journalNode, $journal)
    {
        import('lib.pkp.plugins.importexport.users.PKPUserImportExportDeployment');

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $usersIterator = $userGroupDao->getUsersByContextId($journal->getId());

        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('user=>user-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setDeployment(new PKPUserImportExportDeployment($journal, null));

        echo __('plugins.importexport.fullJournal.exportingUsers') . "\n";

        $userDao = DAORegistry::getDAO('UserDAO');
        $users = [];
        foreach ($usersIterator->toArray() as $userId) {
            if (is_a($userId, 'User')) {
                $users[] = $userId;
            } else {
                $user = $userDao->getById($userId, $journal->getId());
                if ($user) {
                    $users[] = $user;
                }
            }
        }

        $usersDoc = $exportFilter->execute($users);
        $this->removeDuplicatedInterests($usersDoc);
        if ($usersDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($usersDoc->documentElement, true);
            $journalNode->appendChild($clone);
        }
    }

    public function addGenres($doc, $journalNode, $journal)
    {
        $genreDAO = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDAO->getByContextId($journal->getId())->toArray();
        $deployment = $this->getDeployment();

        if (!count($genres)) {
            return;
        }

        $genresNode = $doc->createElementNS($deployment->getNamespace(), 'genres');
        foreach ($genres as $genre) {
            $genreNode = $doc->createElementNS($deployment->getNamespace(), 'genre');

            $genreNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'id',
                $genre->getId()
            ));
            $node->setAttribute('type', 'internal');
            $node->setAttribute('advice', 'ignore');

            $genreNode->setAttribute('key', $genre->getKey());
            $genreNode->setAttribute('category', (int) $genre->getCategory());
            $genreNode->setAttribute('dependent', (int) $genre->getDependent());
            $genreNode->setAttribute('supplementary', (int) $genre->getSupplementary());
            $genreNode->setAttribute('seq', $genre->getSequence());
            $genreNode->setAttribute('enabled', $genre->getEnabled());

            $this->createLocalizedNodes($doc, $genreNode, 'name', $genre->getName(null));

            $genresNode->appendChild($genreNode);
        }

        $journalNode->appendChild($genresNode);
    }

    public function addSections($doc, $journalNode, $journal)
    {
        $sectionDao = DAORegistry::getDAO('SectionDAO');
        $sections = $sectionDao->getByJournalId($journal->getId())->toArray();
        $deployment = $this->getDeployment();

        if (!count($sections)) {
            return;
        }

        echo __('plugins.importexport.fullJournal.exportingSections') . "\n";

        $sectionsNode = $doc->createElementNS($deployment->getNamespace(), 'sections');
        foreach ($sections as $section) {
            $sectionNode = $doc->createElementNS($deployment->getNamespace(), 'section');

            $sectionNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'id',
                $section->getId()
            ));
            $node->setAttribute('type', 'internal');
            $node->setAttribute('advice', 'ignore');

            if ($section->getReviewFormId()) {
                $sectionNode->setAttribute('review_form_id', $section->getReviewFormId());
            }
            $sectionNode->setAttribute('ref', $section->getAbbrev($journal->getPrimaryLocale()));
            $sectionNode->setAttribute('seq', (int) $section->getSequence());
            $sectionNode->setAttribute('editor_restricted', $section->getEditorRestricted());
            $sectionNode->setAttribute('meta_indexed', $section->getMetaIndexed());
            $sectionNode->setAttribute('meta_reviewed', $section->getMetaReviewed());
            $sectionNode->setAttribute('abstracts_not_required', $section->getAbstractsNotRequired());
            $sectionNode->setAttribute('hide_title', $section->getHideTitle());
            $sectionNode->setAttribute('hide_author', $section->getHideAuthor());
            $sectionNode->setAttribute('abstract_word_count', (int) $section->getAbstractWordCount());

            $this->createLocalizedNodes($doc, $sectionNode, 'abbrev', $section->getAbbrev(null));
            $this->createLocalizedNodes($doc, $sectionNode, 'policy', $section->getPolicy(null));
            $this->createLocalizedNodes($doc, $sectionNode, 'title', $section->getTitle(null));

            $sectionsNode->appendChild($sectionNode);
        }

        $journalNode->appendChild($sectionsNode);
    }

    public function addIssues($doc, $journalNode, $journal)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('extended-issue=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setOpts($this->opts);
        $exportFilter->setDeployment($this->getDeployment());

        echo __('plugins.importexport.fullJournal.exportingIssues') . "\n";

        $issueDao = DAORegistry::getDAO('IssueDAO');
        $issuesArray = $issueDao->getIssues($journal->getId())->toArray();
        $issuesDoc = $exportFilter->execute($issuesArray);

        if ($issuesDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($issuesDoc->documentElement, true);
            $journalNode->appendChild($clone);
        }
    }

    public function addArticles($doc, $journalNode, $journal)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('extended-article=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setOpts($this->opts);
        $exportFilter->setDeployment($this->getDeployment());
        $exportFilter->setIncludeSubmissionsNode(true);

        echo __('plugins.importexport.fullJournal.exportingArticles') . "\n";

        $submissionsArray = [];
        $submissions = Services::get('submission')->getMany([
            'contextId' => $journal->getId()
        ]);

        foreach ($submissions as $submission) {
            if ($this->getDeployment()->validateSubmission($submission)) {
                $currentPublication = $submission->getCurrentPublication();
                if ($currentPublication && !$currentPublication->getData('issueId')) {
                    $submissionsArray[] = $submission;
                }
            }
        }

        $articlesDoc = $exportFilter->execute($submissionsArray);
        if ($articlesDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($articlesDoc->documentElement, true);
            $journalNode->appendChild($clone);
        }
    }

    private function removeDuplicatedInterests($usersDoc)
    {
        $deployment = $this->getDeployment();
        $userNodes = $usersDoc->getElementsByTagNameNS($deployment->getNamespace(), 'user');
        foreach ($userNodes as $userNode) {
            $interestNodeList = $userNode->getElementsByTagNameNS($deployment->getNamespace(), 'review_interests');
            if ($interestNodeList->length == 1) {
                $node = $interestNodeList->item(0);
                if ($node) {
                    $interests = preg_split('/,\s*/', $node->textContent);
                    $uniqueInterests = array_intersect_key(
                        $interests,
                        array_unique(array_map([$this, 'removeAccents'], $interests))
                    );
                    $node->nodeValue = htmlspecialchars(implode(', ', $uniqueInterests), ENT_COMPAT, 'UTF-8');
                }
            }
        }
    }

    public function removeAccents($string)
    {
        $transliterator = Transliterator::createFromRules(
            ':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;',
            Transliterator::FORWARD
        );
        $normalized = $transliterator->transliterate($string);

        return strtolower($normalized);
    }

    private function camelCaseToSnakeCase($string)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $string));
    }
}
