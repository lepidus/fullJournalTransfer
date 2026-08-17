<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class ReviewCommentNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$comments)
    {
        if (!is_array($comments)) {
            throw new InvalidArgumentException('Expected review comments for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'review_comments');
        $document->appendChild($root);
        foreach ($comments as $comment) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'review_comment');
            $node->setAttribute('review_ref', (string) $comment->assoc_id);
            $node->setAttribute('author_ref', (string) $comment->author_id);
            $node->setAttribute('role_id', (string) $comment->role_id);
            $node->setAttribute('title', (string) $comment->comment_title);
            $node->setAttribute('date_posted', (string) $comment->date_posted);
            $node->setAttribute('date_modified', (string) ($comment->date_modified ?: $comment->date_posted));
            $node->setAttribute('viewable', $comment->viewable ? 'true' : 'false');
            $node->appendChild($document->createTextNode((string) $comment->comments));
            $root->appendChild($node);
        }
        return $document;
    }
}
