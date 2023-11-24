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

    private function createJournal()
    {
        $journal = new Journal();
        $journal->setPath('ojs');
        $journal->setName('Open Journal Systems', 'en_US');
        $journal->setPrimaryLocale('en_US');
        $journal->setSequence(6);
        $journal->setEnabled(true);
        $journal->setData('supportedLocales', ['en_US', 'es_ES', 'pt_BR']);
        $journal->setData('supportedFormLocales', ['en_US', 'es_ES', 'pt_BR']);
        $journal->setData('supportedSubmissionLocales', ['en_US', 'es_ES', 'pt_BR']);
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

        return $journal;
    }

    private function createDefaultSubmissionChecklistNode($doc, $deployment, $parentNode)
    {
        $submissionChecklist = [
            'en_US' => [
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
            ]
        ];

        foreach ($submissionChecklist as $locale => $submissionChecklist) {
            $parentNode->appendChild($checklistNode = $doc->createElementNS(
                $deployment->getNamespace(),
                'submission_checklist'
            ));
            $checklistNode->setAttribute('locale', $locale);
            foreach ($submissionChecklist as $checklistItem) {
                $checklistNode->appendChild($node = $doc->createElementNS(
                    $deployment->getNamespace(),
                    'submission_checklist_item',
                    htmlspecialchars($checklistItem['content'], ENT_COMPAT, 'UTF-8')
                ));
                $node->setAttribute('order', $checklistItem['order']);
            }
        }

        return $checklistNode;
    }

    private function createOptionalNodes($exportFilter, $doc, $parentNode)
    {
        $exportFilter->createOptionalNode(
            $doc,
            $parentNode,
            'contact_email',
            'admin@ojs.com'
        );
        $exportFilter->createOptionalNode(
            $doc,
            $parentNode,
            'contact_name',
            'Admin OJS'
        );
        $exportFilter->createOptionalNode(
            $doc,
            $parentNode,
            'contact_phone',
            '555-5555'
        );
        $exportFilter->createOptionalNode(
            $doc,
            $parentNode,
            'mailing_address',
            'Test mailing address'
        );
        $exportFilter->createOptionalNode(
            $doc,
            $parentNode,
            'online_issn',
            '1234-1234'
        );
        $exportFilter->createOptionalNode(
            $doc,
            $parentNode,
            'print_issn',
            '1234-123x'
        );
        $exportFilter->createOptionalNode(
            $doc,
            $parentNode,
            'publisher_institution',
            'Public Knowledge Project'
        );
        $exportFilter->createOptionalNode(
            $doc,
            $parentNode,
            'support_email',
            'support@ojs.com'
        );
        $exportFilter->createOptionalNode(
            $doc,
            $parentNode,
            'support_name',
            'Support OJS'
        );
        $exportFilter->createOptionalNode(
            $doc,
            $parentNode,
            'support_phone',
            '555-5566'
        );
    }

    private function createLocalizedNodes($exportFilter, $doc, $parentNode)
    {
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'acronym',
            ['en_US' => 'pkpojs']
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'author_information',
            ['en_US' => __('default.contextSettings.forAuthors')]
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'clockss_license',
            ['en_US' => __('default.contextSettings.clockssLicense')]
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'librarian_information',
            ['en_US' => __('default.contextSettings.forLibrarians')]
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'lockss_license',
            ['en_US' => __('default.contextSettings.lockssLicense')]
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'name',
            ['en_US' => 'Open Journal Systems']
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'open_access_policy',
            ['en_US' => __('default.contextSettings.openAccessPolicy')]
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'privacy_statement',
            ['en_US' => __('default.contextSettings.privacyStatement')]
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'reader_information',
            ['en_US' => __('default.contextSettings.forReaders')]
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'abbreviation',
            ['en_US' => 'ojs']
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'about',
            ['en_US' => '<p>This is a journal for test purpose</p>']
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'contact_affiliation',
            ['en_US' => 'Public Knowledge Project']
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'description',
            ['en_US' => '<p>A test journal</p>']
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $parentNode,
            'editorial_team',
            ['en_US' => '<p>The editorial team of this journal</p>']
        );
    }

    public function testCreateSubmissionChecklistNode()
    {
        $journalExportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $journal = $this->createJournal();

        $expectedJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $this->createDefaultSubmissionChecklistNode($doc, $deployment, $expectedJournalNode);

        $actualJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $journalExportFilter->createSubmissionChecklistNode(
            $doc,
            $actualJournalNode,
            $journal->getData('submissionChecklist')
        );

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedJournalNode),
            $doc->saveXML($actualJournalNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateJournalOptionalNodes()
    {
        $journalExportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $this->createOptionalNodes($journalExportFilter, $doc, $expectedJournalNode);

        $journal = $this->createJournal();

        $actualJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $journalExportFilter->createJournalOptionalNodes($doc, $actualJournalNode, $journal);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedJournalNode),
            $doc->saveXML($actualJournalNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateJournalLocalizedNodes()
    {
        $journalExportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $this->createLocalizedNodes($journalExportFilter, $doc, $expectedJournalNode);

        $journal = $this->createJournal();
        $actualJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $journalExportFilter->createJournalLocalizedNodes($doc, $actualJournalNode, $journal);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedJournalNode),
            $doc->saveXML($actualJournalNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateJournalNode()
    {
        $journalExportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $locales = ['en_US', 'es_ES', 'pt_BR'];

        $expectedJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $expectedJournalNode->setAttribute('seq', 6);
        $expectedJournalNode->setAttribute('url_path', 'ojs');
        $expectedJournalNode->setAttribute('primary_locale', 'en_US');
        $expectedJournalNode->setAttribute('enabled', 1);
        $expectedJournalNode->setAttribute('copyright_year_basis', 'issue');
        $expectedJournalNode->setAttribute('default_review_mode', 2);
        $expectedJournalNode->setAttribute('disable_submissions', 'false');
        $expectedJournalNode->setAttribute('enable_oai', 1);
        $expectedJournalNode->setAttribute('items_per_page', 25);
        $expectedJournalNode->setAttribute('keywords', 'request');
        $expectedJournalNode->setAttribute('membership_fee', 0);
        $expectedJournalNode->setAttribute('num_page_links', 10);
        $expectedJournalNode->setAttribute('num_weeks_per_response', 4);
        $expectedJournalNode->setAttribute('num_weeks_per_review', 4);
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

        $this->createOptionalNodes($journalExportFilter, $doc, $expectedJournalNode);
        $this->createLocalizedNodes($journalExportFilter, $doc, $expectedJournalNode);
        $this->createDefaultSubmissionChecklistNode($doc, $deployment, $expectedJournalNode);

        $journal = $this->createJournal();
        $actualJournalNode = $journalExportFilter->createJournalNode($doc, $journal);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedJournalNode),
            $doc->saveXML($actualJournalNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteJournalXml()
    {
        $journalExportFilter = $this->getNativeImportExportFilter();
        $journal = $this->createJournal();
        $doc = $journalExportFilter->execute($journal);
        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('journal.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
