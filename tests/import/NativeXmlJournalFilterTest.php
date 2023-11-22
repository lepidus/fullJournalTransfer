<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlJournalFilter');

class NativeXmlJournalFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>journal';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlJournalFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['journals', 'journal_settings'];
    }

    private function setJournalAttributeData($journal)
    {
        $journal->setSequence(6);
        $journal->setPath('ojs');
        $journal->setPrimaryLocale('en_US');
        $journal->setEnabled(true);
        $journal->setData('copyrightYearBasis', 'issue');
        $journal->setData('defaultReviewMode', 2);
        $journal->setData('disableSubmissions', false);
        $journal->setData('enableOai', true);
        $journal->setData('itemsPerPage', 25);
        $journal->setData('keywords', 'request');
        $journal->setData('membershipFee', 0);
        $journal->setData('numPageLinks', 10);
        $journal->setData('numWeeksPerResponse', 4);
        $journal->setData('numWeeksPerReview', 4);
        $journal->setData('publicationFee', 0);
        $journal->setData('purchaseArticleFee', 0);
        $journal->setData('themePluginPath', 'default');
    }

    private function setJournalSimpleNodeData($journal)
    {
        $journal->setData('emailSignature', __('default.contextSettings.emailSignature'));
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
    }

    private function setJournalLocalizedNodeData($journal)
    {
        $journal->setData('acronym', 'pkpojs', 'en_US');
        $journal->setData('authorInformation', __('default.contextSettings.forAuthors'), 'en_US');
        $journal->setData('clockssLicense', __('default.contextSettings.clockssLicense'), 'en_US');
        $journal->setData('librarianInformation', __('default.contextSettings.forLibrarians'), 'en_US');
        $journal->setData('lockssLicense', __('default.contextSettings.lockssLicense'), 'en_US');
        $journal->setData('name', 'Open Journal Systems', 'en_US');
        $journal->setData('openAccessPolicy', __('default.contextSettings.openAccessPolicy'), 'en_US');
        $journal->setData('privacyStatement', __('default.contextSettings.privacyStatement'), 'en_US');
        $journal->setData('readerInformation', __('default.contextSettings.forReaders'), 'en_US');
        $journal->setData('abbreviation', 'ojs', 'en_US');
        $journal->setData('about', '<p>This is a journal for test purpose</p>', 'en_US');
        $journal->setData('contactAffiliation', 'Public Knowledge Project', 'en_US');
        $journal->setData('description', '<p>A test journal</p>', 'en_US');
        $journal->setData('editorialTeam', '<p>The editorial team of this journal</p>', 'en_US');
    }

    private function setJournalLocalesNodeData($journal)
    {
        $journal->setData('supportedLocales', ['en_US', 'es_ES', 'pt_BR']);
        $journal->setData('supportedFormLocales', ['en_US', 'es_ES', 'pt_BR']);
        $journal->setData('supportedSubmissionLocales', ['en_US', 'es_ES', 'pt_BR']);
    }

    private function setJournalChecklistNodeData($journal)
    {
        $defaultJournal = new Journal();
        Services::get('schema')->setDefaults(
            'context',
            $defaultJournal,
            ['en_US'],
            'en_US'
        );

        $journal->setData('submissionChecklist', $defaultJournal->getData('submissionChecklist'));
    }

    public function testJournalFilterParseSubmissionChecklist()
    {
        $journalImportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalImportFilter->getDeployment();

        $doc = $this->getSampleXml('journal.xml');
        $submissionChecklistNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'submission_checklist'
        );
        $submissionChecklistElement = $submissionChecklistNodeList->item(0);

        $expectedParsedSubmissionChecklist = ['en_US', [
            [
                "order" => 1,
                "content" => __("default.contextSettings.checklist.notPreviouslyPublished")
            ],
            [
                "order" => 2,
                "content" => __("default.contextSettings.checklist.fileFormat")
            ],
            [
                "order" => 3,
                "content" => __("default.contextSettings.checklist.addressesLinked")
            ],
            [
                "order" => 4,
                "content" => __("default.contextSettings.checklist.submissionAppearance")
            ],
            [
                "order" => 5,
                "content" => __("default.contextSettings.checklist.bibliographicRequirements")
            ]
        ]];

        $parsedSubmissionChecklist = $journalImportFilter->parseSubmissionChecklist($submissionChecklistElement);
        $this->assertEquals($expectedParsedSubmissionChecklist, $parsedSubmissionChecklist);
    }

    public function testHandleJournalChildElement()
    {
        $journalImportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalImportFilter->getDeployment();

        $expectedJournal = new Journal();
        $this->setJournalSimpleNodeData($expectedJournal);
        $this->setJournalLocalizedNodeData($expectedJournal);
        $this->setJournalLocalesNodeData($expectedJournal);
        $this->setJournalChecklistNodeData($expectedJournal);

        $doc = $this->getSampleXml('journal.xml');
        $journalNode = $doc->documentElement;

        $actualJournal = new Journal();
        for ($n = $journalNode->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                $journalImportFilter->handleChildElement($n, $actualJournal);
            }
        }

        $this->assertEquals($expectedJournal->_data, $actualJournal->_data);
    }

    public function testHandleJournalElement()
    {
        $journalImportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalImportFilter->getDeployment();


        $journal = new Journal();
        $this->setJournalAttributeData($journal);
        $this->setJournalSimpleNodeData($journal);
        $this->setJournalLocalizedNodeData($journal);
        $this->setJournalLocalesNodeData($journal);
        $this->setJournalChecklistNodeData($journal);
        $expectedJournalData = $journal->_data;

        $doc = $this->getSampleXml('journal.xml');
        $journalNode = $doc->documentElement;

        $journal = $journalImportFilter->handleElement($journalNode);
        $journalId = array_pop($journal->_data);

        $this->assertEquals($expectedJournalData, $journal->_data);

        $journalDAO = $journal->getDAO();
        $insertedJournal = $journalDAO->getById($journalId);
        $expectedJournalData['id'] = $journalId;

        $this->assertEquals($expectedJournalData, $insertedJournal->_data);
    }
}
