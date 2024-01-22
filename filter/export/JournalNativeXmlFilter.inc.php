<?php

/**
 * Copyright (c) 2014-2023 Lepidus Tecnologia
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
        $this->addSections($doc, $journalNode, $journal);
        $this->addArticles($doc, $journalNode, $journal);
        $this->addReviewRounds($doc, $journalNode, $journal);

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
        if ($usersDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($usersDoc->documentElement, true);
            $journalNode->appendChild($clone);
        }
    }

    public function addSections($doc, $journalNode, $journal)
    {
        $sectionDao = DAORegistry::getDAO('SectionDAO');
        $sections = $sectionDao->getByJournalId($journal->getId())->toArray();
        $deployment = $this->getDeployment();

        if (!count($sections)) {
            return;
        }

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

    public function addArticles($doc, $journalNode, $journal)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('article=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setOpts($this->opts);
        $exportFilter->setDeployment($this->getDeployment());
        $exportFilter->setIncludeSubmissionsNode(true);

        $submissionsIterator = Services::get('submission')->getMany([
            'contextId' => $journal->getId(),
        ]);
        $submissionsArray = iterator_to_array($submissionsIterator);
        $articlesDoc = $exportFilter->execute($submissionsArray);
        if ($articlesDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($articlesDoc->documentElement, true);
            $journalNode->appendChild($clone);
        }
    }

    public function addReviewRounds($doc, $journalNode, $journal)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('review-round=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setDeployment($this->getDeployment());
        $allReviewRounds = [];

        $submissions = Services::get('submission')->getMany([
            'contextId' => $journal->getId(),
        ]);
        foreach ($submissions as $submission) {
            $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
            $reviewRounds = $reviewRoundDAO->getBySubmissionId($submission->getId());
            // if (!$reviewRounds->wasEmpty()) {

            // }

            while ($reviewRound = $reviewRounds->next()) {
                $allReviewRounds[] = $reviewRound;
            }
        }

        libxml_use_internal_errors(true);
        $reviewRoundsDoc = $exportFilter->execute($allReviewRounds);
        $errors = libxml_get_errors();
        if ($reviewRoundsDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($reviewRoundsDoc->documentElement, true);
            $journalNode->appendChild($clone);
        }
    }

    private function camelCaseToSnakeCase($string)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $string));
    }
}
