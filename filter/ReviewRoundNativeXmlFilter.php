<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class ReviewRoundNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$rounds)
    {
        if (!is_array($rounds)) {
            throw new InvalidArgumentException('Expected review rounds for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'review_rounds');
        $document->appendChild($root);
        foreach ($rounds as $round) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'review_round');
            $node->setAttribute('source_ref', (string) $round->review_round_id);
            $node->setAttribute('submission_ref', (string) $round->submission_id);
            $node->setAttribute('stage_id', (string) $round->stage_id);
            $node->setAttribute('round', (string) $round->round);
            $node->setAttribute('status', (string) $round->status);
            $assignments = \Illuminate\Support\Facades\DB::table('review_assignments')
                ->where('review_round_id', $round->review_round_id)
                ->orderBy('review_id')
                ->get()
                ->all();
            $filter = PKPImportExportFilter::getFilter(
                'review-assignment=>full-journal-workflow-xml',
                $this->getDeployment()
            );
            $assignmentDocument = $filter->execute($assignments);
            foreach ($assignmentDocument->documentElement->childNodes as $assignmentNode) {
                if ($assignmentNode instanceof \DOMElement) {
                    $imported = $document->importNode($assignmentNode, true);
                    $imported->setAttribute('review_round_ref', (string) $round->review_round_id);
                    $node->appendChild($imported);
                }
            }
            $files = \Illuminate\Support\Facades\DB::table('review_round_files')
                ->where('review_round_id', $round->review_round_id)
                ->orderBy('review_round_file_id')
                ->get();
            foreach ($files as $file) {
                $fileNode = $document->createElementNS('http://pkp.sfu.ca', 'review_round_file');
                $fileNode->setAttribute('submission_file_ref', (string) $file->submission_file_id);
                $fileNode->setAttribute('stage_id', (string) $file->stage_id);
                $node->appendChild($fileNode);
            }
            $root->appendChild($node);
        }
        return $document;
    }
}
