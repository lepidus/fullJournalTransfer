<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use InvalidArgumentException;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileTransferPlanner
{
    /**
     * @param iterable<SubmissionFile> $submissionFiles
     *
     * @return array{native: list<SubmissionFile>, workflow: list<SubmissionFile>}
     */
    public function partition(iterable $submissionFiles): array
    {
        $pending = [];
        foreach ($submissionFiles as $submissionFile) {
            $pending[(int) $submissionFile->getId()] = $submissionFile;
        }
        $allIds = array_fill_keys(array_keys($pending), true);
        $workflowIds = [];
        $native = [];
        $workflow = [];

        while ($pending !== []) {
            $progress = false;
            foreach ($pending as $id => $submissionFile) {
                $sourceId = (int) $submissionFile->getData('sourceSubmissionFileId');
                if ($sourceId && !isset($allIds[$sourceId])) {
                    throw new InvalidArgumentException(sprintf(
                        'Submission file %d references missing source submission file %d',
                        $id,
                        $sourceId
                    ));
                }
                if ($sourceId && isset($pending[$sourceId])) {
                    continue;
                }
                $deferred = $this->requiresReviewRound($submissionFile)
                    || ($sourceId && isset($workflowIds[$sourceId]));
                if ($deferred) {
                    $workflow[] = $submissionFile;
                    $workflowIds[$id] = true;
                } else {
                    $native[] = $submissionFile;
                }
                unset($pending[$id]);
                $progress = true;
            }
            if (!$progress) {
                throw new InvalidArgumentException(sprintf(
                    'Submission file dependency cycle detected among source IDs: %s',
                    implode(', ', array_keys($pending))
                ));
            }
        }

        return ['native' => $native, 'workflow' => $workflow];
    }

    public function requiresReviewRound(SubmissionFile $submissionFile): bool
    {
        return in_array($submissionFile->getFileStage(), [
            SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
        ], true);
    }
}
