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
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.reviewRoundSubmissionMismatch',
                [
                    'reviewRoundRef' => $node->getAttribute('review_round_ref'),
                    'submissionRef' => $node->getAttribute('submission_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        $submissionFileId = $deployment->requireReference(
            'submission_file',
            $this->required($node, 'submission_file_ref')
        );
        if ((int) DB::table('submission_files')->where('submission_file_id', $submissionFileId)
            ->value('submission_id') !== $submissionId
        ) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.reviewRoundFileSubmissionMismatch',
                [
                    'submissionFileRef' => $node->getAttribute('submission_file_ref'),
                    'submissionRef' => $node->getAttribute('submission_ref'),
                    'line' => $node->getLineNo(),
                ]
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
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.missingReviewRoundFileAttributeReviewRoundRefLine',
                [
                    'attribute' => $attribute,
                    'reviewRoundRef' => $node->getAttribute('review_round_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        return $value;
    }

    private function integer($node, string $attribute): int
    {
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.invalidReviewRoundFileReviewRoundRefLine',
                [
                    'attribute' => $attribute,
                    'value' => $node->getAttribute($attribute),
                    'reviewRoundRef' => $node->getAttribute('review_round_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        return $value;
    }
}
