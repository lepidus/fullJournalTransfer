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
        return [
            'journals', 'journal_settings', 'plugin_settings',
            'user_group_settings', 'user_group_stage',
            'user_groups', 'user_interests',
            'user_settings', 'user_user_groups',
            'users',
            'navigation_menus',
            'navigation_menu_items',
            'navigation_menu_item_settings',
            'navigation_menu_item_assignments',
            'navigation_menu_item_assignment_settings',
            'sections', 'section_settings',
            'genres', 'genre_settings', 'review_rounds',
            'review_assignments'
        ];
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

    public function testInstallDefaultGenres()
    {
        $journalImportFilter = $this->getNativeImportExportFilter();
        $journal = new Journal();
        $journal->setId(rand());
        $journal->setData('supportedLocales', ['en_US']);

        $expectedGenreNames = [
            "##default.genres.article##",
            "##default.genres.researchInstrument##",
            "##default.genres.researchMaterials##",
            "##default.genres.researchResults##",
            "##default.genres.transcripts##",
            "##default.genres.dataAnalysis##",
            "##default.genres.dataSet##",
            "##default.genres.sourceTexts##",
            "##default.genres.multimedia##",
            "##default.genres.image##",
            "##default.genres.styleSheet##",
            "##default.genres.other##"
        ];

        AppLocale::requireComponents(LOCALE_COMPONENT_APP_DEFAULT);

        $journalImportFilter->installDefaultGenres($journal);

        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($journal->getId());
        $genreNames = [];
        while ($genre = $genres->next()) {
            foreach ($genre->getName(null) as $locale => $name) {
                $genreNames[] = $name;
            }
        }

        $this->assertEquals($expectedGenreNames, $genreNames);
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

    public function testParsePlugin()
    {
        $journalImportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalImportFilter->getDeployment();

        $expectedSettings = [
            'someSetting' => 'Test Value'
        ];

        $journal = new Journal();
        $journal->setId(rand());

        $deployment->setContext($journal);

        $doc = $this->getSampleXml('journal.xml');
        $pluginNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'plugin'
        );
        $journalImportFilter->parsePlugin($pluginNodeList->item(0));

        $pluginSettingsDAO = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettings = $pluginSettingsDAO->getPluginSettings($journal->getId(), 'testgenericplugin');

        $this->assertEquals($expectedSettings, $pluginSettings);
    }

    public function testParsePlugins()
    {
        $journalImportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalImportFilter->getDeployment();

        $expectedSettings = [
            'someSetting' => 'Test Value',
            'someOption' => 'Option Value'
        ];

        $journal = new Journal();
        $journal->setId(rand());

        $deployment->setContext($journal);

        $doc = $this->getSampleXml('journal.xml');
        $pluginsNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'plugins'
        );
        $journalImportFilter->parsePlugins($pluginsNodeList->item(0));

        $pluginSettingsDAO = DAORegistry::getDAO('PluginSettingsDAO');
        $actualSettings = array_merge(
            $pluginSettingsDAO->getPluginSettings($journal->getId(), 'testgenericplugin'),
            $pluginSettingsDAO->getPluginSettings($journal->getId(), 'testthemeplugin')
        );

        $this->assertEquals($expectedSettings, $actualSettings);
    }

    public function testParseUsers()
    {
        $journalImportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalImportFilter->getDeployment();

        $expectedUserData = [
            'username' => 'siteadmin',
            'email' => 'john@admin-site.com',
            'url' => 'http://www.admin-site.com',
            'billingAddress' => 'my billing address',
            'country' => 'CA',
            'dateRegistered' => '2013-11-05 12:42:05',
            'dateValidated' => '2013-11-06 00:00:00',
            'dateLastLogin' => '2014-01-06 08:58:08',
            'mustChangePassword' => 1,
            'disabled' => 0,
            'authId' => 23,
            'inlineHelp' => 0,
            'familyName' => [
                'en_US' => 'Smith'
            ],
            'givenName' => [
                'en_US' => 'admin'
            ],
            'locales' => []
        ];

        $doc = $this->getSampleXml('journal.xml');
        $usersNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'PKPUsers'
        );

        $journal = new Journal();
        $journal->setId(rand());
        $journalImportFilter->parseUsers($usersNodeList->item(0), $journal);

        $userDao = DAORegistry::getDAO('UserDAO');
        $userByUsername = $userDao->getByUsername('siteadmin', true);
        unset($userByUsername->_data['id']);
        unset($userByUsername->_data['password']);

        $this->assertEquals($expectedUserData, $userByUsername->_data);
    }

    public function testParseSections()
    {
        $journalImportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalImportFilter->getDeployment();

        $journal = new Journal();
        $journal->setId(rand());

        $expectedSectionData = [
            'contextId' => $journal->getId(),
            'abbrev' => ['en_US' => 'ART'],
            'policy' => ['en_US' => '<p>Section default policy</p>'],
            'title' => ['en_US' => 'Articles'],
            'sequence' => 1.0,
            'editorRestricted' => 0,
            'metaIndexed' => 1,
            'metaReviewed' => 1,
            'abstractsNotRequired' => 0,
            'hideTitle' => 0,
            'hideAuthor' => 0,
            'wordCount' => 500,
            'isInactive' => 0,
            'reviewFormId' => 0
        ];

        $doc = $this->getSampleXml('journal.xml');
        $sectionNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'sections'
        );
        $journalImportFilter->parseSections($sectionNodeList->item(0), $journal);

        $sectionDAO = DAORegistry::getDAO('SectionDAO');
        $section = $sectionDAO->getByAbbrev('ART', $journal->getId(), 'en_US');
        unset($section->_data['id']);

        $this->assertEquals($expectedSectionData, $section->_data);
    }

    public function testParseIssues()
    {
        $journalImportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalImportFilter->getDeployment();
        $journal = new Journal();
        $journal->setId(rand());
        $deployment->setContext($journal);

        $expectedIssueData = [
            'journalId' => $journal->getId(),
            'year' => 2024,
            'published' => 0,
            'current' => 0,
            'lastModified' => Core::getCurrentDate(),
            'accessStatus' => 0,
            'showVolume' => 0,
            'showNumber' => 0,
            'showYear' => 1,
            'showTitle' => 0,
            'urlPath' => 'testes'
        ];

        $doc = $this->getSampleXml('journal.xml');
        $issueNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'issue'
        );

        $journalImportFilter->parseIssue($issueNodeList->item(0), $journal);

        $issueDAO = DAORegistry::getDAO('IssueDAO');
        $issues = $issueDAO->getIssuesByIdentification($journal->getId(), null, null, 2024)->toArray();
        $issue = array_shift($issues);
        unset($issue->_data['id']);

        $this->assertEquals($expectedIssueData, $issue->_data);
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

        $importedObjects = $journalImportFilter->process($doc);
        $journal = array_shift($importedObjects);
        $journalId = $journal->getId();
        unset($journal->_data['id']);

        $this->assertEquals($expectedJournalData, $journal->_data);

        $journalDAO = $journal->getDAO();
        $insertedJournal = $journalDAO->getById($journalId);
        $expectedJournalData['id'] = $journalId;

        $this->assertEquals($expectedJournalData, $insertedJournal->_data);
    }

    public function testParseReviewAssignments()
    {
        $journalImportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalImportFilter->getDeployment();
        $deployment->setSubmissionDBId(13, 107);
        $journal = new Journal();
        $journal->setId(rand());

        $expectedReviewAssignmentData = [
            'submissionId' => 107,
            'reviewerId' => 7,
            'competingInterests' => 'test interest',
            'recommendation' => 2,
            'dateAssigned' => '2023-10-29 21:52:08',
            'dateNotified' => '2023-10-28 21:52:08',
            'dateConfirmed' => '2023-10-27 21:52:08',
            'dateCompleted' => '2023-10-26 21:52:08',
            'dateAcknowledged' => '2023-10-25 21:52:08',
            'dateDue' => '2023-10-24 21:52:08',
            'dateResponseDue' => '2023-10-23 21:52:08',
            'lastModified' => '2023-10-22 21:52:08',
            'declined' => 0,
            'cancelled' => 0,
            'quality' => 5,
            'dateRated' => '2023-10-31 21:52:08',
            'dateReminded' => '2023-10-30 21:52:08',
            'reminderWasAutomatic' => 0,
            'round' => 1,
            'reviewMethod' => 2,
            'stageId' => 3,
            'unconsidered' => 0,
            'reviewerFullName' => 'Julie Janssen',
            'reviewRoundId' => 0
        ];

        $doc = $this->getSampleXml('journal.xml');
        $reviewAssignmentsNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'review_assignments'
        );

        $importedObjects = $journalImportFilter->parseReviewAssignments($reviewAssignmentsNodeList->item(0), $journal);

        $reviewAssignmentDAO = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignments = $reviewAssignmentDAO->getBySubmissionId(107);
        $reviewAssignment = array_shift($reviewAssignments);
        unset($reviewAssignment->_data['id']);

        $this->assertEquals($expectedReviewAssignmentData, $reviewAssignment->_data);
    }
}
