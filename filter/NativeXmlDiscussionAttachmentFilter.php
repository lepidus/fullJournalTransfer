<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\HistoricalDiscussionPersistenceAdapter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\core\PKPApplication;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\submissionFile\SubmissionFile;

class NativeXmlDiscussionAttachmentFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'discussion_attachments';
    }

    public function getSingularElementName()
    {
        return 'discussion_attachment';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $sourceReference = $this->required($node, 'source_ref');
        $noteId = $deployment->requireReference('discussion_note', $this->required($node, 'note_ref'));
        $submissionFileId = $deployment->requireReference(
            'submission_file',
            $this->required($node, 'submission_file_ref')
        );
        $note = DB::table('notes')->where('note_id', $noteId)->first();
        $discussion = $note ? DB::table('queries')->where('query_id', $note->assoc_id)->first() : null;
        $submissionFile = Repo::submissionFile()->get($submissionFileId);
        if (!$note || (int) $note->assoc_type !== PKPApplication::ASSOC_TYPE_QUERY || !$discussion || !$submissionFile
            || (int) $submissionFile->getData('submissionId') !== (int) $discussion->assoc_id
            || !in_array($submissionFile->getData('fileStage'), [
                SubmissionFile::SUBMISSION_FILE_NOTE,
                SubmissionFile::SUBMISSION_FILE_QUERY,
            ], true)
        ) {
            throw new InvalidArgumentException('Discussion attachment does not belong to its note submission');
        }
        (new HistoricalDiscussionPersistenceAdapter())->attachFile($submissionFileId, $noteId);
        $deployment->mapReference('discussion_attachment', $sourceReference, $submissionFileId);
        return Repo::submissionFile()->get($submissionFileId);
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException('Missing discussion attachment value: ' . $attribute);
        }
        return $value;
    }
}
