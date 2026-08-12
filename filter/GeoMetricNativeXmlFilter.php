<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class GeoMetricNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$metrics)
    {
        if (!$metrics instanceof \stdClass || !isset($metrics->daily, $metrics->monthly)) {
            throw new InvalidArgumentException('Expected geographic metrics for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'geo_metrics');
        $document->appendChild($root);
        foreach (['daily' => $metrics->daily, 'monthly' => $metrics->monthly] as $granularity => $rows) {
            foreach ($rows as $metric) {
                $node = $document->createElementNS('http://pkp.sfu.ca', 'geo_metric');
                $node->setAttribute('granularity', $granularity);
                if ($granularity === 'daily') {
                    $node->setAttribute('load_id', (string) $metric->load_id);
                    $node->setAttribute('date', (string) $metric->date);
                } else {
                    $node->setAttribute('month', (string) $metric->month);
                }
                $node->setAttribute('submission_ref', (string) $metric->submission_id);
                $node->setAttribute('country', (string) $metric->country);
                $node->setAttribute('region', (string) $metric->region);
                $node->setAttribute('city', (string) $metric->city);
                $node->setAttribute('metric', (string) $metric->metric);
                $node->setAttribute('metric_unique', (string) $metric->metric_unique);
                $root->appendChild($node);
            }
        }
        return $document;
    }
}
