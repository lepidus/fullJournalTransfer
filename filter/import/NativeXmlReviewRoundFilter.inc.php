<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlReviewRoundFilter extends NativeImportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML review round import');
        parent::__construct($filterGroup);
    }

    public function getPluralElementName()
    {
        return 'review_rounds';
    }

    public function getSingularElementName()
    {
        return 'review_round';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlReviewRoundFilter';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $submission = $deployment->getSubmission();

        $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRound = $reviewRoundDAO->newDataObject();

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                switch ($n->tagName) {
                    case 'id':
                        $oldId = $n->textContent;
                        break;
                    case 'stage':
                        $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO');
                        $stageId = WorkflowStageDAO::getIdFromPath($n->textContent);
                        break;
                    case 'round':
                        $round = $n->textContent;
                        break;
                    case 'status':
                        $status = $n->textContent;
                        break;
                    default:
                        break;
                }
            }
        }

        $reviewRound = $reviewRoundDAO->build($submission->getId(), $stageId, $round, $status);
        $deployment->setReviewRound($reviewRound);

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                $this->handleChildElement($childNode, $reviewRound);
            }
        }

        return $reviewRound;
    }

    public function handleChildElement($n, $reviewRound)
    {
        switch ($n->tagName) {
            case 'review_file':
                $this->parseReviewFile($n, $reviewRound);
                break;
            case 'review_assignments':
                $this->parseReviewAssignments($n, $reviewRound);
                break;
            default:
                break;
        }
    }

    public function parseReviewFile($node, $reviewRound)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>review-file');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $reviewFileDoc = new DOMDocument('1.0', 'utf-8');
        $reviewFileDoc->appendChild($reviewFileDoc->importNode($node, true));
        return $importFilter->execute($reviewFileDoc);
    }

    public function parseReviewAssignments($node, $reviewRound)
    {
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName  === 'review_assignment') {
                $this->parseReviewAssignment($n, $reviewRound);
            }
        }
    }

    public function parseReviewAssignment($node, $reviewRound)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>review-assignment');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $reviewAssignmentDoc = new DOMDocument();
        $reviewAssignmentDoc->appendChild($reviewAssignmentDoc->importNode($node, true));
        return $importFilter->execute($reviewAssignmentDoc);
    }
}
