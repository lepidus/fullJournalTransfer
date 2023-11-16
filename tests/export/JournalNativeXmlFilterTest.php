<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.JournalNativeXmlFilter');

class JournalNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getAffectedTables()
    {
        return ['journals', 'journal_settings'];
    }
    protected function getSymbolicFilterGroup()
    {
        return 'journal=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return JournalNativeXmlFilter::class;
    }

    public function testCreateJournalNode()
    {
        $locales = ['en_US', 'es_ES', 'pt_BR'];
        $journalExportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $expectedJournalNode->setAttribute('seq', 6);
        $expectedJournalNode->setAttribute('path', 'ojs');
        $expectedJournalNode->setAttribute('primary_locale', 'en_US');
        $expectedJournalNode->setAttribute('enabled', 1);
        $expectedJournalNode->setAttribute('copyright_year_basis', 'issue');
        $expectedJournalNode->setAttribute('default_review_mode', 2);
        $expectedJournalNode->setAttribute('disable_submissions', 0);
        $expectedJournalNode->setAttribute('enable_oai', 1);
        $expectedJournalNode->setAttribute('items_per_page', 25);
        $expectedJournalNode->setAttribute('keywords', 'request');
        $expectedJournalNode->setAttribute('membership_fee', 0);
        $expectedJournalNode->setAttribute('num_page_links', 10);
        $expectedJournalNode->setAttribute('num_weeks_per_response', 4);
        $expectedJournalNode->setAttribute('numWeeks_per_review', 4);
        $expectedJournalNode->setAttribute('publication_fee', 0);
        $expectedJournalNode->setAttribute('purchase_article_fee', 0);
        $expectedJournalNode->setAttribute('theme_plugin_path', 'default');

        $expectedJournalNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'email_signature',
            htmlspecialchars(
                __('default.contextSettings.emailSignature'),
                ENT_COMPAT,
                'UTF-8'
            )
        ));
        $expectedJournalNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'supported_locales',
            htmlspecialchars(join(':', $locales), ENT_COMPAT, 'UTF-8')
        ));
        $expectedJournalNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'supported_form_locales',
            htmlspecialchars(join(':', $locales), ENT_COMPAT, 'UTF-8')
        ));
        $expectedJournalNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'supported_submission_locales',
            htmlspecialchars(join(':', $locales), ENT_COMPAT, 'UTF-8')
        ));

        $journalExportFilter->createOptionalNode(
            $doc,
            $expectedJournalNode,
            'contact_email',
            'admin@ojs.com'
        );
        $journalExportFilter->createOptionalNode(
            $doc,
            $expectedJournalNode,
            'contact_name',
            'Admin OJS'
        );
        $journalExportFilter->createOptionalNode(
            $doc,
            $expectedJournalNode,
            'contact_phone',
            '555-5555'
        );
        $journalExportFilter->createOptionalNode(
            $doc,
            $expectedJournalNode,
            'mailing_address',
            'Test mailing address'
        );
        $journalExportFilter->createOptionalNode(
            $doc,
            $expectedJournalNode,
            'online_issn',
            '1234-1234'
        );
        $journalExportFilter->createOptionalNode(
            $doc,
            $expectedJournalNode,
            'print_issn',
            '1234-123x'
        );
        $journalExportFilter->createOptionalNode(
            $doc,
            $expectedJournalNode,
            'publisher_institution',
            'Public Knowledge Project'
        );
        $journalExportFilter->createOptionalNode(
            $doc,
            $expectedJournalNode,
            'support_email',
            'support@ojs.com'
        );
        $journalExportFilter->createOptionalNode(
            $doc,
            $expectedJournalNode,
            'support_name',
            'Support OJS'
        );
        $journalExportFilter->createOptionalNode(
            $doc,
            $expectedJournalNode,
            'support_phone',
            '555-5566'
        );

        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'acronym',
            ['en_US' => 'pkpojs']
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'author_information',
            ['en_US' => __('default.contextSettings.forAuthors')]
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'clockss_license',
            ['en_US' => __('default.contextSettings.clockssLicense')]
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'librarian_information',
            ['en_US' => __('default.contextSettings.forLibrarians')]
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'lockss_license',
            ['en_US' => __('default.contextSettings.lockssLicense')]
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'name',
            ['en_US' => 'Open Journal Systems']
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'open_access_policy',
            ['en_US' => __('default.contextSettings.openAccessPolicy')]
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'privacy_statement',
            ['en_US' => __('default.contextSettings.privacyStatement')]
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'reader_information',
            ['en_US' => __('default.contextSettings.forReaders')]
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'abbreviation',
            ['en_US' => 'ojs']
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'about',
            ['en_US' => '<p>This is a journal for test purpose</p>']
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'contact_affiliation',
            ['en_US' => 'Public Knowledge Project']
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'description',
            ['en_US' => '<p>A test journal</p>']
        );
        $journalExportFilter->createLocalizedNodes(
            $doc,
            $expectedJournalNode,
            'editorial_team',
            ['en_US' => '<p>The editorial team of this journal</p>']
        );

        $submissionChecklist = [
            [
                'order' => 1,
                'content' => __('default.contextSettings.checklist.notPreviouslyPublished')
            ],
            [
                'order' => 2,
                'content' => __('default.contextSettings.checklist.fileFormat')
            ],
            [
                'order' => 3,
                'content' => __('default.contextSettings.checklist.addressesLinked')
            ],
            [
                'order' => 4,
                'content' => __('default.contextSettings.checklist.submissionAppearance')
            ],
            [
                'order' => 5,
                'content' => __('default.contextSettings.checklist.bibliographicRequirements')
            ]
        ];
        $submissionChecklistNode = $doc->createElementNS($deployment->getNamespace(), 'submission_checklist');
        $submissionChecklistNode->setAttribute('locale', 'en_US');
        foreach ($submissionChecklist as $checklistItem) {
            $submissionChecklistNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'submission_checklist_item',
                htmlspecialchars($checklistItem['content'], ENT_COMPAT, 'UTF-8')
            ));
            $node->setAttribute('order', $checklistItem['order']);
        }
        $expectedJournalNode->appendChild($submissionChecklistNode);

        $journal = new Journal();
        $journal->setPath('ojs');
        $journal->setName('Open Journal Systems', 'en_US');
        $journal->setPrimaryLocale('en_US');
        $journal->setSequence(6);
        $journal->setEnabled(true);
        $journal->setData('supportedLocales', $locales);
        $journal->setData('supportedFormLocales', $locales);
        $journal->setData('supportedSubmissionLocales', $locales);
        $journal->setData('acronym', 'pkpojs', 'en_US');

        $journal->setData('contactEmail', 'admin@ojs.com');
        $journal->setData('contactName', 'Admin OJS');
        $journal->setData('contactPhone', '555-5555');
        $journal->setData('mailingAddress', 'Test mailing address');
        $journal->setData('onlineIssn', '1234-1234');
        $journal->setData('printIssn', '1234-123x');
        $journal->setData('publisherInstitution', 'Public Knowledge Project');
        $journal->setData('supportEmail', 'support@ojs.com');
        $journal->setData('supportName', 'Support OJS');
        $journal->setData('supportPhone', '555-5566');
        $journal->setData('abbreviation', 'ojs', 'en_US');
        $journal->setData('about', '<p>This is a journal for test purpose</p>', 'en_US');
        $journal->setData('contactAffiliation', 'Public Knowledge Project', 'en_US');
        $journal->setData('description', '<p>A test journal</p>', 'en_US');
        $journal->setData('editorialTeam', '<p>The editorial team of this journal</p>', 'en_US');

        $journal = Services::get('schema')->setDefaults(
            'context',
            $journal,
            ['en_US'],
            $journal->getData('primaryLocale')
        );

        $actualJournalNode = $journalExportFilter->createJournalNode($doc, $journal);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedJournalNode),
            $doc->saveXML($actualJournalNode),
            "actual xml is equal to expected xml"
        );
    }
}
