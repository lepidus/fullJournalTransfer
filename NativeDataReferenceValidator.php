<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use DOMElement;
use InvalidArgumentException;

class NativeDataReferenceValidator
{
    public function validate(DOMElement $root): void
    {
        if ($root->localName !== 'native_data') {
            throw new InvalidArgumentException('Invalid native data root');
        }
        $issueReferences = $this->collectRecords(
            $this->requiredContainer($root, 'issues'),
            'issue_record',
            'issue'
        );
        $currentIssueReference = trim($root->getAttribute('current_issue_ref'));
        if ($currentIssueReference !== '' && !isset($issueReferences[$currentIssueReference])) {
            throw new InvalidArgumentException('Unknown current issue reference');
        }
        $submissions = $this->requiredContainer($root, 'submissions');
        $this->collectRecords($submissions, 'submission_record', 'submission');
        foreach ($this->children($submissions, 'submission_record') as $record) {
            $article = $this->requiredChild($record, 'article');
            $publicationReferences = [];
            foreach ($this->children($article, 'publication') as $publication) {
                $sourceReference = $this->internalId($publication);
                if (isset($publicationReferences[$sourceReference])) {
                    throw new InvalidArgumentException('Duplicated publication source reference');
                }
                $publicationReferences[$sourceReference] = true;
                $issueReference = trim($publication->getAttribute('issue_ref'));
                $hasIssueIdentification = $this->children($publication, 'issue_identification') !== [];
                if ($hasIssueIdentification && $issueReference === '') {
                    throw new InvalidArgumentException('Missing issue reference in publication');
                }
                if ($issueReference !== '' && !isset($issueReferences[$issueReference])) {
                    throw new InvalidArgumentException('Unknown issue reference in publication');
                }
            }
            $currentPublicationReference = trim($article->getAttribute('current_publication_id'));
            if ($currentPublicationReference === '' || !isset($publicationReferences[$currentPublicationReference])) {
                throw new InvalidArgumentException('Unknown current publication reference');
            }
        }
        $this->validateChecksums($root);
    }

    private function collectRecords(
        DOMElement $container,
        string $recordName,
        string $entityName
    ): array {
        $references = [];
        foreach ($this->children($container, $recordName) as $record) {
            $reference = trim($record->getAttribute('source_ref'));
            if ($reference === '') {
                throw new InvalidArgumentException('Missing ' . $entityName . ' source reference');
            }
            if (isset($references[$reference])) {
                throw new InvalidArgumentException('Duplicated ' . $entityName . ' source reference');
            }
            $references[$reference] = true;
            $this->requiredChild($record, $entityName === 'submission' ? 'article' : $entityName);
        }
        return $references;
    }

    private function internalId(DOMElement $parent): string
    {
        foreach ($this->children($parent, 'id') as $id) {
            if ($id->getAttribute('type') === 'internal') {
                $value = trim($id->textContent);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        throw new InvalidArgumentException('Missing publication source reference');
    }

    private function validateChecksums(DOMElement $root): void
    {
        foreach (['file', 'issue_file'] as $elementName) {
            foreach ($root->getElementsByTagNameNS('http://pkp.sfu.ca', $elementName) as $file) {
                if ($elementName === 'file'
                    && (!$file->parentNode instanceof DOMElement || $file->parentNode->localName !== 'submission_file')
                ) {
                    continue;
                }
                $expected = $file->getAttribute('checksum');
                $embeds = $this->children($file, 'embed');
                $content = count($embeds) === 1 && $embeds[0]->getAttribute('encoding') === 'base64'
                    ? base64_decode($embeds[0]->textContent, true)
                    : false;
                if (preg_match('/^[a-f0-9]{64}$/', $expected) !== 1
                    || $content === false
                    || !hash_equals($expected, hash('sha256', $content))
                ) {
                    throw new InvalidArgumentException('File checksum does not match its payload');
                }
            }
        }
    }

    private function requiredContainer(DOMElement $parent, string $name): DOMElement
    {
        return $this->requiredChild($parent, $name);
    }

    private function requiredChild(DOMElement $parent, string $name): DOMElement
    {
        $matches = $this->children($parent, $name);
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('Expected exactly one native data element: ' . $name);
        }
        return $matches[0];
    }

    private function children(DOMElement $parent, string $name): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $children[] = $child;
            }
        }
        return $children;
    }
}
