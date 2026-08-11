<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlIssueFilter extends \APP\plugins\importexport\native\filter\NativeXmlIssueFilter
{
    public function parseIssueGalley($node, $issue)
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($node, true));
        $filter = PKPImportExportFilter::getFilter(
            'full-journal-native-xml=>issue-galley',
            $this->getDeployment()
        );
        return $filter->execute($document);
    }
}
