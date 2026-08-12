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

        $discussions = DB::table('queries as discussion')
            ->join('submissions as submission', 'submission.submission_id', '=', 'discussion.assoc_id')
            ->where('discussion.assoc_type', \APP\core\Application::ASSOC_TYPE_SUBMISSION)
            ->where('submission.context_id', (int) $context->getId())
            ->orderBy('discussion.assoc_id')
            ->orderBy('discussion.stage_id')
            ->orderBy('discussion.seq')
            ->select('discussion.*')
            ->get()
            ->all();
        $discussionFilter = PKPImportExportFilter::getFilter(
            'discussion=>full-journal-workflow-xml',
            $this->getDeployment()
        );
        $discussionDocument = $discussionFilter->execute($discussions);
        $root->appendChild($document->importNode($discussionDocument->documentElement, true));

        $decisions = DB::table('edit_decisions as decision')
            ->join('submissions as submission', 'submission.submission_id', '=', 'decision.submission_id')
            ->where('submission.context_id', (int) $context->getId())
            ->orderBy('decision.date_decided')
            ->orderBy('decision.edit_decision_id')
            ->select('decision.*')
            ->get()
            ->all();
        $decisionFilter = PKPImportExportFilter::getFilter(
            'editorial-decision=>full-journal-workflow-xml',
            $this->getDeployment()
        );
        $decisionDocument = $decisionFilter->execute($decisions);
        $root->appendChild($document->importNode($decisionDocument->documentElement, true));
        return $document;
    }
}
