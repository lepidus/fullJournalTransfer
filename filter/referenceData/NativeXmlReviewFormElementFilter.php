<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\referenceData;

use DOMElement;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\reviewForm\ReviewFormElement;

class NativeXmlReviewFormElementFilter extends NativeImportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function getPluralElementName()
    {
        return 'review_form_elements';
    }

    public function getSingularElementName()
    {
        return 'review_form_element';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $reviewFormId = $deployment->getCurrentReviewFormId();
        if (!$reviewFormId) {
            throw new InvalidArgumentException('A review form element requires an imported review form');
        }
        $sourceReference = $this->sourceReference(
            $node,
            $deployment->getReferenceMap('review_form_element')
        );
        $elementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $element = $elementDao->newDataObject();
        $element->setReviewFormId($reviewFormId);
        $element->setSequence($this->floatAttribute($node, 'sequence'));
        $elementType = $this->integerAttribute($node, 'element_type');
        if (!in_array($elementType, [
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX,
        ], true)) {
            throw new InvalidArgumentException('Invalid review form element type');
        }
        $element->setElementType($elementType);
        $element->setRequired($this->booleanAttribute($node, 'required'));
        $element->setIncluded($this->booleanAttribute($node, 'included'));
        $this->applyLocalized($node, 'question', [$element, 'setQuestion'], true);
        $this->applyLocalized($node, 'description', [$element, 'setDescription'], false);
        foreach ($this->children($node, 'possible_responses') as $responsesNode) {
            $locale = $this->localeAttribute($responsesNode);
            $responses = array_map(
                static fn (DOMElement $response): string => $response->textContent,
                $this->children($responsesNode, 'response')
            );
            $element->setPossibleResponses($responses, $locale);
        }
        $id = $elementDao->insertObject($element);
        $deployment->mapReference('review_form_element', $sourceReference, $id);
        return $element;
    }
}
