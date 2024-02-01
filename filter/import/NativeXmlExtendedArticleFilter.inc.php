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
            case 'review_rounds':
                $this->parseReviewRounds($n, $submission);
                break;
            case 'review_assignments':
                $this->parseReviewAssignments($n, $submission);
                break;
            default:
                $deployment = $this->getDeployment();
                $deployment->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', array('param' => $n->tagName)));
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
}
