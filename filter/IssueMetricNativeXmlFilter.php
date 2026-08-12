<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class IssueMetricNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$metrics)
    {
        if (!is_array($metrics)) {
            throw new InvalidArgumentException('Expected issue metrics for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'issue_metrics');
        $document->appendChild($root);
        foreach ($metrics as $metric) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'issue_metric');
            $node->setAttribute('load_id', (string) $metric->load_id);
            $node->setAttribute('issue_ref', (string) $metric->issue_id);
            if ($metric->issue_galley_id !== null) {
                $node->setAttribute('issue_galley_ref', (string) $metric->issue_galley_id);
            }
            $node->setAttribute('date', (string) $metric->date);
            $node->setAttribute('metric', (string) $metric->metric);
            $root->appendChild($node);
        }
        return $document;
    }
}
