<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\plugins\importexport\fullJournalTransfer\SubmissionFileTransferPlanner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileTransferPlannerTest extends TestCase
{
    public function testItDefersReviewRevisionsAndTheirDependentFiles(): void
    {
        $native = $this->submissionFile(1, SubmissionFile::SUBMISSION_FILE_SUBMISSION);
        $revision = $this->submissionFile(2, SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION);
        $dependent = $this->submissionFile(3, SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT, 2);

        $partition = (new SubmissionFileTransferPlanner())->partition([$dependent, $native, $revision]);

        $this->assertSame([1], $this->ids($partition['native']));
        $this->assertSame([2, 3], $this->ids($partition['workflow']));
    }

    public function testItRejectsAMissingSourceFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A source submission file is missing from the journal export');

        (new SubmissionFileTransferPlanner())->partition([
            $this->submissionFile(2, SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT, 99),
        ]);
    }

    private function submissionFile(int $id, int $stage, int $sourceId = 0): SubmissionFile
    {
        $submissionFile = $this->createMock(SubmissionFile::class);
        $submissionFile->method('getId')->willReturn($id);
        $submissionFile->method('getFileStage')->willReturn($stage);
        $submissionFile->method('getData')->willReturnCallback(
            fn (string $name) => $name === 'sourceSubmissionFileId' ? $sourceId : null
        );
        return $submissionFile;
    }

    /** @param list<SubmissionFile> $submissionFiles */
    private function ids(array $submissionFiles): array
    {
        return array_map(fn (SubmissionFile $submissionFile): int => (int) $submissionFile->getId(), $submissionFiles);
    }
}
