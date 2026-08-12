<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class EditorialDecisionNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$decisions)
    {
        if (!is_array($decisions)) {
            throw new InvalidArgumentException('Expected editorial decisions for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'editorial_decisions');
        $document->appendChild($root);
        foreach ($decisions as $decision) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'editorial_decision');
            $node->setAttribute('source_ref', (string) $decision->edit_decision_id);
            $node->setAttribute('submission_ref', (string) $decision->submission_id);
            $node->setAttribute('editor_ref', (string) $decision->editor_id);
            $node->setAttribute('decision', (string) $decision->decision);
            $node->setAttribute('date_decided', (string) $decision->date_decided);
            $node->setAttribute('stage_id', (string) $decision->stage_id);
            if ($decision->review_round_id !== null) {
                $node->setAttribute('review_round_ref', (string) $decision->review_round_id);
                $node->setAttribute('round', (string) $decision->round);
            }
            $root->appendChild($node);
        }
        return $document;
    }
}
