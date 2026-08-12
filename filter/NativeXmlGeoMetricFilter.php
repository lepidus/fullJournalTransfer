<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NativeXmlGeoMetricFilter extends NativeXmlMetricFilter
{
    public function getPluralElementName()
    {
        return 'geo_metrics';
    }

    public function getSingularElementName()
    {
        return 'geo_metric';
    }

    public function handleElement($node)
    {
        $granularity = $this->required($node, 'granularity');
        if ($granularity === 'daily') {
            return $this->importDaily($node);
        }
        if ($granularity === 'monthly') {
            return $this->importMonthly($node);
        }
        throw new InvalidArgumentException('Invalid geographic metric granularity');
    }

    private function importDaily($node): object
    {
        $key = array_merge($this->dimensions($node), [
            'load_id' => $this->required($node, 'load_id'),
            'date' => $this->date($node),
        ]);
        DB::table('metrics_submission_geo_daily')->updateOrInsert($key, $this->values($node));
        return (object) $key;
    }

    private function importMonthly($node): object
    {
        $month = $this->month($node);
        $dimensions = $this->dimensions($node);
        [$from, $to] = $this->monthRange($month);
        $daily = DB::table('metrics_submission_geo_daily')
            ->where($dimensions)
            ->whereBetween('date', [$from, $to]);
        $values = $daily->exists()
            ? [
                'metric' => (int) (clone $daily)->sum('metric'),
                'metric_unique' => (int) (clone $daily)->sum('metric_unique'),
            ]
            : $this->values($node);
        $key = array_merge($dimensions, ['month' => $month]);
        DB::table('metrics_submission_geo_monthly')->updateOrInsert($key, $values);
        return (object) $key;
    }

    private function dimensions($node): array
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

    private function geoDimension($node, string $attribute, int $maximumLength): string
    {
        if (!$node->hasAttribute($attribute)) {
            throw new InvalidArgumentException('Missing geographic metric value: ' . $attribute);
        }
        $value = trim($node->getAttribute($attribute));
        if (mb_strlen($value) > $maximumLength || ($attribute === 'country' &&
            preg_match('/^(?:|[A-Z]{2})$/', $value) !== 1)) {
            throw new InvalidArgumentException('Invalid geographic metric value: ' . $attribute);
        }
        return $value;
    }

    private function values($node): array
    {
        return [
            'metric' => $this->nonNegativeInteger($node, 'metric'),
            'metric_unique' => $this->nonNegativeInteger($node, 'metric_unique'),
        ];
    }
}
