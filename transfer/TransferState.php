<?php

/**
 * Copyright (c) 2014-2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\transfer;

use InvalidArgumentException;

class TransferState
{
    private array $referenceMaps = [];
    private array $userConflicts = [];
    private ?int $currentReviewFormId = null;
    private array $submissionsByIssue = [];
    private array $metricRejections = [];

    public function mapReference(string $entity, string $sourceReference, int $destinationId): void
    {
        $this->referenceMaps[$entity][$sourceReference] = $destinationId;
    }

    public function getReferenceMap(string $entity): array
    {
        return $this->referenceMaps[$entity] ?? [];
    }

    public function resetReferenceMap(string $entity): void
    {
        $this->referenceMaps[$entity] = [];
    }

    public function requireReference(string $entity, string $sourceReference): int
    {
        $destinationId = $this->referenceMaps[$entity][$sourceReference] ?? null;
        if (!is_int($destinationId)) {
            throw new InvalidArgumentException(sprintf(
                'Missing mapped %s reference: "%s"',
                $entity,
                $sourceReference
            ));
        }
        return $destinationId;
    }

    public function addUserConflict(array $conflict): void
    {
        $this->userConflicts[] = $conflict;
    }

    public function getUserConflicts(): array
    {
        return $this->userConflicts;
    }

    public function resetUserConflicts(): void
    {
        $this->userConflicts = [];
    }

    public function setCurrentReviewFormId(?int $reviewFormId): void
    {
        $this->currentReviewFormId = $reviewFormId;
    }

    public function getCurrentReviewFormId(): ?int
    {
        return $this->currentReviewFormId;
    }

    public function setSubmissionsByIssue(array $submissionsByIssue): void
    {
        $this->submissionsByIssue = $submissionsByIssue;
    }

    public function getSubmissionsForIssue(int $issueId): array
    {
        return $this->submissionsByIssue[$issueId] ?? [];
    }

    public function addMetricRejection(array $rejection): void
    {
        $this->metricRejections[] = $rejection;
    }

    public function getMetricRejections(): array
    {
        return $this->metricRejections;
    }

    public function resetMetricRejections(): void
    {
        $this->metricRejections = [];
    }
}
