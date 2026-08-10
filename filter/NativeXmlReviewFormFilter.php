<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use APP\journal\Journal;
use DOMElement;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\reviewForm\ReviewFormElement;

class NativeXmlReviewFormFilter extends NativeImportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function importAll(DOMElement $root, Journal $context, array &$formMap, array &$elementMap): void
    {
        $formDao = DAORegistry::getDAO('ReviewFormDAO');
        $elementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        foreach ($this->children($this->requiredContainer($root, 'review_forms'), 'review_form') as $formNode) {
            $sourceReference = $this->sourceReference($formNode, $formMap);
            $form = $formDao->newDataObject();
            $form->setAssocType(Application::ASSOC_TYPE_JOURNAL);
            $form->setAssocId($context->getId());
            $form->setSequence($this->floatAttribute($formNode, 'sequence'));
            $form->setActive($this->booleanAttribute($formNode, 'active') ? 1 : 0);
            $this->applyLocalized($formNode, 'title', [$form, 'setTitle'], true);
            $this->applyLocalized($formNode, 'description', [$form, 'setDescription'], false);
            $formId = $formDao->insertObject($form);
            $formMap[$sourceReference] = $formId;
            $elements = $this->requiredContainer($formNode, 'review_form_elements');
            foreach ($this->children($elements, 'review_form_element') as $elementNode) {
                $elementReference = $this->sourceReference($elementNode, $elementMap);
                $element = $elementDao->newDataObject();
                $element->setReviewFormId($formId);
                $element->setSequence($this->floatAttribute($elementNode, 'sequence'));
                $elementType = $this->integerAttribute($elementNode, 'element_type');
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
                $element->setRequired($this->booleanAttribute($elementNode, 'required'));
                $element->setIncluded($this->booleanAttribute($elementNode, 'included'));
                $this->applyLocalized($elementNode, 'question', [$element, 'setQuestion'], true);
                $this->applyLocalized($elementNode, 'description', [$element, 'setDescription'], false);
                foreach ($this->children($elementNode, 'possible_responses') as $responsesNode) {
                    $locale = $this->localeAttribute($responsesNode);
                    $responses = array_map(
                        static fn (DOMElement $response): string => $response->textContent,
                        $this->children($responsesNode, 'response')
                    );
                    $element->setPossibleResponses($responses, $locale);
                }
                $elementMap[$elementReference] = $elementDao->insertObject($element);
            }
        }
    }
}
