<?php

/**
 * Copyright (c) 2014-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class ReviewRoundNativeXmlFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML review round export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.ReviewRoundNativeXmlFilter';
    }

    public function &process(&$reviewRounds)
    {
        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'review_rounds');
        foreach ($reviewRounds as $reviewRound) {
            $rootNode->appendChild($this->createReviewRoundNode($doc, $reviewRound));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    public function createReviewRoundNode($doc, $reviewRound)
    {
        $deployment = $this->getDeployment();

        $reviewRoundNode = $doc->createElementNS($deployment->getNamespace(), 'review_round');

        $reviewRoundNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'id',
            $reviewRound->getId()
        ));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');

        $reviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'submission_id',
            intval($reviewRound->getSubmissionId())
        ));
        $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO');
        $reviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'stage',
            htmlspecialchars(WorkflowStageDAO::getPathFromId($reviewRound->getStageId()), ENT_COMPAT, 'UTF-8')
        ));
        $reviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'round',
            intval($reviewRound->getRound())
        ));
        $reviewRoundNode->appendChild($doc->createElementNS(
            $deployment->getNamespace(),
            'status',
            intval($reviewRound->getStatus())
        ));

        return $reviewRoundNode;
    }
}
