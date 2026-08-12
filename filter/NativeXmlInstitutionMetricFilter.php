<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NativeXmlInstitutionMetricFilter extends NativeXmlMetricFilter
{
    private const FAMILY = 'counter_submission_institution';
    private const VALUES = [
        'metric_investigations',
        'metric_investigations_unique',
        'metric_requests',
        'metric_requests_unique',
    ];

    public function getPluralElementName()
    {
        return 'institution_metrics';
    }

    public function getSingularElementName()
    {
        return 'institution_metric';
    }

    public function handleElement($node)
    {
        $granularity = $this->required($node, 'granularity');
        if (!in_array($granularity, ['daily', 'monthly'], true)) {
            throw new InvalidArgumentException('Invalid institutional metric granularity');
        }
        $institutionId = $this->destinationInstitutionId($node, $granularity);
        if ($institutionId === null) {
            return (object) [];
        }
        $dimensions = [
            'context_id' => (int) $this->getDeployment()->getContext()->getId(),
            'submission_id' => $this->getDeployment()->requireReference(
                'submission',
                $this->required($node, 'submission_ref')
            ),
            'institution_id' => $institutionId,
        ];
        if ($granularity === 'daily') {
            $key = array_merge($dimensions, [
                'load_id' => $this->required($node, 'load_id'),
                'date' => $this->date($node),
            ]);
            DB::table('metrics_counter_submission_institution_daily')->updateOrInsert($key, $this->values($node));
            return (object) $key;
        }
        $month = $this->month($node);
        [$from, $to] = $this->monthRange($month);
        $daily = DB::table('metrics_counter_submission_institution_daily')
            ->where($dimensions)
            ->whereBetween('date', [$from, $to]);
        $values = $this->values($node);
        if ($daily->exists()) {
            foreach (self::VALUES as $field) {
                $values[$field] = (int) (clone $daily)->sum($field);
            }
        }
        $key = array_merge($dimensions, ['month' => $month]);
        DB::table('metrics_counter_submission_institution_monthly')->updateOrInsert($key, $values);
        return (object) $key;
    }

    private function destinationInstitutionId($node, string $granularity): ?int
    {
        $sourceReference = $this->required($node, 'source_institution_ref');
        $ror = $this->normalizeRor(trim($node->getAttribute('institution_ror')));
        if ($ror === null) {
            $this->reject($granularity, $sourceReference, 'Institution metric has no stable ROR key');
            return null;
        }
        $matches = [];
        foreach (DB::table('institutions')
            ->where('context_id', (int) $this->getDeployment()->getContext()->getId())
            ->whereNull('deleted_at')
            ->get(['institution_id', 'ror']) as $institution) {
            if ($this->normalizeRor((string) $institution->ror) === $ror) {
                $matches[] = (int) $institution->institution_id;
            }
        }
        if (count($matches) === 0) {
            $this->reject($granularity, $sourceReference, 'Institution metric ROR was not found in the destination');
            return null;
        }
        if (count($matches) > 1) {
            $this->reject($granularity, $sourceReference, 'Institution metric ROR is ambiguous in the destination');
            return null;
        }
        return $matches[0];
    }

    private function normalizeRor(string $ror): ?string
    {
        $ror = strtolower(rtrim(trim($ror), '/'));
        if (preg_match('#^https://ror\.org/0[^ilou]{6}\d{2}$#i', $ror) !== 1) {
            return null;
        }
        return $ror;
    }

    private function reject(string $granularity, string $sourceReference, string $reason): void
    {
        $this->getDeployment()->addMetricRejection([
            'family' => self::FAMILY,
            'granularity' => $granularity,
            'source_institution_ref' => $sourceReference,
            'reason' => $reason,
        ]);
    }

    private function values($node): array
    {
        $values = [];
        foreach (self::VALUES as $field) {
            $values[$field] = $this->nonNegativeInteger($node, $field);
        }
        return $values;
    }
}
