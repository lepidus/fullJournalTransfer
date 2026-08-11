<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMElement;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\PKPImportExportFilter;

class IssueNativeXmlFilter extends \APP\plugins\importexport\native\filter\IssueNativeXmlFilter
{
    public function addArticles($doc, $issueNode, $issue)
    {
    }

    public function addIssueGalleys($document, $issueNode, $issue)
    {
        $galleys = DAORegistry::getDAO('IssueGalleyDAO')->getByIssueId($issue->getId());
        $filter = PKPImportExportFilter::getFilter(
            'issue-galley=>full-journal-native-xml',
            $this->getDeployment()
        );
        $galleysDocument = $filter->execute($galleys);
        if ($galleysDocument && $galleysDocument->documentElement instanceof DOMElement) {
            $issueNode->appendChild($document->importNode($galleysDocument->documentElement, true));
        }
    }
}
