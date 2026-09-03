<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\nativeData;

use APP\issue\IssueGalley;
use DOMDocument;
use DOMElement;

class IssueGalleyNativeXmlFilter extends \APP\plugins\importexport\native\filter\IssueGalleyNativeXmlFilter
{
    public function createIssueGalleyNode(DOMDocument $document, IssueGalley $issueGalley): ?DOMElement
    {
        $node = parent::createIssueGalleyNode($document, $issueGalley);
        if (!$node) {
            return null;
        }
        return $node;
    }
}
