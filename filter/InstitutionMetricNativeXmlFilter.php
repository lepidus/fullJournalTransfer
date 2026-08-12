<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class InstitutionMetricNativeXmlFilter extends NativeExportFilter
{
    private const VALUES = [
        'metric_investigations',
        'metric_investigations_unique',
        'metric_requests',
        'metric_requests_unique',
    ];

    public function &process(&$metrics)
    {
        if (!$metrics instanceof \stdClass || !isset($metrics->daily, $metrics->monthly)) {
            throw new InvalidArgumentException('Expected institutional metrics for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'institution_metrics');
        $document->appendChild($root);
        foreach (['daily' => $metrics->daily, 'monthly' => $metrics->monthly] as $granularity => $rows) {
            foreach ($rows as $metric) {
                $node = $document->createElementNS('http://pkp.sfu.ca', 'institution_metric');
                $node->setAttribute('granularity', $granularity);
                $node->setAttribute('source_institution_ref', (string) $metric->institution_id);
                if (is_string($metric->institution_ror) && trim($metric->institution_ror) !== '') {
                    $node->setAttribute('institution_ror', trim($metric->institution_ror));
                }
                if ($granularity === 'daily') {
                    $node->setAttribute('load_id', (string) $metric->load_id);
                    $node->setAttribute('date', (string) $metric->date);
                } else {
                    $node->setAttribute('month', (string) $metric->month);
                }
                $node->setAttribute('submission_ref', (string) $metric->submission_id);
                foreach (self::VALUES as $field) {
                    $node->setAttribute($field, (string) $metric->{$field});
                }
                $root->appendChild($node);
            }
        }
        return $document;
    }
}
