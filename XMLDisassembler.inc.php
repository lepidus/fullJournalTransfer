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

class XMLDisassembler {
	var $logger;
	var $xml;
	var $journal;
	var $passwordEncryptionInTheXML;
	var $idTranslationTable;
	var $publicFolderPath;
	var $journalFolderPath;
	var $siteFolderPath;

	function XMLDisassembler($inputFile, $publicFolderPath, $siteFolderPath, $journalFolderPath) {
		import('classes.file.JournalFileManager');
		import('classes.file.ArticleFileManager');
		import('classes.file.PublicFileManager');
		import('classes.file.IssueFileManager');

		$this->xml = new XMLReader();
		$this->xml->open($inputFile);
		$this->logger = new NullFullJournalTransferLogger();

		$this->idTranslationTable = new IdTranslationTable(
											array(
												__('manager.reviewForms') => INTERNAL_TRANSFER_OBJECT_REVIEW_FORM, 
												__('manager.reviewFormElements') => INTERNAL_TRANSFER_OBJECT_REVIEW_FORM_ELEMENT, 
												__('common.user') => INTERNAL_TRANSFER_OBJECT_USER, 
												__('section.section') => INTERNAL_TRANSFER_OBJECT_SECTION, 
												__('issue.issue') => INTERNAL_TRANSFER_OBJECT_ISSUE, 
												__('plugins.importexport.fullJournalTransfer.terms.article_file') => INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, 
												__('plugins.importexport.fullJournalTransfer.terms.email_log') => INTERNAL_TRANSFER_OBJECT_ARTICLE_EMAIL_LOG,
												__('user.group') => INTERNAL_TRANSFER_OBJECT_GROUP,
												__('plugins.importexport.fullJournalTransfer.terms.issue_file') => INTERNAL_TRANSFER_OBJECT_ISSUE_FILE,
												__('plugins.importexport.fullJournalTransfer.terms.issue_galley') => INTERNAL_TRANSFER_OBJECT_ISSUE_GALLEY
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

		$journal->setPath((string)$journalConfigXML->path);
		$journal->setEnabled((int)$journalConfigXML->enabled);
		$journal->setPrimaryLocale((string)$journalConfigXML->primaryLocale);
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
		while($this->xml->name == 'announcementType') {
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
				$announcement->setDateExpire((string) $announcementXML->dateExpire);
				$announcement->setDatePosted((string) $announcementXML->datePosted);
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
			$reviewForm->setSequence((int)$reviewFormXML->sequence);
			$reviewForm->setActive((int)$reviewFormXML->active);
			$reviewForm->setAssocType(ASSOC_TYPE_JOURNAL);
			$reviewForm->setAssocId($this->journal->getId());
			$reviewFormDao->insertObject($reviewForm);
			$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_REVIEW_FORM, (int)$reviewFormXML->oldId, $reviewForm->getId());

			foreach ($reviewFormXML->reviewElement as $reviewElementXML) {
				$reviewFormElement = new ReviewFormElement();
				$reviewFormElement->setReviewFormId($reviewForm->getId());
				$reviewFormElement->setSequence((int)$reviewElementXML->sequence);
				$reviewFormElement->setElementType((int)$reviewElementXML->elementType);
				$reviewFormElement->setRequired((int)$reviewElementXML->required);
				$reviewFormElement->setIncluded((int)$reviewElementXML->included);
				$reviewFormElementDao->insertObject($reviewFormElement);
				$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_REVIEW_FORM_ELEMENT, (int)$reviewElementXML->oldId, $reviewFormElement->getId());

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

			$username = (string)$userXML->username;
			$email = (string)$userXML->email;

			$userByEmail = $userDAO->getUserByEmail($email);

			$user = null;
			if (!empty($userByEmail)) {
				$user = $userByEmail;
			} else {
				$user = new User();
				$user->setUsername((string)$userXML->username);
				$user->setPassword((string)$userXML->password);
				$user->setSalutation((string)$userXML->salutation);
				$user->setFirstName((string)$userXML->firstName);
				$user->setMiddleName((string)$userXML->middleName);
				$user->setInitials((string)$userXML->initials);
				$user->setLastName((string)$userXML->lastName);
				$user->setSuffix((string)$userXML->suffix);
				$user->setGender((string)$userXML->gender);
				$user->setEmail((string)$userXML->email);
				$user->setUrl((string)$userXML->url);
				$user->setPhone((string)$userXML->phone);
				$user->setFax((string)$userXML->fax);
				$user->setMailingAddress((string)$userXML->mailingAddress);
				$user->setBillingAddress((string)$userXML->billingAddress);
				$user->setCountry((string)$userXML->country);
			
				$locales = array();
				foreach (explode(':', (string)$userXML->locales) as $locale) {
					if (AppLocale::isLocaleValid($locale) && in_array($locale, $siteSupportedLocales)) {
						array_push($locales, $locale);
					}
				}
				$user->setLocales($locales);
				$user->setDateLastEmail((string)$userXML->dateLastEmail);
				$user->setDateRegistered((string)$userXML->dateRegistered);
				$user->setDateValidated((string)$userXML->dateValidated);
				$user->setDateLastLogin((string)$userXML->dateLastLogin);
				$user->setMustChangePassword((int)$userXML->mustChangePassword);
				$user->setDisabled((int)$userXML->disabled);
				$user->setDisabledReason((string)$userXML->disabledReason);
				$user->setAuthId((int)$userXML->authId);
				$user->setAuthStr((string)$userXML->authStr);
				$user->setInlineHelp((int)$userXML->inlineHelp);

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

			$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_USER, (int)$userXML->oldId, $user->getId());

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
				$groupMembership->setUserId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int)$groupMembershipXML->userId));
				$groupMembership->setSequence((int)$groupMembershipXML->sequence);
				$groupMembership->setAboutDisplayed((int)$groupMembershipXML->aboutDisplayed);
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
			$section->setReviewFormId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_REVIEW_FORM, (int)$sectionXML->reviewFormId));
			$section->setSequence((int)$sectionXML->sequence);
			$section->setMetaIndexed((string)$sectionXML->metaIndexed);
			$section->setMetaReviewed((string)$sectionXML->metaReviewed);
			$section->setAbstractsNotRequired((int)$sectionXML->abstractsNotRequired);
			$section->setEditorRestricted((int)$sectionXML->editorRestricted);
			$section->setHideTitle((int)$sectionXML->hideTitle);
			$section->setHideAuthor((int)$sectionXML->hideAuthor);
			$section->setHideAbout((int)$sectionXML->hideAbout);
			$section->setDisableComments((int)$sectionXML->disableComments);
			$section->setAbstractWordCount((int)$sectionXML->wordCount);
			$sectionDAO->insertSection($section);

			$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_SECTION, (int)$sectionXML->oldId, $section->getId());
			$this->restoreDataObjectSettings($sectionDAO, $sectionXML->settings, 'section_settings', 'section_id', $section->getId());

			foreach ($sectionXML->sectionEditor as $sectionEditorXML) {
				$userId = $this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int)$sectionEditorXML->userId);
				$canReview = (int)$sectionEditorXML->canReview;
				$canEdit = (int)$sectionEditorXML->canEdit;
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
			$issue->setVolume((int)$issueXML->volume);
			$issue->setNumber((string)$issueXML->number);
			$issue->setYear((int)$issueXML->year);
			$issue->setPublished((int)$issueXML->published);
			$issue->setCurrent((int)$issueXML->current);
			$issue->setDatePublished((string)$issueXML->datePublished);
			$issue->setDateNotified((string)$issueXML->dateNotified);
			$issue->setLastModified((string)$issueXML->lastModified);
			$issue->setAccessStatus((int)$issueXML->accessStatus);
			$issue->setOpenAccessDate((string)$issueXML->openAccessDate);
			$issue->setShowVolume((int)$issueXML->showVolume);
			$issue->setShowNumber((int)$issueXML->showNumber);
			$issue->setShowYear((int)$issueXML->showYear);
			$issue->setShowTitle((int)$issueXML->showTitle);
			$issue->setStyleFileName((string)$issueXML->styleFileName);
			$issue->setOriginalStyleFileName((string)$issueXML->originalStyleFileName);
			
			$oldIssueId = (int)$issueXML->oldId;

			$issueDAO->insertIssue($issue);
			//$issueDAO->insertCustomIssueOrder($this->journal->getId(), $issue->getId(), (int)$issueXML->customOrder);
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
				$issueFile->setFileName((string)$issueFileXML->fileName);
				$issueFile->setFileType((string)$issueFileXML->fileType);
				$issueFile->setFileSize((int)$issueFileXML->fileSize);
				$issueFile->setContentType((string)$issueFileXML->contentType);
				$issueFile->setOriginalFileName((string)$issueFileXML->originalFileName);
				$issueFile->setDateUploaded((string)$issueFileXML->dateUploaded);
				$issueFile->setDateModified((string)$issueFileXML->dateModified);

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
				$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_ISSUE_FILE, (int)$issueFileXML->oldId, $issueFile->getId());
			}

			foreach ($issueXML->issueGalley as $issueGalleyXML) {
				$issueGalley = new issueGalley();

				$issueGalley->setIssueId($issue->getId());
				$issueGalley->setLocale((string)$issueGalleyXML->locale);
				$issueGalley->setFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ISSUE_FILE, (int)$issueGalleyXML->fileId));
				$issueGalley->setLabel((string)$issueGalleyXML->label);
				$issueGalley->setSequence((int)$issueGalleyXML->sequence);

				$issueGalleyDAO->insertGalley($issueGalley);
				$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_ISSUE_GALLEY, (int)$issueGalleyXML->oldId, $issueGalley->getId());
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
			
			$article->setLocale((string) $articleXML->locale);
			$article->setLanguage((string) $articleXML->language);
			$article->setCommentsToEditor((string) $articleXML->commentsToEditor);
			$article->setCitations((string) $articleXML->citations);
			$article->setDateSubmitted((string) $articleXML->dateSubmitted);
			$article->setDateStatusModified((string) $articleXML->dateStatusModified);
			$article->setLastModified((string) $articleXML->lastModified);
			$article->setStatus((int) $articleXML->status);
			$article->setSubmissionProgress((int) $articleXML->submissionProgress);
			$article->setCurrentRound((int) $articleXML->currentRound);
			$article->setPages((string) $articleXML->pages);
			$article->setFastTracked((int) $articleXML->fastTracked);
			$article->setHideAuthor((int) $articleXML->hideAuthor);
			$article->setCommentsStatus((int) $articleXML->commentsStatus);
			$articleDAO->insertArticle($article);

			$oldArticleId = (int)$articleXML->oldId;
			$this->restoreDataObjectSettings($articleDAO, $articleXML->settings, 'article_settings', 'article_id', $article->getId());

			$article =& $articleDAO->getArticle($article->getId()); // Reload article with restored settings
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
				$author->setFirstName((string) $authorXML->firstName);
				$author->setMiddleName((string) $authorXML->middleName);
				$author->setLastName((string) $authorXML->lastName);
				$author->setSuffix((string) $authorXML->suffix);
				$author->setCountry((string) $authorXML->country);
				$author->setEmail((string) $authorXML->email);
				$author->setUrl((string) $authorXML->url);
				$author->setUserGroupId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_GROUP, (int)$authorXML->userGroupId));
				$author->setPrimaryContact((int) $authorXML->primaryContact);
				$author->setSequence((int) $authorXML->sequence);
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
				$emailLog->setSenderId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int)$emailLogXML->senderId));
				$emailLog->setDateSent((string)$emailLogXML->dateSent);
				$emailLog->setIPAddress((string)$emailLogXML->IPAddress);
				$emailLog->setEventType((int)$emailLogXML->eventType);
				$emailLog->setFrom((string)$emailLogXML->from);
				$emailLog->setRecipients((string)$emailLogXML->recipients);
				$emailLog->setCcs((string)$emailLogXML->ccs);
				$emailLog->setBccs((string)$emailLogXML->bccs);
				$emailLog->setSubject((string)$emailLogXML->subject);
				$emailLog->setBody((string)$emailLogXML->body);
				$articleEmailLogDAO->insertObject($emailLog);
				$this->idTranslationTable->register(INTERNAL_TRANSFER_OBJECT_ARTICLE_EMAIL_LOG, (int)$emailLogXML->oldId, $emailLog->getId());
			}

			$articleFileDAO =& DAORegistry::getDAO('ArticleFileDAO');
			foreach ($articleXML->articleFile as $articleFileXML) {
				try {
					$articleFile = new ArticleFile();
					$articleFile->setArticleId($article->getId());
					$articleFile->setSourceFileId((int) $articleFileXML->sourceFileId);
					$articleFile->setSourceRevision((int) $articleFileXML->sourceRevision);
					$articleFile->setRevision((int) $articleFileXML->revision);
					$articleFile->setFileName((string) $articleFileXML->fileName);
					$articleFile->setFileType((string) $articleFileXML->fileType);
					$articleFile->setFileSize((string) $articleFileXML->fileSize);
					$articleFile->setOriginalFileName((string) $articleFileXML->originalFileName);
					$articleFile->setFileStage((int) $articleFileXML->fileStage);
					$articleFile->setAssocId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_EMAIL_LOG, (int) $articleFileXML->assocId));
					$articleFile->setDateUploaded((string) $articleFileXML->dateUploaded);
					$articleFile->setDateModified((string) $articleFileXML->dateModified);
					$articleFile->setRound((int) $articleFileXML->round);
					$articleFile->setViewable((int) $articleFileXML->viewable);
					$articleFileDAO->insertArticleFile($articleFile);

					$oldArticleFileId = (int)$articleFileXML->oldId;

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
				$suppFile->setRemoteURL((string) $suppFileXML->remoteURL);
				$suppFile->setFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int) $suppFileXML->fileId));
				$suppFile->setType((string) $suppFileXML->type);
				$suppFile->setDateCreated((string) $suppFileXML->dateCreated);
				$suppFile->setLanguage((string) $suppFileXML->language);
				$suppFile->setShowReviewers((int) $suppFileXML->showReviewers);
				$suppFile->setDateSubmitted((string) $suppFileXML->dateSubmitted);
				$suppFile->setSequence((int) $suppFileXML->sequence);
				$suppFileDAO->insertSuppFile($suppFile);

				$this->restoreDataObjectSettings($suppFileDAO, $suppFileXML->settings, 'article_supp_file_settings', 'supp_id', $suppFile->getId());
			}


			$articleCommentDAO =& DAORegistry::getDAO('ArticleCommentDAO');
			foreach ($articleXML->articleComment as $articleCommentXML) {
				$articleComment = new ArticleComment();
				$articleComment->setArticleId($article->getId());
				$articleComment->setAssocId($article->getId());

				$articleComment->setCommentType((int) $articleCommentXML->commentType);
				$articleComment->setRoleId((int) $articleCommentXML->roleId);
				$articleComment->setAuthorId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int)$articleCommentXML->authorId));
				$articleComment->setCommentTitle((string)$articleCommentXML->commentTitle);
				$articleComment->setComments((string)$articleCommentXML->comments);
				$articleComment->setDatePosted((string)$articleCommentXML->datePosted);
				$articleComment->setDateModified((string)$articleCommentXML->dateModified);
				$articleComment->setViewable((int)$articleCommentXML->viewable);
				$articleCommentDAO->insertArticleComment($articleComment);
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
				$articleGalley->setLocale((string)$articleGalleyXML->locale);
				$articleGalley->setFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$articleGalleyXML->fileId));
				$articleGalley->setLabel((string)$articleGalleyXML->label);
				$articleGalley->setSequence((int)$articleGalleyXML->sequence);
				$articleGalley->setRemoteURL((string)$articleGalleyXML->remoteURL);
				if ($articleGalley instanceof ArticleHTMLGalley) {
					$articleGalley->setStyleFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$articleGalleyXML->styleFileId));
				}
				$articleGalleyDAO->insertGalley($articleGalley);

				if ($articleGalley instanceof ArticleHTMLGalley) {
					foreach ($articleGalleyXML->htmlGalleyImage as $articleGalleyImageXML) {
						$imageId = $this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$articleGalleyImageXML);
						$articleGalleyDAO->insertGalleyImage($articleGalley->getId(), $imageId);
					}
				}
				$this->restoreDataObjectSettings($articleGalleyDAO, $authorXML->settings, 'article_galley_settings', 'galley_id', $articleGalley->getId());
			}

			$noteDAO =& DAORegistry::getDAO('NoteDAO');
			foreach ($articleXML->articleNote as $articleNoteXML) {
				$articleNote = new Note();
				$articleNote->setUserId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int)$articleNoteXML->userId));
				$articleNote->setFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$articleNoteXML->fileId));

				$articleNote->setAssocType(ASSOC_TYPE_ARTICLE);
				$articleNote->setAssocId($article->getId());

				$articleNote->setDateCreated((string)$articleNoteXML->dateCreated);
				$articleNote->setDateModified((string)$articleNoteXML->dateModified);
				$articleNote->setContents((string)$articleNoteXML->contents);
				$articleNote->setTitle((string)$articleNoteXML->title);
				$noteDAO->insertObject($articleNote);
			}

			$editAssignmentDAO =& DAORegistry::getDAO('EditAssignmentDAO');
			foreach ($articleXML->editAssignment as $editAssignmentXML) {
				$editAssignment = new EditAssignment();
				$editAssignment->setArticleId($article->getId());
	
				$editAssignment->setEditorId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int)$editAssignmentXML->editorId));
				$editAssignment->setCanReview((int)$editAssignmentXML->canReview);
				$editAssignment->setCanEdit((int)$editAssignmentXML->canEdit);
				$editAssignment->setDateUnderway((string)$editAssignmentXML->dateUnderway);
				$editAssignment->setDateNotified((string)$editAssignmentXML->dateNotified);

				$editAssignmentDAO->insertEditAssignment($editAssignment);
			}

			$reviewAssignmentDAO =& DAORegistry::getDAO('ReviewAssignmentDAO');
			$reviewFormResponseDAO =& DAORegistry::getDAO('ReviewFormResponseDAO');
			foreach ($articleXML->reviewAssignment as $reviewAssignmentXML) {
				$reviewAssignment = new ReviewAssignment();
				$reviewAssignment->setSubmissionId($article->getId());
				$reviewAssignment->setReviewerId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int)$reviewAssignmentXML->reviewerId));
				try {
					$reviewAssignment->setReviewerFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$reviewAssignmentXML->reviewerFileId));
				} catch (Exception $e) {
					$this->logger->log("Arquivo do artigo $oldArticleId não encontrado. ID: " . (int)$reviewAssignmentXML->reviewerFileId . "\n");
				}
				$reviewAssignment->setReviewFormId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_REVIEW_FORM, (int)$reviewAssignmentXML->reviewFormId));
				$reviewAssignment->setReviewRoundId((int)$reviewAssignmentXML->reviewRoundId);
				$reviewAssignment->setStageId((int)$reviewAssignmentXML->stageId);
				$reviewAssignment->setReviewerFullName((string)$reviewAssignmentXML->reviewerFullName);
				$reviewAssignment->setCompetingInterests((string)$reviewAssignmentXML->competingInterests);
				$reviewAssignment->setRegretMessage((string)$reviewAssignmentXML->regretMessage);
				$reviewAssignment->setRecommendation((string)$reviewAssignmentXML->recommendation);
				$reviewAssignment->setDateAssigned((string)$reviewAssignmentXML->dateAssigned);
				$reviewAssignment->setDateNotified((string)$reviewAssignmentXML->dateNotified);
				$reviewAssignment->setDateConfirmed((string)$reviewAssignmentXML->dateConfirmed);
				$reviewAssignment->setDateCompleted((string)$reviewAssignmentXML->dateCompleted);
				$reviewAssignment->setDateAcknowledged((string)$reviewAssignmentXML->dateAcknowledged);
				$reviewAssignment->setDateDue((string)$reviewAssignmentXML->dateDue);
				$reviewAssignment->setDateResponseDue((string)$reviewAssignmentXML->dateResponseDue);
				$reviewAssignment->setLastModified((string)$reviewAssignmentXML->lastModified);
				$reviewAssignment->setDeclined((int)$reviewAssignmentXML->declined);
				$reviewAssignment->setReplaced((int)$reviewAssignmentXML->replaced);
				$reviewAssignment->setCancelled((int)$reviewAssignmentXML->cancelled);
				$reviewAssignment->setQuality((int)$reviewAssignmentXML->quality);
				$reviewAssignment->setDateRated((string)$reviewAssignmentXML->dateRated);
				$reviewAssignment->setDateReminded((string)$reviewAssignmentXML->dateReminded);
				$reviewAssignment->setReminderWasAutomatic((int)$reviewAssignmentXML->reminderWasAutomatic);
				$reviewAssignment->setRound((int)$reviewAssignmentXML->round);
				$reviewAssignment->setReviewRevision((int)$reviewAssignmentXML->reviewRevision);
				$reviewAssignment->setReviewMethod((int)$reviewAssignmentXML->reviewMethod);
				$reviewAssignment->setUnconsidered((int)$reviewAssignmentXML->unconsidered);
				$reviewAssignmentDAO->insertObject($reviewAssignment);

				foreach ($reviewAssignmentXML->formResponses->formResponse as $formResponseXML) {
					$reviewFormResponseDAO->update(
						'INSERT INTO review_form_responses
							(review_form_element_id, review_id, response_type, response_value)
							VALUES
							(?, ?, ?, ?)',
						array(
							$this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_REVIEW_FORM_ELEMENT, (int)$formResponseXML->reviewFormElementId),
							$reviewAssignment->getId(),
							(string)$formResponseXML->responseType,
							(string)$formResponseXML->responseValue
						)
					);
				}
			}

			$signoffDAO =& DAORegistry::getDAO('SignoffDAO');
			foreach ($articleXML->signoff as $signoffXML) {
				$signoff = new Signoff();
				$signoff->setAssocType(ASSOC_TYPE_ARTICLE);
				$signoff->setAssocId($article->getId());
				$signoff->setUserId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int)$signoffXML->userId));
				$signoff->setFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$signoffXML->fileId));
				$signoff->setUserGroupId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_GROUP, (int)$signoffXML->userGroupId));
				
				$signoff->setSymbolic((string)$signoffXML->symbolic);
				$signoff->setFileRevision((int)$signoffXML->fileRevision);
				$signoff->setDateUnderway((string)$signoffXML->dateUnderway);
				$signoff->setDateNotified((string)$signoffXML->dateNotified);
				$signoff->setDateCompleted((string)$signoffXML->dateCompleted);
				$signoff->setDateAcknowledged((string)$signoffXML->dateAcknowledged);
				$signoffDAO->insertObject($signoff);
			}

			$editorSubmissionDAO =& DAORegistry::getDAO('EditorSubmissionDAO');
			foreach ($articleXML->editDecisions as $editDecisionXML) {
				$editDecisions =& $editorSubmissionDAO->update(
					'INSERT INTO edit_decisions (article_id, round, editor_id, decision, date_decided) values (?, ?, ?, ?, ?)', 
					array($article->getId(), 
						(string)$editDecisionXML->round, 
						$this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int)$editDecisionXML->editorId), 
						(string)$editDecisionXML->decision, 
						(string)$editDecisionXML->dateDecided)
				);
			}

			$publishedArticleDAO =& DAORegistry::getDAO('PublishedArticleDAO');
			if (isset($articleXML->publishedArticle)) {
				$publishedArticleXML = $articleXML->publishedArticle;
				$publishedArticle = new PublishedArticle();
				$publishedArticle->setId($article->getId());
				$publishedArticle->setIssueId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ISSUE, (int)$publishedArticleXML->issueId));
				$publishedArticle->setDatePublished((string)$publishedArticleXML->datePublished);
				$publishedArticle->setSeq((int)$publishedArticleXML->seq);
				$publishedArticle->setAccessStatus((int)$publishedArticleXML->accessStatus);
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
				$eventLog->setUserId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_USER, (int)$eventLogXML->userId));
				$eventLog->setDateLogged((string)$eventLogXML->dateLogged);
				$eventLog->setIPAddress((string)$eventLogXML->IPAddress);
				$eventLog->setEventType((int)$eventLogXML->eventType);
				$eventLog->setMessage((string)$eventLogXML->message);
				$eventLog->setIsTranslated((int)$eventLogXML->isTranslated);
				$articleEventLogDAO->insertObject($eventLog);

				$this->restoreDataObjectSettings($articleEventLogDAO, $eventLogXML->settings, 'event_log_settings', 'log_id', $eventLog->getId());
			}

			try {
				$article->setSubmissionFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$articleXML->submissionFileId));
			} catch (Exception $e) {
			}
			try {
				$article->setRevisedFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$articleXML->revisedFileId));
			} catch (Exception $e) {
			}
			try {
				$article->setReviewFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$articleXML->reviewFileId));
			} catch (Exception $e) {
			}
			try {
				$article->setEditorFileId($this->idTranslationTable->resolve(INTERNAL_TRANSFER_OBJECT_ARTICLE_FILE, (int)$articleXML->editorFileId));
			} catch (Exception $e) {
			}
			$articleDAO->updateArticle($article);

			$this->nextElement();
		}
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
		for ($username = $baseUsername, $i=1; $userDao->userExistsByUsername($username, true); $i++) {
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

}

?>