<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class ReviewRoundFileNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$files)
    {
        if (!is_array($files)) {
            throw new InvalidArgumentException('Expected review round files for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'review_round_files');
        $document->appendChild($root);
        foreach ($files as $file) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'review_round_file');
            $node->setAttribute('review_round_ref', (string) $file->review_round_id);
            $node->setAttribute('submission_ref', (string) $file->submission_id);
            $node->setAttribute('submission_file_ref', (string) $file->submission_file_id);
            $node->setAttribute('stage_id', (string) $file->stage_id);
            $root->appendChild($node);
        }
        return $document;
    }
}
