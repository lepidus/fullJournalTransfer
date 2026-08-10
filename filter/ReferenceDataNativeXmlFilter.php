<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use DOMDocument;
use PKP\filter\FilterGroup;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class ReferenceDataNativeXmlFilter extends NativeExportFilter
{
    private ReviewFormNativeXmlFilter $reviewForms;
    private GenreNativeXmlFilter $genres;
    private SectionNativeXmlFilter $sections;

    public function __construct(
        FilterGroup $filterGroup,
        ReviewFormNativeXmlFilter $reviewForms,
        GenreNativeXmlFilter $genres,
        SectionNativeXmlFilter $sections
    ) {
        parent::__construct($filterGroup);
        $this->reviewForms = $reviewForms;
        $this->genres = $genres;
        $this->sections = $sections;
    }

    public function &process(&$context)
    {
        if (!$context instanceof Journal) {
            throw new \InvalidArgumentException('Expected a journal for reference data export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS('http://pkp.sfu.ca', 'reference_data');
        $document->appendChild($root);
        $this->reviewForms->append($document, $root, $context);
        $this->genres->append($document, $root, $context);
        $this->sections->append($document, $root, $context);
        return $document;
    }
}
