<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\workflow;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class DiscussionAttachmentNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$attachments)
    {
        if (!is_array($attachments)) {
            throw new InvalidArgumentException('Expected discussion attachments for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'discussion_attachments');
        $document->appendChild($root);
        foreach ($attachments as $attachment) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'discussion_attachment');
            $node->setAttribute('source_ref', (string) $attachment->getId());
            $node->setAttribute('note_ref', (string) $attachment->getData('assocId'));
            $node->setAttribute('submission_file_ref', (string) $attachment->getId());
            $root->appendChild($node);
        }
        return $document;
    }
}
