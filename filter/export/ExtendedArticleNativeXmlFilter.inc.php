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
}
