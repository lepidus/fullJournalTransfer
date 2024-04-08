<?php

import('plugins.importexport.native.filter.NativeXmlArticleFilter');

class NativeXmlExtendedArticleFilter extends NativeXmlArticleFilter
{
    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlExtendedArticleFilter';
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

        return $reviewRound;
    }

    public function parseDecision($node, $reviewRound)
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
            $reviewRound->getSubmissionId(),
            $editorDecision,
            $reviewRound->getStageId(),
            $reviewRound
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
        $reviewAssignment->setReviewFormId($node->getAttribute('review_form_id'));
        $reviewAssignment->setReviewRoundId($reviewRound->getId());
        $reviewAssignment->setCompetingInterests($node->getAttribute('competing_interests'));
        $reviewAssignment->setRecommendation($node->getAttribute('recommendation'));
        $reviewAssignment->setDateAssigned($node->getAttribute('date_assigned'));
        $reviewAssignment->setDateNotified($node->getAttribute('date_notified'));
        $reviewAssignment->setDateConfirmed($node->getAttribute('date_confirmed'));
        $reviewAssignment->setDateCompleted($node->getAttribute('date_completed'));
        $reviewAssignment->setDateAcknowledged($node->getAttribute('date_acknowledged'));
        $reviewAssignment->setDateDue($node->getAttribute('date_due'));
        $reviewAssignment->setDateResponseDue($node->getAttribute('date_response_due'));
        $reviewAssignment->setLastModified($node->getAttribute('last_modified'));
        $reviewAssignment->setDeclined((int) $node->getAttribute('declined'));
        $reviewAssignment->setCancelled((int) $node->getAttribute('cancelled'));
        $reviewAssignment->setQuality($node->getAttribute('quality'));
        $reviewAssignment->setDateRated($node->getAttribute('date_rated'));
        $reviewAssignment->setDateReminded($node->getAttribute('date_reminded'));
        $reviewAssignment->setReminderWasAutomatic((int) $node->getAttribute('reminder_was_automatic'));
        $reviewAssignment->setRound($reviewRound->getRound());
        $reviewAssignment->setReviewMethod((int) $node->getAttribute('method'));
        $reviewAssignment->setStageId($reviewRound->getStageId());
        $reviewAssignment->setUnconsidered((int) $node->getAttribute('unconsidered'));

        $reviewAssignmentDAO->insertObject($reviewAssignment);
        return $reviewAssignment;
    }

    public function parseResponse($node, $reviewAssignment)
    {
        $deployment = $this->getDeployment();

        $newReviewFormElementId = $deployment->getReviewFormElementDBId($node->getAttribute('form_element_id'));

        $reviewFormResponseDAO = DAORegistry::getDAO('ReviewFormResponseDAO');
        $reviewFormResponse = $reviewFormResponseDAO->newDataObject();
        $reviewFormResponse->setReviewId($reviewAssignment->getId());
        $reviewFormResponse->setReviewFormElementId($newReviewFormElementId);
        $reviewFormResponse->setResponseType($node->getAttribute('type'));

        if ($node->getAttribute('type') === 'object') {
            $reviewFormResponse->setValue(preg_split('/:/', $node->textContent));
        } else {
            $reviewFormResponse->setValue($node->textContent);
        }

        $reviewFormResponseDAO->insertObject($reviewFormResponse);
    }
}
