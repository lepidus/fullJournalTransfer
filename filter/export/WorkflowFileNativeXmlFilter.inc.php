<?php

/**
 * @file plugins/importexport/fullJournalTransfer/filter/export/WorkflowFileNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkflowFileNativeXmlFilter
 * @ingroup plugins_importexport_fullJournalTransfer
 *
 * @brief Filter to convert an review file to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.SubmissionFileNativeXmlFilter');

class WorkflowFileNativeXmlFilter extends SubmissionFileNativeXmlFilter
{
    public function __construct($filterGroup)
    {
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.WorkflowFileNativeXmlFilter';
    }

    public function getSubmissionFileElementName()
    {
        return 'workflow_file';
    }

    public function createSubmissionFileNode($doc, $submissionFile)
    {
        $deployment =  $this->getDeployment();
        $submissionFileNode = parent::createSubmissionFileNode($doc, $submissionFile);

        if ($submissionFileNode && $submissionFile->getData('assocType')) {
            $submissionFileNode->setAttribute('assoc_type', $submissionFile->getData('assocType'));
        }

        return $submissionFileNode;
    }
}
