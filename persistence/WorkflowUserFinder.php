<?php

/**
 * Copyright (c) 2014-2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\persistence;

use APP\core\Application;
use Illuminate\Support\Facades\DB;

class WorkflowUserFinder
{
    public function findIds(int $contextId): array
    {
        $userIds = [];
        $queries = [
            DB::table('stage_assignments as assignment')
                ->join('submissions as submission', 'submission.submission_id', '=', 'assignment.submission_id')
                ->where('submission.context_id', $contextId)
                ->pluck('assignment.user_id'),
            DB::table('review_assignments as assignment')
                ->join('review_rounds as round', function ($join): void {
                    $join->on('round.review_round_id', '=', 'assignment.review_round_id')
                        ->on('round.submission_id', '=', 'assignment.submission_id');
                })
                ->join('submissions as submission', 'submission.submission_id', '=', 'round.submission_id')
                ->where('submission.context_id', $contextId)
                ->pluck('assignment.reviewer_id'),
            DB::table('submission_comments as comment')
                ->join('review_assignments as assignment', function ($join): void {
                    $join->on('assignment.review_id', '=', 'comment.assoc_id')
                        ->on('assignment.submission_id', '=', 'comment.submission_id');
                })
                ->join('review_rounds as round', function ($join): void {
                    $join->on('round.review_round_id', '=', 'assignment.review_round_id')
                        ->on('round.submission_id', '=', 'assignment.submission_id');
                })
                ->join('submissions as submission', 'submission.submission_id', '=', 'round.submission_id')
                ->where('comment.comment_type', 1)
                ->where('submission.context_id', $contextId)
                ->pluck('comment.author_id'),
            DB::table('query_participants as participant')
                ->join('queries as discussion', 'discussion.query_id', '=', 'participant.query_id')
                ->join('submissions as submission', 'submission.submission_id', '=', 'discussion.assoc_id')
                ->where('discussion.assoc_type', Application::ASSOC_TYPE_SUBMISSION)
                ->where('submission.context_id', $contextId)
                ->pluck('participant.user_id'),
            DB::table('notes as note')
                ->join('queries as discussion', 'discussion.query_id', '=', 'note.assoc_id')
                ->join('submissions as submission', 'submission.submission_id', '=', 'discussion.assoc_id')
                ->where('note.assoc_type', Application::ASSOC_TYPE_QUERY)
                ->where('discussion.assoc_type', Application::ASSOC_TYPE_SUBMISSION)
                ->where('submission.context_id', $contextId)
                ->pluck('note.user_id'),
            DB::table('edit_decisions as decision')
                ->join('submissions as submission', 'submission.submission_id', '=', 'decision.submission_id')
                ->where('submission.context_id', $contextId)
                ->pluck('decision.editor_id'),
        ];
        foreach ($queries as $queryUserIds) {
            foreach ($queryUserIds as $userId) {
                if ((int) $userId > 0) {
                    $userIds[(int) $userId] = true;
                }
            }
        }
        $userIds = array_keys($userIds);
        sort($userIds, SORT_NUMERIC);
        return $userIds;
    }
}
