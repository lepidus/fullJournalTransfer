<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class DiscussionParticipantNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$participants)
    {
        if (!is_array($participants)) {
            throw new InvalidArgumentException('Expected discussion participants for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'discussion_participants');
        $document->appendChild($root);
        foreach ($participants as $participant) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'discussion_participant');
            $node->setAttribute('discussion_ref', (string) $participant->query_id);
            $node->setAttribute('user_ref', (string) $participant->user_id);
            $root->appendChild($node);
        }
        return $document;
    }
}
