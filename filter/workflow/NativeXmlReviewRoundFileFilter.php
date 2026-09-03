<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\workflow;

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
            throw new InvalidArgumentException(sprintf(
                'Review round_ref "%s" does not belong to submission_ref "%s" at line %d',
                $node->getAttribute('review_round_ref'),
                $node->getAttribute('submission_ref'),
                $node->getLineNo()
            ));
        }
        $submissionFileId = $deployment->requireReference(
            'submission_file',
            $this->required($node, 'submission_file_ref')
        );
        if ((int) DB::table('submission_files')->where('submission_file_id', $submissionFileId)
            ->value('submission_id') !== $submissionId
        ) {
            throw new InvalidArgumentException(sprintf(
                'Review round submission_file_ref "%s" does not belong to submission_ref "%s" at line %d',
                $node->getAttribute('submission_file_ref'),
                $node->getAttribute('submission_ref'),
                $node->getLineNo()
            ));
        }
        DB::table('review_round_files')->updateOrInsert(
            [
                'submission_id' => $submissionId,
                'review_round_id' => $reviewRoundId,
                'submission_file_id' => $submissionFileId,
            ],
            ['stage_id' => $this->integer($node, 'stage_id')]
        );
        return Repo::submissionFile()->get($submissionFileId);
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing review round file attribute "%s" for review_round_ref "%s" at line %d',
                $attribute,
                $node->getAttribute('review_round_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function integer($node, string $attribute): int
    {
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid review round file %s "%s" for review_round_ref "%s" at line %d',
                $attribute,
                $node->getAttribute($attribute),
                $node->getAttribute('review_round_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }
}
