<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.JournalNativeXmlFilter');
import('lib.pkp.plugins.importexport.users.PKPUserImportExportDeployment');
import('lib.pkp.classes.plugins.Plugin');

class JournalNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getAffectedTables()
    {
        return [
            'journals', 'journal_settings', 'plugin_settings',
            'user_group_settings', 'user_group_stage',
            'user_groups', 'user_interests',
            'user_settings', 'user_user_groups',
            'users', 'sections', 'section_settings',
            'submissions', 'submission_settings', 'publications', 'publication_settings'
        ];
    }
    protected function getSymbolicFilterGroup()
    {
        return 'journal=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return JournalNativeXmlFilter::class;
    }

    protected function getMockedDAOs()
    {
        return [
            'NavigationMenuDAO',
            'NavigationMenuItemDAO',
            'NavigationMenuItemAssignmentDAO',
            'SectionDAO',
            'SubmissionDAO',
            'IssueDAO'
        ];
    }

    protected function getMockedRegistryKeys()
    {
        return ['request'];
    }

    private function registerMockRequest($journal)
    {
        import('lib.pkp.classes.core.PKPRouter');
        $router = $this->getMockBuilder(PKPRouter::class)
            ->setMethods(['getContext'])
            ->getMock();
        $application = Application::get();
        $router->setApplication($application);
        $router->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($journal));

        import('classes.core.Request');
        $request = $this->getMockBuilder(Request::class)
            ->setMethods(array('getRouter'))
            ->getMock();
        $request->expects($this->any())
                ->method('getRouter')
                ->will($this->returnValue($router));
        Registry::set('request', $request);
    }

    private function registerMockPlugin($journal, $pluginName, $category, $settings)
    {
        $mockPlugin = $this->getMockBuilder(Plugin::class)
            ->getMockForAbstractClass();

        $mockPlugin->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($pluginName));

        foreach ($settings as $settingName => $settingValue) {
            $mockPlugin->updateSetting($journal->getId(), $settingName, $settingValue);
        }

        $success = PluginRegistry::register($category, $mockPlugin, $category . '/' . $pluginName);
    }

    private function registerMockNavigationMenu($daoClass, $daoClassName, $method, $data)
    {
        $mockDAO = $this->getMockBuilder($daoClass)
            ->setMethods([$method])
            ->getMock();

        $object = $mockDAO->newDataObject();
        $object->_data = $data;

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$object]));

        $mockDAO->expects($this->any())
            ->method($method)
            ->will($this->returnValue($mockResult));

        DAORegistry::registerDAO($daoClassName, $mockDAO);
    }

    private function registerMockSectionDAO()
    {
        $mockDAO = $this->getMockBuilder(SectionDAO::class)
            ->setMethods(['getByJournalId', 'getByIssueId'])
            ->getMock();

        $section = $mockDAO->newDataObject();
        $section->setId(1);
        $section->setAbbrev('ART', 'en_US');
        $section->setPolicy('<p>Section default policy</p>', 'en_US');
        $section->setTitle('Articles', 'en_US');
        $section->setSequence(1);
        $section->setEditorRestricted(0);
        $section->setMetaIndexed(1);
        $section->setMetaReviewed(1);
        $section->setAbstractsNotRequired(0);
        $section->setHideTitle(0);
        $section->setHideAuthor(0);
        $section->setAbstractWordCount(500);

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$section]));

        $mockDAO->expects($this->any())
            ->method('getByJournalId')
            ->will($this->returnValue($mockResult));

        $mockDAO->expects($this->any())
            ->method('getByIssueId')
            ->will($this->returnValue([]));

        DAORegistry::registerDAO('SectionDAO', $mockDAO);
    }

    private function getUsersExportFilter($journal)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('user=>user-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setDeployment(new PKPUserImportExportDeployment($journal, null));

        return $exportFilter;
    }

    private function createUsersAndUserGroups($journal)
    {
        $userGroupDao = \DAORegistry::getDAO('UserGroupDAO');
        $userGroup = $userGroupDao->newDataObject();
        $userGroup->setRoleId(9234);
        $userGroup->setContextId($journal->getId());
        $userGroup->setShowTitle(true);
        $userGroup->setPermitSelfRegistration(false);
        $userGroup->setPermitMetadataEdit(true);
        $userGroup->setDefault(true);
        $userGroup->setName('Press managerxx', 'en_US');
        $userGroup->setAbbrev('PM', 'en_US');
        $userGroupId = $userGroupDao->insertObject($userGroup);

        $userDao = DAORegistry::getDAO('UserDAO');
        $user = $userDao->newDataObject();
        $user->setUsername('siteadmin');
        $user->setGivenName('admin', 'en_US');
        $user->setFamilyName('Smith', 'en_US');
        $user->setPassword('6f7303f0285dd527b2da3620ccaf25ee384ae7db');
        $user->setEmail('john@admin-site.com');
        $user->setUrl('http://www.admin-site.com');
        $user->setBillingAddress('my billing address');
        $user->setCountry('CA');
        $user->setDateRegistered('2013-11-05 12:42:05');
        $user->setDateValidated('2013-11-06 00:00:00');
        $user->setDateLastLogin('2014-01-06 08:58:08');
        $user->setMustChangePassword(false);
        $user->setAuthId('23');
        $user->setInlineHelp(false);
        $userDao->insertObject($user);

        $userGroupDao->assignUserToGroup($user->getId(), $userGroupId);

        return $user;
    }

    private function createJournal()
    {
        $journal = new Journal();
        $journal->setId(1483);
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

    private function createPluginsNode($doc, $deployment, $parentNode, $pluginName)
    {
        $parentNode->appendChild($pluginsNode = $doc->createElementNS($deployment->getNamespace(), 'plugins'));
        $pluginsNode->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $pluginsNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );
        $pluginsNode->appendChild($pluginNode = $doc->createElementNS($deployment->getNamespace(), 'plugin'));
        $pluginNode->setAttribute('plugin_name', $pluginName);
        $pluginNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'plugin_setting',
            htmlspecialchars('Test Value', ENT_COMPAT, 'UTF-8')
        ));
        $node->setAttribute('setting_name', 'someSetting');
    }

    private function createNavigationMenuItemNode($doc, $deployment, $exportFilter, $parentNode)
    {
        $parentNode->appendChild($menuItemsNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'navigation_menu_items'
        ));
        $menuItemsNode->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $menuItemsNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );
        $menuItemsNode->appendChild($menuItemNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'navigation_menu_item'
        ));
        $menuItemNode->setAttribute('id', 626);
        $menuItemNode->setAttribute('path', 'testMenuItem');
        $menuItemNode->setAttribute('title_locale_key', 'navigation.about');
        $menuItemNode->setAttribute('type', 'NMI_TYPE_CUSTOM');
        $exportFilter->createLocalizedNodes(
            $doc,
            $menuItemNode,
            'title',
            ['en_US' => 'Test Nav Menu Item Title']
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $menuItemNode,
            'content',
            ['en_US' => '<p>Test Nav Menu Item Content</p>']
        );
        $exportFilter->createLocalizedNodes(
            $doc,
            $menuItemNode,
            'remote_url',
            ['en_US' => 'http://path/to/page']
        );
    }

    private function createNavigationMenuNode($doc, $deployment, $exportFilter, $parentNode)
    {
        $parentNode->appendChild($menusNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'navigation_menus'
        ));
        $menusNode->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $menusNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );
        $menusNode->appendChild($menuNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'navigation_menu'
        ));
        $menuNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'title',
            htmlspecialchars('Test Navigation Menu Title', ENT_COMPAT, 'UTF-8')
        ));
        $menuNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'area_name',
            htmlspecialchars('primary', ENT_COMPAT, 'UTF-8')
        ));
        $menuNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'navigation_menu_item_assignment'
        ));
        $node->setAttribute('menu_item_id', 626);
        $node->setAttribute('parent_id', 0);
        $node->setAttribute('seq', 5);
    }

    private function createSectionsNode($doc, $deployment, $exportFilter, $parentNode)
    {
        $parentNode->appendChild($sectionsNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'sections'
        ));
        $sectionsNode->appendChild($sectionNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'section'
        ));
        $sectionNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'id',
            1
        ));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');

        $sectionNode->setAttribute('ref', 'ART');
        $sectionNode->setAttribute('seq', 1);
        $sectionNode->setAttribute('editor_restricted', 0);
        $sectionNode->setAttribute('meta_indexed', 1);
        $sectionNode->setAttribute('meta_reviewed', 1);
        $sectionNode->setAttribute('abstracts_not_required', 0);
        $sectionNode->setAttribute('hide_title', 0);
        $sectionNode->setAttribute('hide_author', 0);
        $sectionNode->setAttribute('abstract_word_count', 500);

        $exportFilter->createLocalizedNodes($doc, $sectionNode, 'abbrev', ['en_US' => 'ART']);
        $exportFilter->createLocalizedNodes($doc, $sectionNode, 'policy', ['en_US' => '<p>Section default policy</p>']);
        $exportFilter->createLocalizedNodes($doc, $sectionNode, 'title', ['en_US' => 'Articles']);
    }

    private function createIssuesNode($doc, $deployment, $exportFilter, $parentNode)
    {
        $parentNode->appendChild($issueNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'extended_issue'
        ));
        $issueNode->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $issueNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );
        $issueNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'id',
            102
        ));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');


        $issueNode->appendChild($identificationNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'issue_identification',
        ));
        $identificationNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'year',
            2024
        ));

        $issueNode->appendChild($issueGalleysNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'issue_galleys'
        ));
        $issueGalleysNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );

        $issueNode->appendChild($articlesNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'extended_articles'
        ));
        $articlesNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );

        $issueNode->setAttribute('access_status', 0);
        $issueNode->setAttribute('current', 0);
        $issueNode->setAttribute('published', 0);
        $issueNode->setAttribute('url_path', 'testes');
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

    private function createSubmission()
    {
        $mockDAO = $this->getMockBuilder(SubmissionDAO::class)
            ->setMethods(['getByContextId'])
            ->getMock();

        $submission = $mockDAO->newDataObject();
        $submission->setId(16);

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$submission]));

        $mockDAO->expects($this->any())
            ->method('getByContextId')
            ->will($this->returnValue($mockResult));

        DAORegistry::registerDAO('SubmissionDAO', $mockDAO);
    }

    private function registerMockIssues()
    {
        $mockDAO = $this->getMockBuilder(IssueDAO::class)
            ->setMethods(['getIssues'])
            ->getMock();

        $issue = $mockDAO->newDataObject();
        $issue->setId(102);
        $issue->setJournalId(1483);
        $issue->setVolume('17');
        $issue->setNumber('5');
        $issue->setYear('2024');
        $issue->setPublished(0);
        $issue->setDatePublished(null);
        $issue->setCurrent(0);
        $issue->setAccessStatus(0);
        $issue->setShowVolume(0);
        $issue->setShowNumber(0);
        $issue->setShowYear(1);
        $issue->setShowTitle(1);
        $issue->setData('urlPath', 'testes');

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$issue]));

        $mockDAO->expects($this->any())
            ->method('getIssues')
            ->will($this->returnValue($mockResult));

        DAORegistry::registerDAO('IssueDAO', $mockDAO);
    }

    public function testAddPlugins()
    {
        $journalExportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalExportFilter->getDeployment();

        $journal = $this->createJournal();
        $deployment->setContext($journal);
        $journalExportFilter->setDeployment($deployment);

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $this->registerMockRequest($journal);
        $this->registerMockPlugin($journal, 'anotherGenericPlugin', 'generic', ['someSetting' => 'Test Value']);

        $expectedJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $this->createPluginsNode($doc, $deployment, $expectedJournalNode, 'anotherGenericPlugin');

        $actualJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $journalExportFilter->addPlugins($doc, $actualJournalNode, $journal);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedJournalNode),
            $doc->saveXML($actualJournalNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testAddSections()
    {
        $journalExportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalExportFilter->getDeployment();

        $journal = $this->createJournal();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $this->createSectionsNode($doc, $deployment, $journalExportFilter, $expectedJournalNode);

        $this->registerMockSectionDAO();
        $actualJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $journalExportFilter->addSections($doc, $actualJournalNode, $journal);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedJournalNode),
            $doc->saveXML($actualJournalNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testAddMetrics()
    {
        $journalExportFilter = $this->getNativeImportExportFilter();
        $deployment = $journalExportFilter->getDeployment();

        $journal = $this->createJournal();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $mockMetricsDAO = $this->getMockBuilder(FullJournalMetricsDAO::class)
            ->setMethods(['getByContextId'])
            ->getMock();

        $metrics = [
            [
                'assoc_type' => ASSOC_TYPE_SUBMISSION_FILE,
                'assoc_id' => 94,
                'day' => '20240101',
                'country_id' => 'BR',
                'region' => 27,
                'city' => 'São Paulo',
                'file_type' => STATISTICS_FILE_TYPE_PDF,
                'metric' => 2,
                'metric_type' => OJS_METRIC_TYPE_COUNTER,
                'load_id' => 'usage_events_20240101.log'
            ]
        ];

        $mockMetricsDAO->expects($this->any())
            ->method('getByContextId')
            ->will($this->returnValue($metrics));

        DAORegistry::registerDAO('FullJournalMetricsDAO', $mockMetricsDAO);

        $expectedJournalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $expectedJournalNode->appendChild($metricsNode = $doc->createElementNS($deployment->getNamespace(), 'metrics'));
        $metricsNode->appendChild($metricNode = $doc->createElementNS($deployment->getNamespace(), 'metric'));
        $metricNode->setAttribute('assoc_type', ASSOC_TYPE_SUBMISSION_FILE);
        $metricNode->setAttribute('assoc_id', 94);
        $metricNode->setAttribute('day', '20240101');
        $metricNode->setAttribute('country_id', 'BR');
        $metricNode->setAttribute('region', 27);
        $metricNode->setAttribute('city', 'São Paulo');
        $metricNode->setAttribute('file_type', STATISTICS_FILE_TYPE_PDF);
        $metricNode->setAttribute('metric', 2);
        $metricNode->setAttribute('metric_type', OJS_METRIC_TYPE_COUNTER);
        $metricNode->setAttribute('load_id', 'usage_events_20240101.log');

        $journalNode = $doc->createElementNS($deployment->getNamespace(), 'journal');
        $journalExportFilter->addMetrics($doc, $journalNode, $journal);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedJournalNode),
            $doc->saveXML($journalNode),
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
        $this->createPluginsNode($doc, $deployment, $expectedJournalNode, 'testPlugin');
        $this->createNavigationMenuItemNode($doc, $deployment, $journalExportFilter, $expectedJournalNode);
        $this->createNavigationMenuNode($doc, $deployment, $journalExportFilter, $expectedJournalNode);

        $journal = $this->createJournal();

        $this->registerMockRequest($journal);
        $this->registerMockPlugin($journal, 'testPlugin', 'generic', ['someSetting' => 'Test Value']);
        $this->registerMockNavigationMenu(
            NavigationMenuItemDAO::class,
            'NavigationMenuItemDAO',
            'getByContextId',
            [
                'id' => 626,
                'type' => NMI_TYPE_CUSTOM,
                'path' => 'testMenuItem',
                'titleLocaleKey' => 'navigation.about',
                'title' => ['en_US' => 'Test Nav Menu Item Title'],
                'content' => ['en_US' => '<p>Test Nav Menu Item Content</p>'],
                'remoteUrl' => ['en_US' => 'http://path/to/page']
            ]
        );
        $this->registerMockNavigationMenu(
            NavigationMenuDAO::class,
            'NavigationMenuDAO',
            'getByContextId',
            ['title' => 'Test Navigation Menu Title', 'areaName' => 'primary']
        );
        $this->registerMockNavigationMenu(
            NavigationMenuItemAssignmentDAO::class,
            'NavigationMenuItemAssignmentDAO',
            'getByMenuId',
            [
                'menuItemId' => 626,
                'parentId' => 0,
                'seq' => 5
            ]
        );
        $this->registerMockSectionDAO();

        $user = $this->createUsersAndUserGroups($journal);
        $users = [$user];
        $usersExportFilter = $this->getUsersExportFilter($journal);
        $usersDoc = $usersExportFilter->execute($users);
        if ($usersDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($usersDoc->documentElement, true);
            $expectedJournalNode->appendChild($clone);
        }
        $this->createSectionsNode($doc, $deployment, $journalExportFilter, $expectedJournalNode);

        $expectedJournalNode->appendChild($reviewFormsNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'review_forms'
        ));
        $reviewFormsNode->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $reviewFormsNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );

        $this->createIssuesNode($doc, $deployment, $journalExportFilter, $expectedJournalNode);
        $this->registerMockIssues();

        $expectedJournalNode->appendChild($articlesNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'extended_articles'
        ));
        $articlesNode->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $articlesNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );

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
        $this->registerMockRequest($journal);
        $this->registerMockPlugin($journal, 'testgenericplugin', 'generic', ['someSetting' => 'Test Value']);
        $this->registerMockPlugin($journal, 'testthemeplugin', 'theme', ['someOption' => 'Option Value']);
        $this->registerMockNavigationMenu(
            NavigationMenuItemDAO::class,
            'NavigationMenuItemDAO',
            'getByContextId',
            [
                'id' => 626,
                'type' => NMI_TYPE_CUSTOM,
                'path' => 'testMenuItem',
                'titleLocaleKey' => 'navigation.about',
                'title' => ['en_US' => 'Test Nav Menu Item Title'],
                'content' => ['en_US' => '<p>Test Nav Menu Item Content</p>'],
                'remoteUrl' => ['en_US' => 'http://path/to/page']
            ]
        );
        $this->registerMockNavigationMenu(
            NavigationMenuDAO::class,
            'NavigationMenuDAO',
            'getByContextId',
            ['title' => 'Test Navigation Menu Title', 'areaName' => 'primary']
        );
        $this->registerMockNavigationMenu(
            NavigationMenuItemAssignmentDAO::class,
            'NavigationMenuItemAssignmentDAO',
            'getByMenuId',
            [
                'menuItemId' => 626,
                'parentId' => 0,
                'seq' => 5
            ]
        );
        $this->registerMockSectionDAO();
        $this->registerMockIssues();
        $this->createUsersAndUserGroups($journal);

        $this->createSubmission();

        $doc = $journalExportFilter->execute($journal);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('journal.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
