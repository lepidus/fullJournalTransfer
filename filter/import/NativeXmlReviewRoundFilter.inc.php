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
        $context = $deployment->getContext();

        $reviewRoundDAO = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRound = $reviewRoundDAO->newDataObject();

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                switch ($n->tagName) {
                    case 'id':
                        $oldId = $n->textContent;
                        break;
                    case 'submission_id':
                        $submissionId = $deployment->getSubmissionDBId($n->textContent);
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

        $reviewRound = $reviewRoundDAO->build($submissionId, $stageId, $round, $status);
        $deployment->setReviewRoundDBId($oldId, $reviewRound->getId());

        return $reviewRound;
    }
}
