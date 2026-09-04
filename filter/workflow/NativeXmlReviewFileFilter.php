<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\workflow;

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
        $this->requireSubmissionFile($node, $submissionFileId, $submissionId);
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
            throw new InvalidArgumentException(sprintf(
                'Missing review file attribute "%s" for review_ref "%s" at line %d',
                $attribute,
                $node->getAttribute('review_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function requireSubmissionFile($node, int $submissionFileId, int $submissionId): void
    {
        if ((int) DB::table('submission_files')->where('submission_file_id', $submissionFileId)
            ->value('submission_id') !== $submissionId
        ) {
            throw new InvalidArgumentException(sprintf(
                'Review submission_file_ref "%s" does not belong to review_ref "%s" at line %d',
                $node->getAttribute('submission_file_ref'),
                $node->getAttribute('review_ref'),
                $node->getLineNo()
            ));
        }
    }
}
