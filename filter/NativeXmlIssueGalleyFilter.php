<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\file\IssueFileManager;
use DOMElement;
use InvalidArgumentException;
use PKP\db\DAORegistry;

class NativeXmlIssueGalleyFilter extends \APP\plugins\importexport\native\filter\NativeXmlIssueGalleyFilter
{
    public function handleElement($node)
    {
        $sourceReference = $this->internalId($node);
        $this->validateChecksum($node);
        $galley = parent::handleElement($node);
        if ($galley) {
            $this->getDeployment()->mapReference('issue_galley', $sourceReference, (int) $galley->getId());
            $file = DAORegistry::getDAO('IssueFileDAO')->getById($galley->getFileId());
            if ($file) {
                $manager = new IssueFileManager($galley->getIssueId());
                $path = $manager->getFilesDir() . $manager->contentTypeToPath($file->getContentType())
                    . DIRECTORY_SEPARATOR . $file->getServerFileName();
                $absolutePath = realpath($path);
                if ($absolutePath === false) {
                    throw new InvalidArgumentException('Imported issue galley file path could not be resolved');
                }
                $this->getDeployment()->recordCreatedFile($absolutePath);
            }
        }
        return $galley;
    }

    private function validateChecksum(DOMElement $node): void
    {
        $files = $node->getElementsByTagNameNS('http://pkp.sfu.ca', 'issue_file');
        if ($files->length !== 1) {
            throw new InvalidArgumentException('Issue galley must contain one file');
        }
        $file = $files->item(0);
        $expected = $file->getAttribute('checksum');
        $embeds = $file->getElementsByTagNameNS('http://pkp.sfu.ca', 'embed');
        $content = $embeds->length === 1 ? base64_decode($embeds->item(0)->textContent, true) : false;
        if (preg_match('/^[a-f0-9]{64}$/', $expected) !== 1
            || $content === false
            || !hash_equals($expected, hash('sha256', $content))
        ) {
            throw new InvalidArgumentException('Issue galley checksum does not match its payload');
        }
    }

    private function internalId(DOMElement $node): string
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'id' && $child->getAttribute('type') === 'internal') {
                return trim($child->textContent);
            }
        }
        throw new InvalidArgumentException('Issue galley source reference is missing');
    }
}
