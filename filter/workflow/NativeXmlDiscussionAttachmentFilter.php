<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\workflow;

use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\persistence\HistoricalDiscussionPersistenceAdapter;
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
            throw new InvalidArgumentException(sprintf(
                'Discussion attachment source_ref "%s" with submission_file_ref "%s" does not belong to '
                    . 'note_ref "%s" at line %d',
                $sourceReference,
                $node->getAttribute('submission_file_ref'),
                $node->getAttribute('note_ref'),
                $node->getLineNo()
            ));
        }
        (new HistoricalDiscussionPersistenceAdapter())->attachFile($submissionFileId, $noteId);
        $deployment->mapReference('discussion_attachment', $sourceReference, $submissionFileId);
        return Repo::submissionFile()->get($submissionFileId);
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing discussion attachment attribute "%s" for source_ref "%s" at line %d',
                $attribute,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }
}
