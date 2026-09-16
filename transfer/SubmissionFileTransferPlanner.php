<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\transfer;

use InvalidArgumentException;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileTransferPlanner
{
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
                    throw new InvalidArgumentException(__(
                        'plugins.importexport.fullJournal.error.submissionFileReferencesMissingSourceSubmissionFile',
                        [
                            'id' => $id,
                            'sourceId' => $sourceId,
                        ]
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
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.submissionFileDependencyCycleDetectedAmongSourceIds',
                    [
                        'sourceIds' => implode(', ', array_keys($pending)),
                    ]
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
