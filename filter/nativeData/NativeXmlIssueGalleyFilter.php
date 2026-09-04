<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\nativeData;

use APP\file\IssueFileManager;
use DOMElement;
use InvalidArgumentException;
use PKP\db\DAORegistry;

class NativeXmlIssueGalleyFilter extends \APP\plugins\importexport\native\filter\NativeXmlIssueGalleyFilter
{
    public function handleElement($node)
    {
        $sourceReference = $this->internalId($node);
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
