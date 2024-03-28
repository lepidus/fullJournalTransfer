<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
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

        $this->addReviewFiles($doc, $reviewRoundNode, $reviewRound);
        $this->addReviewAssignments($doc, $reviewRoundNode, $reviewRound);

        return $reviewRoundNode;
    }

    public function addReviewFiles($doc, $reviewRoundNode, $reviewRound)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $fileStages = $this->getFileStagesByStageId($reviewRound->getStageId());
        $submissionFilesIterator = Services::get('submissionFile')->getMany([
            'submissionIds' => [$reviewRound->getSubmissionId()],
            'reviewRoundIds' => [$reviewRound->getId()],
            'fileStages' => $fileStages,
        ]);

        $deployment = $this->getDeployment();
        foreach ($submissionFilesIterator as $submissionFile) {
            $nativeExportFilters = $filterDao->getObjectsByGroup('review-file=>native-xml');
            assert(count($nativeExportFilters) == 1);
            $exportFilter = array_shift($nativeExportFilters);
            $exportFilter->setDeployment($this->getDeployment());

            $exportFilter->setOpts($this->opts);
            $submissionFileDoc = $exportFilter->execute($submissionFile, true);
            if ($submissionFileDoc) {
                $clone = $doc->importNode($submissionFileDoc->documentElement, true);
                $reviewRoundNode->appendChild($clone);
            }
        }
    }

    public function addReviewAssignments($doc, $reviewRoundNode, $reviewRound)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('review-assignment=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setDeployment($this->getDeployment());

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewRounds = $reviewAssignmentDao->getByReviewRoundId($reviewRound->getId());

        $reviewAssignmentsDoc = $exportFilter->execute($reviewRounds);
        if ($reviewAssignmentsDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($reviewAssignmentsDoc->documentElement, true);
            $reviewRoundNode->appendChild($clone);
        }
    }

    public function getFileStagesByStageId($stageId)
    {
        switch ($stageId) {
            case WORKFLOW_STAGE_ID_SUBMISSION:
                return [SUBMISSION_FILE_SUBMISSION];
                break;
            case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
                return [SUBMISSION_FILE_INTERNAL_REVIEW_FILE, SUBMISSION_FILE_INTERNAL_REVIEW_REVISION];
                break;
            case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                return [SUBMISSION_FILE_REVIEW_FILE, SUBMISSION_FILE_REVIEW_REVISION];
                break;
            case WORKFLOW_STAGE_ID_EDITING:
                return [SUBMISSION_FILE_FINAL, SUBMISSION_FILE_COPYEDIT];
                break;
            case WORKFLOW_STAGE_ID_PRODUCTION:
                return [SUBMISSION_FILE_PRODUCTION_READY];
                break;
            default:
                break;
        }
        return [];
    }
}
