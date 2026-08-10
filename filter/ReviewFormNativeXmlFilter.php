<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class ReviewFormNativeXmlFilter extends NativeExportFilter
{
    public function append(DOMDocument $document, DOMElement $root, Journal $context): void
    {
        (new ReferenceDataXmlSupport())->exportReviewForms($document, $root, $context);
    }
}
