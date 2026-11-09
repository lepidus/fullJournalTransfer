<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\nativeData;

use APP\core\Services;
use APP\plugins\importexport\fullJournalTransfer\validation\PackageFileReferenceValidator;
use DOMElement;
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
        $this->validatePackageFileReferences($node);
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
        return $fileId;
    }

    private function validatePackageFileReferences(DOMElement $node): void
    {
        $validator = new PackageFileReferenceValidator();
        $packagePath = (string) $this->getDeployment()->getImportPath();
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'href') {
                $validator->validate($child, $packagePath);
            }
        }
    }
}
