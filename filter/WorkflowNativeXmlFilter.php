<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use DOMDocument;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class WorkflowNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$context)
    {
        if (!$context instanceof Journal) {
            throw new InvalidArgumentException('Expected a journal for workflow export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS('http://pkp.sfu.ca', 'workflow_history');
        $document->appendChild($root);

        $assignments = DB::table('stage_assignments as assignment')
            ->join('submissions as submission', 'submission.submission_id', '=', 'assignment.submission_id')
            ->where('submission.context_id', (int) $context->getId())
            ->orderBy('assignment.stage_assignment_id')
            ->select('assignment.*')
            ->get()
            ->all();
        $assignmentFilter = PKPImportExportFilter::getFilter(
            'stage-assignment=>full-journal-workflow-xml',
            $this->getDeployment()
        );
        $assignmentDocument = $assignmentFilter->execute($assignments);
        $root->appendChild($document->importNode($assignmentDocument->documentElement, true));

        $rounds = DB::table('review_rounds as review_round')
            ->join('submissions as submission', 'submission.submission_id', '=', 'review_round.submission_id')
            ->where('submission.context_id', (int) $context->getId())
            ->orderBy('review_round.submission_id')
            ->orderBy('review_round.stage_id')
            ->orderBy('review_round.round')
            ->select('review_round.*')
            ->get()
            ->all();
        $roundFilter = PKPImportExportFilter::getFilter(
            'review-round=>full-journal-workflow-xml',
            $this->getDeployment()
        );
        $roundDocument = $roundFilter->execute($rounds);
        $root->appendChild($document->importNode($roundDocument->documentElement, true));
        return $document;
    }
}
