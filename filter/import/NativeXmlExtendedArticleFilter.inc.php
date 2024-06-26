<?php

import('plugins.importexport.native.filter.NativeXmlArticleFilter');

class NativeXmlExtendedArticleFilter extends NativeXmlArticleFilter
{
    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlExtendedArticleFilter';
    }

    public function handleElement($node)
    {
        $submission = parent::handleElement($node);
        $deployment = $this->getDeployment();

        if ($submission) {
            for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
                if (
                    is_a($childNode, 'DOMElement')
                    && $childNode->tagName == 'id'
                    && $childNode->getAttribute('type') == 'internal'
                ) {
                    $deployment->setSubmissionDBId($childNode->textContent, $submission->getId());
                }
            }
        }
    }

    public function handleChildElement($node, $submission)
    {
        if ($node->tagName === 'stage') {
            $this->parseStage($node, $submission);
        } else {
            parent::handleChildElement($node, $submission);
        }
    }

    public function parseStage($node, $submission)
    {
        $stageId = WorkflowStageDAO::getIdFromPath($node->getAttribute('path'));
        $deployment = $this->getDeployment();

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                switch ($childNode->tagName) {
                    case 'participant':
                        $this->parseStageAssignment($childNode, $submission, $stageId);
                        break;
                    case 'decision':
                        $this->parseDecision($childNode, $submission, $stageId);
                        break;
                    case 'review_round':
                        $this->parseReviewRound($childNode, $submission, $stageId);
                        break;
                    case 'queries':
                        $queryNodes = $childNode->getElementsByTagNameNS($deployment->getNamespace(), 'query');
                        for ($i = 0; $i < $queryNodes->count(); $i++) {
                            $queryNode = $queryNodes->item($i);
                            $this->parseQuery($queryNode, $submission, $stageId);
                        }
                        break;
                    default:
                        break;
                }
            }
        }
    }

    public function parseStageAssignment($node, $submission, $stageId)
    {
        $deployment = $this->getDeployment();

        $user = DAORegistry::getDAO('UserDAO')
            ->getUserByEmail($node->getAttribute('user_email'));

        if (is_null($user)) {
            $deployment->addWarning(
                ASSOC_TYPE_SUBMISSION,
                $submission->getId(),
                __(
                    'plugins.importexport.fullJournal.error.userNotFound',
                    ['email' => $node->getAttribute('user_email')]
                )
            );

            return null;
        }

        $userGroups = DAORegistry::getDAO('UserGroupDAO')
            ->getByContextId($submission->getContextId())
            ->toArray();

        $userGroupRef = $node->getAttribute('user_group_ref');
        foreach ($userGroups as $userGroup) {
            if (in_array($userGroupRef, $userGroup->getName(null))) {
                return DAORegistry::getDAO('StageAssignmentDAO')->build(
                    $submission->getId(),
                    $userGroup->getId(),
                    $user->getId(),
                    $node->getAttribute('recommend_only'),
                    $node->getAttribute('can_change_metadata')
                );
            }
        }
    }

    public function parseReviewRound($node, $submission, $stageId)
    {
        $deployment = $this->getDeployment();

        $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRound = $reviewRoundDAO->newDataObject();

        $reviewRound = $reviewRoundDAO->build(
            $submission->getId(),
            $stageId,
            $node->getAttribute('round'),
            $node->getAttribute('status')
        );

        $deployment->setReviewRound($reviewRound);

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                switch ($childNode->tagName) {
                    case 'workflow_file':
                        $this->parseArticleFile($childNode);
                        break;
                    case 'review_assignment':
                        $this->parseReviewAssignment($childNode, $reviewRound);
                        break;
                    case 'decision':
                        $this->parseDecision($childNode, $submission, $stageId, $reviewRound);
                        break;
                    default:
                        break;
                }
            }
        }

        return $reviewRound;
    }

    public function parseDecision($node, $submission, $stageId, $reviewRound = null)
    {
        $deployment = $this->getDeployment();

        $userDAO = DAORegistry::getDAO('UserDAO');
        $editor = $userDAO->getUserByEmail($node->getAttribute('editor_email'));

        if (is_null($editor)) {
            $deployment->addWarning(
                ASSOC_TYPE_SUBMISSION,
                $submission->getId(),
                __(
                    'plugins.importexport.fullJournal.error.userNotFound',
                    ['email' => $node->getAttribute('editor_email')]
                )
            );

            return null;
        }

        $editorDecision = [
            'editDecisionId' => null,
            'editorId' => $editor->getId(),
            'decision' => $node->getAttribute('decision'),
            'dateDecided' => $node->getAttribute('date_decided')
        ];

        $editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
        $editDecisionDao->updateEditorDecision(
            $submission->getId(),
            $editorDecision,
            $stageId,
            $reviewRound ?? null
        );
    }

    public function parseQuery($node, $submission, $stageId)
    {
        $queryDAO = DAORegistry::getDAO('QueryDAO');
        $noteDAO = DAORegistry::getDAO('NoteDAO');
        $userDAO = DAORegistry::getDAO('UserDAO');
        $deployment = $this->getDeployment();

        $query = $queryDAO->newDataObject();
        $query->setAssocType(ASSOC_TYPE_SUBMISSION);
        $query->setAssocId($submission->getId());
        $query->setStageId($stageId);
        $query->setIsClosed((bool) $node->getAttribute('closed'));
        $query->setSequence((float) $node->getAttribute('seq'));

        $queryId = $queryDAO->insertObject($query);

        $participantNodes = $node->getElementsByTagNameNS($deployment->getNamespace(), 'participant');
        for ($i = 0; $i < $participantNodes->count(); $i++) {
            $participantNode = $participantNodes->item($i);
            $email = $participantNode->textContent;
            $participant = $userDAO->getUserByEmail($email);

            if ($participant) {
                $queryDAO->insertParticipant($queryId, $participant->getId());
            }
        }

        $noteNodes = $node->getElementsByTagNameNS($deployment->getNamespace(), 'note');
        for ($i = 0; $i < $noteNodes->count(); $i++) {
            $noteNode = $noteNodes->item($i);
            $titleNode = $noteNode->getElementsByTagNameNS($deployment->getNamespace(), 'title')->item(0);
            $contentsNode = $noteNode->getElementsByTagNameNS($deployment->getNamespace(), 'contents')->item(0);
            $email = $noteNode->getAttribute('user_email');
            $noteUser = $userDAO->getUserByEmail($email);

            $note = $noteDAO->newDataObject();
            if ($noteUser) {
                $note->setUserId($noteUser->getId());
            }
            $note->setDateCreated($noteNode->getAttribute('date_created'));
            $note->setTitle($titleNode->textContent);
            $note->setContents($contentsNode->textContent);
            $note->setAssocType(ASSOC_TYPE_QUERY);
            $note->setAssocId($queryId);

            $noteDAO->insertObject($note);

            $deployment->setNote($note);
            $noteFilesNodes = $noteNode->getElementsByTagNameNS($deployment->getNamespace(), 'workflow_file');
            for ($fileIndex = 0; $fileIndex < $noteFilesNodes->count(); $fileIndex++) {
                $noteNode = $noteFilesNodes->item($fileIndex);
                $this->parseArticleFile($noteNode);
            }
        }

        return $queryId;
    }

    public function parseReviewAssignment($node, $reviewRound)
    {
        $deployment = $this->getDeployment();
        $submission = $deployment->getSubmission();

        $reviewAssignmentDAO = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignment = $reviewAssignmentDAO->newDataObject();

        $userDAO = DAORegistry::getDAO('UserDAO');
        $reviewer = $userDAO->getUserByEmail($node->getAttribute('reviewer_email'));

        if (is_null($reviewer)) {
            $deployment->addWarning(
                ASSOC_TYPE_SUBMISSION,
                $submission->getId(),
                __(
                    'plugins.importexport.fullJournal.error.userNotFound',
                    ['email' => $node->getAttribute('reviewer_email')]
                )
            );

            return null;
        }

        $reviewAssignment->setSubmissionId($reviewRound->getSubmissionId());
        $reviewAssignment->setReviewerId($reviewer->getId());
        $reviewAssignment->setReviewRoundId($reviewRound->getId());
        $reviewAssignment->setDateAssigned($node->getAttribute('date_assigned'));
        $reviewAssignment->setDateNotified($node->getAttribute('date_notified'));
        $reviewAssignment->setDateDue($node->getAttribute('date_due'));
        $reviewAssignment->setDateResponseDue($node->getAttribute('date_response_due'));
        $reviewAssignment->setLastModified($node->getAttribute('last_modified'));
        $reviewAssignment->setDeclined((int) $node->getAttribute('declined'));
        $reviewAssignment->setCancelled((int) $node->getAttribute('cancelled'));
        $reviewAssignment->setReminderWasAutomatic((int) $node->getAttribute('reminder_was_automatic'));
        $reviewAssignment->setRound($reviewRound->getRound());
        $reviewAssignment->setReviewMethod((int) $node->getAttribute('method'));
        $reviewAssignment->setStageId($reviewRound->getStageId());
        $reviewAssignment->setUnconsidered((int) $node->getAttribute('unconsidered'));

        if ($reviewFormId = $node->getAttribute('review_form_id')) {
            $reviewAssignment->setReviewFormId($deployment->getReviewFormDBId($reviewFormId));
        }
        if ($quality = $node->getAttribute('quality')) {
            $reviewAssignment->setQuality($quality);
        }
        if ($recommendation = $node->getAttribute('recommendation')) {
            $reviewAssignment->setRecommendation($recommendation);
        }
        if ($competingInterests = $node->getAttribute('competing_interests')) {
            $reviewAssignment->setCompetingInterests($competingInterests);
        }
        if ($dateRated = $node->getAttribute('date_rated')) {
            $reviewAssignment->setDateRated($dateRated);
        }
        if ($dateReminded = $node->getAttribute('date_reminded')) {
            $reviewAssignment->setDateReminded($dateReminded);
        }
        if ($dateConfirmed = $node->getAttribute('date_confirmed')) {
            $reviewAssignment->setDateConfirmed($dateConfirmed);
        }
        if ($dateCompleted = $node->getAttribute('date_completed')) {
            $reviewAssignment->setDateCompleted($dateCompleted);
        }
        if ($dateAcknowledged = $node->getAttribute('date_acknowledged')) {
            $reviewAssignment->setDateAcknowledged($dateAcknowledged);
        }

        $reviewAssignmentDAO->insertObject($reviewAssignment);
        $deployment->setReviewAssignment($reviewAssignment);

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                switch ($childNode->tagName) {
                    case 'review_files':
                        $reviewFileIds = preg_split('/:/', $childNode->textContent);
                        $reviewFilesDAO = DAORegistry::getDAO('ReviewFilesDAO');
                        foreach ($reviewFileIds as $reviewFileId) {
                            $newSubmissionFileId = $deployment->getSubmissionFileDBId($reviewFileId);
                            if (!is_null($newSubmissionFileId)) {
                                $reviewFilesDAO->grant($reviewAssignment->getId(), $newSubmissionFileId);
                            }
                        }
                        break;
                    case 'workflow_file':
                        $this->parseArticleFile($childNode);
                        break;
                    case 'response':
                        $this->parseResponse($childNode, $reviewAssignment);
                        break;
                    case 'submission_comment':
                        $this->parseSubmissionComment($childNode, $reviewAssignment);
                        break;
                    default:
                        break;
                }
            }
        }

        return $reviewAssignment;
    }

    public function parseResponse($node, $reviewAssignment)
    {
        $deployment = $this->getDeployment();

        $newReviewFormElementId = $deployment->getReviewFormElementDBId($node->getAttribute('form_element_id'));

        $reviewFormResponseDAO = DAORegistry::getDAO('ReviewFormResponseDAO');
        $reviewFormResponse = $reviewFormResponseDAO->newDataObject();
        $reviewFormResponse->setReviewId($reviewAssignment->getId());
        $reviewFormResponse->setResponseType($node->getAttribute('type'));
        $reviewFormResponse->setReviewFormElementId($newReviewFormElementId);

        if ($node->getAttribute('type') === 'object') {
            $reviewFormResponse->setValue(preg_split('/:/', $node->textContent));
        } else {
            $reviewFormResponse->setValue($node->textContent);
        }

        $reviewFormResponseDAO->insertObject($reviewFormResponse);
    }

    public function parseSubmissionComment($node, $reviewAssignment)
    {
        $deployment = $this->getDeployment();

        $userDAO = DAORegistry::getDAO('UserDAO');
        $commentAuthor = $userDAO->getUserByEmail($node->getAttribute('author'));

        if (is_null($commentAuthor)) {
            $deployment->addWarning(
                ASSOC_TYPE_SUBMISSION,
                $reviewAssignment->getSubmissionId(),
                __(
                    'plugins.importexport.fullJournal.error.userNotFound',
                    ['email' => $node->getAttribute('author')]
                )
            );

            return null;
        }

        $submissionCommentDAO = DAORegistry::getDAO('SubmissionCommentDAO');
        $comment = $submissionCommentDAO->newDataObject();
        $comment->setCommentType($node->getAttribute('comment_type'));
        $comment->setRoleId($node->getAttribute('role'));
        $comment->setSubmissionId($reviewAssignment->getSubmissionId());
        $comment->setAssocId($reviewAssignment->getId());
        $comment->setAuthorId($commentAuthor->getId());
        $comment->setDatePosted($node->getAttribute('date_posted'));
        $comment->setDateModified($node->getAttribute('date_modified'));
        $comment->setViewable($node->getAttribute('viewable'));

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                switch ($childNode->tagName) {
                    case 'title':
                        $comment->setCommentTitle($childNode->textContent);
                        break;
                    case 'comments':
                        $comment->setComments($childNode->textContent);
                        break;
                    default:
                        break;
                }
            }
        }

        return $submissionCommentDAO->insertObject($comment);
    }

    public function parseArticleFile($node)
    {
        $filterDAO = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDAO->getObjectsByGroup('native-xml=>workflow-file');
        $importFilter = array_shift($importFilters);
        assert(isset($importFilter));

        $importFilter->setDeployment($this->getDeployment());
        $reviewRoundFileDoc = new DOMDocument('1.0', 'utf-8');
        $reviewRoundFileDoc->appendChild($reviewRoundFileDoc->importNode($node, true));
        return $importFilter->execute($reviewRoundFileDoc);
    }

    public function getImportFilter($elementName)
    {
        if ($elementName == 'publication') {
            $importClass = 'extended-publication';

            $filterDao = DAORegistry::getDAO('FilterDAO');
            $importFilters = $filterDao->getObjectsByGroup('native-xml=>' . $importClass);
            $importFilter = array_shift($importFilters);
            return $importFilter;
        }

        return parent::getImportFilter($elementName);
    }
}
