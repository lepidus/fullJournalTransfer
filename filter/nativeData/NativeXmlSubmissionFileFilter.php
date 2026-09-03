<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\nativeData;

use APP\core\Services;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\config\Config;
use PKP\file\TemporaryFileManager;
use RuntimeException;

class NativeXmlSubmissionFileFilter extends \APP\plugins\importexport\native\filter\NativeXmlArticleFileFilter
{
    public function handleElement($node)
    {
        $sourceId = trim($node->getAttribute('source_submission_file_id'));
        if ($sourceId !== '') {
            $destinationId = $this->getDeployment()->getSubmissionFileDBId($sourceId);
            if (!$destinationId) {
                throw new InvalidArgumentException('Source submission file has not been imported');
            }
            $node->setAttribute('source_submission_file_id', (string) $destinationId);
        }
        return parent::handleElement($node);
    }

    public function handleRevisionElement($node)
    {
        $exportedMimeType = $this->getExportedMimeType($node);
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryPath = $temporaryFileManager->getBasePath();
        if (!is_dir($temporaryPath) && !$temporaryFileManager->mkdirtree($temporaryPath)) {
            throw new RuntimeException('Temporary file directory could not be created');
        }
        $fileId = parent::handleRevisionElement($node);
        if (!$fileId) {
            throw new InvalidArgumentException('Imported file revision could not be persisted');
        }
        $file = Services::get('file')->get($fileId);
        if (!$file) {
            throw new InvalidArgumentException('Imported file was not persisted');
        }
        $path = rtrim((string) Config::getVar('files', 'files_dir'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $file->path;
        $absolutePath = realpath($path);
        if ($absolutePath === false) {
            throw new InvalidArgumentException('Imported file path could not be resolved');
        }
        $this->getDeployment()->recordCreatedFile($absolutePath);
        if ($exportedMimeType !== null) {
            DB::table('files')->where('file_id', $fileId)->update(['mimetype' => $exportedMimeType]);
        }
        return $fileId;
    }

    private function getExportedMimeType($node): ?string
    {
        $mimeType = null;
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->localName !== 'href' || !$child->hasAttribute('mime_type')) {
                continue;
            }
            $value = $child->getAttribute('mime_type');
            $token = "[a-z0-9!#$%&'*+.^_`|~-]+";
            if (strlen($value) > 255 || preg_match('@\\A' . $token . '/' . $token . '\\z@iD', $value) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid exported MIME type for file revision at line %d',
                    $child->getLineNo()
                ));
            }
            if ($mimeType !== null && $mimeType !== $value) {
                throw new InvalidArgumentException(sprintf(
                    'Conflicting exported MIME types for file revision at line %d',
                    $node->getLineNo()
                ));
            }
            $mimeType = $value;
        }
        return $mimeType;
    }
}
