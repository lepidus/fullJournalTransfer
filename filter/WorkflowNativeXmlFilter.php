<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\SubmissionFileTransferPlanner;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class WorkflowNativeXmlFilter extends NativeExportFilter
{
    private const REVIEW_ASSIGNMENT_FIELDS = [
        'stage_id', 'review_method', 'round', 'step', 'competing_interests', 'recommendation', 'declined',
        'cancelled', 'date_assigned', 'date_notified', 'date_confirmed', 'date_completed', 'date_acknowledged',
        'date_due', 'date_response_due', 'quality', 'date_rated', 'last_modified', 'date_reminded',
        'reminder_was_automatic', 'considered', 'request_resent',
    ];

    public function &process(&$context)
    {
        if (!$context instanceof Journal) {
            throw new InvalidArgumentException('Expected a journal for workflow export');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'workflow_history');
        $document->appendChild($root);

        $this->appendStageAssignments($document, $root, (int) $context->getId());
        $this->appendReviewRounds($document, $root, (int) $context->getId());
        $this->appendWorkflowFiles($document, $root, (int) $context->getId());
        $this->appendDiscussions($document, $root, (int) $context->getId());
        $this->appendEditorialDecisions($document, $root, (int) $context->getId());

        return $document;
    }

    private function appendWorkflowFiles(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'workflow_files');
        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();
        $planner = new SubmissionFileTransferPlanner();
        foreach ($submissions as $submission) {
            $submissionFiles = Repo::submissionFile()->getCollector()
                ->filterBySubmissionIds([(int) $submission->getId()])
                ->includeDependentFiles()
                ->getMany();
            $partition = $planner->partition($submissionFiles);
            foreach ($partition['workflow'] as $submissionFile) {
                $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'workflow_file');
                $node->setAttribute('submission_ref', (string) $submission->getId());
                if ($planner->requiresReviewRound($submissionFile)) {
                    $reviewRoundId = $this->reviewRoundId($submissionFile, (int) $submission->getId());
                    $node->setAttribute('review_round_ref', (string) $reviewRoundId);
                }
                $filter = PKPImportExportFilter::getFilter(
                    'submission-file=>full-journal-native-xml',
                    $this->getDeployment(),
                    array_merge($this->opts, ['no-embed' => true])
                );
                $fileDocument = $filter->execute($submissionFile, true);
                if (!$fileDocument || !$fileDocument->documentElement instanceof DOMElement) {
                    throw new InvalidArgumentException('A workflow submission file could not be exported');
                }
                $node->appendChild($document->importNode($fileDocument->documentElement, true));
                $root->appendChild($node);
            }
        }
        $parent->appendChild($root);
    }

    private function reviewRoundId($submissionFile, int $submissionId): int
    {
        if ((int) $submissionFile->getData('assocType') !== Application::ASSOC_TYPE_REVIEW_ROUND) {
            throw new InvalidArgumentException('A review revision has no review round association');
        }
        $reviewRoundId = (int) $submissionFile->getData('assocId');
        $reviewRound = DB::table('review_rounds')->where('review_round_id', $reviewRoundId)->first();
        if (!$reviewRound || (int) $reviewRound->submission_id !== $submissionId) {
            throw new InvalidArgumentException('A review revision references an invalid review round');
        }
        return $reviewRoundId;
    }

    private function appendStageAssignments(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'stage_assignments');
        $assignments = DB::table('stage_assignments as assignment')
            ->join('submissions as submission', 'submission.submission_id', '=', 'assignment.submission_id')
            ->where('submission.context_id', $contextId)
            ->orderBy('assignment.stage_assignment_id')
            ->select('assignment.*')
            ->get();
        foreach ($assignments as $assignment) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'stage_assignment');
            $node->setAttribute('source_ref', (string) $assignment->stage_assignment_id);
            $node->setAttribute('submission_ref', (string) $assignment->submission_id);
            $node->setAttribute('user_group_ref', (string) $assignment->user_group_id);
            $node->setAttribute('user_ref', (string) $assignment->user_id);
            $node->setAttribute('date_assigned', (string) $assignment->date_assigned);
            $node->setAttribute('recommend_only', $assignment->recommend_only ? 'true' : 'false');
            $node->setAttribute('can_change_metadata', $assignment->can_change_metadata ? 'true' : 'false');
            $root->appendChild($node);
        }
        $parent->appendChild($root);
    }

    private function appendReviewRounds(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'review_rounds');
        $rounds = DB::table('review_rounds as review_round')
            ->join('submissions as submission', 'submission.submission_id', '=', 'review_round.submission_id')
            ->where('submission.context_id', $contextId)
            ->orderBy('review_round.submission_id')
            ->orderBy('review_round.stage_id')
            ->orderBy('review_round.round')
            ->select('review_round.*')
            ->get();
        foreach ($rounds as $round) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'review_round');
            $node->setAttribute('source_ref', (string) $round->review_round_id);
            $node->setAttribute('submission_ref', (string) $round->submission_id);
            $node->setAttribute('stage_id', (string) $round->stage_id);
            $node->setAttribute('round', (string) $round->round);
            $node->setAttribute('status', (string) $round->status);
            $this->appendReviewAssignments($document, $node, (int) $round->review_round_id);
            $this->appendReviewRoundFiles($document, $node, (int) $round->review_round_id);
            $root->appendChild($node);
        }
        $parent->appendChild($root);
    }

    private function appendReviewAssignments(DOMDocument $document, DOMElement $parent, int $reviewRoundId): void
    {
        $assignments = DB::table('review_assignments')
            ->where('review_round_id', $reviewRoundId)
            ->orderBy('review_id')
            ->get();
        foreach ($assignments as $assignment) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'review_assignment');
            $node->setAttribute('source_ref', (string) $assignment->review_id);
            $node->setAttribute('submission_ref', (string) $assignment->submission_id);
            $node->setAttribute('reviewer_ref', (string) $assignment->reviewer_id);
            $node->setAttribute('review_round_ref', (string) $reviewRoundId);
            if ($assignment->review_form_id !== null) {
                $node->setAttribute('review_form_ref', (string) $assignment->review_form_id);
            }
            foreach (self::REVIEW_ASSIGNMENT_FIELDS as $field) {
                if ($assignment->{$field} !== null) {
                    $node->setAttribute($field, (string) $assignment->{$field});
                }
            }
            $this->appendReviewResponses($document, $node, (int) $assignment->review_id);
            $this->appendReviewComments(
                $document,
                $node,
                (int) $assignment->submission_id,
                (int) $assignment->review_id
            );
            $this->appendReviewFiles($document, $node, (int) $assignment->review_id);
            $parent->appendChild($node);
        }
    }

    private function appendReviewResponses(DOMDocument $document, DOMElement $parent, int $reviewId): void
    {
        $responses = DB::table('review_form_responses')
            ->where('review_id', $reviewId)
            ->orderBy('review_form_response_id')
            ->get();
        foreach ($responses as $response) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'review_response');
            $node->setAttribute('review_ref', (string) $response->review_id);
            $node->setAttribute('element_ref', (string) $response->review_form_element_id);
            $node->setAttribute('type', (string) $response->response_type);
            $node->setAttribute('is_null', $response->response_value === null ? 'true' : 'false');
            if ($response->response_value !== null) {
                $node->appendChild($document->createTextNode((string) $response->response_value));
            }
            $parent->appendChild($node);
        }
    }

    private function appendReviewComments(
        DOMDocument $document,
        DOMElement $parent,
        int $submissionId,
        int $reviewId
    ): void {
        $comments = DB::table('submission_comments')
            ->where('submission_id', $submissionId)
            ->where('assoc_id', $reviewId)
            ->where('comment_type', 1)
            ->orderBy('date_posted')
            ->orderBy('comment_id')
            ->get();
        foreach ($comments as $comment) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'review_comment');
            $node->setAttribute('review_ref', (string) $comment->assoc_id);
            $node->setAttribute('author_ref', (string) $comment->author_id);
            $node->setAttribute('role_id', (string) $comment->role_id);
            $node->setAttribute('title', (string) $comment->comment_title);
            $node->setAttribute('date_posted', (string) $comment->date_posted);
            $node->setAttribute('date_modified', (string) ($comment->date_modified ?: $comment->date_posted));
            $node->setAttribute('viewable', $comment->viewable ? 'true' : 'false');
            $node->appendChild($document->createTextNode((string) $comment->comments));
            $parent->appendChild($node);
        }
    }

    private function appendReviewFiles(DOMDocument $document, DOMElement $parent, int $reviewId): void
    {
        $files = DB::table('review_files')->where('review_id', $reviewId)->orderBy('review_file_id')->get();
        foreach ($files as $file) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'review_file');
            $node->setAttribute('review_ref', (string) $file->review_id);
            $node->setAttribute('submission_file_ref', (string) $file->submission_file_id);
            $parent->appendChild($node);
        }
    }

    private function appendReviewRoundFiles(DOMDocument $document, DOMElement $parent, int $reviewRoundId): void
    {
        $files = DB::table('review_round_files')
            ->where('review_round_id', $reviewRoundId)
            ->orderBy('review_round_file_id')
            ->get();
        foreach ($files as $file) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'review_round_file');
            $node->setAttribute('review_round_ref', (string) $file->review_round_id);
            $node->setAttribute('submission_ref', (string) $file->submission_id);
            $node->setAttribute('submission_file_ref', (string) $file->submission_file_id);
            $node->setAttribute('stage_id', (string) $file->stage_id);
            $parent->appendChild($node);
        }
    }

    private function appendDiscussions(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'discussions');
        $discussions = DB::table('queries as discussion')
            ->join('submissions as submission', 'submission.submission_id', '=', 'discussion.assoc_id')
            ->where('discussion.assoc_type', Application::ASSOC_TYPE_SUBMISSION)
            ->where('submission.context_id', $contextId)
            ->orderBy('discussion.assoc_id')
            ->orderBy('discussion.stage_id')
            ->orderBy('discussion.seq')
            ->select('discussion.*')
            ->get();
        foreach ($discussions as $discussion) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'discussion');
            $node->setAttribute('source_ref', (string) $discussion->query_id);
            $node->setAttribute('submission_ref', (string) $discussion->assoc_id);
            $node->setAttribute('stage_id', (string) $discussion->stage_id);
            $node->setAttribute('closed', $discussion->closed ? 'true' : 'false');
            $node->setAttribute('sequence', (string) $discussion->seq);
            $this->appendDiscussionParticipants($document, $node, (int) $discussion->query_id);
            $this->appendDiscussionNotes($document, $node, (int) $discussion->query_id);
            $root->appendChild($node);
        }
        $parent->appendChild($root);
    }

    private function appendDiscussionParticipants(DOMDocument $document, DOMElement $parent, int $discussionId): void
    {
        $participants = DB::table('query_participants')
            ->where('query_id', $discussionId)
            ->orderBy('user_id')
            ->get();
        foreach ($participants as $participant) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'discussion_participant');
            $node->setAttribute('discussion_ref', (string) $participant->query_id);
            $node->setAttribute('user_ref', (string) $participant->user_id);
            $parent->appendChild($node);
        }
    }

    private function appendDiscussionNotes(DOMDocument $document, DOMElement $parent, int $discussionId): void
    {
        $notes = DB::table('notes')
            ->where('assoc_type', Application::ASSOC_TYPE_QUERY)
            ->where('assoc_id', $discussionId)
            ->orderBy('date_created')
            ->orderBy('note_id')
            ->get();
        foreach ($notes as $note) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'discussion_note');
            $node->setAttribute('source_ref', (string) $note->note_id);
            $node->setAttribute('discussion_ref', (string) $note->assoc_id);
            $node->setAttribute('user_ref', (string) $note->user_id);
            $node->setAttribute('date_created', (string) $note->date_created);
            if ($note->date_modified !== null) {
                $node->setAttribute('date_modified', (string) $note->date_modified);
            }
            $this->appendText($document, $node, 'title', $note->title);
            $this->appendText($document, $node, 'contents', $note->contents);
            $attachments = Repo::submissionFile()->getCollector()
                ->filterByAssoc(Application::ASSOC_TYPE_NOTE, [(int) $note->note_id])
                ->getMany()
                ->values()
                ->all();
            $attachmentFilter = PKPImportExportFilter::getFilter(
                'discussion-attachment=>full-journal-workflow-xml',
                $this->getDeployment()
            );
            $attachmentDocument = $attachmentFilter->execute($attachments);
            foreach ($attachmentDocument->documentElement->childNodes as $attachmentNode) {
                if ($attachmentNode instanceof DOMElement) {
                    $node->appendChild($document->importNode($attachmentNode, true));
                }
            }
            $parent->appendChild($node);
        }
    }

    private function appendText(DOMDocument $document, DOMElement $parent, string $name, ?string $value): void
    {
        $node = $document->createElementNS($this->getDeployment()->getNamespace(), $name);
        $node->appendChild($document->createTextNode($value ?? ''));
        $parent->appendChild($node);
    }

    private function appendEditorialDecisions(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'editorial_decisions');
        $decisions = DB::table('edit_decisions as decision')
            ->join('submissions as submission', 'submission.submission_id', '=', 'decision.submission_id')
            ->where('submission.context_id', $contextId)
            ->orderBy('decision.date_decided')
            ->orderBy('decision.edit_decision_id')
            ->select('decision.*')
            ->get();
        foreach ($decisions as $decision) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'editorial_decision');
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
        $parent->appendChild($root);
    }
}
