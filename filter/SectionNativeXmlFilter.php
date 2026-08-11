<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use DOMElement;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class SectionNativeXmlFilter extends NativeExportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function &process(&$sections)
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $container = $document->createElementNS('http://pkp.sfu.ca', 'sections');
        $document->appendChild($container);
        foreach ($sections as $section) {
            $container->appendChild($this->createSectionNode($document, $section));
        }
        return $document;
    }

    public function createSectionNode(DOMDocument $document, $section): DOMElement
    {
        $node = $document->createElementNS('http://pkp.sfu.ca', 'section');
        $node->setAttribute('source_ref', (string) $section->getId());
        $node->setAttribute('sequence', (string) $section->getSequence());
        $node->setAttribute('editor_restricted', $section->getEditorRestricted() ? 'true' : 'false');
        $node->setAttribute('meta_indexed', $section->getMetaIndexed() ? 'true' : 'false');
        $node->setAttribute('meta_reviewed', $section->getMetaReviewed() ? 'true' : 'false');
        $node->setAttribute('abstracts_not_required', $section->getAbstractsNotRequired() ? 'true' : 'false');
        $node->setAttribute('hide_title', $section->getHideTitle() ? 'true' : 'false');
        $node->setAttribute('hide_author', $section->getHideAuthor() ? 'true' : 'false');
        $node->setAttribute('inactive', $section->getIsInactive() ? 'true' : 'false');
        $node->setAttribute('abstract_word_count', (string) ($section->getAbstractWordCount() ?? 0));
        if ($section->getReviewFormId()) {
            $node->setAttribute('review_form_ref', (string) $section->getReviewFormId());
        }
        $this->createLocalizedNodes($document, $node, 'title', $section->getTitle(null));
        $this->createLocalizedNodes($document, $node, 'abbrev', $section->getAbbrev(null));
        $this->createLocalizedNodes($document, $node, 'policy', $section->getPolicy(null));
        return $node;
    }
}
