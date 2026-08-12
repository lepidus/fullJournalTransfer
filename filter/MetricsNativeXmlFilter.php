<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use DOMDocument;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class MetricsNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$context)
    {
        if (!$context instanceof Journal) {
            throw new InvalidArgumentException('Expected a journal for metrics export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS('http://pkp.sfu.ca', 'metrics');
        $document->appendChild($root);
        $metrics = DB::table('metrics_context')
            ->where('context_id', (int) $context->getId())
            ->orderBy('date')
            ->orderBy('metrics_context_id')
            ->get()
            ->all();
        $filter = PKPImportExportFilter::getFilter(
            'context-metric=>full-journal-metrics-xml',
            $this->getDeployment()
        );
        $metricsDocument = $filter->execute($metrics);
        $root->appendChild($document->importNode($metricsDocument->documentElement, true));
        foreach ([
            'metrics_submission' => 'submission-metric=>full-journal-metrics-xml',
            'metrics_issue' => 'issue-metric=>full-journal-metrics-xml',
        ] as $table => $group) {
            $rows = DB::table($table)
                ->where('context_id', (int) $context->getId())
                ->orderBy('date')
                ->orderBy($table . '_id')
                ->get()
                ->all();
            $entityFilter = PKPImportExportFilter::getFilter($group, $this->getDeployment());
            $entityDocument = $entityFilter->execute($rows);
            $root->appendChild($document->importNode($entityDocument->documentElement, true));
        }
        foreach ([
            'geo-metric-family=>full-journal-metrics-xml' => [
                'daily' => 'metrics_submission_geo_daily',
                'monthly' => 'metrics_submission_geo_monthly',
            ],
            'counter-metric-family=>full-journal-metrics-xml' => [
                'daily' => 'metrics_counter_submission_daily',
                'monthly' => 'metrics_counter_submission_monthly',
            ],
        ] as $group => $tables) {
            $family = new \stdClass();
            foreach ($tables as $granularity => $table) {
                $family->{$granularity} = DB::table($table)
                    ->where('context_id', (int) $context->getId())
                    ->orderBy($granularity === 'daily' ? 'date' : 'month')
                    ->orderBy($table . '_id')
                    ->get()
                    ->all();
            }
            $familyFilter = PKPImportExportFilter::getFilter($group, $this->getDeployment());
            $familyDocument = $familyFilter->execute($family);
            $root->appendChild($document->importNode($familyDocument->documentElement, true));
        }
        $institutionMetrics = new \stdClass();
        foreach ([
            'daily' => 'metrics_counter_submission_institution_daily',
            'monthly' => 'metrics_counter_submission_institution_monthly',
        ] as $granularity => $table) {
            $institutionMetrics->{$granularity} = DB::table($table . ' as metric')
                ->join('institutions as institution', 'institution.institution_id', '=', 'metric.institution_id')
                ->where('metric.context_id', (int) $context->getId())
                ->orderBy($granularity === 'daily' ? 'metric.date' : 'metric.month')
                ->orderBy('metric.' . $table . '_id')
                ->select(['metric.*', 'institution.ror as institution_ror'])
                ->get()
                ->all();
        }
        $institutionFilter = PKPImportExportFilter::getFilter(
            'institution-metric-family=>full-journal-metrics-xml',
            $this->getDeployment()
        );
        $institutionDocument = $institutionFilter->execute($institutionMetrics);
        $root->appendChild($document->importNode($institutionDocument->documentElement, true));
        return $document;
    }
}
