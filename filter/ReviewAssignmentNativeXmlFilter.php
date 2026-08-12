<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class ReviewAssignmentNativeXmlFilter extends NativeExportFilter
{
    private const FIELDS = [
        'stage_id', 'review_method', 'round', 'step', 'competing_interests', 'recommendation', 'declined',
        'cancelled', 'date_assigned', 'date_notified', 'date_confirmed', 'date_completed', 'date_acknowledged',
        'date_due', 'date_response_due', 'quality', 'date_rated', 'last_modified', 'date_reminded',
        'reminder_was_automatic', 'considered', 'request_resent',
    ];

    public function &process(&$assignments)
    {
        if (!is_array($assignments)) {
            throw new InvalidArgumentException('Expected review assignments for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'review_assignments');
        $document->appendChild($root);
        foreach ($assignments as $assignment) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'review_assignment');
            $node->setAttribute('source_ref', (string) $assignment->review_id);
            $node->setAttribute('submission_ref', (string) $assignment->submission_id);
            $node->setAttribute('reviewer_ref', (string) $assignment->reviewer_id);
            if ($assignment->review_form_id !== null) {
                $node->setAttribute('review_form_ref', (string) $assignment->review_form_id);
            }
            foreach (self::FIELDS as $field) {
                if ($assignment->{$field} !== null) {
                    $node->setAttribute($field, (string) $assignment->{$field});
                }
            }
            $responses = DB::table('review_form_responses')
                ->where('review_id', $assignment->review_id)
                ->orderBy('review_form_response_id')
                ->get();
            foreach ($responses as $response) {
                $responseNode = $document->createElementNS('http://pkp.sfu.ca', 'review_response');
                $responseNode->setAttribute('element_ref', (string) $response->review_form_element_id);
                $responseNode->setAttribute('type', (string) $response->response_type);
                $responseNode->appendChild($document->createTextNode((string) $response->response_value));
                $node->appendChild($responseNode);
            }
            $comments = DB::table('submission_comments')
                ->where('submission_id', $assignment->submission_id)
                ->where('assoc_id', $assignment->review_id)
                ->where('comment_type', 1)
                ->orderBy('date_posted')
                ->orderBy('comment_id')
                ->get();
            foreach ($comments as $comment) {
                $commentNode = $document->createElementNS('http://pkp.sfu.ca', 'review_comment');
                $commentNode->setAttribute('author_ref', (string) $comment->author_id);
                $commentNode->setAttribute('role_id', (string) $comment->role_id);
                $commentNode->setAttribute('title', (string) $comment->comment_title);
                $commentNode->setAttribute('date_posted', (string) $comment->date_posted);
                $commentNode->setAttribute('date_modified', (string) $comment->date_modified);
                $commentNode->setAttribute('viewable', $comment->viewable ? 'true' : 'false');
                $commentNode->appendChild($document->createTextNode((string) $comment->comments));
                $node->appendChild($commentNode);
            }
            $files = DB::table('review_files')
                ->where('review_id', $assignment->review_id)
                ->orderBy('review_file_id')
                ->get();
            foreach ($files as $file) {
                $fileNode = $document->createElementNS('http://pkp.sfu.ca', 'review_file');
                $fileNode->setAttribute('submission_file_ref', (string) $file->submission_file_id);
                $node->appendChild($fileNode);
            }
            $root->appendChild($node);
        }
        return $document;
    }
}
