<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use DOMElement;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class ReviewFormElementNativeXmlFilter extends NativeExportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function &process(&$elements)
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $container = $document->createElementNS('http://pkp.sfu.ca', 'review_form_elements');
        $document->appendChild($container);
        foreach ($elements as $element) {
            $container->appendChild($this->createReviewFormElementNode($document, $element));
        }
        return $document;
    }

    public function createReviewFormElementNode(DOMDocument $document, $element): DOMElement
    {
        $node = $document->createElementNS('http://pkp.sfu.ca', 'review_form_element');
        $node->setAttribute('source_ref', (string) $element->getId());
        $node->setAttribute('sequence', (string) $element->getSequence());
        $node->setAttribute('element_type', (string) $element->getElementType());
        $node->setAttribute('required', $element->getRequired() ? 'true' : 'false');
        $node->setAttribute('included', $element->getIncluded() ? 'true' : 'false');
        $this->createLocalizedNodes($document, $node, 'question', $element->getQuestion(null));
        $this->createLocalizedNodes($document, $node, 'description', $element->getDescription(null));
        $this->addPossibleResponses($document, $node, $element);
        return $node;
    }

    public function addPossibleResponses(DOMDocument $document, DOMElement $node, $element): void
    {
        foreach ((array) $element->getPossibleResponses(null) as $locale => $responses) {
            $responsesNode = $document->createElementNS('http://pkp.sfu.ca', 'possible_responses');
            $responsesNode->setAttribute('locale', (string) $locale);
            foreach ((array) $responses as $response) {
                $responseNode = $document->createElementNS('http://pkp.sfu.ca', 'response');
                $responseNode->appendChild($document->createTextNode((string) $response));
                $responsesNode->appendChild($responseNode);
            }
            $node->appendChild($responsesNode);
        }
    }
}
