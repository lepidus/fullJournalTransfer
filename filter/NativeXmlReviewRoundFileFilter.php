<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlReviewRoundFileFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'review_round_files';
    }

    public function getSingularElementName()
    {
        return 'review_round_file';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $submissionId = $deployment->requireReference('submission', $this->required($node, 'submission_ref'));
        $reviewRoundId = $deployment->requireReference(
            'review_round',
            $this->required($node, 'review_round_ref')
        );
        if ((int) DB::table('review_rounds')->where('review_round_id', $reviewRoundId)
            ->value('submission_id') !== $submissionId
        ) {
            throw new InvalidArgumentException('Review round file does not belong to the review submission');
        }
        $submissionFileId = $deployment->requireReference(
            'submission_file',
            $this->required($node, 'submission_file_ref')
        );
        if ((int) DB::table('submission_files')->where('submission_file_id', $submissionFileId)
            ->value('submission_id') !== $submissionId
        ) {
            throw new InvalidArgumentException('Review round file does not belong to the review submission');
        }
        DB::table('review_round_files')->insert([
            'submission_id' => $submissionId,
            'review_round_id' => $reviewRoundId,
            'stage_id' => $this->integer($node, 'stage_id'),
            'submission_file_id' => $submissionFileId,
        ]);
        return Repo::submissionFile()->get($submissionFileId);
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException('Missing review round file value: ' . $attribute);
        }
        return $value;
    }

    private function integer($node, string $attribute): int
    {
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new InvalidArgumentException('Invalid review round file stage');
        }
        return $value;
    }
}
