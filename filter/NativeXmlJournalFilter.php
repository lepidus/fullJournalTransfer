<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use DOMElement;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlJournalFilter extends NativeImportFilter
{
    public function hydrate(DOMElement $node, Journal $journal): void
    {
        (new JournalXmlSupport())->import($node, $journal);
    }

    public function handleElement($node)
    {
        return (new JournalXmlSupport())->create($node);
    }

    public function getPluralElementName()
    {
        return 'journals';
    }

    public function getSingularElementName()
    {
        return 'journal';
    }
}
