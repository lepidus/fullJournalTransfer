<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Services;
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
        return $fileId;
    }

}
