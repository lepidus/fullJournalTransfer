<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class StageAssignmentNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$assignments)
    {
        if (!is_array($assignments)) {
            throw new InvalidArgumentException('Expected stage assignments for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'stage_assignments');
        $document->appendChild($root);
        foreach ($assignments as $assignment) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'stage_assignment');
            $node->setAttribute('source_ref', (string) $assignment->stage_assignment_id);
            $node->setAttribute('submission_ref', (string) $assignment->submission_id);
            $node->setAttribute('user_group_ref', (string) $assignment->user_group_id);
            $node->setAttribute('user_ref', (string) $assignment->user_id);
            $node->setAttribute('date_assigned', (string) $assignment->date_assigned);
            $node->setAttribute('recommend_only', $assignment->recommend_only ? 'true' : 'false');
            $node->setAttribute('can_change_metadata', $assignment->can_change_metadata ? 'true' : 'false');
            $root->appendChild($node);
        }
        return $document;
    }
}
