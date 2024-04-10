<?php

/**
 * @file plugins/importexport/fullJournalTransfer/filter/export/ReviewRoundFileNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundFileNativeXmlFilter
 * @ingroup plugins_importexport_fullJournalTransfer
 *
 * @brief Filter to convert an review file to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.SubmissionFileNativeXmlFilter');

class ReviewRoundFileNativeXmlFilter extends SubmissionFileNativeXmlFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML review file export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.ReviewRoundFileNativeXmlFilter';
    }

    public function getSubmissionFileElementName()
    {
        return 'review_round_file';
    }

    public function createSubmissionFileNode($doc, $submissionFile)
    {
        $deployment =  $this->getDeployment();
        $submissionFileNode = parent::createSubmissionFileNode($doc, $submissionFile);

        if ($submissionFile->getData('assocType')) {
            $reviewRefNode = $doc->createElementNS($deployment->getNamespace(), 'review_ref');
            $reviewRefNode->setAttribute('id', $submissionFile->getData('assocId'));
            $submissionFileNode->appendChild($reviewRefNode);
        }

        return $submissionFileNode;
    }
}