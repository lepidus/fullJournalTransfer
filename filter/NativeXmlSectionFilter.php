<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlSectionFilter extends NativeImportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function getPluralElementName()
    {
        return 'sections';
    }

    public function getSingularElementName()
    {
        return 'section';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $sourceReference = $this->sourceReference($node, $deployment->getReferenceMap('section'));
        $section = Repo::section()->newDataObject();
        $section->setContextId((int) $context->getId());
        $section->setSequence($this->floatAttribute($node, 'sequence'));
        $section->setEditorRestricted($this->booleanAttribute($node, 'editor_restricted'));
        $section->setMetaIndexed($this->booleanAttribute($node, 'meta_indexed'));
        $section->setMetaReviewed($this->booleanAttribute($node, 'meta_reviewed'));
        $section->setAbstractsNotRequired($this->booleanAttribute($node, 'abstracts_not_required'));
        $section->setHideTitle($this->booleanAttribute($node, 'hide_title'));
        $section->setHideAuthor($this->booleanAttribute($node, 'hide_author'));
        $section->setIsInactive($this->booleanAttribute($node, 'inactive'));
        $section->setAbstractWordCount($this->integerAttribute($node, 'abstract_word_count'));
        $reviewFormReference = trim($node->getAttribute('review_form_ref'));
        if ($reviewFormReference !== '') {
            $reviewFormMap = $deployment->getReferenceMap('review_form');
            if (!isset($reviewFormMap[$reviewFormReference])) {
                throw new \InvalidArgumentException('Unknown review form reference in section');
            }
            $section->setReviewFormId($reviewFormMap[$reviewFormReference]);
        }
        $this->applyLocalized($node, 'title', [$section, 'setTitle'], true);
        $this->applyLocalized($node, 'abbrev', [$section, 'setAbbrev'], true);
        $this->applyLocalized($node, 'policy', [$section, 'setPolicy'], false);
        $id = Repo::section()->add($section);
        $deployment->mapReference('section', $sourceReference, $id);
        return $section;
    }
}
