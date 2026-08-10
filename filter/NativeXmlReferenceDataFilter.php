<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\DefaultContextDataCleaner;
use APP\plugins\importexport\fullJournalTransfer\PackageReferenceValidator;
use DOMElement;
use PKP\filter\FilterGroup;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlReferenceDataFilter extends NativeImportFilter
{
    private NativeXmlReviewFormFilter $reviewForms;
    private NativeXmlGenreFilter $genres;
    private NativeXmlSectionFilter $sections;
    private PackageReferenceValidator $validator;
    private DefaultContextDataCleaner $cleaner;

    public function __construct(
        FilterGroup $filterGroup,
        NativeXmlReviewFormFilter $reviewForms,
        NativeXmlGenreFilter $genres,
        NativeXmlSectionFilter $sections,
        PackageReferenceValidator $validator,
        DefaultContextDataCleaner $cleaner
    ) {
        parent::__construct($filterGroup);
        $this->reviewForms = $reviewForms;
        $this->genres = $genres;
        $this->sections = $sections;
        $this->validator = $validator;
        $this->cleaner = $cleaner;
    }

    public function importAll(DOMElement $root, Journal $context): array
    {
        $this->validator->validateReferenceData($root);
        $this->cleaner->cleanReferenceData($context);
        $reviewFormMap = [];
        $elementMap = [];
        $genreMap = [];
        $sectionMap = [];
        $this->reviewForms->importAll($root, $context, $reviewFormMap, $elementMap);
        $this->genres->importAll($root, $context, $genreMap);
        $this->sections->importAll($root, $context, $reviewFormMap, $sectionMap);
        return [
            'review_form_id_map' => $reviewFormMap,
            'review_form_element_id_map' => $elementMap,
            'genre_id_map' => $genreMap,
            'section_id_map' => $sectionMap,
        ];
    }
}
