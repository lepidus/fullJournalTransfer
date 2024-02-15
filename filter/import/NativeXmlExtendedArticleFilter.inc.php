<?php

import('plugins.importexport.native.filter.NativeXmlArticleFilter');

class NativeXmlExtendedArticleFilter extends NativeXmlArticleFilter
{
    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlExtendedArticleFilter';
    }

    public function handleChildElement($n, $submission)
    {
        switch ($n->tagName) {
            case 'id':
                $this->parseIdentifier($n, $submission);
                break;
            case 'submission_file':
                $this->parseSubmissionFile($n, $submission);
                break;
            case 'publication':
                $this->parsePublication($n, $submission);
                break;
            case 'stage_assignment':
                $this->parseStageAssignment($n, $submission);
                break;
            case 'review_rounds':
                $this->parseReviewRounds($n, $submission);
                break;
            case 'editor_decisions':
                $this->parseEditorDecisions($n, $submission);
                break;
            default:
                $deployment = $this->getDeployment();
                $deployment->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', array('param' => $n->tagName)));
        }
    }

    public function parseStageAssignment($node, $submission)
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

    public function parseReviewRounds($node, $submission)
    {
        $deployment = $this->getDeployment();
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                switch ($n->tagName) {
                    case 'review_round':
                        $this->parseReviewRound($n, $submission);
                        break;
                    default:
                        $deployment->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', array('param' => $n->tagName)));
                }
            }
        }
    }

    public function parseReviewRound($node, $submission)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>review-round');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $reviewRoundDoc = new DOMDocument('1.0', 'utf-8');
        $reviewRoundDoc->appendChild($reviewRoundDoc->importNode($node, true));
        return $importFilter->execute($reviewRoundDoc);
    }

    public function parseEditorDecisions($node, $submission)
    {
        $deployment = $this->getDeployment();
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName  === 'editor_decision') {
                $this->parseEditorDecision($n, $submission);
            }
        }
    }

    public function parseEditorDecision($node, $submission)
    {
        $editorDecision = [
            'editDecisionId' => null,
            'decision' => $node->getAttribute('decision')
        ];

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                switch ($n->tagName) {
                    case 'editor':
                        $userDAO = DAORegistry::getDAO('UserDAO');
                        $editor = $userDAO->getByUsername($n->textContent);
                        $editorDecision['editorId'] = $editor->getId();
                        break;
                    case 'date_decided':
                        $editorDecision['dateDecided'] = $n->textContent;
                        break;
                }
            }
        }

        $reviewRound = null;
        if ($node->getAttribute('round') != 0) {
            $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
            $reviewRound = $reviewRoundDAO->getReviewRound(
                $submission->getId(),
                $node->getAttribute('stage_id'),
                $node->getAttribute('round')
            );
        }

        $editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
        $editDecisionDao->updateEditorDecision(
            $submission->getId(),
            $editorDecision,
            $node->getAttribute('stage_id'),
            $reviewRound
        );
    }
}
