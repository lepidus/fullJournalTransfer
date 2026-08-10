<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class ReviewFormNativeXmlFilter extends NativeExportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function append(DOMDocument $document, DOMElement $root, Journal $context): void
    {
        $formDao = DAORegistry::getDAO('ReviewFormDAO');
        $elementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $container = $document->createElementNS('http://pkp.sfu.ca', 'review_forms');
        foreach ($formDao->getByAssocId(Application::ASSOC_TYPE_JOURNAL, $context->getId())->toArray() as $form) {
            $formNode = $document->createElementNS('http://pkp.sfu.ca', 'review_form');
            $formNode->setAttribute('source_ref', (string) $form->getId());
            $formNode->setAttribute('sequence', (string) $form->getSequence());
            $formNode->setAttribute('active', $form->getActive() ? 'true' : 'false');
            $this->appendLocalized($document, $formNode, 'title', $form->getTitle(null));
            $this->appendLocalized($document, $formNode, 'description', $form->getDescription(null));
            $elementsNode = $document->createElementNS('http://pkp.sfu.ca', 'review_form_elements');
            foreach ($elementDao->getByReviewFormId($form->getId())->toArray() as $element) {
                $elementNode = $document->createElementNS('http://pkp.sfu.ca', 'review_form_element');
                $elementNode->setAttribute('source_ref', (string) $element->getId());
                $elementNode->setAttribute('sequence', (string) $element->getSequence());
                $elementNode->setAttribute('element_type', (string) $element->getElementType());
                $elementNode->setAttribute('required', $element->getRequired() ? 'true' : 'false');
                $elementNode->setAttribute('included', $element->getIncluded() ? 'true' : 'false');
                $this->appendLocalized($document, $elementNode, 'question', $element->getQuestion(null));
                $this->appendLocalized($document, $elementNode, 'description', $element->getDescription(null));
                foreach ((array) $element->getPossibleResponses(null) as $locale => $responses) {
                    $responsesNode = $document->createElementNS('http://pkp.sfu.ca', 'possible_responses');
                    $responsesNode->setAttribute('locale', (string) $locale);
                    foreach ((array) $responses as $response) {
                        $responseNode = $document->createElementNS('http://pkp.sfu.ca', 'response');
                        $responseNode->appendChild($document->createTextNode((string) $response));
                        $responsesNode->appendChild($responseNode);
                    }
                    $elementNode->appendChild($responsesNode);
                }
                $elementsNode->appendChild($elementNode);
            }
            $formNode->appendChild($elementsNode);
            $container->appendChild($formNode);
        }
        $root->appendChild($container);
    }
}
