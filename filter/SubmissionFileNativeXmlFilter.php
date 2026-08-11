<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Services;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\config\Config;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileNativeXmlFilter extends \PKP\plugins\importexport\native\filter\SubmissionFileNativeXmlFilter
{
    public function createSubmissionFileNode(DOMDocument $document, SubmissionFile $submissionFile): ?DOMElement
    {
        $node = parent::createSubmissionFileNode($document, $submissionFile);
        if (!$node) {
            return null;
        }
        $basePath = rtrim((string) Config::getVar('files', 'files_dir'), DIRECTORY_SEPARATOR);
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->localName !== 'file') {
                continue;
            }
            $file = Services::get('file')->get((int) $child->getAttribute('id'));
            $path = $file ? $basePath . DIRECTORY_SEPARATOR . $file->path : '';
            $checksum = $path !== '' ? hash_file('sha256', $path) : false;
            if ($checksum === false) {
                throw new InvalidArgumentException('Submission file checksum could not be calculated');
            }
            $child->setAttribute('checksum', $checksum);
        }
        return $node;
    }
}
