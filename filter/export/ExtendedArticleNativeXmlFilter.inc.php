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

        $this->addReviewRounds($doc, $submissionNode, $submission);
        $this->addEditorDecisions($doc, $submissionNode, $submission);

        return $submissionNode;
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
}
