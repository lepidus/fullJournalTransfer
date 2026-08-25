<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class MetricsNativeXmlFilter extends NativeExportFilter
{
    private const COUNTER_VALUES = [
        'metric_investigations',
        'metric_investigations_unique',
        'metric_requests',
        'metric_requests_unique',
    ];

    public function &process(&$context)
    {
        if (!$context instanceof Journal) {
            throw new InvalidArgumentException('Expected a journal for metrics export');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'metrics');
        $document->appendChild($root);
        $contextId = (int) $context->getId();

        $this->appendContextMetrics($document, $root, $contextId);
        $this->appendSubmissionMetrics($document, $root, $contextId);
        $this->appendIssueMetrics($document, $root, $contextId);
        $this->appendGeoMetrics($document, $root, $contextId);
        $this->appendCounterMetrics($document, $root, $contextId);
        $this->appendInstitutionMetrics($document, $root, $contextId);

        return $document;
    }

    private function appendContextMetrics(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'context_metrics');
        $metrics = DB::table('metrics_context')
            ->where('context_id', $contextId)
            ->orderBy('date')
            ->orderBy('metrics_context_id')
            ->get();
        foreach ($metrics as $metric) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'context_metric');
            $node->setAttribute('load_id', (string) $metric->load_id);
            $node->setAttribute('date', (string) $metric->date);
            $node->setAttribute('metric', (string) $metric->metric);
            $root->appendChild($node);
        }
        $parent->appendChild($root);
    }

    private function appendSubmissionMetrics(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'submission_metrics');
        $metrics = DB::table('metrics_submission')
            ->where('context_id', $contextId)
            ->orderBy('date')
            ->orderBy('metrics_submission_id')
            ->get();
        foreach ($metrics as $metric) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'submission_metric');
            $node->setAttribute('load_id', (string) $metric->load_id);
            $node->setAttribute('submission_ref', (string) $metric->submission_id);
            foreach ([
                'representation_id' => 'representation_ref',
                'submission_file_id' => 'submission_file_ref',
                'file_type' => 'file_type',
            ] as $field => $attribute) {
                if ($metric->{$field} !== null) {
                    $node->setAttribute($attribute, (string) $metric->{$field});
                }
            }
            $node->setAttribute('assoc_type', (string) $metric->assoc_type);
            $node->setAttribute('date', (string) $metric->date);
            $node->setAttribute('metric', (string) $metric->metric);
            $root->appendChild($node);
        }
        $parent->appendChild($root);
    }

    private function appendIssueMetrics(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'issue_metrics');
        $metrics = DB::table('metrics_issue')
            ->where('context_id', $contextId)
            ->orderBy('date')
            ->orderBy('metrics_issue_id')
            ->get();
        foreach ($metrics as $metric) {
            $node = $document->createElementNS($this->getDeployment()->getNamespace(), 'issue_metric');
            $node->setAttribute('load_id', (string) $metric->load_id);
            $node->setAttribute('issue_ref', (string) $metric->issue_id);
            if ($metric->issue_galley_id !== null) {
                $node->setAttribute('issue_galley_ref', (string) $metric->issue_galley_id);
            }
            $node->setAttribute('date', (string) $metric->date);
            $node->setAttribute('metric', (string) $metric->metric);
            $root->appendChild($node);
        }
        $parent->appendChild($root);
    }

    private function appendGeoMetrics(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'geo_metrics');
        foreach ([
            'daily' => 'metrics_submission_geo_daily',
            'monthly' => 'metrics_submission_geo_monthly',
        ] as $granularity => $table) {
            $metrics = $this->metricRows($table, $contextId, $granularity);
            foreach ($metrics as $metric) {
                $node = $this->createFamilyMetricNode($document, 'geo_metric', $granularity, $metric);
                $node->setAttribute('country', (string) $metric->country);
                $node->setAttribute('region', (string) $metric->region);
                $node->setAttribute('city', (string) $metric->city);
                $node->setAttribute('metric', (string) $metric->metric);
                $node->setAttribute('metric_unique', (string) $metric->metric_unique);
                $root->appendChild($node);
            }
        }
        $parent->appendChild($root);
    }

    private function appendCounterMetrics(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'counter_metrics');
        foreach ([
            'daily' => 'metrics_counter_submission_daily',
            'monthly' => 'metrics_counter_submission_monthly',
        ] as $granularity => $table) {
            $metrics = $this->metricRows($table, $contextId, $granularity);
            foreach ($metrics as $metric) {
                $node = $this->createFamilyMetricNode($document, 'counter_metric', $granularity, $metric);
                $this->setCounterValues($node, $metric);
                $root->appendChild($node);
            }
        }
        $parent->appendChild($root);
    }

    private function appendInstitutionMetrics(DOMDocument $document, DOMElement $parent, int $contextId): void
    {
        $root = $document->createElementNS($this->getDeployment()->getNamespace(), 'institution_metrics');
        foreach ([
            'daily' => 'metrics_counter_submission_institution_daily',
            'monthly' => 'metrics_counter_submission_institution_monthly',
        ] as $granularity => $table) {
            $metrics = DB::table($table . ' as metric')
                ->join('institutions as institution', 'institution.institution_id', '=', 'metric.institution_id')
                ->where('metric.context_id', $contextId)
                ->orderBy($granularity === 'daily' ? 'metric.date' : 'metric.month')
                ->orderBy('metric.' . $table . '_id')
                ->select(['metric.*', 'institution.ror as institution_ror'])
                ->get();
            foreach ($metrics as $metric) {
                $node = $this->createFamilyMetricNode($document, 'institution_metric', $granularity, $metric);
                $node->setAttribute('source_institution_ref', (string) $metric->institution_id);
                if (is_string($metric->institution_ror) && trim($metric->institution_ror) !== '') {
                    $node->setAttribute('institution_ror', trim($metric->institution_ror));
                }
                $this->setCounterValues($node, $metric);
                $root->appendChild($node);
            }
        }
        $parent->appendChild($root);
    }

    private function metricRows(string $table, int $contextId, string $granularity): iterable
    {
        return DB::table($table)
            ->where('context_id', $contextId)
            ->orderBy($granularity === 'daily' ? 'date' : 'month')
            ->orderBy($table . '_id')
            ->get();
    }

    private function createFamilyMetricNode(
        DOMDocument $document,
        string $name,
        string $granularity,
        object $metric
    ): DOMElement {
        $node = $document->createElementNS($this->getDeployment()->getNamespace(), $name);
        $node->setAttribute('granularity', $granularity);
        if ($granularity === 'daily') {
            $node->setAttribute('load_id', (string) $metric->load_id);
            $node->setAttribute('date', (string) $metric->date);
        } else {
            $node->setAttribute('month', (string) $metric->month);
        }
        $node->setAttribute('submission_ref', (string) $metric->submission_id);
        return $node;
    }

    private function setCounterValues(DOMElement $node, object $metric): void
    {
        foreach (self::COUNTER_VALUES as $field) {
            $node->setAttribute($field, (string) $metric->{$field});
        }
    }
}
