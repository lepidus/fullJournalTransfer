<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use DOMElement;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileNativeXmlFilter extends \PKP\plugins\importexport\native\filter\SubmissionFileNativeXmlFilter
{
    public function createSubmissionFileNode(DOMDocument $document, SubmissionFile $submissionFile): ?DOMElement
    {
        $node = parent::createSubmissionFileNode($document, $submissionFile);
        if (!$node) {
            return null;
        }
        return $node;
    }
}
