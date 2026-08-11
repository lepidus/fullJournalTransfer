<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\issue\IssueGalley;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

class IssueGalleyNativeXmlFilter extends \APP\plugins\importexport\native\filter\IssueGalleyNativeXmlFilter
{
    public function createIssueGalleyNode(DOMDocument $document, IssueGalley $issueGalley): ?DOMElement
    {
        $node = parent::createIssueGalleyNode($document, $issueGalley);
        if (!$node) {
            return null;
        }
        foreach ($node->getElementsByTagNameNS('http://pkp.sfu.ca', 'issue_file') as $fileNode) {
            $embeds = $fileNode->getElementsByTagNameNS('http://pkp.sfu.ca', 'embed');
            $content = $embeds->length === 1 ? base64_decode($embeds->item(0)->textContent, true) : false;
            if ($content === false) {
                throw new InvalidArgumentException('Issue galley checksum could not be calculated');
            }
            $fileNode->setAttribute('checksum', hash('sha256', $content));
        }
        return $node;
    }
}
