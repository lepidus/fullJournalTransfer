<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlReviewFileFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'review_files';
    }

    public function getSingularElementName()
    {
        return 'review_file';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $reviewId = $deployment->requireReference('review_assignment', $this->required($node, 'review_ref'));
        $submissionId = (int) DB::table('review_assignments')
            ->where('review_id', $reviewId)->value('submission_id');
        $submissionFileId = $deployment->requireReference(
            'submission_file',
            $this->required($node, 'submission_file_ref')
        );
        $this->requireSubmissionFile($submissionFileId, $submissionId);
        DB::table('review_files')->insert([
            'review_id' => $reviewId,
            'submission_file_id' => $submissionFileId,
        ]);
        return Repo::submissionFile()->get($submissionFileId);
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException('Missing review file value: ' . $attribute);
        }
        return $value;
    }

    private function requireSubmissionFile(int $submissionFileId, int $submissionId): void
    {
        if ((int) DB::table('submission_files')->where('submission_file_id', $submissionFileId)
            ->value('submission_id') !== $submissionId
        ) {
            throw new InvalidArgumentException('Review file does not belong to the review submission');
        }
    }
}
