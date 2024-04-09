<?php

import('plugins.importexport.native.filter.NativeXmlArticleFilter');

class NativeXmlExtendedArticleFilter extends NativeXmlArticleFilter
{
    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlExtendedArticleFilter';
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

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                switch ($childNode->tagName) {
                    case 'participant':
                        $this->parseStageAssignment($childNode, $submission, $stageId);
                        break;
                    case 'decision':
                        $this->parseDecision($childNode, $submission, $stageId);
                        break;
                    case 'round':
                        $this->parseReviewRound($childNode, $submission, $stageId);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    public function parseStageAssignment($node, $submission, $stageId)
    {
        $user = DAORegistry::getDAO('UserDAO')
            ->getByUsername($node->getAttribute('user'));

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
        $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRound = $reviewRoundDAO->newDataObject();

        $reviewRound = $reviewRoundDAO->build(
            $submission->getId(),
            $stageId,
            $node->getAttribute('round'),
            $node->getAttribute('status')
        );

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                switch ($childNode->tagName) {
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
        $userDAO = DAORegistry::getDAO('UserDAO');
        $editor = $userDAO->getByUsername($node->getAttribute('editor'));

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

    public function parseReviewAssignment($node, $reviewRound)
    {
        $reviewAssignmentDAO = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignment = $reviewAssignmentDAO->newDataObject();

        $userDAO = DAORegistry::getDAO('UserDAO');
        $reviewer = $userDAO->getByUsername($node->getAttribute('reviewer'));

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
            $reviewAssignment->setReviewFormId($reviewFormId);
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

        if ($node->getAttribute('review_form_id')) {
            for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
                if (is_a($childNode, 'DOMElement') && $childNode->tagName === 'response') {
                    $this->parseResponse($childNode, $reviewAssignment);
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
}
