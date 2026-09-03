<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\persistence\HistoricalDecisionPersistenceAdapter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlEditorialDecisionFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'editorial_decisions';
    }

    public function getSingularElementName()
    {
        return 'editorial_decision';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $sourceReference = $this->required($node, 'source_ref');
        $submissionId = $deployment->requireReference('submission', $this->required($node, 'submission_ref'));
        $editorId = $deployment->requireReference('user', $this->required($node, 'editor_ref'));
        $decisionType = $this->positiveInteger($node, 'decision');
        if (!Repo::decision()->getDecisionType($decisionType)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid editorial decision type "%d" for source_ref "%s" at line %d',
                $decisionType,
                $sourceReference,
                $node->getLineNo()
            ));
        }
        $reviewRoundId = null;
        $reviewRoundReference = trim($node->getAttribute('review_round_ref'));
        if ($reviewRoundReference !== '') {
            $reviewRoundId = $deployment->requireReference('review_round', $reviewRoundReference);
            $round = DB::table('review_rounds')->where('review_round_id', $reviewRoundId)->first();
            if (!$round || (int) $round->submission_id !== $submissionId) {
                throw new InvalidArgumentException('Editorial decision review round belongs to another submission');
            }
        }
        $round = $this->optionalPositiveInteger($node, 'round');
        if (($reviewRoundId === null) !== ($round === null)) {
            throw new InvalidArgumentException('Editorial decision round references must be provided together');
        }
        $decision = (new HistoricalDecisionPersistenceAdapter())->insert([
            'submissionId' => $submissionId,
            'editorId' => $editorId,
            'decision' => $decisionType,
            'dateDecided' => $this->requiredDate($node, 'date_decided'),
            'reviewRoundId' => $reviewRoundId,
            'stageId' => $this->positiveInteger($node, 'stage_id'),
            'round' => $round,
        ]);
        $deployment->mapReference('editorial_decision', $sourceReference, (int) $decision->getId());
        return $decision;
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing editorial decision attribute "%s" for source_ref "%s" at line %d',
                $attribute,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function requiredDate($node, string $attribute): string
    {
        $value = $this->required($node, $attribute);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException(sprintf(
                'Invalid editorial decision %s "%s" for source_ref "%s" at line %d',
                $attribute,
                $value,
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
                'Invalid editorial decision %s "%s" for source_ref "%s" at line %d; expected a positive integer',
                $attribute,
                $node->getAttribute($attribute),
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function optionalPositiveInteger($node, string $attribute): ?int
    {
        $rawValue = trim($node->getAttribute($attribute));
        if ($rawValue === '') {
            return null;
        }
        $value = filter_var($rawValue, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid editorial decision %s "%s" for source_ref "%s" at line %d; expected a positive integer',
                $attribute,
                $rawValue,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }
}
