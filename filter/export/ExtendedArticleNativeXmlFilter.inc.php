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

        $this->addStageAssignments($doc, $submissionNode, $submission);
        $this->addReviewRounds($doc, $submissionNode, $submission);
        $this->addReviewFiles($doc, $submissionNode, $submission);
        $this->addEditorDecisions($doc, $submissionNode, $submission);

        return $submissionNode;
    }

    public function addStageAssignments($doc, $submissionNode, $submission)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $stageAssignments = DAORegistry::getDAO('StageAssignmentDAO')
            ->getBySubmissionAndStageId($submission->getId());

        while ($stageAssignment = $stageAssignments->next()) {
            $validAssignment = $this->validateAssignment(
                $context->getId(),
                $stageAssignment->getUserId(),
                $stageAssignment->getStageId(),
            );
            if (!$validAssignment) {
                continue;
            }

            $user = DAORegistry::getDAO('UserDAO')
                ->getById($stageAssignment->getUserId());
            $userGroup = DAORegistry::getDAO('UserGroupDAO')
                ->getById($stageAssignment->getUserGroupId(), $context->getId());
            $stagePath = WorkflowStageDAO::getPathFromId($stageAssignment->getStageId());

            $stageAssignmentNode = $doc->createElementNS($deployment->getNamespace(), 'stage_assignment');
            $stageAssignmentNode->setAttribute('user', $user->getUsername());
            $stageAssignmentNode->setAttribute('user_group_ref', $userGroup->getName($context->getPrimaryLocale()));
            $stageAssignmentNode->setAttribute('stage', $stagePath);
            $stageAssignmentNode->setAttribute('recommend_only', (int) $stageAssignment->getRecommendOnly());
            $stageAssignmentNode->setAttribute('can_change_metadata', (int) $stageAssignment->getCanChangeMetadata());
            $submissionNode->appendChild($stageAssignmentNode);
        }
    }

    public function addReviewRounds($doc, $submissionNode, $submission)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('review-round=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setDeployment($this->getDeployment());

        $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRounds = $reviewRoundDAO->getBySubmissionId($submission->getId())->toArray();

        $reviewRoundsDoc = $exportFilter->execute($reviewRounds);
        if ($reviewRoundsDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($reviewRoundsDoc->documentElement, true);
            $submissionNode->appendChild($clone);
        }
    }

    public function addReviewFiles($doc, $submissionNode, $submission)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $submissionFilesIterator = Services::get('submissionFile')->getMany([
            'submissionIds' => [$submission->getId()],
            'includeDependentFiles' => true,
            'fileStages' => [
                SUBMISSION_FILE_QUERY,
                SUBMISSION_FILE_NOTE,
                SUBMISSION_FILE_REVIEW_ATTACHMENT,
                SUBMISSION_FILE_REVIEW_FILE,
                SUBMISSION_FILE_REVIEW_ATTACHMENT,
                SUBMISSION_FILE_REVIEW_REVISION,
                SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
                SUBMISSION_FILE_INTERNAL_REVIEW_REVISION
            ]
        ]);

        $deployment = $this->getDeployment();
        foreach ($submissionFilesIterator as $submissionFile) {
            $nativeExportFilters = $filterDao->getObjectsByGroup(get_class($submissionFile) . '=>native-xml');
            assert(count($nativeExportFilters) == 1);
            $exportFilter = array_shift($nativeExportFilters);
            $exportFilter->setDeployment($this->getDeployment());

            $exportFilter->setOpts($this->opts);
            $submissionFileDoc = $exportFilter->execute($submissionFile, true);
            if ($submissionFileDoc) {
                $clone = $doc->importNode($submissionFileDoc->documentElement, true);
                $submissionNode->appendChild($clone);
            }
        }
    }

    public function addEditorDecisions($doc, $submissionNode, $submission)
    {
        $deployment = $this->getDeployment();

        $editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
        $editorDecisions = $editDecisionDao->getEditorDecisions($submission->getId());

        if (!count($editorDecisions)) {
            return;
        }

        $editorDecisionsNode = $doc->createElementNS($deployment->getNamespace(), 'editor_decisions');
        foreach ($editorDecisions as $editorDecision) {
            $validAssignment = $this->validateAssignment(
                $submission->getContextId(),
                $editorDecision['editorId'],
                $editorDecision['stageId'],
            );
            if (!$validAssignment) {
                continue;
            }

            $userDAO = DAORegistry::getDAO('UserDAO');
            $editor = $userDAO->getById($editorDecision['editorId']);

            $editorDecisionNode = $doc->createElementNS($deployment->getNamespace(), 'editor_decision');
            $editorDecisionNode->setAttribute('submission_id', $submission->getId());
            $editorDecisionNode->setAttribute('round', $editorDecision['round']);
            $editorDecisionNode->setAttribute('review_round_id', $editorDecision['reviewRoundId'] ?: 0);
            $editorDecisionNode->setAttribute('stage_id', $editorDecision['stageId']);
            $editorDecisionNode->setAttribute('decision', $editorDecision['decision']);

            $editorDecisionNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'editor',
                htmlspecialchars($editor->getUsername(), ENT_COMPAT, 'UTF-8')
            ));
            $editorDecisionNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'date_decided',
                strftime('%Y-%m-%d %H:%M:%S', strtotime($editorDecision['dateDecided']))
            ));

            $editorDecisionsNode->appendChild($editorDecisionNode);
        }

        $submissionNode->appendChild($editorDecisionsNode);
    }

    private function validateAssignment($contextId, $userId, $stageId)
    {
        $userGroupDAO = DAORegistry::getDAO('UserGroupDAO');
        if (!$userGroupDAO->userAssignmentExists($contextId, $userId, $stageId)) {
            return false;
        }

        return true;
    }
}
