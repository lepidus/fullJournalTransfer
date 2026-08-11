<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlPublicationFilter extends \APP\plugins\importexport\native\filter\NativeXmlPublicationFilter
{
    public function populatePublishedPublication($publication, $node)
    {
        $issueReference = trim($node->getAttribute('issue_ref'));
        if ($issueReference !== '') {
            $publication->setData(
                'issueId',
                $this->getDeployment()->requireReference('issue', $issueReference)
            );
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
