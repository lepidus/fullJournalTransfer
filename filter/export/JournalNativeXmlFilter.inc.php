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
        return 'plugins.importexport.native.filter.export.JournalNativeXmlFilter';
    }

    public function createJournalNode($doc, $journal)
    {
        $deployment = $this->getDeployment();

        $journalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $journalNode->setAttribute('seq', $journal->getSequence());
        $journalNode->setAttribute('path', $journal->getPath());
        $journalNode->setAttribute('primary_locale', $journal->getPrimaryLocale());
        $journalNode->setAttribute('enabled', $journal->getEnabled());
        $journalNode->setAttribute('copyright_year_basis', $journal->getData('copyrightYearBasis'));
        $journalNode->setAttribute('default_review_mode', $journal->getData('defaultReviewMode'));
        $journalNode->setAttribute('disable_submissions', (int) $journal->getData('disableSubmissions'));
        $journalNode->setAttribute('enable_oai', $journal->getData('enableOai'));
        $journalNode->setAttribute('items_per_page', $journal->getData('itemsPerPage'));
        $journalNode->setAttribute('keywords', $journal->getData('keywords'));
        $journalNode->setAttribute('membership_fee', $journal->getData('membershipFee'));
        $journalNode->setAttribute('num_page_links', $journal->getData('numPageLinks'));
        $journalNode->setAttribute('num_weeks_per_response', $journal->getData('numWeeksPerResponse'));
        $journalNode->setAttribute('numWeeks_per_review', $journal->getData('numWeeksPerReview'));
        $journalNode->setAttribute('publication_fee', $journal->getData('publicationFee'));
        $journalNode->setAttribute('purchase_article_fee', $journal->getData('purchaseArticleFee'));
        $journalNode->setAttribute('theme_plugin_path', $journal->getData('themePluginPath'));

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

        $this->createLocalizedNodes(
            $doc,
            $journalNode,
            'acronym',
            $journal->getData('acronym')
        );
        $this->createLocalizedNodes(
            $doc,
            $journalNode,
            'author_information',
            $journal->getData('authorInformation')
        );
        $this->createLocalizedNodes(
            $doc,
            $journalNode,
            'clockss_license',
            $journal->getData('clockssLicense')
        );
        $this->createLocalizedNodes(
            $doc,
            $journalNode,
            'librarian_information',
            $journal->getData('librarianInformation')
        );
        $this->createLocalizedNodes(
            $doc,
            $journalNode,
            'lockss_license',
            $journal->getData('lockssLicense')
        );
        $this->createLocalizedNodes(
            $doc,
            $journalNode,
            'name',
            $journal->getData('name')
        );
        $this->createLocalizedNodes(
            $doc,
            $journalNode,
            'open_access_policy',
            $journal->getData('openAccessPolicy')
        );
        $this->createLocalizedNodes(
            $doc,
            $journalNode,
            'privacy_statement',
            $journal->getData('privacyStatement')
        );
        $this->createLocalizedNodes(
            $doc,
            $journalNode,
            'reader_information',
            $journal->getData('readerInformation')
        );

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

        return $journalNode;
    }
}
