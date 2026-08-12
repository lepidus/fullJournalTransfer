<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlPublicationFilter extends \APP\plugins\importexport\native\filter\NativeXmlPublicationFilter
{
    public function populatePublishedPublication($publication, $node)
    {
        $deployment = $this->getDeployment();
        $issue = $deployment->getIssue();
        if ($node->getElementsByTagName('issue_identification')->length === 1) {
            $deployment->setIssue(null);
            try {
                return parent::populatePublishedPublication($publication, $node);
            } finally {
                $deployment->setIssue($issue);
            }
        }
        if ($issue) {
            return parent::populatePublishedPublication($publication, $node);
        }
        return $publication;
    }

    public function parseArticleGalley($node, $publication)
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($node, true));
        $filter = PKPImportExportFilter::getFilter(
            'full-journal-native-xml=>article-galley',
            $this->getDeployment()
        );
        return $filter->execute($document);
    }
}
