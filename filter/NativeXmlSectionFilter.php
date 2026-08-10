<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use DOMElement;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlSectionFilter extends NativeImportFilter
{
    public function importAll(DOMElement $root, Journal $context, array $reviewFormMap, array &$map): void
    {
        (new ReferenceDataXmlSupport())->importSections($root, $context, $reviewFormMap, $map);
    }
}
