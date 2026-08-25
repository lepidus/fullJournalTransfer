<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlMetricsFilter extends NativeImportFilter
{
    private const INSTITUTION_FAMILY = 'counter_submission_institution';
    private const COUNTER_VALUES = [
        'metric_investigations',
        'metric_investigations_unique',
        'metric_requests',
        'metric_requests_unique',
    ];

    public function getPluralElementName()
    {
        return 'metrics_collection';
    }

    public function getSingularElementName()
    {
        return 'metrics';
    }

    public function handleElement($root)
    {
        return DB::transaction(function () use ($root) {
            $this->provisionInstitutions($root);
            $this->importChildren($root, 'context_metrics', 'context_metric', [$this, 'importContextMetric']);
            $this->importChildren($root, 'submission_metrics', 'submission_metric', [$this, 'importSubmissionMetric']);
            $this->importChildren($root, 'issue_metrics', 'issue_metric', [$this, 'importIssueMetric']);
            $this->importChildren($root, 'geo_metrics', 'geo_metric', [$this, 'importGeoMetric']);
            $this->importChildren($root, 'counter_metrics', 'counter_metric', [$this, 'importCounterMetric']);
            $this->importChildren(
                $root,
                'institution_metrics',
                'institution_metric',
                [$this, 'importInstitutionMetric']
            );
            return $this->getDeployment()->getContext();
        });
    }

    private function importChildren(DOMElement $parent, string $container, string $element, callable $importer): void
    {
        foreach ($this->requiredChild($parent, $container)->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $element) {
                $importer($child);
            }
        }
    }

    private function importContextMetric(DOMElement $node): void
    {
        $key = [
            'load_id' => $this->required($node, 'load_id'),
            'context_id' => (int) $this->getDeployment()->getContext()->getId(),
            'date' => $this->date($node),
        ];
        DB::table('metrics_context')->updateOrInsert($key, [
            'metric' => $this->nonNegativeInteger($node, 'metric'),
        ]);
    }

    private function importSubmissionMetric(DOMElement $node): void
    {
        $key = [
            'load_id' => $this->required($node, 'load_id'),
            'context_id' => (int) $this->getDeployment()->getContext()->getId(),
            'submission_id' => $this->getDeployment()->requireReference(
                'submission',
                $this->required($node, 'submission_ref')
            ),
            'representation_id' => $this->optionalReference($node, 'article_galley', 'representation_ref'),
            'submission_file_id' => $this->optionalReference($node, 'submission_file', 'submission_file_ref'),
            'file_type' => $this->optionalInteger($node, 'file_type'),
            'assoc_type' => $this->nonNegativeInteger($node, 'assoc_type'),
            'date' => $this->date($node),
        ];
        DB::table('metrics_submission')->updateOrInsert($key, [
            'metric' => $this->nonNegativeInteger($node, 'metric'),
        ]);
    }

    private function importIssueMetric(DOMElement $node): void
    {
        $key = [
            'load_id' => $this->required($node, 'load_id'),
            'context_id' => (int) $this->getDeployment()->getContext()->getId(),
            'issue_id' => $this->getDeployment()->requireReference('issue', $this->required($node, 'issue_ref')),
            'issue_galley_id' => $this->optionalReference($node, 'issue_galley', 'issue_galley_ref'),
            'date' => $this->date($node),
        ];
        DB::table('metrics_issue')->updateOrInsert($key, [
            'metric' => $this->nonNegativeInteger($node, 'metric'),
        ]);
    }

    private function importGeoMetric(DOMElement $node): void
    {
        $granularity = $this->required($node, 'granularity');
        if ($granularity === 'daily') {
            $key = array_merge($this->geoDimensions($node), [
                'load_id' => $this->required($node, 'load_id'),
                'date' => $this->date($node),
            ]);
            DB::table('metrics_submission_geo_daily')->updateOrInsert($key, $this->geoValues($node));
            return;
        }
        if ($granularity !== 'monthly') {
            throw new InvalidArgumentException('Invalid geographic metric granularity');
        }
        $month = $this->month($node);
        $dimensions = $this->geoDimensions($node);
        [$from, $to] = $this->monthRange($month);
        $daily = DB::table('metrics_submission_geo_daily')
            ->where($dimensions)
            ->whereBetween('date', [$from, $to]);
        $values = $daily->exists()
            ? [
                'metric' => (int) (clone $daily)->sum('metric'),
                'metric_unique' => (int) (clone $daily)->sum('metric_unique'),
            ]
            : $this->geoValues($node);
        DB::table('metrics_submission_geo_monthly')->updateOrInsert(
            array_merge($dimensions, ['month' => $month]),
            $values
        );
    }

    private function geoDimensions(DOMElement $node): array
    {
        return [
            'context_id' => (int) $this->getDeployment()->getContext()->getId(),
            'submission_id' => $this->getDeployment()->requireReference(
                'submission',
                $this->required($node, 'submission_ref')
            ),
            'country' => $this->geoDimension($node, 'country', 2),
            'region' => $this->geoDimension($node, 'region', 3),
            'city' => $this->geoDimension($node, 'city', 255),
        ];
    }

    private function geoDimension(DOMElement $node, string $attribute, int $maximumLength): string
    {
        if (!$node->hasAttribute($attribute)) {
            throw new InvalidArgumentException('Missing geographic metric value: ' . $attribute);
        }
        $value = trim($node->getAttribute($attribute));
        if (mb_strlen($value) > $maximumLength || ($attribute === 'country'
            && preg_match('/^(?:|[A-Z]{2})$/', $value) !== 1)) {
            throw new InvalidArgumentException('Invalid geographic metric value: ' . $attribute);
        }
        return $value;
    }

    private function geoValues(DOMElement $node): array
    {
        return [
            'metric' => $this->nonNegativeInteger($node, 'metric'),
            'metric_unique' => $this->nonNegativeInteger($node, 'metric_unique'),
        ];
    }

    private function importCounterMetric(DOMElement $node): void
    {
        $granularity = $this->required($node, 'granularity');
        $dimensions = $this->submissionDimensions($node);
        if ($granularity === 'daily') {
            $key = array_merge($dimensions, [
                'load_id' => $this->required($node, 'load_id'),
                'date' => $this->date($node),
            ]);
            DB::table('metrics_counter_submission_daily')->updateOrInsert($key, $this->counterValues($node));
            return;
        }
        if ($granularity !== 'monthly') {
            throw new InvalidArgumentException('Invalid COUNTER metric granularity');
        }
        $this->importMonthlyCounterMetric(
            $node,
            $dimensions,
            'metrics_counter_submission_daily',
            'metrics_counter_submission_monthly'
        );
    }

    private function importInstitutionMetric(DOMElement $node): void
    {
        $granularity = $this->required($node, 'granularity');
        if (!in_array($granularity, ['daily', 'monthly'], true)) {
            throw new InvalidArgumentException('Invalid institutional metric granularity');
        }
        $institutionId = $this->destinationInstitutionId($node, $granularity);
        if ($institutionId === null) {
            return;
        }
        $dimensions = array_merge($this->submissionDimensions($node), ['institution_id' => $institutionId]);
        if ($granularity === 'daily') {
            $key = array_merge($dimensions, [
                'load_id' => $this->required($node, 'load_id'),
                'date' => $this->date($node),
            ]);
            DB::table('metrics_counter_submission_institution_daily')->updateOrInsert(
                $key,
                $this->counterValues($node)
            );
            return;
        }
        $this->importMonthlyCounterMetric(
            $node,
            $dimensions,
            'metrics_counter_submission_institution_daily',
            'metrics_counter_submission_institution_monthly'
        );
    }

    private function importMonthlyCounterMetric(
        DOMElement $node,
        array $dimensions,
        string $dailyTable,
        string $monthlyTable
    ): void {
        $month = $this->month($node);
        [$from, $to] = $this->monthRange($month);
        $daily = DB::table($dailyTable)->where($dimensions)->whereBetween('date', [$from, $to]);
        $values = $this->counterValues($node);
        if ($daily->exists()) {
            foreach (self::COUNTER_VALUES as $field) {
                $values[$field] = (int) (clone $daily)->sum($field);
            }
        }
        DB::table($monthlyTable)->updateOrInsert(array_merge($dimensions, ['month' => $month]), $values);
    }

    private function submissionDimensions(DOMElement $node): array
    {
        return [
            'context_id' => (int) $this->getDeployment()->getContext()->getId(),
            'submission_id' => $this->getDeployment()->requireReference(
                'submission',
                $this->required($node, 'submission_ref')
            ),
        ];
    }

    private function counterValues(DOMElement $node): array
    {
        $values = [];
        foreach (self::COUNTER_VALUES as $field) {
            $values[$field] = $this->nonNegativeInteger($node, $field);
        }
        return $values;
    }

    private function destinationInstitutionId(DOMElement $node, string $granularity): ?int
    {
        $sourceReference = $this->required($node, 'source_institution_ref');
        $ror = $this->normalizeRor(trim($node->getAttribute('institution_ror')));
        if ($ror === null) {
            $this->rejectInstitutionMetric($granularity, $sourceReference, 'Institution metric has no stable ROR key');
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
            $this->rejectInstitutionMetric(
                $granularity,
                $sourceReference,
                'Institution metric ROR was not found in the destination'
            );
            return null;
        }
        if (count($matches) > 1) {
            $this->rejectInstitutionMetric(
                $granularity,
                $sourceReference,
                'Institution metric ROR is ambiguous in the destination'
            );
            return null;
        }
        return $matches[0];
    }

    private function rejectInstitutionMetric(string $granularity, string $sourceReference, string $reason): void
    {
        $this->getDeployment()->addMetricRejection([
            'family' => self::INSTITUTION_FAMILY,
            'granularity' => $granularity,
            'source_institution_ref' => $sourceReference,
            'reason' => $reason,
        ]);
    }

    private function provisionInstitutions(DOMElement $root): void
    {
        $contextId = (int) $this->getDeployment()->getContext()->getId();
        $rors = [];
        foreach ($root->getElementsByTagNameNS($this->getDeployment()->getNamespace(), 'institution_metric') as $node) {
            $ror = $this->normalizeRor($node->getAttribute('institution_ror'));
            if ($ror !== null) {
                $rors[$ror] = true;
            }
        }
        foreach (array_keys($rors) as $ror) {
            $matches = 0;
            $institutions = DB::table('institutions')
                ->where('context_id', $contextId)
                ->whereNull('deleted_at')
                ->get();
            foreach ($institutions as $row) {
                if ($this->normalizeRor((string) $row->ror) === $ror) {
                    $matches++;
                }
            }
            if ($matches === 0) {
                DB::table('institutions')->insert([
                    'context_id' => $contextId,
                    'ror' => $ror,
                    'deleted_at' => null,
                ]);
            }
        }
    }

    private function normalizeRor(string $ror): ?string
    {
        $ror = strtolower(rtrim(trim($ror), '/'));
        return preg_match('#^https://ror\.org/0[^ilou]{6}\d{2}$#i', $ror) === 1 ? $ror : null;
    }

    private function requiredChild(DOMElement $parent, string $name): DOMElement
    {
        $matches = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $matches[] = $child;
            }
        }
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('Expected exactly one metrics element: ' . $name);
        }
        return $matches[0];
    }

    private function required(DOMElement $node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException('Missing metric value: ' . $attribute);
        }
        return $value;
    }

    private function date(DOMElement $node): string
    {
        $value = $this->required($node, 'date');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid metric date');
        }
        return $value;
    }

    private function nonNegativeInteger(DOMElement $node, string $attribute): int
    {
        $value = $this->required($node, $attribute);
        if (preg_match('/^(0|[1-9][0-9]*)$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid metric value: ' . $attribute);
        }
        return (int) $value;
    }

    private function optionalInteger(DOMElement $node, string $attribute): ?int
    {
        return $node->hasAttribute($attribute) ? $this->nonNegativeInteger($node, $attribute) : null;
    }

    private function optionalReference(DOMElement $node, string $entity, string $attribute): ?int
    {
        if (!$node->hasAttribute($attribute)) {
            return null;
        }
        return $this->getDeployment()->requireReference($entity, $this->required($node, $attribute));
    }

    private function month(DOMElement $node): int
    {
        $value = $this->required($node, 'month');
        $month = \DateTimeImmutable::createFromFormat('!Ym', $value);
        if (!$month || $month->format('Ym') !== $value) {
            throw new InvalidArgumentException('Invalid metric month');
        }
        return (int) $value;
    }

    private function monthRange(int $month): array
    {
        $start = \DateTimeImmutable::createFromFormat('!Ym', (string) $month);
        if (!$start) {
            throw new InvalidArgumentException('Invalid metric month');
        }
        return [$start->format('Y-m-d'), $start->modify('last day of this month')->format('Y-m-d')];
    }
}
