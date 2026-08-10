<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class SectionNativeXmlFilter extends NativeExportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function append(DOMDocument $document, DOMElement $root, Journal $context): void
    {
        $container = $document->createElementNS('http://pkp.sfu.ca', 'sections');
        $sections = Repo::section()->getCollector()->filterByContextIds([(int) $context->getId()])->getMany();
        foreach ($sections as $section) {
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
            $this->appendLocalized($document, $node, 'title', $section->getTitle(null));
            $this->appendLocalized($document, $node, 'abbrev', $section->getAbbrev(null));
            $this->appendLocalized($document, $node, 'policy', $section->getPolicy(null));
            $container->appendChild($node);
        }
        $root->appendChild($container);
    }
}
