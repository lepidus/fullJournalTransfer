<?php

/**
 * Copyright (c) 2014 Instituto Brasileiro de Informação em Ciência e Tecnologia 
 * Author: Giovani Pieri <giovani@lepidus.com.br>
 *
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 */

DEFINE("INTERNAL_TRANSFER_OBJECT_REVIEW_FORM", 1);
DEFINE("INTERNAL_TRANSFER_OBJECT_REVIEW_FORM_ELEMENT", 2);
DEFINE("INTERNAL_TRANSFER_OBJECT_USER", 3);
DEFINE("INTERNAL_TRANSFER_OBJECT_SECTION", 4);
DEFINE("INTERNAL_TRANSFER_OBJECT_ISSUE", 5);
DEFINE("INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE", 6);
DEFINE("INTERNAL_TRANSFER_OBJECT_ARTICLE_EMAIL_LOG", 7);
DEFINE("INTERNAL_TRANSFER_OBJECT_GROUP", 8);
DEFINE("INTERNAL_TRANSFER_OBJECT_ISSUE_FILE", 9);
DEFINE("INTERNAL_TRANSFER_OBJECT_ISSUE_GALLEY", 10);
DEFINE("INTERNAL_TRANSFER_OBJECT_REVIEW", 11);

class XMLDisassembler {
	var $logger;
	var $xml;
	var $journal;
	var $passwordEncryptionInTheXML;
	var $idTranslationTable;
	var $publicFolderPath;
	var $journalFolderPath;
	var $siteFolderPath;
	var $apacheRedirect = [];
	var $nginxRedirect = [];
	var $oldName;

	function createApacheRedirect($array,$journalName){
		$f = fopen("/tmp/{$journalName}.apache.redirect",'w+');
		fwrite($f,"##Redirects \n");
		fwrite($f,"<IfModule mod_rewrite.c>\n");
		fwrite($f,"RewriteEngine On\n");

		foreach($array as $string){
			fwrite($f,$string);
		}
		fwrite($f,"</IfModule>\n");
		fclose($f);

	}

	function createNginxRedirect($array,$journalName){
		$f = fopen("/tmp/{$journalName}.nginx.redirect",'w+');
		fwrite($f,"##Redirects \n");
		foreach($array as $string){
			fwrite($f,$string);
		}
		fclose($f);
	}
	function XMLDisassembler($inputFile, $publicFolderPath, $siteFolderPath, $journalFolderPath) {
		import('classes.file.JournalFileManager');
		import('classes.file.ArticleFileManager');
		import('classes.file.PublicFileManager');
		import('classes.file.IssueFileManager');

		$this->xml = new XMLReader();
		$this->xml->open($inputFile);
		$this->logger = new NullFullJournalLogger();

		$this->idTranslationTable = new IdTranslationTable(
											array(
												__('manager.reviewForms') => INTERNAL_TRANSFER_OBJECT_REVIEW_FORM, 
												__('manager.reviewFormElements') => INTERNAL_TRANSFER_OBJECT_REVIEW_FORM_ELEMENT, 
												__('common.user') => INTERNAL_TRANSFER_OBJECT_USER, 
												__('section.section') => INTERNAL_TRANSFER_OBJECT_SECTION, 
												__('issue.issue') => INTERNAL_TRANSFER_OBJECT_ISSUE, 
												__('plugins.importexport.fullJournal.terms.article_file') => INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, 
												__('plugins.importexport.fullJournal.terms.email_log') => INTERNAL_TRANSFER_OBJECT_ARTICLE_EMAIL_LOG,
												__('user.group') => INTERNAL_TRANSFER_OBJECT_GROUP,
												__('plugins.importexport.fullJournal.terms.issue_file') => INTERNAL_TRANSFER_OBJECT_ISSUE_FILE,
												__('plugins.importexport.fullJournal.terms.issue_galley') => INTERNAL_TRANSFER_OBJECT_ISSUE_GALLEY,
												__('submission.review') => INTERNAL_TRANSFER_OBJECT_REVIEW
											)
										);
		$this->publicFolderPath = $publicFolderPath;
		$this->journalFolderPath = $journalFolderPath;
		$this->siteFolderPath = $siteFolderPath;
	}

	function setLogger($logger) {
		$this->logger = $logger;
	}

	function startImporting() {
		$xml = $this->xml;
		$logger = $this->logger;

		$this->nextElement();
		assert($xml->name == 'journal');

		$this->nextElement();
		assert($xml->name == 'journalConfig');

		$logger->log("Creating journal\n");
		$this->createJournal();
		if ($xml->name != 'reviewForms') {
			$this->nextElement();
		}

		assert($xml->name == 'announcements');
		$logger->log("Importing announcements\n");
		$this->importAnnouncements();

		assert($xml->name == 'reviewForms');
		$logger->log("Importing review forms\n");
		$this->importReviewForms();

		assert($xml->name == 'users');
		$logger->log("Importing users\n");
		$this->importUsers();

		assert($xml->name == 'groups');
		$logger->log("Importing groups\n");
		$this->importGroups();

		assert($xml->name == 'sections');
		$logger->log("Importing sections\n");
		$this->importSections();

		assert($xml->name == 'issues');
		$logger->log("Importing issues\n");
		$this->importIssues();

		assert($xml->name == 'articles');
		$logger->log("Importing articles\n");
		$this->importArticles();

		$this->restorePublicFolder();
	}

	function createJournal() {
		$journalConfigXML = $this->getCurrentElementAsDom();

		$journalDao =& DAORegistry::getDAO("JournalDAO");
		$journal = new Journal();

		$this->oldName = $this->getChildValueAsString($journalConfigXML, "path");

		$journal->setPath($this->getChildValueAsString($journalConfigXML, "path"));
		$journal->setEnabled($this->getChildValueAsInt($journalConfigXML, "enabled"));
		$journal->setPrimaryLocale($this->getChildValueAsString($journalConfigXML, "primaryLocale"));
		$this->generateJournalPath($journal);
		$journalId = $journalDao->insertJournal($journal);
		$journalDao->resequenceJournals();

		// Make the file directories for the journal
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		if (!file_exists(Config::getVar('files', 'files_dir') . '/journals/' . $journalId)) {
			$fileManager->mkdir(Config::getVar('files', 'files_dir') . '/journals/' . $journalId);
		}
		if (!file_exists(Config::getVar('files', 'files_dir') . '/journals/' . $journalId . '/articles')) {
			$fileManager->mkdir(Config::getVar('files', 'files_dir') . '/journals/' . $journalId . '/articles');
		}
		if (!file_exists(Config::getVar('files', 'files_dir') . '/journals/' . $journalId . '/issues')) {
			$fileManager->mkdir(Config::getVar('files', 'files_dir') . '/journals/' . $journalId . '/issues');
		}
		if (!file_exists(Config::getVar('files', 'public_files_dir') . '/journals/' . $journalId)) {
			$fileManager->mkdir(Config::getVar('files', 'public_files_dir') . '/journals/' . $journalId);
		}

		import('classes.rt.ojs.JournalRTAdmin');
		$journalRtAdmin = new JournalRTAdmin($journalId);
		$journalRtAdmin->restoreVersions(false);

		// Make sure all plugins are loaded for settings preload
		PluginRegistry::loadAllPlugins();
		HookRegistry::call('JournalSiteSettingsForm::execute', array(&$this, &$journal, &$section, &$isNewJournal));

		$journalSettingsDao =& DAORegistry::getDAO('JournalSettingsDAO');
		$this->restoreDataObjectSettings($journalSettingsDao, $journalConfigXML->settings, "journal_settings", "journal_id", $journal->getId());

		$this->journal = $journal;
	}

	function importAnnouncements() {
		assert($this->xml->name == 'announcements');
		$journal = $this->journal;

		$announcementTypeDAO =& DAORegistry::getDAO('AnnouncementTypeDAO');
		$announcementDAO =& DAORegistry::getDAO('AnnouncementDAO');

		$this->nextElement();
		while($this->xml->name == 'announcementType' || $this->xml->name == 'announcement') {
			$isAnnouncementType = $this->xml->name == 'announcementType';

			if ($isAnnouncementType) {
				$announcementTypeXML = $this->getCurrentElementAsDom();

				$announcementType = new AnnouncementType();
				$announcementType->setAssocType(ASSOC_TYPE_JOURNAL);
				$announcementType->setAssocId($this->journal->getId());
				$announcementTypeDAO->insertAnnouncementType($announcementType);
				$this->restoreDataObjectSettings($announcementTypeDAO, $announcementTypeXML->settings, 'announcement_type_settings', 'type_id', $announcementType->getId());

				foreach ($announcementTypeXML->announcement as $announcementXML) {
					$announcement = new Announcement();

					$announcement->setAssocType(ASSOC_TYPE_JOURNAL);
					$announcement->setAssocId($this->journal->getId());
					$announcement->setTypeId($announcementType->getId());
					$announcement->setDateExpire($this->getChildValueAsString($announcementXML, "dateExpire"));
					$announcement->setDatePosted($this->getChildValueAsString($announcementXML, "datePosted"));
					$announcementDAO->insertAnnouncement($announcement);

					$this->restoreDataObjectSettings($announcementDAO, $announcementXML->settings, 'announcement_settings', 'announcement_id', $announcement->getId());
				}
			} else {
				$announcementXML = $this->getCurrentElementAsDom();
				$announcement = new Announcement();

				$announcement->setAssocType(ASSOC_TYPE_JOURNAL);
				$announcement->setAssocId($this->journal->getId());
				$announcement->setDateExpire($this->getChildValueAsString($announcementXML, "dateExpire"));
				$announcement->setDatePosted($this->getChildValueAsString($announcementXML, "datePosted"));
				$announcementDAO->insertAnnouncement($announcement);

				$this->restoreDataObjectSettings($announcementDAO, $announcementXML->settings, 'announcement_settings', 'announcement_id', $announcement->getId());
			}
			$this->nextElement();
		}
	}

	function importReviewForms() {
		assert($this->xml->name == 'reviewForms');
		$journal = $this->journal;

		$reviewFormDao =& DAORegistry::getDAO('ReviewFormDAO');
		$reviewFormElementDao =& DAORegistry::getDAO('ReviewFormElementDAO');

		$this->nextElement();
		while($this->xml->name == 'reviewForm') {
			$reviewFormXML = $this->getCurrentElementAsDom();

			$reviewForm = new ReviewForm();
			$reviewForm->setSequence($this->getChildValueAsInt($reviewFormXML, "sequence"));
			$reviewForm->setActive($this->getChildValueAsInt($reviewFormXML, "active"));
			$reviewForm->setAssocType(ASSOC_TYPE_JOURNAL);
			$reviewForm->setAssocId($this->journal->getId());
			$reviewFormDao->insertObject($reviewForm);
			$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_REVIEW_FORM, $this->getChildValueAsInt($reviewFormXML, "oldId"), $reviewForm->getId());

			foreach ($reviewFormXML->reviewElement as $reviewElementXML) {
				$reviewFormElement = new ReviewFormElement();
				$reviewFormElement->setReviewFormId($reviewForm->getId());
				$reviewFormElement->setSequence($this->getChildValueAsInt($reviewElementXML, "sequence"));
				$reviewFormElement->setElementType($this->getChildValueAsInt($reviewElementXML, "elementType"));
				$reviewFormElement->setRequired($this->getChildValueAsInt($reviewElementXML, "required"));
				$reviewFormElement->setIncluded($this->getChildValueAsInt($reviewElementXML, "included"));
				$reviewFormElementDao->insertObject($reviewFormElement);
				$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_REVIEW_FORM_ELEMENT, $this->getChildValueAsInt($reviewElementXML, "oldId"), $reviewFormElement->getId());

				$this->restoreDataObjectSettings($reviewFormElementDao, $reviewElementXML->settings, 'review_form_element_settings', 'review_form_element_id', $reviewFormElement->getId());
			}
			$this->restoreDataObjectSettings($reviewFormDao, $reviewFormXML->settings, 'review_form_settings', 'review_form_id', $reviewForm->getId());
			$this->nextElement();
		}
	}

	function importUsers() {
		assert($this->xml->name == 'users');
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();

		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$userDAO =& DAORegistry::getDAO('UserDAO');

		$publicFileManager =& new PublicFileManager();

		$site =& Request::getSite();
		$siteSupportedLocales = $site->getSupportedLocales();

		$this->nextElement();
		while($this->xml->name == 'user') {
			$userXML = $this->getCurrentElementAsDom();

			$username = $this->getChildValueAsString($userXML, "username");
			$email = $this->getChildValueAsString($userXML, "email");

			$userByEmail = $userDAO->getUserByEmail($email);

			$user = null;
			if (!empty($userByEmail)) {
				$user = $userByEmail;
			} else {
				$user = new User();
				$user->setUsername($this->getChildValueAsString($userXML, "username"));
				$user->setPassword($this->getChildValueAsString($userXML, "password"));
				$user->setSalutation($this->getChildValueAsString($userXML, "salutation"));
				$user->setFirstName($this->getChildValueAsString($userXML, "firstName"));
				$user->setMiddleName($this->getChildValueAsString($userXML, "middleName"));
				$user->setInitials($this->getChildValueAsString($userXML, "initials"));
				$user->setLastName($this->getChildValueAsString($userXML, "lastName"));
				$user->setSuffix($this->getChildValueAsString($userXML, "suffix"));
				$user->setGender($this->getChildValueAsString($userXML, "gender"));
				$user->setEmail($this->getChildValueAsString($userXML, "email"));
				$user->setUrl($this->getChildValueAsString($userXML, "url"));
				$user->setPhone($this->getChildValueAsString($userXML, "phone"));
				$user->setFax($this->getChildValueAsString($userXML, "fax"));
				$user->setMailingAddress($this->getChildValueAsString($userXML, "mailingAddress"));
				$user->setBillingAddress($this->getChildValueAsString($userXML, "billingAddress"));
				$user->setCountry($this->getChildValueAsString($userXML, "country"));
			
				$locales = array();
				foreach (explode(':', $this->getChildValueAsString($userXML, "locales")) as $locale) {
					if (AppLocale::isLocaleValid($locale) && in_array($locale, $siteSupportedLocales)) {
						array_push($locales, $locale);
					}
				}
				$user->setLocales($locales);
				$user->setDateLastEmail($this->getChildValueAsString($userXML, "dateLastEmail"));
				$user->setDateRegistered($this->getChildValueAsString($userXML, "dateRegistered"));
				$user->setDateValidated($this->getChildValueAsString($userXML, "dateValidated"));
				$user->setDateLastLogin($this->getChildValueAsString($userXML, "dateLastLogin"));
				$user->setMustChangePassword($this->getChildValueAsInt($userXML, "mustChangePassword"));
				$user->setDisabled($this->getChildValueAsInt($userXML, "disabled"));
				$user->setDisabledReason($this->getChildValueAsString($userXML, "disabledReason"));
				$user->setAuthId($this->getChildValueAsInt($userXML, "authId"));
				$user->setAuthStr($this->getChildValueAsString($userXML, "authStr"));
				$user->setInlineHelp($this->getChildValueAsInt($userXML, "inlineHelp"));

				$this->generateUsername($user);

				$userDAO->insertUser($user);
				$this->restoreDataObjectSettings($userDAO, $userXML->settings, 'user_settings', 'user_id', $user->getId());

				$user = $userDAO->getById($user->getId());
				$profileImage =& $user->getSetting('profileImage');
				if ($profileImage) {
					$oldProfileImage = $profileImage['uploadName'];
					$extension = $publicFileManager->getExtension($oldProfileImage);
					$newProfileImage = 'profileImage-' . $user->getId() . "." . $extension;
					$sourceFile = $this->siteFolderPath . '/' . $oldProfileImage;
					$publicFileManager->copyFile($sourceFile, $publicFileManager->getSiteFilesPath() . "/" .  $newProfileImage);
					unlink($sourceFile);

					$profileImage['uploadName'] = $newProfileImage;
					$user->updateSetting('profileImage', $profileImage);
				}

				$interests = array();
				foreach ($userXML->interest as $interest) {
					$interests[] = (string)$interest;
				}
				$interestManager->setInterestsForUser($user, $interests);
			}

			$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($userXML, "oldId"), $user->getId());

			foreach ($userXML->role as $roleXML) {
				$role = new Role();
				$role->setRoleId((int)$roleXML);
				$role->setUserId($user->getId());
				$role->setJournalId($this->journal->getId());
				$roleDao->insertRole($role);
			}

			$this->nextElement();
		}
	}

	function importGroups() {
		assert($this->xml->name == 'groups');
		$journal = $this->journal;

		$groupDao =& DAORegistry::getDAO('GroupDAO');
		$groupMembershipDao =& DAORegistry::getDAO('GroupMembershipDAO');

		$this->nextElement();
		while($this->xml->name == 'group') {
			$groupXML = $this->getCurrentElementAsDom();
			$group =& new Group();
			$group->setAssocType(ASSOC_TYPE_JOURNAL);
			$group->setAssocId($journal->getId());
			$group->setAboutDisplayed((int) $groupXML->aboutDisplayed);
			$group->setPublishEmail((int) $groupXML->publishEmail);
			$group->setSequence((int) $groupXML->sequence);
			$group->setContext((int) $groupXML->context);
			$groupDao->insertGroup($group);
			foreach ($groupXML->groupMembership as $groupMembershipXML) {
				$groupMembership = new GroupMembership();
				$groupMembership->setGroupId($group->getId());
				$groupMembership->setUserId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($groupMembershipXML, "userId")));
				$groupMembership->setSequence($this->getChildValueAsInt($groupMembershipXML, "sequence"));
				$groupMembership->setAboutDisplayed($this->getChildValueAsInt($groupMembershipXML, "aboutDisplayed"));
				$groupMembershipDao->insertMembership($groupMembership);
			}
			$this->restoreDataObjectSettings($groupDao, $groupXML->settings, 'group_settings', 'group_id', $group->getId());
			$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_GROUP, (int) $groupXML->oldId, $group->getId());
			$this->nextElement();
		}
	}

	function importSections() {
		assert($this->xml->name == 'sections');
		$sectionDAO =& DAORegistry::getDAO('SectionDAO');
		$sectionEditorsDAO =& DAORegistry::getDAO('SectionEditorsDAO');
		$sections = $sectionDAO->getJournalSections($this->journal->getId());

		$this->nextElement();
		while($this->xml->name == 'section') {
			$sectionXML = $this->getCurrentElementAsDom();

			$section = new Section();
			$section->setJournalId($this->journal->getId());
			$section->setReviewFormId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_REVIEW_FORM, $this->getChildValueAsInt($sectionXML, "reviewFormId")));
			$section->setSequence($this->getChildValueAsInt($sectionXML, "sequence"));
			$section->setMetaIndexed($this->getChildValueAsString($sectionXML, "metaIndexed"));
			$section->setMetaReviewed($this->getChildValueAsString($sectionXML, "metaReviewed"));
			$section->setAbstractsNotRequired($this->getChildValueAsInt($sectionXML, "abstractsNotRequired"));
			$section->setEditorRestricted($this->getChildValueAsInt($sectionXML, "editorRestricted"));
			$section->setHideTitle($this->getChildValueAsInt($sectionXML, "hideTitle"));
			$section->setHideAuthor($this->getChildValueAsInt($sectionXML, "hideAuthor"));
			$section->setHideAbout($this->getChildValueAsInt($sectionXML, "hideAbout"));
			$section->setDisableComments($this->getChildValueAsInt($sectionXML, "disableComments"));
			$section->setAbstractWordCount($this->getChildValueAsInt($sectionXML, "wordCount"));
			$sectionDAO->insertSection($section);

			$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_SECTION, $this->getChildValueAsInt($sectionXML, "oldId"), $section->getId());
			$this->restoreDataObjectSettings($sectionDAO, $sectionXML->settings, 'section_settings', 'section_id', $section->getId());

			foreach ($sectionXML->sectionEditor as $sectionEditorXML) {
				$userId = $this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($sectionEditorXML, "userId"));
				$canReview = $this->getChildValueAsInt($sectionEditorXML, "canReview");
				$canEdit = $this->getChildValueAsInt($sectionEditorXML, "canEdit");
				$sectionEditorsDAO->insertEditor($this->journal->getId(), $section->getId(), $userId, $canReview, $canEdit);
			}
			$this->nextElement();
		}
	}

	function importIssues() {
		assert($this->xml->name == 'issues');

		$issueDAO =& DAORegistry::getDAO('IssueDAO');
		$issueFileDAO =& DAORegistry::getDAO('IssueFileDAO');
		$issueGalleyDAO =& DAORegistry::getDAO('IssueGalleyDAO');
		$sectionDAO =& DAORegistry::getDAO('SectionDAO');
		$issues = $issueDAO->getIssues($this->journal->getId());

		$publicFileManager =& new PublicFileManager();

		$this->nextElement();
		while($this->xml->name == 'issue') {
			$issueXML = $this->getCurrentElementAsDom();

			$issue = new Issue();
			$issue->setJournalId($this->journal->getId());
			$issue->setVolume($this->getChildValueAsInt($issueXML, "volume"));
			$issue->setNumber($this->getChildValueAsString($issueXML, "number"));
			$issue->setYear($this->getChildValueAsInt($issueXML, "year"));
			$issue->setPublished($this->getChildValueAsInt($issueXML, "published"));
			$issue->setCurrent($this->getChildValueAsInt($issueXML, "current"));
			$issue->setDatePublished($this->getChildValueAsString($issueXML, "datePublished"));
			$issue->setDateNotified($this->getChildValueAsString($issueXML, "dateNotified"));
			$issue->setLastModified($this->getChildValueAsString($issueXML, "lastModified"));
			$issue->setAccessStatus($this->getChildValueAsInt($issueXML, "accessStatus"));
			$issue->setOpenAccessDate($this->getChildValueAsString($issueXML, "openAccessDate"));
			$issue->setShowVolume($this->getChildValueAsInt($issueXML, "showVolume"));
			$issue->setShowNumber($this->getChildValueAsInt($issueXML, "showNumber"));
			$issue->setShowYear($this->getChildValueAsInt($issueXML, "showYear"));
			$issue->setShowTitle($this->getChildValueAsInt($issueXML, "showTitle"));
			$issue->setStyleFileName($this->getChildValueAsString($issueXML, "styleFileName"));
			$issue->setOriginalStyleFileName($this->getChildValueAsString($issueXML, "originalStyleFileName"));
			
			$oldIssueId = $this->getChildValueAsInt($issueXML, "oldId");

			$issueDAO->insertIssue($issue);
			//$issueDAO->insertCustomIssueOrder($this->journal->getId(), $issue->getId(), $this->getChildValueAsInt($issueXML, "customOrder"));
			$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_ISSUE, $oldIssueId, $issue->getId());
			$this->restoreDataObjectSettings($issueDAO, $issueXML->settings, 'issue_settings', 'issue_id', $issue->getId());

			$issue =& $issueDAO->getIssueById($issue->getId()); // Reload issue to get restored settings
			$covers = $issue->getFileName(null);
			if ($covers) {
				foreach ($covers as $locale => $oldCoverFileName) {
					$sourceFile = $this->publicFolderPath . '/' . $oldCoverFileName;
					$extension = $publicFileManager->getExtension($oldCoverFileName);
					$destFile = 'cover_issue_' . $issue->getId() . "_$locale.$extension";
					$publicFileManager->copyJournalFile($this->journal->getId(), $sourceFile, $destFile);
					unlink($sourceFile);
					
					$issue->setFileName($destFile, $locale);
					$issueDAO->updateIssue($issue);
				}
			}

			if ($issue->getStyleFileName()) {
				$oldStyleFileName = $issue->getStyleFileName();
				$sourceFile = $this->publicFolderPath . '/' . $oldStyleFileName;
				$destFile = 'style_' . $issue->getId() . '.css';
				$publicFileManager->copyJournalFile($this->journal->getId(), $sourceFile, $destFile);
				unlink($sourceFile);

				$issue->setStyleFileName($destFile);
				$issueDAO->updateIssue($issue);
			}

			$issueFileManager = new IssueFileManager($issue->getId());
			foreach ($issueXML->issueFile as $issueFileXML) {
				$issueFile = new IssueFile();
				
				$issueFile->setIssueId($issue->getId());
				$issueFile->setFileName($this->getChildValueAsString($issueFileXML, "fileName"));
				$issueFile->setFileType($this->getChildValueAsString($issueFileXML, "fileType"));
				$issueFile->setFileSize($this->getChildValueAsInt($issueFileXML, "fileSize"));
				$issueFile->setContentType($this->getChildValueAsString($issueFileXML, "contentType"));
				$issueFile->setOriginalFileName($this->getChildValueAsString($issueFileXML, "originalFileName"));
				$issueFile->setDateUploaded($this->getChildValueAsString($issueFileXML, "dateUploaded"));
				$issueFile->setDateModified($this->getChildValueAsString($issueFileXML, "dateModified"));

				$issueFileDAO->insertIssueFile($issueFile);

				$oldFileName = $issueFile->getFileName();
				$extension = $issueFileManager->parseFileExtension($oldFileName);

				$contentPath = $issueFileManager->contentTypeToPath($issueFile->getContentType());
				$contentAbbrev = $issueFileManager->contentTypeToAbbrev($issueFile->getContentType());
				$fileInTransferPackage = $this->journalFolderPath."/issues/$oldIssueId/$contentPath/$oldFileName";
				$newFileName = $issue->getId().'-'.$issueFile->getId().'-'.$contentAbbrev.'.'.$extension;
				$newFilePath = "$contentPath/$newFileName";

				$issueFileManager->copyFile($fileInTransferPackage, $issueFileManager->getFilesDir() . $newFilePath);
				unlink($fileInTransferPackage);

				$issueFile->setFileName($newFileName);
				$issueFileDAO->updateIssueFile($issueFile);
				$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_ISSUE_FILE, $this->getChildValueAsInt($issueFileXML, "oldId"), $issueFile->getId());
			}

			foreach ($issueXML->issueGalley as $issueGalleyXML) {
				$issueGalley = new issueGalley();

				$issueGalley->setIssueId($issue->getId());
				$issueGalley->setLocale($this->getChildValueAsString($issueGalleyXML, "locale"));
				$issueGalley->setFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ISSUE_FILE, $this->getChildValueAsInt($issueGalleyXML, "fileId")));
				$issueGalley->setLabel($this->getChildValueAsString($issueGalleyXML, "label"));
				$issueGalley->setSequence($this->getChildValueAsInt($issueGalleyXML, "sequence"));

				$issueGalleyDAO->insertGalley($issueGalley);
				$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_ISSUE_GALLEY, $this->getChildValueAsInt($issueGalleyXML, "oldId"), $issueGalley->getId());
				$this->restoreDataObjectSettings($issueGalleyDAO, $issueGalleyXML->settings, 'issue_galley_settings', 'galley_id', $issueGalley->getId());
			}

			if (isset($issueXML->customSectionOrder)) {
				foreach ($issueXML->customSectionOrder->sectionOrder as $sectionOrderXML) {
					try {
						$sectionId = $this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_SECTION, (int)$sectionOrderXML['sectionId']);
						$seq = (int) $sectionOrderXML['seq'];
						$sectionDAO->insertCustomSectionOrder($issue->getId(), $sectionId, $seq);
					} catch (Exception $e) {
					}
				}
			}
			$this->nextElement();
		}
	}

	function importArticles() {
		assert($this->xml->name == 'articles');

		$articleDAO =& DAORegistry::getDAO('ArticleDAO');
		$articles = $articleDAO->getArticlesByJournalId($this->journal->getId());

		$journalFileManager = new JournalFileManager($this->journal);
		$publicFileManager =& new PublicFileManager();

		$this->nextElement();
		while($this->xml->name == 'article') {
			$articleXML = $this->getCurrentElementAsDom();

			$article = new Article();
			$article->setJournalId($this->journal->getId());

			$article->setUserId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int) $articleXML->userId));
			$article->setSectionId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_SECTION, (int) $articleXML->sectionId));
			
			$article->setLocale($this->getChildValueAsString($articleXML, "locale"));
			$article->setLanguage($this->getChildValueAsString($articleXML, "language"));
			$article->setCommentsToEditor($this->getChildValueAsString($articleXML, "commentsToEditor"));
			$article->setCitations($this->getChildValueAsString($articleXML, "citations"));
			$article->setDateSubmitted($this->getChildValueAsString($articleXML, "dateSubmitted"));
			$article->setDateStatusModified($this->getChildValueAsString($articleXML, "dateStatusModified"));
			$article->setLastModified($this->getChildValueAsString($articleXML, "lastModified"));
			$article->setStatus((int) $articleXML->status);
			$article->setSubmissionProgress((int) $articleXML->submissionProgress);
			$article->setCurrentRound((int) $articleXML->currentRound);
			$article->setPages($this->getChildValueAsString($articleXML, "pages"));
			$article->setFastTracked((int) $articleXML->fastTracked);
			$article->setHideAuthor((int) $articleXML->hideAuthor);
			$article->setCommentsStatus((int) $articleXML->commentsStatus);
			$articleDAO->insertArticle($article);

			$oldArticleId = $this->getChildValueAsInt($articleXML, "oldId");
			$this->restoreDataObjectSettings($articleDAO, $articleXML->settings, 'article_settings', 'article_id', $article->getId());

			$article =& $articleDAO->getArticle($article->getId()); // Reload article with restored settings

			import("lib.pkp.classes.config.Config");
			$Config = new Config;
			
			//creating the article redirect
			array_push($this->apacheRedirect,"Redirect 301 /{$this->oldName}/article/view/{$oldArticleId} {$Config->getVar("general","base_url")}/{$this->journal->getPath()}/article/view/{$article->getId()}\n");
			array_push($this->apacheRedirect,"Redirect 301 /{$this->oldName}/article/view/{$oldArticleId}/(.*)$ {$Config->getVar("general","base_url")}/{$this->journal->getPath()}/article/view/{$article->getId()}\n");
			
			array_push($this->nginxRedirect,
				"location /{$this->oldName}/article/view/{$oldArticleId} { \n\trewrite ^(.*)\$ {$Config->getVar("general","base_url")}/{$this->journal->getPath()}/article/view/{$article->getId()} redirect;\n}\n");
			array_push($this->nginxRedirect,
				"location /{$this->oldName}/article/view/{$oldArticleId}/^(.*)\$ { \n\trewrite ^(.*)\$ {$Config->getVar("general","base_url")}/{$this->journal->getPath()}/article/view/{$article->getId()} redirect;\n}\n");
			
			$covers = $article->getFileName(null);
			if ($covers) {
				foreach ($covers as $locale => $oldCoverFileName) {
					$sourceFile = $this->publicFolderPath . '/' . $oldCoverFileName;
					$extension = $publicFileManager->getExtension($oldCoverFileName);
					$destFile = 'cover_issue_' . $article->getId() . "_$locale.$extension";
					$publicFileManager->copyJournalFile($this->journal->getId(), $sourceFile, $destFile);
					unlink($sourceFile);
					
					$article->setFileName($destFile, $locale);
					$articleDAO->updateArticle($article);
				}
			}
			$articleFileManager = new ArticleFileManager($article->getId());

			$authorDAO =& DAORegistry::getDAO('AuthorDAO');
			foreach ($articleXML->author as $authorXML) {
				$author = new Author();

				$author->setArticleId($article->getId());
				$author->setFirstName($this->getChildValueAsString($authorXML, "firstName"));
				$author->setMiddleName($this->getChildValueAsString($authorXML, "middleName"));
				$author->setLastName($this->getChildValueAsString($authorXML, "lastName"));
				$author->setSuffix($this->getChildValueAsString($authorXML, "suffix"));
				$author->setCountry($this->getChildValueAsString($authorXML, "country"));
				$author->setEmail($this->getChildValueAsString($authorXML, "email"));
				$author->setUrl($this->getChildValueAsString($authorXML, "url"));
				$author->setUserGroupId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_GROUP, $this->getChildValueAsInt($authorXML, "userGroupId")));
				$author->setPrimaryContact($this->getChildValueAsInt($authorXML, "primaryContact"));
				$author->setSequence($this->getChildValueAsInt($authorXML, "sequence"));
				$authorDAO->insertAuthor($author);

				$this->restoreDataObjectSettings($authorDAO, $authorXML->settings, 'author_settings', 'author_id', $author->getId());
				unset($author);
			}

			$articleEmailLogDAO =& DAORegistry::getDAO('ArticleEmailLogDAO');
			$emailLogsXML = array();
			foreach ($articleXML->emailLogs->emailLog as $emailLogXML) {
				array_unshift($emailLogsXML, $emailLogXML);
			}
			foreach ($emailLogsXML as $emailLogXML) {
				$emailLog = new ArticleEmailLogEntry();
				$emailLog->setAssocType(ASSOC_TYPE_ARTICLE);
				$emailLog->setAssocId($article->getId());
				$emailLog->setSenderId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($emailLogXML, "senderId")));
				$emailLog->setDateSent($this->getChildValueAsString($emailLogXML, "dateSent"));
				$emailLog->setIPAddress($this->getChildValueAsString($emailLogXML, "IPAddress"));
				$emailLog->setEventType($this->getChildValueAsInt($emailLogXML, "eventType"));
				$emailLog->setFrom($this->getChildValueAsString($emailLogXML, "from"));
				$emailLog->setRecipients($this->getChildValueAsString($emailLogXML, "recipients"));
				$emailLog->setCcs($this->getChildValueAsString($emailLogXML, "ccs"));
				$emailLog->setBccs($this->getChildValueAsString($emailLogXML, "bccs"));
				$emailLog->setSubject($this->getChildValueAsString($emailLogXML, "subject"));
				$emailLog->setBody($this->getChildValueAsString($emailLogXML, "body"));
				$articleEmailLogDAO->insertObject($emailLog);
				$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_ARTICLE_EMAIL_LOG, $this->getChildValueAsInt($emailLogXML, "oldId"), $emailLog->getId());
			}

			$articleFileDAO =& DAORegistry::getDAO('ArticleFileDAO');
			foreach ($articleXML->articleFile as $articleFileXML) {
				try {
					$articleFile = new ArticleFile();
					$articleFile->setArticleId($article->getId());
					$articleFile->setSourceFileId($this->getChildValueAsInt($articleFileXML, "sourceFileId"));
					$articleFile->setSourceRevision($this->getChildValueAsInt($articleFileXML, "sourceRevision"));
					$articleFile->setRevision($this->getChildValueAsInt($articleFileXML, "revision"));
					$articleFile->setFileName($this->getChildValueAsString($articleFileXML, "fileName"));
					$articleFile->setFileType($this->getChildValueAsString($articleFileXML, "fileType"));
					$articleFile->setFileSize($this->getChildValueAsString($articleFileXML, "fileSize"));
					$articleFile->setOriginalFileName($this->getChildValueAsString($articleFileXML, "originalFileName"));
					$articleFile->setFileStage($this->getChildValueAsInt($articleFileXML, "fileStage"));
					$articleFile->setAssocId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_EMAIL_LOG, $this->getChildValueAsInt($articleFileXML, "assocId")));
					$articleFile->setDateUploaded($this->getChildValueAsString($articleFileXML, "dateUploaded"));
					$articleFile->setDateModified($this->getChildValueAsString($articleFileXML, "dateModified"));
					$articleFile->setRound($this->getChildValueAsInt($articleFileXML, "round"));
					$articleFile->setViewable($this->getChildValueAsInt($articleFileXML, "viewable"));
					$articleFileDAO->insertArticleFile($articleFile);

					$oldArticleFileId = $this->getChildValueAsInt($articleFileXML, "oldId");

					$oldFileName = $articleFile->getFileName();
					$stagePath = $articleFileManager->fileStageToPath($articleFile->getFileStage());
					$fileInTransferPackage = $this->journalFolderPath."/articles/$oldArticleId/$stagePath/$oldFileName";
					$newFileName = $articleFileManager->generateFilename($articleFile, $articleFile->getFileStage(), $articleFile->getOriginalFileName());
					$newFilePath = "/articles/".$article->getId()."/$stagePath/$newFileName";

					$journalFileManager->copyFile($fileInTransferPackage, $journalFileManager->filesDir . $newFilePath);
					unlink($fileInTransferPackage);

					$articleFileDAO->updateArticleFile($articleFile);
					$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $oldArticleFileId, $articleFile->getFileId());
				} catch (Exception $e) {
				}
			}

			$articleFiles = $articleFileDAO->getArticleFilesByArticle($article->getId());
			foreach ($articleFiles as $articleFile) {
				try {
					$articleFile->setSourceFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $articleFile->getSourceFileId()));
					$articleFileDAO->updateArticleFile($articleFile);
				} catch (Exception $e) {
				}
			}

			$suppFileDAO =& DAORegistry::getDAO('SuppFileDAO');
			foreach ($articleXML->suppFile as $suppFileXML) {
				$suppFile =& new SuppFile();
				$suppFile->setArticleId($article->getId());
				$suppFile->setRemoteURL($this->getChildValueAsString($suppFileXML, "remoteURL"));
				$suppFile->setFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $this->getChildValueAsInt($suppFileXML, "fileId")));
				$suppFile->setType($this->getChildValueAsString($suppFileXML, "type"));
				$suppFile->setDateCreated($this->getChildValueAsString($suppFileXML, "dateCreated"));
				$suppFile->setLanguage($this->getChildValueAsString($suppFileXML, "language"));
				$suppFile->setShowReviewers($this->getChildValueAsInt($suppFileXML, "showReviewers"));
				$suppFile->setDateSubmitted($this->getChildValueAsString($suppFileXML, "dateSubmitted"));
				$suppFile->setSequence($this->getChildValueAsInt($suppFileXML, "sequence"));
				$suppFileDAO->insertSuppFile($suppFile);

				$this->restoreDataObjectSettings($suppFileDAO, $suppFileXML->settings, 'article_supp_file_settings', 'supp_id', $suppFile->getId());
			}

			$articleGalleyDAO =& DAORegistry::getDAO('ArticleGalleyDAO');
			foreach ($articleXML->articleGalley as $articleGalleyXML) {
				$articleGalley = null;
				if ($articleGalleyXML->htmlGalley == "1") {
					$articleGalley = new ArticleHTMLGalley();
				} else {
					$articleGalley = new ArticleGalley();
				}

				$articleGalley->setArticleId($article->getId());
				$articleGalley->setLocale($this->getChildValueAsString($articleGalleyXML, "locale"));
				$articleGalley->setFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $this->getChildValueAsInt($articleGalleyXML, "fileId")));
				$articleGalley->setLabel($this->getChildValueAsString($articleGalleyXML, "label"));
				$articleGalley->setSequence($this->getChildValueAsInt($articleGalleyXML, "sequence"));
				$articleGalley->setRemoteURL($this->getChildValueAsString($articleGalleyXML, "remoteURL"));
				if ($articleGalley instanceof ArticleHTMLGalley) {
					$articleGalley->setStyleFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $this->getChildValueAsInt($articleGalleyXML, "styleFileId")));
				}
				$articleGalleyDAO->insertGalley($articleGalley);

				if ($articleGalley instanceof ArticleHTMLGalley) {
					foreach ($articleGalleyXML->htmlGalleyImage as $articleGalleyImageXML) {
						$imageId = $this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$articleGalleyImageXML);
						$articleGalleyDAO->insertGalleyImage($articleGalley->getId(), $imageId);
					}
				}
				$this->restoreDataObjectSettings($articleGalleyDAO, $articleGalleyXML->settings, 'article_galley_settings', 'galley_id', $articleGalley->getId());
			}

			$noteDAO =& DAORegistry::getDAO('NoteDAO');
			foreach ($articleXML->articleNote as $articleNoteXML) {
				$articleNote = new Note();
				$articleNote->setUserId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($articleNoteXML, "userId")));
				$articleNote->setFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $this->getChildValueAsInt($articleNoteXML, "fileId")));

				$articleNote->setAssocType(ASSOC_TYPE_ARTICLE);
				$articleNote->setAssocId($article->getId());

				$articleNote->setDateCreated($this->getChildValueAsString($articleNoteXML, "dateCreated"));
				$articleNote->setDateModified($this->getChildValueAsString($articleNoteXML, "dateModified"));
				$articleNote->setContents($this->getChildValueAsString($articleNoteXML, "contents"));
				$articleNote->setTitle($this->getChildValueAsString($articleNoteXML, "title"));
				$noteDAO->insertObject($articleNote);
			}

			$editAssignmentDAO =& DAORegistry::getDAO('EditAssignmentDAO');
			foreach ($articleXML->editAssignment as $editAssignmentXML) {
				$editAssignment = new EditAssignment();
				$editAssignment->setArticleId($article->getId());
	
				$editAssignment->setEditorId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($editAssignmentXML, "editorId")));
				$editAssignment->setCanReview($this->getChildValueAsInt($editAssignmentXML, "canReview"));
				$editAssignment->setCanEdit($this->getChildValueAsInt($editAssignmentXML, "canEdit"));
				$editAssignment->setDateUnderway($this->getChildValueAsString($editAssignmentXML, "dateUnderway"));
				$editAssignment->setDateNotified($this->getChildValueAsString($editAssignmentXML, "dateNotified"));

				$editAssignmentDAO->insertEditAssignment($editAssignment);
			}

			$sectionEditorSubmissionDAO =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
			foreach ($articleXML->reviewRounds->reviewRound as $reviewRoundXML) {
				$sectionEditorSubmissionDAO->createReviewRound($article->getId(), $this->getChildValueAsInt($reviewRoundXML, "round"), $this->getChildValueAsInt($reviewRoundXML, "reviewRevision"));
			}

			$reviewAssignmentDAO =& DAORegistry::getDAO('ReviewAssignmentDAO');
			$reviewFormResponseDAO =& DAORegistry::getDAO('ReviewFormResponseDAO');
			foreach ($articleXML->reviewAssignment as $reviewAssignmentXML) {
				$reviewAssignment = new ReviewAssignment();
				$reviewAssignment->setSubmissionId($article->getId());
				$reviewAssignment->setReviewerId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($reviewAssignmentXML, "reviewerId")));
				try {
					$reviewAssignment->setReviewerFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $this->getChildValueAsInt($reviewAssignmentXML, "reviewerFileId")));
				} catch (Exception $e) {
					$this->logger->log("Arquivo do artigo $oldArticleId não encontrado. ID: " . $this->getChildValueAsInt($reviewAssignmentXML, "reviewerFileId") . "\n");
				}
				$reviewAssignment->setReviewFormId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_REVIEW_FORM, $this->getChildValueAsInt($reviewAssignmentXML, "reviewFormId")));
				$reviewAssignment->setReviewRoundId($this->getChildValueAsInt($reviewAssignmentXML, "reviewRoundId"));
				$reviewAssignment->setStageId($this->getChildValueAsInt($reviewAssignmentXML, "stageId"));
				$reviewAssignment->setReviewerFullName($this->getChildValueAsString($reviewAssignmentXML, "reviewerFullName"));
				$reviewAssignment->setCompetingInterests($this->getChildValueAsString($reviewAssignmentXML, "competingInterests"));
				$reviewAssignment->setRegretMessage($this->getChildValueAsString($reviewAssignmentXML, "regretMessage"));
				$reviewAssignment->setRecommendation($this->getChildValueAsString($reviewAssignmentXML, "recommendation"));
				$reviewAssignment->setDateAssigned($this->getChildValueAsString($reviewAssignmentXML, "dateAssigned"));
				$reviewAssignment->setDateNotified($this->getChildValueAsString($reviewAssignmentXML, "dateNotified"));
				$reviewAssignment->setDateConfirmed($this->getChildValueAsString($reviewAssignmentXML, "dateConfirmed"));
				$reviewAssignment->setDateCompleted($this->getChildValueAsString($reviewAssignmentXML, "dateCompleted"));
				$reviewAssignment->setDateAcknowledged($this->getChildValueAsString($reviewAssignmentXML, "dateAcknowledged"));
				$reviewAssignment->setDateDue($this->getChildValueAsString($reviewAssignmentXML, "dateDue"));
				$reviewAssignment->setDateResponseDue($this->getChildValueAsString($reviewAssignmentXML, "dateResponseDue"));
				$reviewAssignment->setLastModified($this->getChildValueAsString($reviewAssignmentXML, "lastModified"));
				$reviewAssignment->setDeclined($this->getChildValueAsInt($reviewAssignmentXML, "declined"));
				$reviewAssignment->setReplaced($this->getChildValueAsInt($reviewAssignmentXML, "replaced"));
				$reviewAssignment->setCancelled($this->getChildValueAsInt($reviewAssignmentXML, "cancelled"));
				$reviewAssignment->setQuality($this->getChildValueAsInt($reviewAssignmentXML, "quality"));
				$reviewAssignment->setDateRated($this->getChildValueAsString($reviewAssignmentXML, "dateRated"));
				$reviewAssignment->setDateReminded($this->getChildValueAsString($reviewAssignmentXML, "dateReminded"));
				$reviewAssignment->setReminderWasAutomatic($this->getChildValueAsInt($reviewAssignmentXML, "reminderWasAutomatic"));
				$reviewAssignment->setRound($this->getChildValueAsInt($reviewAssignmentXML, "round"));
				$reviewAssignment->setReviewRevision($this->getChildValueAsInt($reviewAssignmentXML, "reviewRevision"));
				$reviewAssignment->setReviewMethod($this->getChildValueAsInt($reviewAssignmentXML, "reviewMethod"));
				$reviewAssignment->setUnconsidered($this->getChildValueAsInt($reviewAssignmentXML, "unconsidered"));
				$reviewAssignmentDAO->insertObject($reviewAssignment);
				$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_REVIEW, $this->getChildValueAsInt($reviewAssignmentXML, "oldId"), $reviewAssignment->getId());

				foreach ($reviewAssignmentXML->formResponses->formResponse as $formResponseXML) {
					$reviewFormResponseDAO->update(
						'INSERT INTO review_form_responses
							(review_form_element_id, review_id, response_type, response_value)
							VALUES
							(?, ?, ?, ?)',
						array(
							$this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_REVIEW_FORM_ELEMENT, $this->getChildValueAsInt($formResponseXML, "reviewFormElementId")),
							$reviewAssignment->getId(),
							$this->getChildValueAsString($formResponseXML, "responseType"),
							$this->getChildValueAsString($formResponseXML, "responseValue")
						)
					);
				}
			}

			$articleCommentDAO =& DAORegistry::getDAO('ArticleCommentDAO');
			foreach ($articleXML->articleComment as $articleCommentXML) {
				$articleComment = new ArticleComment();
				$articleComment->setArticleId($article->getId());
				$articleComment->setCommentType($this->getChildValueAsInt($articleCommentXML, "commentType"));

				switch ($articleComment->getCommentType()) {
					case COMMENT_TYPE_EDITOR_DECISION:
					case COMMENT_TYPE_COPYEDIT:
					case COMMENT_TYPE_LAYOUT:
					case COMMENT_TYPE_PROOFREAD:
						$articleComment->setAssocId($article->getId());
						break;
					case COMMENT_TYPE_PEER_REVIEW:
						$articleComment->setAssocId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_REVIEW, $this->getChildValueAsInt($articleCommentXML, "assocId")));
						break;
				}

				$articleComment->setRoleId($this->getChildValueAsInt($articleCommentXML, "roleId"));
				$articleComment->setAuthorId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($articleCommentXML, "authorId")));
				$articleComment->setCommentTitle($this->getChildValueAsString($articleCommentXML, "commentTitle"));
				$articleComment->setComments($this->getChildValueAsString($articleCommentXML, "comments"));
				$articleComment->setDatePosted($this->getChildValueAsString($articleCommentXML, "datePosted"));
				$articleComment->setDateModified($this->getChildValueAsString($articleCommentXML, "dateModified"));
				$articleComment->setViewable($this->getChildValueAsInt($articleCommentXML, "viewable"));
				$articleCommentDAO->insertArticleComment($articleComment);
			}

			$signoffDAO =& DAORegistry::getDAO('SignoffDAO');
			foreach ($articleXML->signoff as $signoffXML) {
				$signoff = new Signoff();
				$signoff->setAssocType(ASSOC_TYPE_ARTICLE);
				$signoff->setAssocId($article->getId());
				$signoff->setUserId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($signoffXML, "userId")));
				$signoff->setFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $this->getChildValueAsInt($signoffXML, "fileId")));
				$signoff->setUserGroupId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_GROUP, $this->getChildValueAsInt($signoffXML, "userGroupId")));
				
				$signoff->setSymbolic($this->getChildValueAsString($signoffXML, "symbolic"));
				$signoff->setFileRevision($this->getChildValueAsInt($signoffXML, "fileRevision"));
				$signoff->setDateUnderway($this->getChildValueAsString($signoffXML, "dateUnderway"));
				$signoff->setDateNotified($this->getChildValueAsString($signoffXML, "dateNotified"));
				$signoff->setDateCompleted($this->getChildValueAsString($signoffXML, "dateCompleted"));
				$signoff->setDateAcknowledged($this->getChildValueAsString($signoffXML, "dateAcknowledged"));
				$signoffDAO->insertObject($signoff);
			}

			$editorSubmissionDAO =& DAORegistry::getDAO('EditorSubmissionDAO');
			foreach ($articleXML->editDecisions as $editDecisionXML) {
				$editDecisions =& $editorSubmissionDAO->update(
					'INSERT INTO edit_decisions (article_id, round, editor_id, decision, date_decided) values (?, ?, ?, ?, ?)', 
					array($article->getId(), 
						$this->getChildValueAsString($editDecisionXML, "round"), 
						$this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($editDecisionXML, "editorId")), 
						$this->getChildValueAsString($editDecisionXML, "decision"), 
						$this->getChildValueAsString($editDecisionXML, "dateDecided"))
				);
			}

			$publishedArticleDAO =& DAORegistry::getDAO('PublishedArticleDAO');
			if (isset($articleXML->publishedArticle)) {
				$publishedArticleXML = $articleXML->publishedArticle;
				$publishedArticle = new PublishedArticle();
				$publishedArticle->setId($article->getId());
				$publishedArticle->setIssueId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ISSUE, $this->getChildValueAsInt($publishedArticleXML, "issueId")));
				$publishedArticle->setDatePublished($this->getChildValueAsString($publishedArticleXML, "datePublished"));
				$publishedArticle->setSeq($this->getChildValueAsInt($publishedArticleXML, "seq"));
				$publishedArticle->setAccessStatus($this->getChildValueAsInt($publishedArticleXML, "accessStatus"));
				$publishedArticleDAO->insertPublishedArticle($publishedArticle);
			}
			
			$articleEventLogDAO =& DAORegistry::getDAO('ArticleEventLogDAO');
			$eventLogsXML =& iterator_to_array($articleXML->eventLogs->eventLog);
			$eventLogsXML = array();
			foreach ($articleXML->eventLogs->eventLog as $eventLogXML) {
				array_unshift($eventLogsXML, $eventLogXML);
			}
			foreach ($eventLogsXML as $eventLogXML) {
				$eventLog = new ArticleEventLogEntry();
				$eventLog->setAssocType(ASSOC_TYPE_ARTICLE);
				$eventLog->setAssocId($article->getId());
				$eventLog->setUserId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, $this->getChildValueAsInt($eventLogXML, "userId")));
				$eventLog->setDateLogged($this->getChildValueAsString($eventLogXML, "dateLogged"));
				$eventLog->setIPAddress($this->getChildValueAsString($eventLogXML, "IPAddress"));
				$eventLog->setEventType($this->getChildValueAsInt($eventLogXML, "eventType"));
				$eventLog->setMessage($this->getChildValueAsString($eventLogXML, "message"));
				$eventLog->setIsTranslated($this->getChildValueAsInt($eventLogXML, "isTranslated"));
				$articleEventLogDAO->insertObject($eventLog);

				$this->restoreDataObjectSettings($articleEventLogDAO, $eventLogXML->settings, 'event_log_settings', 'log_id', $eventLog->getId());
			}

			try {
				$article->setSubmissionFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $this->getChildValueAsInt($articleXML, "submissionFileId")));
			} catch (Exception $e) {
			}
			try {
				$article->setRevisedFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $this->getChildValueAsInt($articleXML, "revisedFileId")));
			} catch (Exception $e) {
			}
			try {
				$article->setReviewFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $this->getChildValueAsInt($articleXML, "reviewFileId")));
			} catch (Exception $e) {
			}
			try {
				$article->setEditorFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, $this->getChildValueAsInt($articleXML, "editorFileId")));
			} catch (Exception $e) {
			}
			$articleDAO->updateArticle($article);

			$this->nextElement();
		}
		$this->createApacheRedirect($this->apacheRedirect,$this->oldName);
		$this->createNginxRedirect($this->nginxRedirect,$this->oldName);
	}

	function restorePublicFolder() {
		$publicFileManager = new PublicFileManager();
		$oldPublicFolder = $this->publicFolderPath;
		$newPublicFolder = $publicFileManager->getJournalFilesPath($this->journal->getId());

		$dir = new DirectoryIterator($oldPublicFolder);
		foreach ($dir as $fileinfo) {
		    if (!$fileinfo->isDot()) {
		    	if ($fileinfo->isDir()) {
					$publicFileManager->copyDir($fileinfo->getPathname(), $newPublicFolder . "/" . $fileinfo->getFileName());
		    	} else if ($fileinfo->isFile()) {
					$publicFileManager->copyFile($fileinfo->getPathname(), $newPublicFolder . "/" . $fileinfo->getFileName());
		    	}
		    }
		}
	}

	function restoreDataObjectSettings($dao, &$settingsNode, $tableName, $idFieldName, $newIdFieldValue) {
		$dao->update("DELETE FROM $tableName WHERE $idFieldName=?", $newIdFieldValue);

		$updateArray = array();
		$updateArray[$idFieldName] = $newIdFieldValue;

		foreach ($settingsNode->setting as $settingXML) {
			$settingAttrs = $settingXML->attributes();
			$updateArray['setting_name'] = (string) $settingXML['name'];
			if (isset($settingAttrs['locale'])) {
				$updateArray['locale'] = (string) $settingXML['locale'];
			} else {
				unset($updateArray['locale']);
			}
			if (isset($settingAttrs['assocId'])) {
				$assocType = (int)$settingAttrs['assocType'];
				if ($assocType == ASSOC_TYPE_JOURNAL) {
					$updateArray['assoc_type'] = ASSOC_TYPE_JOURNAL;
					if ((int) $settingXML['assoc_id'] != 0) {
						$updateArray['assoc_id'] = $this->journal->getId();
					}
				}
			} else {
				unset($updateArray['assoc_type']);
				unset($updateArray['assoc_id']);
			}
			$updateArray['setting_type'] = (string) $settingXML['type'];
			$updateArray['setting_value'] = (string) $settingXML;
			$dao->replace($tableName, $updateArray, array('setting_name', 'locale', $idFieldName));
		}
	}

	function generateUsername(&$user) {
		$userDao =& DAORegistry::getDAO('UserDAO');
		$baseUsername = $user->getUsername();
		for ($username = $baseUsername, $i=1; $userDao->userExistsByUsername($username, null, true); $i++) {
			$username = $baseUsername . $i;
		}
		$user->setUsername($username);
	}

	function generateJournalPath(&$journal) {
		$journalDao =& DAORegistry::getDAO('JournalDAO');
		$basePath = $journal->getPath();
		for ($path = $basePath, $i=1; $journalDao->journalExistsByPath($path, true); $i++) {
			$path = $basePath . $i;
		}
		$journal->setPath($path);
	}

	function nextElement() {
		do { } while ($this->xml->read() && $this->xml->nodeType != XMLReader::ELEMENT);
	}

	function getCurrentElementAsDom() {
		$element = $this->xml->expand();
		$doc = new DOMDocument("1.0", "UTF-8");
        $node = $doc->importNode($element, true);
        $doc->appendChild($node);
		$simpleXml = simplexml_import_dom($this->xml->expand($doc));
		$this->xml->next();
		return $simpleXml;
	}

	function getChildValueAsInt($dom, $child) {
		if (isset($dom->$child)) {
			return (int)$dom->$child;
		}
		return null;
	}

	function getChildValueAsString($dom, $child) {
		if (isset($dom->$child)) {
			return (string)$dom->$child;
		}
		return null;
	}


}

?>