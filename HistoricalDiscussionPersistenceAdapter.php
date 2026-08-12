<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\note\Note;
use PKP\query\Query;

class HistoricalDiscussionPersistenceAdapter
{
    public function insertDiscussion(array $data): Query
    {
        $id = DB::table('queries')->insertGetId([
            'assoc_type' => Application::ASSOC_TYPE_SUBMISSION,
            'assoc_id' => $data['submission_id'],
            'stage_id' => $data['stage_id'],
            'closed' => $data['closed'],
            'seq' => $data['sequence'],
        ], 'query_id');
        return DAORegistry::getDAO('QueryDAO')->getById((int) $id);
    }

    public function insertParticipant(int $discussionId, int $userId): void
    {
        DB::table('query_participants')->insert([
            'query_id' => $discussionId,
            'user_id' => $userId,
        ]);
    }

    public function insertNote(array $data): Note
    {
        $id = DB::table('notes')->insertGetId([
            'assoc_type' => Application::ASSOC_TYPE_QUERY,
            'assoc_id' => $data['discussion_id'],
            'user_id' => $data['user_id'],
            'date_created' => $data['date_created'],
            'date_modified' => $data['date_modified'],
            'title' => $data['title'],
            'contents' => $data['contents'],
        ], 'note_id');
        return DAORegistry::getDAO('NoteDAO')->getById((int) $id);
    }

    public function attachFile(int $submissionFileId, int $noteId): void
    {
        DB::table('submission_files')->where('submission_file_id', $submissionFileId)->update([
            'assoc_type' => Application::ASSOC_TYPE_NOTE,
            'assoc_id' => $noteId,
        ]);
    }
}
