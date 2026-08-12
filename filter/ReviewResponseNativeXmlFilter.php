<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class ReviewResponseNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$responses)
    {
        if (!is_array($responses)) {
            throw new InvalidArgumentException('Expected review responses for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'review_responses');
        $document->appendChild($root);
        foreach ($responses as $response) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'review_response');
            $node->setAttribute('review_ref', (string) $response->review_id);
            $node->setAttribute('element_ref', (string) $response->review_form_element_id);
            $node->setAttribute('type', (string) $response->response_type);
            $node->appendChild($document->createTextNode((string) $response->response_value));
            $root->appendChild($node);
        }
        return $document;
    }
}
