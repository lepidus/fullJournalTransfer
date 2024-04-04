<?php

import('plugins.importexport.native.filter.ArticleNativeXmlFilter');

class ExtendedArticleNativeXmlFilter extends ArticleNativeXmlFilter
{
    public function __construct($filterGroup)
    {
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.ExtendedArticleNativeXmlFilter';
    }

    public function createSubmissionNode($doc, $submission)
    {
        $deployment = $this->getDeployment();
        $submissionNode = parent::createSubmissionNode($doc, $submission);

        $this->addStages($doc, $submissionNode, $submission);

        return $submissionNode;
    }

    public function addStages($doc, $submissionNode, $submission)
    {
        $deployment = $this->getDeployment();
        foreach ($this->getStageMapping() as $stageId => $stagePath) {
            $submissionNode->appendChild($stageNode = $doc->createElementNS($deployment->getNamespace(), 'stage'));
            $stageNode->setAttribute('path', $stagePath);

            $this->addStageChildNodes($doc, $stageNode, $submission, $stageId);

            if ($stageId == $submission->getStageId()) {
                break;
            }
        }
    }

    public function addStageChildNodes($doc, $stageNode, $submission, $stageId)
    {
        $this->addParticipants($doc, $stageNode, $submission, $stageId);

        if ($stageId === WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            $this->addReviewRounds($doc, $stageNode, $submission, $stageId);
            return;
        }

        $this->addEditorDecisions($doc, $stageNode, $submission, $stageId);
    }

    public function addParticipants($doc, $stageNode, $submission, $stageId)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $stageAssignmentDAO = DAORegistry::getDAO('StageAssignmentDAO');
        $stageAssignments = $stageAssignmentDAO->getBySubmissionAndStageId($submission->getId(), $stageId);

        $userDAO = DAORegistry::getDAO('UserDAO');
        $userGroupDAO = DAORegistry::getDAO('UserGroupDAO');
        while ($stageAssignment = $stageAssignments->next()) {
            $user = $userDAO->getById($stageAssignment->getUserId());
            $userGroup = $userGroupDAO->getById($stageAssignment->getUserGroupId(), $context->getId());

            $participantNode = $doc->createElementNS($deployment->getNamespace(), 'participant');
            $participantNode->setAttribute('user', $user->getUsername());
            $participantNode->setAttribute('user_group_ref', $userGroup->getName($context->getPrimaryLocale()));
            $participantNode->setAttribute('recommend_only', (int) $stageAssignment->getRecommendOnly());
            $participantNode->setAttribute('can_change_metadata', (int) $stageAssignment->getCanChangeMetadata());
            $stageNode->appendChild($participantNode);
        }
    }

    public function addEditorDecisions($doc, $parentNode, $submission, $stageId, $reviewRound = null)
    {
        $deployment = $this->getDeployment();
        $userDAO = DAORegistry::getDAO('UserDAO');
        $userGroupDAO = DAORegistry::getDAO('UserGroupDAO');

        $editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
        $round = $reviewRound ? $reviewRound->getRound() : null;
        $editorDecisions = $editDecisionDao->getEditorDecisions($submission->getId(), $stageId, $round);

        foreach ($editorDecisions as $editorDecision) {
            $contextId = $submission->getContextId();
            $userId = $editorDecision['editorId'];

            if (!$userGroupDAO->userAssignmentExists($contextId, $userId, $stageId)) {
                continue;
            }

            $editor = $userDAO->getById($editorDecision['editorId']);
            $decisionNode = $doc->createElementNS($deployment->getNamespace(), 'decision');
            $decisionNode->setAttribute('round', $editorDecision['round']);
            $decisionNode->setAttribute('review_round_id', $editorDecision['reviewRoundId'] ?: 0);
            $decisionNode->setAttribute('decision', $editorDecision['decision']);
            $decisionNode->setAttribute('editor', $editor->getUsername());
            $decisionNode->setAttribute('date_decided', $editorDecision['dateDecided']);
            $parentNode->appendChild($decisionNode);
        }
    }

    public function addReviewRounds($doc, $stageNode, $submission, $stageId)
    {
        $deployment = $this->getDeployment();

        $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRounds = $reviewRoundDAO->getBySubmissionId($submission->getId(), $stageId);
        while ($reviewRound = $reviewRounds->next()) {
            $stageNode->appendChild($roundNode = $doc->createElementNS($deployment->getNamespace(), 'round'));
            $roundNode->setAttribute('round', $reviewRound->getRound());
            $roundNode->setAttribute('status', $reviewRound->getStatus());
            $this->addReviewAssignments($doc, $roundNode, $reviewRound);
            $this->addEditorDecisions($doc, $roundNode, $submission, $stageId, $reviewRound);
        }
    }

    public function addReviewAssignments($doc, $roundNode, $reviewRound)
    {
        $deployment = $this->getDeployment();
        $reviewAssignmentDAO = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignments = $reviewAssignmentDAO->getByReviewRoundId($reviewRound->getId());

        $userDAO = DAORegistry::getDAO('UserDAO');
        foreach ($reviewAssignments as $reviewAssignment) {
            $reviewer = $userDAO->getById($reviewAssignment->getReviewerId());
            $reviewAssignmentNode = $doc->createElementNS($deployment->getNamespace(), 'review_assignment');
            $reviewAssignmentNode->setAttribute('reviewer', $reviewer->getUsername());
            $reviewAssignmentNode->setAttribute('recommendation', $reviewAssignment->getRecommendation());
            $reviewAssignmentNode->setAttribute('quality', $reviewAssignment->getQuality());
            $reviewAssignmentNode->setAttribute('method', $reviewAssignment->getReviewMethod());
            $reviewAssignmentNode->setAttribute('unconsidered', $reviewAssignment->getUnconsidered());
            $reviewAssignmentNode->setAttribute('competing_interests', $reviewAssignment->getCompetingInterests());
            $reviewAssignmentNode->setAttribute('declined', (int) $reviewAssignment->getDeclined());
            $reviewAssignmentNode->setAttribute('cancelled', (int) $reviewAssignment->getCancelled());
            $reviewAssignmentNode->setAttribute('was_automatic', $reviewAssignment->getReminderWasAutomatic());
            $reviewAssignmentNode->setAttribute('date_rated', $reviewAssignment->getDateRated());
            $reviewAssignmentNode->setAttribute('date_reminded', $reviewAssignment->getDateReminded());
            $reviewAssignmentNode->setAttribute('date_assigned', $reviewAssignment->getDateAssigned());
            $reviewAssignmentNode->setAttribute('date_notified', $reviewAssignment->getDateNotified());
            $reviewAssignmentNode->setAttribute('date_confirmed', $reviewAssignment->getDateConfirmed());
            $reviewAssignmentNode->setAttribute('date_completed', $reviewAssignment->getDateCompleted());
            $reviewAssignmentNode->setAttribute('date_acknowledged', $reviewAssignment->getDateAcknowledged());
            $reviewAssignmentNode->setAttribute('date_due', $reviewAssignment->getDateDue());
            $reviewAssignmentNode->setAttribute('date_response_due', $reviewAssignment->getDateResponseDue());
            $reviewAssignmentNode->setAttribute('last_modified', $reviewAssignment->getLastModified());

            if ($reviewAssignment->getReviewFormId()) {
                $reviewAssignmentNode->setAttribute('review_form_id', $reviewAssignment->getReviewFormId());
                $this->addReviewFormResponses($doc, $reviewAssignmentNode, $reviewAssignment);
            }

            $roundNode->appendChild($reviewAssignmentNode);
        }
    }

    public function addReviewFormResponses($doc, $reviewAssignmentNode, $reviewAssignment)
    {
        $deployment = $this->getDeployment();
        $reviewFormResponseDAO = DAORegistry::getDAO('ReviewFormResponseDAO');
        $reviewFormResponses = $reviewFormResponseDAO->getReviewReviewFormResponseValues($reviewAssignment->getId());
        foreach ($reviewFormResponses as $response) {
            $responseValue = null;
            switch ($response->getResponseType()) {
                case 'int':
                    $responseValue = intval($response->getValue());
                    break;
                case 'string':
                    $responseValue = htmlspecialchars($response->getValue(), ENT_COMPAT, 'UTF-8');
                    break;
                case 'object':
                    $responseValue = join(':', $response->getValue());
                    break;
                default:
                    break;
            }
            $responseNode = $doc->createElementNS($deployment->getNamespace(), 'response', $responseValue);
            $responseNode->setAttribute('form_element_id', $response->getReviewFormElementId());
            $reviewAssignmentNode->appendChild($responseNode);
        }
    }

    private function getStageMapping()
    {
        return [
            WORKFLOW_STAGE_ID_SUBMISSION => WORKFLOW_STAGE_PATH_SUBMISSION,
            WORKFLOW_STAGE_ID_INTERNAL_REVIEW => WORKFLOW_STAGE_PATH_INTERNAL_REVIEW,
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW => WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW,
            WORKFLOW_STAGE_ID_EDITING => WORKFLOW_STAGE_PATH_EDITING,
            WORKFLOW_STAGE_ID_PRODUCTION => WORKFLOW_STAGE_PATH_PRODUCTION
        ];
    }
}
