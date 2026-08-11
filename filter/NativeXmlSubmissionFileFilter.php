<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Services;
use DOMElement;
use InvalidArgumentException;
use PKP\config\Config;

class NativeXmlSubmissionFileFilter extends \APP\plugins\importexport\native\filter\NativeXmlArticleFileFilter
{
    public function handleRevisionElement($node)
    {
        $this->validateChecksum($node);
        $fileId = parent::handleRevisionElement($node);
        if ($fileId) {
            $file = Services::get('file')->get($fileId);
            if (!$file) {
                throw new InvalidArgumentException('Imported file was not persisted');
            }
            $path = rtrim((string) Config::getVar('files', 'files_dir'), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . $file->path;
            $this->getDeployment()->recordCreatedFile($path);
        }
        return $fileId;
    }

    private function validateChecksum(DOMElement $node): void
    {
        $expected = $node->getAttribute('checksum');
        if (preg_match('/^[a-f0-9]{64}$/', $expected) !== 1) {
            throw new InvalidArgumentException('Submission file checksum is invalid');
        }
        $embeds = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'embed') {
                $embeds[] = $child;
            }
        }
        if (count($embeds) !== 1 || $embeds[0]->getAttribute('encoding') !== 'base64') {
            throw new InvalidArgumentException('Submission file revision must contain one base64 payload');
        }
        $content = base64_decode($embeds[0]->textContent, true);
        if ($content === false || !hash_equals($expected, hash('sha256', $content))) {
            throw new InvalidArgumentException('Submission file checksum does not match its payload');
        }
    }
}
