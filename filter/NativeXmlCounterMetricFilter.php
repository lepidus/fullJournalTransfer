<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NativeXmlCounterMetricFilter extends NativeXmlMetricFilter
{
    private const VALUES = [
        'metric_investigations',
        'metric_investigations_unique',
        'metric_requests',
        'metric_requests_unique',
    ];

    public function getPluralElementName()
    {
        return 'counter_metrics';
    }

    public function getSingularElementName()
    {
        return 'counter_metric';
    }

    public function handleElement($node)
    {
        $granularity = $this->required($node, 'granularity');
        if ($granularity === 'daily') {
            $key = array_merge($this->dimensions($node), [
                'load_id' => $this->required($node, 'load_id'),
                'date' => $this->date($node),
            ]);
            DB::table('metrics_counter_submission_daily')->updateOrInsert($key, $this->values($node));
            return (object) $key;
        }
        if ($granularity !== 'monthly') {
            throw new InvalidArgumentException('Invalid COUNTER metric granularity');
        }
        $month = $this->month($node);
        $dimensions = $this->dimensions($node);
        [$from, $to] = $this->monthRange($month);
        $daily = DB::table('metrics_counter_submission_daily')
            ->where($dimensions)
            ->whereBetween('date', [$from, $to]);
        $values = $this->values($node);
        if ($daily->exists()) {
            foreach (self::VALUES as $field) {
                $values[$field] = (int) (clone $daily)->sum($field);
            }
        }
        $key = array_merge($dimensions, ['month' => $month]);
        DB::table('metrics_counter_submission_monthly')->updateOrInsert($key, $values);
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
        ];
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
