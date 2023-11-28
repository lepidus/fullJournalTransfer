<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
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
        $this->addUsers($doc, $journalNode, $journal);

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

    private function camelCaseToSnakeCase($string)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $string));
    }
}
