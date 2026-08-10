<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class JournalNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$journal)
    {
        if (!$journal instanceof Journal) {
            throw new \InvalidArgumentException('Expected a journal for export');
        }
        $document = (new JournalXmlSupport())->export($journal);
        return $document;
    }
}
