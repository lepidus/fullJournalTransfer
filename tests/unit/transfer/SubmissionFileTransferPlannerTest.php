<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\transfer;

use APP\plugins\importexport\fullJournalTransfer\transfer\SubmissionFileTransferPlanner;
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
        $this->expectExceptionMessage('Submission file 2 references missing source submission file 99');

        (new SubmissionFileTransferPlanner())->partition([
            $this->submissionFile(2, SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT, 99),
        ]);
    }

    public function testItReportsEveryFileInADependencyCycle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Submission file dependency cycle detected among source IDs: 2, 3');

        (new SubmissionFileTransferPlanner())->partition([
            $this->submissionFile(2, SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT, 3),
            $this->submissionFile(3, SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT, 2),
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

    private function ids(array $submissionFiles): array
    {
        return array_map(fn (SubmissionFile $submissionFile): int => (int) $submissionFile->getId(), $submissionFiles);
    }
}
