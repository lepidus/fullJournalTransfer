<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\core\Core;

class NativeDataReferenceValidator
{
    public function validate(DOMElement $root): void
    {
        if ($root->localName !== 'native_data') {
            throw new InvalidArgumentException('Invalid native data root');
        }
        $issueReferences = [];
        $submissionReferences = [];
        $issues = $this->requiredChild($root, 'issues');
        $articles = $this->requiredChild($root, 'articles');
        if ($this->children($issues, 'issue') !== []) {
            $this->validateNativeSchema($issues);
        }
        $this->validateNativeSchema($articles);
        foreach ($this->children($issues, 'issue') as $issue) {
            $sourceReference = $this->internalId($issue, 'issue');
            $this->addUnique($issueReferences, $sourceReference, 'issue');
            $issueArticles = $this->requiredChild($issue, 'articles');
            $this->validateArticles($issueArticles, $submissionReferences);
        }
        foreach ($this->children($this->requiredChild($root, 'issue_orders'), 'issue_order') as $order) {
            $sourceReference = trim($order->getAttribute('issue_ref'));
            if (!isset($issueReferences[$sourceReference])) {
                throw new InvalidArgumentException('Unknown issue order reference');
            }
        }
        $this->validateArticles($articles, $submissionReferences);
        $this->validateFileReferences($root);
    }

    private function validateArticles(DOMElement $articles, array &$submissionReferences): void
    {
        foreach ($this->children($articles, 'article') as $article) {
            $this->addUnique(
                $submissionReferences,
                $this->internalId($article, 'submission'),
                'submission'
            );
            $publicationReferences = [];
            foreach ($this->children($article, 'publication') as $publication) {
                $this->addUnique(
                    $publicationReferences,
                    $this->internalId($publication, 'publication'),
                    'publication'
                );
            }
            $current = trim($article->getAttribute('current_publication_id'));
            if ($current === '' || !isset($publicationReferences[$current])) {
                throw new InvalidArgumentException('Unknown current publication reference');
            }
        }
    }

    private function validateFileReferences(DOMElement $root): void
    {
        foreach ($root->getElementsByTagNameNS('http://pkp.sfu.ca', 'href') as $href) {
            $source = trim($href->getAttribute('src'));
            $segments = preg_split('~[/\\\\]+~', $source);
            if ($source === ''
                || str_starts_with($source, '/')
                || preg_match('~^[a-z][a-z0-9+.-]*:~i', $source)
                || in_array('..', $segments, true)
                || in_array('.', $segments, true)
                || str_contains($source, "\0")
            ) {
                throw new InvalidArgumentException('Unsafe native file reference');
            }
        }
    }

    private function validateNativeSchema(DOMElement $root): void
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($root, true));
        $previous = libxml_use_internal_errors(true);
        $valid = $document->schemaValidate(
            Core::getBaseDir() . '/plugins/importexport/native/native.xsd'
        );
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$valid) {
            $message = $errors ? trim($errors[0]->message) : 'unknown schema error';
            throw new InvalidArgumentException('Invalid OJS Native XML: ' . $message);
        }
    }

    private function internalId(DOMElement $parent, string $entity): string
    {
        foreach ($this->children($parent, 'id') as $id) {
            if ($id->getAttribute('type') === 'internal' && trim($id->textContent) !== '') {
                return trim($id->textContent);
            }
        }
        throw new InvalidArgumentException('Missing ' . $entity . ' source reference');
    }

    private function addUnique(array &$references, string $reference, string $entity): void
    {
        if (isset($references[$reference])) {
            throw new InvalidArgumentException('Duplicated ' . $entity . ' source reference: ' . $reference);
        }
        $references[$reference] = true;
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
