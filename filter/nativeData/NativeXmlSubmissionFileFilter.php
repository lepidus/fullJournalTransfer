<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\nativeData;

use APP\core\Application;
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
                throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.sourceSubmissionFileNotImported'));
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
            throw new RuntimeException(__('plugins.importexport.fullJournal.error.temporaryDirectoryCreationFailed'));
        }
        $fileId = parent::handleRevisionElement($node);
        if (!$fileId) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.fileRevisionSaveFailed'));
        }
        $file = Services::get('file')->get($fileId);
        if (!$file) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.importedFileNotSaved'));
        }
        $path = rtrim((string) Config::getVar('files', 'files_dir'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $file->path;
        $absolutePath = realpath($path);
        if ($absolutePath === false) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.importedFilePathResolutionFailed'));
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
                $this->getDeployment()->addWarning(
                    Application::ASSOC_TYPE_NONE,
                    0,
                    __('plugins.importexport.fullJournal.warning.invalidExportedMimeTypeFileRevisionLine', [
                        'fileId' => (int) $node->getAttribute('id'),
                        'line' => $child->getLineNo(),
                    ])
                );
                return null;
            }
            if ($mimeType !== null && $mimeType !== $value) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.conflictingExportedMimeTypesFileRevisionLine',
                    [
                        'line' => $node->getLineNo(),
                    ]
                ));
            }
            $mimeType = $value;
        }
        return $mimeType;
    }
}
