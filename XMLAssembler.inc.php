<?php

/**
 * Copyright (c) 2014 Instituto Brasileiro de Informação em Ciência e Tecnologia 
 * Author: Giovani Pieri <giovani@lepidus.com.br>
 *
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 */

class XMLAssembler {
	var $outputFolder;
	var $journal;

	function XMLAssembler($outputFolder, $journal) {
		$this->outputFolder = $outputFolder;
		$this->journal = $journal;
	}

	function exportJournal() {
		$writer = new XmlWriter();
		$writer->openURI($this->outputFolder . "/journal.xml");
		$writer->startDocument('1.0', 'utf-8');
		$writer->startElement('journal');
		$writer->setIndent(true);
		$this->exportJournalConfig($writer);
		$this->exportAnnouncements($writer);
		$this->exportReviewForms($writer);
		$this->exportUsers($writer);
		$this->exportGroups($writer);
		$this->exportSections($writer);
		$this->exportIssues($writer);
		$this->exportArticles($writer);
		$writer->endElement();
		$writer->flush();
		return $this->outputFolder . "/journal.xml";
	}

	function exportJournalConfig($writer) {
		$journal = $this->journal;

		$writer->startElement('journalConfig');

		$this->writeElement($writer, 'passwordEncryption', Config::getVar('security', 'encryption'));
		$this->writeElement($writer, 'path', $journal->getPath());
		$this->writeElement($writer, 'enabled', $journal->getEnabled());
		$this->writeElement($writer, 'primaryLocale', $journal->getPrimaryLocale());

		$journalSettingsDao =& DAORegistry::getDAO('JournalSettingsDAO');
		$this->exportDataObjectSettings($journalSettingsDao, $writer, "journal_settings", "journal_id", $journal->getId());

		$writer->endElement();
		$writer->flush();
	}

	function exportAnnouncements($writer) {
		$journal = $this->journal;
		$writer->startElement('announcements');

		$announcementTypeDAO =& DAORegistry::getDAO('AnnouncementTypeDAO');
		$announcementDAO =& DAORegistry::getDAO('AnnouncementDAO');
		$announcementTypes =& $announcementTypeDAO->getByAssoc(ASSOC_TYPE_JOURNAL, $journal->getId());

		while (!$announcementTypes->eof()) {
			$announcementType =& $announcementTypes->next();
			
			$writer->startElement('announcementType');
			
			$this->writeElement($writer, 'oldId', $announcementType->getId());

			$announcements =& $announcementDAO->getByTypeId($announcementType->getId());
			while (!$announcements->eof()) {
				$announcement =& $announcements->next();
				$writer->startElement('announcement');

				$this->writeElement($writer, 'oldId', $announcement->getId());
				$this->writeElement($writer, 'dateExpire', $announcement->getDateExpire());
				$this->writeElement($writer, 'datePosted', $announcement->getDatePosted());

				$this->exportDataObjectSettings($announcementDAO, $writer, 'announcement_settings', 'announcement_id', $announcement->getId());
				$writer->endElement();
				$writer->flush();
			}

			$this->exportDataObjectSettings($announcementTypeDAO, $writer, 'announcement_type_settings', 'type_id', $announcementType->getId());

			$writer->endElement();
			$writer->flush();
		}

		$result = $announcementDAO->retrieveRange('SELECT * FROM announcements WHERE type_id is NULL or type_id=0 ORDER BY announcement_id ASC');
		$announcements = new DAOResultFactory($result, $announcementDAO, '_returnAnnouncementFromRow');
		while (!$announcements->eof()) {
			$announcement =& $announcements->next();
			$writer->startElement('announcement');

			$this->writeElement($writer, 'oldId', $announcement->getId());
			$this->writeElement($writer, 'dateExpire', $announcement->getDateExpire());
			$this->writeElement($writer, 'datePosted', $announcement->getDatePosted());

			$this->exportDataObjectSettings($announcementDAO, $writer, 'announcement_settings', 'announcement_id', $announcement->getId());
			$writer->endElement();
			$writer->flush();
		}

		$writer->endElement();
		$writer->flush();
	}

	function exportReviewForms($writer) {
		$journal = $this->journal;
		$writer->startElement('reviewForms');

		$reviewFormDao =& DAORegistry::getDAO('ReviewFormDAO');
		$reviewFormElementDao =& DAORegistry::getDAO('ReviewFormElementDAO');
		$reviewForms =& $reviewFormDao->getByAssocId(ASSOC_TYPE_JOURNAL, $journal->getId());

		while (!$reviewForms->eof()) {
			$reviewForm =& $reviewForms->next();
			
			$writer->startElement('reviewForm');
			
			$this->writeElement($writer, 'oldId', $reviewForm->getId());
			$this->writeElement($writer, 'sequence', $reviewForm->getSequence());
			$this->writeElement($writer, 'active', $reviewForm->getActive());

			$reviewFormElements =& $reviewFormElementDao->getReviewFormElements($reviewForm->getId());
			foreach ($reviewFormElements as $reviewFormElement) {
				$writer->startElement('reviewElement');
				
				$this->writeElement($writer, 'oldId', $reviewFormElement->getId());
				$this->writeElement($writer, 'sequence', $reviewFormElement->getSequence());
				$this->writeElement($writer, 'elementType', $reviewFormElement->getElementType());
				$this->writeElement($writer, 'required', $reviewFormElement->getRequired());
				$this->writeElement($writer, 'included', $reviewFormElement->getIncluded());

				$this->exportDataObjectSettings($reviewFormElementDao, $writer, 'review_form_element_settings', 'review_form_element_id', $reviewFormElement->getId());
				$writer->endElement();
				$writer->flush();
			}

			$this->exportDataObjectSettings($reviewFormDao, $writer, 'review_form_settings', 'review_form_id', $reviewForm->getId());

			$writer->endElement();
			$writer->flush();
		}

		$writer->endElement();
		$writer->flush();
	}

	function exportUsers($writer) {
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();

		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$userDAO =& DAORegistry::getDAO('UserDAO');

		$result =& $userDAO->retrieveRange(
			'SELECT DISTINCT u.*
			FROM	users u
				LEFT JOIN controlled_vocabs cv ON (cv.symbolic = \'interest\')
				LEFT JOIN user_interests ui ON (ui.user_id = u.user_id)
				LEFT JOIN controlled_vocab_entries cve ON (cve.controlled_vocab_id = cv.controlled_vocab_id AND ui.controlled_vocab_entry_id = cve.controlled_vocab_entry_id)
				LEFT JOIN controlled_vocab_entry_settings cves ON (cves.controlled_vocab_entry_id = cve.controlled_vocab_entry_id)
			WHERE u.user_id IN (
				SELECT r.user_id FROM roles AS r WHERE r.journal_id = ?
				UNION
				SELECT gm.user_id FROM group_memberships AS gm INNER JOIN groups AS g ON gm.group_id=g.group_id WHERE g.assoc_id = ?
				UNION
				SELECT se.user_id FROM section_editors AS se WHERE se.journal_id = ?
				UNION
				SELECT a.user_id FROM articles AS a WHERE a.journal_id = ?
				UNION
				SELECT ea.editor_id FROM edit_assignments AS ea INNER JOIN articles AS a ON ea.article_id=a.article_id WHERE a.journal_id = ?
				UNION
				SELECT ed.editor_id FROM edit_decisions AS ed INNER JOIN articles AS a ON ed.article_id=a.article_id WHERE a.journal_id = ?
				UNION
				SELECT ra.reviewer_id FROM review_assignments AS ra INNER JOIN articles AS a ON ra.submission_id=a.article_id WHERE a.journal_id = ?
				UNION
				SELECT s.user_id FROM signoffs AS s INNER JOIN articles AS a ON s.assoc_id=a.article_id WHERE a.journal_id = ?
				UNION
				SELECT el.sender_id FROM email_log AS el INNER JOIN articles AS a ON el.assoc_id=a.article_id WHERE a.journal_id = ?
				UNION
				SELECT evenl.user_id FROM event_log AS evenl INNER JOIN articles AS a ON evenl.assoc_id=a.article_id WHERE a.journal_id = ?
				UNION
				SELECT ac.author_id FROM article_comments AS ac INNER JOIN articles AS a ON ac.article_id=a.article_id WHERE a.journal_id = ?
				UNION
				SELECT n.user_id FROM notes AS n INNER JOIN articles AS a ON n.assoc_id=a.article_id WHERE a.journal_id = ?
			)',
			array($this->journal->getId(), $this->journal->getId(), $this->journal->getId(),
				$this->journal->getId(), $this->journal->getId(), $this->journal->getId(),
				$this->journal->getId(), $this->journal->getId(), $this->journal->getId(),
				$this->journal->getId(), $this->journal->getId(), $this->journal->getId()),
			null
		);

		$users =& new DAOResultFactory($result, $userDAO, '_returnUserFromRowWithData');

		$writer->startElement('users');
		while (!$users->eof()) {
			$user = $users->next();

			$writer->startElement('user');

			$this->writeElement($writer, 'oldId', $user->getId());
			$this->writeElement($writer, 'username', $user->getUsername());
			$this->writeElement($writer, 'password', $user->getPassword());
			$this->writeElement($writer, 'salutation', $user->getSalutation());
			$this->writeElement($writer, 'firstName', $user->getFirstName());
			$this->writeElement($writer, 'middleName', $user->getMiddleName());
			$this->writeElement($writer, 'initials', $user->getInitials());
			$this->writeElement($writer, 'lastName', $user->getLastName());
			$this->writeElement($writer, 'suffix', $user->getSuffix());
			$this->writeElement($writer, 'gender', $user->getGender());
			$this->writeElement($writer, 'email', $user->getEmail());
			$this->writeElement($writer, 'url', $user->getUrl());
			$this->writeElement($writer, 'phone', $user->getPhone());
			$this->writeElement($writer, 'fax', $user->getFax());
			$this->writeElement($writer, 'mailingAddress', $user->getMailingAddress());
			$this->writeElement($writer, 'billingAddress', $user->getBillingAddress());
			$this->writeElement($writer, 'country', $user->getCountry());
			$this->writeElement($writer, 'locales', $user->getLocales() ? implode(':', $user->getLocales()) : null);
			$this->writeElement($writer, 'dateLastEmail', $user->getDateLastEmail());
			$this->writeElement($writer, 'dateRegistered', $user->getDateRegistered());
			$this->writeElement($writer, 'dateValidated', $user->getDateValidated());
			$this->writeElement($writer, 'dateLastLogin', $user->getDateLastLogin());
			$this->writeElement($writer, 'mustChangePassword', $user->getMustChangePassword());
			$this->writeElement($writer, 'disabled', $user->getDisabled());
			$this->writeElement($writer, 'disabledReason', $user->getDisabledReason());
			$this->writeElement($writer, 'authId', $user->getAuthId());
			$this->writeElement($writer, 'authStr', $user->getAuthStr());
			$this->writeElement($writer, 'inlineHelp', $user->getInlineHelp());

			$interests = $interestManager->getInterestsForUser($user);
			if (is_array($interests)) {
				foreach ($interests as $interest) {
					$this->writeElement($writer, 'interest', $interest);
				}
			}

			$roles = $roleDao->getRolesByUserId($user->getId(), $this->journal->getId());
			foreach ($roles as $role) {
				$this->writeElement($writer, 'role', $role->getRoleId());
			}

			$this->exportUserSettings($userDAO, $writer, $user->getId());
			$writer->endElement();
			$writer->flush();
		}
		$writer->endElement();
		$writer->flush();
	}

	function exportGroups($writer) {
		$journal = $this->journal;
		$writer->startElement('groups');

		$groupDao =& DAORegistry::getDAO('GroupDAO');
		$groupMembershipDao =& DAORegistry::getDAO('GroupMembershipDAO');
		$groups =& $groupDao->getGroups(ASSOC_TYPE_JOURNAL, $journal->getId());

		while (!$groups->eof()) {
			$group =& $groups->next();
			
			$writer->startElement('group');
			
			$this->writeElement($writer, 'oldId', $group->getId());
			$this->writeElement($writer, 'aboutDisplayed', $group->getAboutDisplayed());
			$this->writeElement($writer, 'publishEmail', $group->getPublishEmail());
			$this->writeElement($writer, 'sequence', $group->getSequence());
			$this->writeElement($writer, 'context', $group->getContext());

			$groupMemberships =& $groupMembershipDao->getMemberships($group->getId());
			while (!$groupMemberships->eof()) {
				$membership =& $groupMemberships->next();

				$writer->startElement('groupMembership');
				$this->writeElement($writer, 'oldId', $membership->getId());
				$this->writeElement($writer, 'userId', $membership->getUserId());
				$this->writeElement($writer, 'sequence', $membership->getSequence());
				$this->writeElement($writer, 'aboutDisplayed', $membership->getAboutDisplayed());

				$writer->endElement();
				$writer->flush();
			}

			$this->exportDataObjectSettings($groupDao, $writer, 'group_settings', 'group_id', $group->getId());

			$writer->endElement();
			$writer->flush();
		}

		$writer->endElement();
		$writer->flush();
	}

	function exportIssues($writer) {
		$issueDAO =& DAORegistry::getDAO('IssueDAO');
		$issueFileDAO =& DAORegistry::getDAO('IssueFileDAO');
		$issueGalleyDAO =& DAORegistry::getDAO('IssueGalleyDAO');
		$sectionDAO =& DAORegistry::getDAO('SectionDAO');
		$issues = $issueDAO->getIssues($this->journal->getId());

		$writer->startElement('issues');
		while (!$issues->eof()) {
			$issue = $issues->next();
			$writer->startElement('issue');

			$this->writeElement($writer, 'oldId', $issue->getId());
			$this->writeElement($writer, 'volume', $issue->getVolume());
			$this->writeElement($writer, 'number', $issue->getNumber());
			$this->writeElement($writer, 'year', $issue->getYear());
			$this->writeElement($writer, 'published', $issue->getPublished());
			$this->writeElement($writer, 'current', $issue->getCurrent());
			$this->writeElement($writer, 'datePublished', $issue->getDatePublished());
			$this->writeElement($writer, 'dateNotified', $issue->getDateNotified());
			$this->writeElement($writer, 'lastModified', $issue->getLastModified());
			$this->writeElement($writer, 'accessStatus', $issue->getAccessStatus());
			$this->writeElement($writer, 'openAccessDate', $issue->getOpenAccessDate());
			$this->writeElement($writer, 'showVolume', $issue->getShowVolume());
			$this->writeElement($writer, 'showNumber', $issue->getShowNumber());
			$this->writeElement($writer, 'showYear', $issue->getShowYear());
			$this->writeElement($writer, 'showTitle', $issue->getShowTitle());
			$this->writeElement($writer, 'styleFileName', $issue->getStyleFileName());
			$this->writeElement($writer, 'originalStyleFileName', $issue->getOriginalStyleFileName());
			$this->writeElement($writer, 'customOrder', $issueDAO->getCustomIssueOrder($this->journal->getId(), $issue->getId()));
			
			$issueFiles =& $issueFileDAO->getIssueFilesByIssue($issue->getId());
			foreach ($issueFiles as $issueFile) {
				$writer->startElement('issueFile');

				$this->writeElement($writer, 'oldId', $issueFile->getId());
				$this->writeElement($writer, 'fileName', $issueFile->getFileName());
				$this->writeElement($writer, 'fileType', $issueFile->getFileType());
				$this->writeElement($writer, 'fileSize', $issueFile->getFileSize());
				$this->writeElement($writer, 'contentType', $issueFile->getContentType());
				$this->writeElement($writer, 'originalFileName', $issueFile->getOriginalFileName());
				$this->writeElement($writer, 'dateUploaded', $issueFile->getDateUploaded());
				$this->writeElement($writer, 'dateModified', $issueFile->getDateModified());

				$writer->endElement();
			}

			$issueGalleys =& $issueGalleyDAO->getGalleysByIssue($issue->getId());

			foreach ($issueGalleys as $issueGalley) {
				$writer->startElement('issueGalley');

				$this->writeElement($writer, 'oldId', $issueGalley->getId());
				$this->writeElement($writer, 'locale', $issueGalley->getLocale());
				$this->writeElement($writer, 'fileId', $issueGalley->getFileId());
				$this->writeElement($writer, 'label', $issueGalley->getLabel());
				$this->writeElement($writer, 'sequence', $issueGalley->getSequence());

				$this->exportDataObjectSettings($issueGalleyDAO, $writer, 'issue_galley_settings', 'galley_id', $issueGalley->getId());

				$writer->endElement();
			}

			$this->exportDataObjectSettings($issueDAO, $writer, 'issue_settings', 'issue_id', $issue->getId());


			$customSectionOrders =& $sectionDAO->retrieve(
				'SELECT section_id, seq FROM custom_section_orders WHERE issue_id = ?',
				array($issue->getId())
			);

			if (!$customSectionOrders->EOF) {
				$writer->startElement('customSectionOrder');
				while (!$customSectionOrders->EOF) {
					$row = $customSectionOrders->GetRowAssoc(false);
					$writer->startElement('sectionOrder');
					$this->writeAttribute($writer, 'sectionId', $row['section_id']);
					$this->writeAttribute($writer, 'seq', $row['seq']);
					$writer->endElement();

					$customSectionOrders->MoveNext();
				}
				$writer->endElement();
			}
			$customSectionOrders->Close();
			unset($customSectionOrders);

			$writer->endElement();
		}
		$writer->endElement();
		$writer->flush();
	}

	function exportSections($writer) {
		$sectionDAO =& DAORegistry::getDAO('SectionDAO');
		$sectionEditorsDAO =& DAORegistry::getDAO('SectionEditorsDAO');
		$sections = $sectionDAO->getJournalSections($this->journal->getId());

		$writer->startElement('sections');
		while (!$sections->eof()) {
			$section = $sections->next();
			$writer->startElement('section');

			$this->writeElement($writer, 'oldId', $section->getId());
			$this->writeElement($writer, 'reviewFormId', $section->getReviewFormId());
			$this->writeElement($writer, 'sequence', $section->getSequence());
			$this->writeElement($writer, 'metaIndexed', $section->getMetaIndexed());
			$this->writeElement($writer, 'metaReviewed', $section->getMetaReviewed());
			$this->writeElement($writer, 'abstractsNotRequired', $section->getAbstractsNotRequired());
			$this->writeElement($writer, 'editorRestricted', $section->getEditorRestricted());
			$this->writeElement($writer, 'hideTitle', $section->getHideTitle());
			$this->writeElement($writer, 'hideAuthor', $section->getHideAuthor());
			$this->writeElement($writer, 'hideAbout', $section->getHideAbout());
			$this->writeElement($writer, 'disableComments', $section->getDisableComments());
			$this->writeElement($writer, 'wordCount', $section->getAbstractWordCount());

			$sectionEditors = $sectionEditorsDAO->getEditorsBySectionId($this->journal->getId(), $section->getId());
			foreach ($sectionEditors as $sectionEditor) {
				$writer->startElement('sectionEditor');
				$user = $sectionEditor['user'];
				$this->writeElement($writer, 'userId', $user->getId());
				$this->writeElement($writer, 'canReview', $sectionEditor['canReview']);
				$this->writeElement($writer, 'canEdit', $sectionEditor['canEdit']);

				$writer->endElement();
			}

			$this->exportDataObjectSettings($sectionDAO, $writer, 'section_settings', 'section_id', $section->getId());

			$writer->endElement();
		}
		$writer->endElement();
		$writer->flush();
	}

	function exportArticles($writer) {
		$articleDAO =& DAORegistry::getDAO('ArticleDAO');
		$articles = $articleDAO->getArticlesByJournalId($this->journal->getId());

		$writer->startElement('articles');
		while (!$articles->eof()) {
			$article = $articles->next();
			// $article = $articleDAO->getArticle(1669);
			$writer->startElement('article');

			$this->writeElement($writer, 'oldId', $article->getId());
			
			$this->writeElement($writer, 'locale', $article->getLocale());
			$this->writeElement($writer, 'userId', $article->getUserId());
			$this->writeElement($writer, 'sectionId', $article->getSectionId());
			$this->writeElement($writer, 'language', $article->getLanguage());
			$this->writeElement($writer, 'commentsToEditor', $article->getCommentsToEditor());
			$this->writeElement($writer, 'citations', $article->getCitations());
			$this->writeElement($writer, 'dateSubmitted', $article->getDateSubmitted());
			$this->writeElement($writer, 'dateStatusModified', $article->getDateStatusModified());
			$this->writeElement($writer, 'lastModified', $article->getLastModified());
			$this->writeElement($writer, 'status', $article->getStatus());
			$this->writeElement($writer, 'submissionProgress', $article->getSubmissionProgress());
			$this->writeElement($writer, 'currentRound', $article->getCurrentRound());
			$this->writeElement($writer, 'submissionFileId', $article->getSubmissionFileId());
			$this->writeElement($writer, 'revisedFileId', $article->getRevisedFileId());
			$this->writeElement($writer, 'reviewFileId', $article->getReviewFileId());
			$this->writeElement($writer, 'editorFileId', $article->getEditorFileId());
			$this->writeElement($writer, 'pages', $article->getPages());
			$this->writeElement($writer, 'fastTracked', $article->getFastTracked());
			$this->writeElement($writer, 'hideAuthor', $article->getHideAuthor());
			$this->writeElement($writer, 'commentsStatus', $article->getCommentsStatus());

			$this->exportDataObjectSettings($articleDAO, $writer, 'article_settings', 'article_id', $article->getId());

			$authorDAO =& DAORegistry::getDAO('AuthorDAO');
			$authors = $article->getAuthors();
			foreach ($authors as $author) {
				$writer->startElement('author');
				$this->writeElement($writer, 'oldId', $author->getId());

				$this->writeElement($writer, 'firstName', $author->getFirstName());
				$this->writeElement($writer, 'middleName', $author->getMiddleName());
				$this->writeElement($writer, 'lastName', $author->getLastName());
				$this->writeElement($writer, 'suffix', $author->getSuffix());
				$this->writeElement($writer, 'country', $author->getCountry());
				$this->writeElement($writer, 'email', $author->getEmail());
				$this->writeElement($writer, 'url', $author->getUrl());
				$this->writeElement($writer, 'userGroupId', $author->getUserGroupId());
				$this->writeElement($writer, 'primaryContact', $author->getPrimaryContact());
				$this->writeElement($writer, 'sequence', $author->getSequence());

				$this->exportDataObjectSettings($authorDAO, $writer, 'author_settings', 'author_id', $author->getId());
				$writer->endElement();
				unset($author);
			}

			$articleFileDAO =& DAORegistry::getDAO('ArticleFileDAO');
			$articleFiles = $articleFileDAO->getArticleFilesByArticle($article->getId());
			foreach ($articleFiles as $articleFile) {
				$writer->startElement('articleFile');

				$this->writeElement($writer, 'oldId', $articleFile->getFileId());
				$this->writeElement($writer, 'sourceFileId', $articleFile->getSourceFileId());
				$this->writeElement($writer, 'sourceRevision', $articleFile->getSourceRevision());
				$this->writeElement($writer, 'revision', $articleFile->getRevision());
				$this->writeElement($writer, 'fileName', $articleFile->getFileName());
				$this->writeElement($writer, 'fileType', $articleFile->getFileType());
				$this->writeElement($writer, 'fileSize', $articleFile->getFileSize());
				$this->writeElement($writer, 'originalFileName', $articleFile->getOriginalFileName());
				$this->writeElement($writer, 'fileStage', $articleFile->getFileStage());
				$this->writeElement($writer, 'assocId', $articleFile->getAssocId());
				$this->writeElement($writer, 'dateUploaded', $articleFile->getDateUploaded());
				$this->writeElement($writer, 'dateModified', $articleFile->getDateModified());
				$this->writeElement($writer, 'round', $articleFile->getRound());
				$this->writeElement($writer, 'viewable', $articleFile->getViewable());

				$writer->endElement();
				unset($author);
			}


			$suppFileDAO =& DAORegistry::getDAO('SuppFileDAO');
			$suppFiles = $suppFileDAO->getSuppFilesByArticle($article->getId());
			foreach ($suppFiles as $suppFile) {
				$writer->startElement('suppFile');

				if(empty($suppFile->getDateCreated()) || !$this->validateDate($suppFile->getDateCreated(),'Y-m-d')):
					$dateCreated = $suppFile->getDateSubmitted();
					echo "Data Em formato inválido.\nA data será trocada pela data da submissão.\nID do arquivo Suplementar:{$suppFile->getId()}\n";
				else:
					$dateCreated = $suppFile->getDateCreated();
				endif;

				$this->writeElement($writer, 'oldId', $suppFile->getId());
				$this->writeElement($writer, 'remoteURL', $suppFile->getRemoteURL());
				$this->writeElement($writer, 'fileId', $suppFile->getFileId());
				$this->writeElement($writer, 'type', $suppFile->getType());
				$this->writeElement($writer, 'dateCreated', $dateCreated );
				$this->writeElement($writer, 'language', $suppFile->getLanguage());
				$this->writeElement($writer, 'showReviewers', $suppFile->getShowReviewers());
				$this->writeElement($writer, 'dateSubmitted', $suppFile->getDateSubmitted());
				$this->writeElement($writer, 'sequence', $suppFile->getSequence());

				$this->exportDataObjectSettings($suppFileDAO, $writer, 'article_supp_file_settings', 'supp_id', $suppFile->getId());

				$writer->endElement();
				unset($author);
			}

			$articleCommentDAO =& DAORegistry::getDAO('ArticleCommentDAO');
			$articleComments = $articleCommentDAO->getArticleComments($article->getId());
			foreach ($articleComments as $articleComment) { /* @var $articleComment ArticleComment */
				$writer->startElement('articleComment');

				$this->writeElement($writer, 'oldId', $articleComment->getId());

				$this->writeElement($writer, 'commentType', $articleComment->getCommentType());
				$this->writeElement($writer, 'roleId', $articleComment->getRoleId());
				$this->writeElement($writer, 'authorId', $articleComment->getAuthorId());
				$this->writeElement($writer, 'commentTitle', $articleComment->getCommentTitle());
				$this->writeElement($writer, 'comments', $articleComment->getComments());
				$this->writeElement($writer, 'datePosted', $articleComment->getDatePosted());
				$this->writeElement($writer, 'dateModified', $articleComment->getDateModified());
				$this->writeElement($writer, 'viewable', $articleComment->getViewable());
				$this->writeElement($writer, 'assocId', $articleComment->getAssocId());

				$writer->endElement();
				unset($articleComment);
			}

			$articleGalleyDAO =& DAORegistry::getDAO('ArticleGalleyDAO');
			$articleGalleys = $articleGalleyDAO->getGalleysByArticle($article->getId());

			foreach ($articleGalleys as $articleGalley) {
				$is_html = $articleGalley instanceof ArticleHTMLGalley;
				
				$writer->startElement('articleGalley');

				$this->writeElement($writer, 'oldId', $articleGalley->getId());
				$this->writeElement($writer, 'locale', $articleGalley->getLocale());
				$this->writeElement($writer, 'fileId', $articleGalley->getFileId());
				$this->writeElement($writer, 'label', $articleGalley->getLabel());
				$this->writeElement($writer, 'sequence', $articleGalley->getSequence());
				$this->writeElement($writer, 'remoteURL', $articleGalley->getRemoteURL());
				$this->writeElement($writer, 'htmlGalley', $is_html ? 1 : 0);

				if ($articleGalley instanceof ArticleHTMLGalley) {
					$this->writeElement($writer, 'styleFileId', $articleGalley->getStyleFileId());

					$articleGalleyImages = $articleGalley->getImageFiles();
					foreach ($articleGalleyImages as $articleGalleyImage) {
						$this->writeElement($writer, 'htmlGalleyImage', $articleGalleyImage->getFileId());
					}
				}

				$this->exportDataObjectSettings($articleGalleyDAO, $writer, 'article_galley_settings', 'galley_id', $articleGalley->getId());

				$writer->endElement();
				unset($articleGalley);
			}

			$articleNoteDAO =& DAORegistry::getDAO('ArticleNoteDAO');
			$articleNotes = $articleNoteDAO->getArticleNotes($article->getId());

			while (!$articleNotes->eof()) {
				$articleNote = $articleNotes->next();
				$writer->startElement('articleNote');
				if(empty($articleNote->getDateCreated()) || !$this->validateDate($articleNote->getDateCreated(),'Y-m-d')):
					$dateCreated = $articleNote->getDateModified();
					echo "Data Em formato inválido.\n
					A data será trocada pela data da submissão.\n
					ID da Nota de Artigo:{$articleNote->getId()}\n";
				else:
					$dateCreated = $articleNote->getDateCreated();
				endif;

				$this->writeElement($writer, 'oldId', $articleNote->getId());
				$this->writeElement($writer, 'userId', $articleNote->getUserId());
				$this->writeElement($writer, 'dateCreated', $dateCreated);
				$this->writeElement($writer, 'dateModified', $articleNote->getDateModified());
				$this->writeElement($writer, 'contents', $articleNote->getContents());
				$this->writeElement($writer, 'title', $articleNote->getTitle());
				$this->writeElement($writer, 'fileId', $articleNote->getFileId());

				$writer->endElement();
				unset($articleNote);
			}

			$editAssignmentDAO =& DAORegistry::getDAO('EditAssignmentDAO');
			$editAssignments = $editAssignmentDAO->getEditAssignmentsByArticleId($article->getId());
			while (!$editAssignments->eof()) {
				$editAssignment =& $editAssignments->next();
				$writer->startElement('editAssignment');
				$this->writeElement($writer, 'oldId', $editAssignment->getEditId());
				$this->writeElement($writer, 'editorId', $editAssignment->getEditorId());
				$this->writeElement($writer, 'canReview', $editAssignment->getCanReview());
				$this->writeElement($writer, 'canEdit', $editAssignment->getCanEdit());
				$this->writeElement($writer, 'dateUnderway', $editAssignment->getDateUnderway());
				$this->writeElement($writer, 'dateNotified', $editAssignment->getDateNotified());
				$writer->endElement();
				unset($editAssignment);
			}

			$sectionEditorSubmissionDAO =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
			$reviewRounds =& $sectionEditorSubmissionDAO->retrieveRange(
				'SELECT DISTINCT r.* FROM	review_rounds r WHERE r.submission_id = ?',
				array($article->getId()),
				null
			);

			$writer->startElement('reviewRounds');
			while (!$reviewRounds->EOF) {
				$row = $reviewRounds->GetRowAssoc(false);
				$writer->startElement('reviewRound');
				$this->writeElement($writer, 'round', $row['round']);
				$this->writeElement($writer, 'reviewRevision', $row['review_revision']);
				$writer->endElement();

				$reviewRounds->MoveNext();
			}
			$writer->endElement();

			$reviewAssignmentsDAO =& DAORegistry::getDAO('ReviewAssignmentDAO');
			$reviewFormResponseDAO =& DAORegistry::getDAO('ReviewFormResponseDAO');
			$reviewAssignments = $reviewAssignmentsDAO->getReviewAssignmentsByArticleId($article->getId());

			foreach ($reviewAssignments as $reviewAssignment) {		
				$writer->startElement('reviewAssignment');

				$this->writeElement($writer, 'oldId', $reviewAssignment->getId());
				$this->writeElement($writer, 'reviewerId', $reviewAssignment->getReviewerId());
				$this->writeElement($writer, 'reviewerFullName', $reviewAssignment->getReviewerFullName());
				$this->writeElement($writer, 'competingInterests', $reviewAssignment->getCompetingInterests());
				$this->writeElement($writer, 'regretMessage', $reviewAssignment->getRegretMessage());
				$this->writeElement($writer, 'recommendation', $reviewAssignment->getRecommendation());
				$this->writeElement($writer, 'dateAssigned', $reviewAssignment->getDateAssigned());
				$this->writeElement($writer, 'dateNotified', $reviewAssignment->getDateNotified());
				$this->writeElement($writer, 'dateConfirmed', $reviewAssignment->getDateConfirmed());
				$this->writeElement($writer, 'dateCompleted', $reviewAssignment->getDateCompleted());
				$this->writeElement($writer, 'dateAcknowledged', $reviewAssignment->getDateAcknowledged());
				$this->writeElement($writer, 'dateDue', $reviewAssignment->getDateDue());
				$this->writeElement($writer, 'dateResponseDue', $reviewAssignment->getDateResponseDue());
				$this->writeElement($writer, 'lastModified', $reviewAssignment->getLastModified());
				$this->writeElement($writer, 'declined', $reviewAssignment->getDeclined());
				$this->writeElement($writer, 'replaced', $reviewAssignment->getReplaced());
				$this->writeElement($writer, 'cancelled', $reviewAssignment->getCancelled());
				$this->writeElement($writer, 'reviewerFileId', $reviewAssignment->getReviewerFileId());
				$this->writeElement($writer, 'quality', $reviewAssignment->getQuality());
				$this->writeElement($writer, 'dateRated', $reviewAssignment->getDateRated());
				$this->writeElement($writer, 'dateReminded', $reviewAssignment->getDateReminded());
				$this->writeElement($writer, 'reminderWasAutomatic', $reviewAssignment->getReminderWasAutomatic());
				$this->writeElement($writer, 'round', $reviewAssignment->getRound());
				$this->writeElement($writer, 'reviewRevision', $reviewAssignment->getReviewRevision());
				$this->writeElement($writer, 'reviewFormId', $reviewAssignment->getReviewFormId());
				$this->writeElement($writer, 'reviewRoundId', $reviewAssignment->getReviewRoundId());
				$this->writeElement($writer, 'reviewMethod', $reviewAssignment->getReviewMethod());
				$this->writeElement($writer, 'stageId', $reviewAssignment->getStageId());
				$this->writeElement($writer, 'unconsidered', $reviewAssignment->getUnconsidered());

				$reviewFormResponses =& $reviewFormResponseDAO->retrieveRange(
					'SELECT * FROM review_form_responses WHERE review_id = ?',
					$reviewAssignment->getId()
				);

				$writer->startElement('formResponses');
				while (!$reviewFormResponses->EOF) {
					$row = $reviewFormResponses->GetRowAssoc(false);
					$writer->startElement('formResponse');

					$this->writeElement($writer, 'reviewFormElementId', $row['review_form_element_id']);
					$this->writeElement($writer, 'responseValue', $row['response_value']);
					$this->writeElement($writer, 'responseType', $row['response_type']);

					$writer->endElement();
					$reviewFormResponses->MoveNext();
				}
				$writer->endElement();
				$reviewFormResponses->Close();
				unset($reviewFormResponses);

				$writer->endElement();
				unset($articleComment);
			}

			$signoffDAO =& DAORegistry::getDAO('SignoffDAO');
			$signoffs = $signoffDAO->getAllByAssocType(ASSOC_TYPE_ARTICLE, $article->getId());
			while (!$signoffs->eof()) {
				$signoff = $signoffs->next();
				$writer->startElement('signoff');

				$this->writeElement($writer, 'symbolic', $signoff->getSymbolic());
				$this->writeElement($writer, 'userId', $signoff->getUserId());
				$this->writeElement($writer, 'fileId', $signoff->getFileId());
				$this->writeElement($writer, 'fileRevision', $signoff->getFileRevision());
				$this->writeElement($writer, 'dateUnderway', $signoff->getDateUnderway());
				$this->writeElement($writer, 'dateNotified', $signoff->getDateNotified());
				$this->writeElement($writer, 'dateCompleted', $signoff->getDateCompleted());
				$this->writeElement($writer, 'dateAcknowledged', $signoff->getDateAcknowledged());
				$this->writeElement($writer, 'userGroupId', $signoff->getUserGroupId());
				
				$writer->endElement();
			}
			unset($signoffs);

			$editorSubmissionDAO =& DAORegistry::getDAO('EditorSubmissionDAO');
			$editDecisions =& $editorSubmissionDAO->retrieve(
				'SELECT edit_decision_id, editor_id, decision, round, date_decided FROM edit_decisions WHERE article_id = ? ORDER BY edit_decision_id ASC', $article->getId()
			);

			while (!$editDecisions->EOF) {
				$row = $editDecisions->GetRowAssoc(false);
				$writer->startElement('editDecisions');

				$this->writeElement($writer, 'oldId', $row['edit_decision_id']);

				$this->writeElement($writer, 'editorId', $row['editor_id']);
				$this->writeElement($writer, 'decision', $row['decision']);
				$this->writeElement($writer, 'round', $row['round']);
				$this->writeElement($writer, 'dateDecided', $row['date_decided']);

				$writer->endElement();

				$editDecisions->moveNext();
			}
			$editDecisions->Close();
			unset($editDecisions);


			$publishedArticleDAO =& DAORegistry::getDAO('PublishedArticleDAO');
			$publishedArticle = $publishedArticleDAO->getPublishedArticleByArticleId($article->getId());
			if ($publishedArticle) {
				$writer->startElement('publishedArticle');

				$this->writeElement($writer, 'oldId', $publishedArticle->getPublishedArticleId());
				$this->writeElement($writer, 'issueId', $publishedArticle->getIssueId());
				$this->writeElement($writer, 'datePublished', $publishedArticle->getDatePublished());
				$this->writeElement($writer, 'seq', $publishedArticle->getSeq());
				$this->writeElement($writer, 'accessStatus', $publishedArticle->getAccessStatus());
				
				$writer->endElement();
			}
			unset($publishedArticle);

			$articleEventLogDAO =& DAORegistry::getDAO('ArticleEventLogDAO');
			$eventLogs =& $articleEventLogDAO->getByAssoc(ASSOC_TYPE_ARTICLE, $article->getId());
			$writer->startElement('eventLogs');
			while (!$eventLogs->eof()) {
				$eventLog = $eventLogs->next();
				$writer->startElement('eventLog');
				$this->writeElement($writer, 'oldId', $eventLog->getId());
				$this->writeElement($writer, 'userId', $eventLog->getUserId());
				$this->writeElement($writer, 'dateLogged', $eventLog->getDateLogged());
				$this->writeElement($writer, 'IPAddress', $eventLog->getIPAddress());
				$this->writeElement($writer, 'eventType', $eventLog->getEventType());
				$this->writeElement($writer, 'message', $eventLog->getMessage());
				$this->writeElement($writer, 'isTranslated', $eventLog->getIsTranslated());				

				$writer->startElement('settings');
					$events =& $articleEventLogDAO->retrieve('SELECT setting_name, setting_value, setting_type FROM event_log_settings WHERE log_id = ?', array((int) $eventLog->getId()));
					while (!$events->EOF) {
						$row =& $events->getRowAssoc(false);

						$writer->startElement('setting');
						$this->writeAttribute($writer, 'name', $row['setting_name']);
						$this->writeAttribute($writer, 'type', $row['setting_type']);
						$this->writeText($writer, $row['setting_value']);
						$writer->endElement();
						unset($row);
						$events->MoveNext();
					}
					$writer->endElement();
				$writer->endElement();
			}
			$writer->endElement();

			$articleEmailLogDAO =& DAORegistry::getDAO('ArticleEmailLogDAO');
			$emailLogs =& $articleEmailLogDAO->getByAssoc(ASSOC_TYPE_ARTICLE, $article->getId());
			$writer->startElement('emailLogs');
			while (!$emailLogs->eof()) {
				$emailLog = $emailLogs->next();
				$writer->startElement('emailLog');
				$this->writeElement($writer, 'oldId', $emailLog->getId());
				$this->writeElement($writer, 'assocType', $emailLog->getAssocType());
				$this->writeElement($writer, 'assocId', $emailLog->getAssocId());
				$this->writeElement($writer, 'senderId', $emailLog->getSenderId());
				$this->writeElement($writer, 'dateSent', $emailLog->getDateSent());
				$this->writeElement($writer, 'IPAddress', $emailLog->getIPAddress());
				$this->writeElement($writer, 'eventType', $emailLog->getEventType());
				$this->writeElement($writer, 'from', $emailLog->getFrom());
				$this->writeElement($writer, 'recipients', $emailLog->getRecipients());
				$this->writeElement($writer, 'ccs', $emailLog->getCcs());
				$this->writeElement($writer, 'bccs', $emailLog->getBccs());
				$this->writeElement($writer, 'subject', $emailLog->getSubject());
				$this->writeElement($writer, 'body', $emailLog->getBody());
				$writer->endElement();
			}
			$writer->endElement();
			$writer->endElement();
		}
		$writer->endElement();
		$writer->flush();
	}

	function _writeLocalizableData($writer, $data) {
		foreach ($data as $locale=>$data) {
			$writer->startElement('title');
			$this->writeAttribute($writer, 'locale', $locale);
			$this->writeText($writer, $locale);
			$writer->endElement();
		}
	}


	function exportDataObjectSettings($dao, &$writer, $tableName, $idFieldName, $idFieldValue) {
		$result =& $dao->retrieve(
			"SELECT setting_name, setting_value, setting_type, locale FROM $tableName WHERE $idFieldName = ?", $idFieldValue
		);

		$writer->startElement('settings');
		while (!$result->EOF) {
			$row =& $result->getRowAssoc(false);
			$writer->startElement('setting');
			$this->writeAttribute($writer, 'name', $row['setting_name']);
			$this->writeAttribute($writer, 'type', $row['setting_type']);
			$this->writeAttribute($writer, 'locale', $row['locale']);
			$this->writeText($writer, $row['setting_value']);
			$writer->endElement();
			unset($row);
			$writer->flush();
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		$writer->endElement();
		$writer->flush();
	}

	function exportUserSettings($userDAO, &$writer, $userId) {
		$result =& $userDAO->retrieve(
			"SELECT setting_name, setting_value, setting_type, locale, assoc_type, assoc_id FROM user_settings WHERE user_id = ?", $userId
		);

		$writer->startElement('settings');
		while (!$result->EOF) {
			$row =& $result->getRowAssoc(false);
			$writer->startElement('setting');
			$this->writeAttribute($writer, 'name', $row['setting_name']);
			$this->writeAttribute($writer, 'type', $row['setting_type']);
			$this->writeAttribute($writer, 'locale', $row['locale']);
			$this->writeAttribute($writer, 'assocType', $row['assoc_type']);
			$this->writeAttribute($writer, 'assocId', $row['assoc_id']);
			$this->writeText($writer, $row['setting_value']);
			$writer->endElement();
			unset($row);
			$writer->flush();
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		$writer->endElement();
		$writer->flush();
	}

	function writeElement($xmlWriter, $element, $value) {
		if (!is_null($value)) {
			if (Config::getVar('i18n', 'charset_normalization') && !String::utf8_compliant($value)) {
				$value = String::utf8_normalize($value);
				$value = String::utf8_bad_strip($value);
			} else if (!String::utf8_compliant($value)) {
				$value = String::utf8_bad_strip($value);
			}

			$xmlWriter->writeElement($element, $value);
		}
	}

	function writeAttribute($xmlWriter, $element, $value) {
		if (!is_null($value)) {
			if (Config::getVar('i18n', 'charset_normalization') && !String::utf8_compliant($value)) {
				$value = String::utf8_normalize($value);
				$value = String::utf8_bad_strip($value);
			} else if (!String::utf8_compliant($value)) {
				$value = String::utf8_bad_strip($value);
			}

			$xmlWriter->writeAttribute($element, $value);
		}
	}

	function writeText($xmlWriter, $value) {
		if (!is_null($value)) {
			if (Config::getVar('i18n', 'charset_normalization') && !String::utf8_compliant($value)) {
				$value = String::utf8_normalize($value);
				$value = String::utf8_bad_strip($value);
			} else if (!String::utf8_compliant($value)) {
				$value = String::utf8_bad_strip($value);
			}

			$xmlWriter->text($value);
		}
	}

	function validateDate($date, $format = 'Y-m-d H:i:s'){
	    $d = DateTime::createFromFormat($format, $date);
	    return $d && $d->format($format) == $date;
	}


}

?>