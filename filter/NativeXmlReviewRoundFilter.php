<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlReviewRoundFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'review_rounds';
    }

    public function getSingularElementName()
    {
        return 'review_round';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $sourceReference = $this->requiredReference($node, 'source_ref');
        $stageId = $this->positiveInteger($node, 'stage_id');
        if (!in_array($stageId, [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid review round stage_id "%d" for source_ref "%s" at line %d',
                $stageId,
                $sourceReference,
                $node->getLineNo()
            ));
        }
        $id = DB::table('review_rounds')->insertGetId([
            'submission_id' => $deployment->requireReference(
                'submission',
                $this->requiredReference($node, 'submission_ref')
            ),
            'stage_id' => $stageId,
            'round' => $this->positiveInteger($node, 'round'),
            'status' => $this->nonNegativeInteger($node, 'status'),
        ], 'review_round_id');
        $deployment->mapReference('review_round', $sourceReference, (int) $id);
        return DAORegistry::getDAO('ReviewRoundDAO')->getById((int) $id);
    }

    private function requiredReference($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing review round attribute "%s" for source_ref "%s" at line %d',
                $attribute,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function positiveInteger($node, string $attribute): int
    {
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid review round %s "%s" for source_ref "%s" at line %d; expected a positive integer',
                $attribute,
                $node->getAttribute($attribute),
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function nonNegativeInteger($node, string $attribute): int
    {
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            throw new InvalidArgumentException(sprintf(
                'Invalid review round %s "%s" for source_ref "%s" at line %d; expected a non-negative integer',
                $attribute,
                $node->getAttribute($attribute),
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

}
