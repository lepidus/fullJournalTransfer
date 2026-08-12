<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class SubmissionMetricNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$metrics)
    {
        if (!is_array($metrics)) {
            throw new InvalidArgumentException('Expected submission metrics for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'submission_metrics');
        $document->appendChild($root);
        foreach ($metrics as $metric) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'submission_metric');
            $node->setAttribute('load_id', (string) $metric->load_id);
            $node->setAttribute('submission_ref', (string) $metric->submission_id);
            foreach (['representation_id' => 'representation_ref',
                'submission_file_id' => 'submission_file_ref', 'file_type' => 'file_type'] as $field => $attribute) {
                if ($metric->{$field} !== null) {
                    $node->setAttribute($attribute, (string) $metric->{$field});
                }
            }
            $node->setAttribute('assoc_type', (string) $metric->assoc_type);
            $node->setAttribute('date', (string) $metric->date);
            $node->setAttribute('metric', (string) $metric->metric);
            $root->appendChild($node);
        }
        return $document;
    }
}
