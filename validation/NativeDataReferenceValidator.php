<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\validation;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\core\Core;

class NativeDataReferenceValidator
{
    private const SUBMISSION_PROGRESS_VALUES = [
        '',
        'start',
        'details',
        'files',
        'contributors',
        'editors',
        'review',
    ];

    public function validate(DOMElement $root): void
    {
        if ($root->localName !== 'native_data') {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.invalidNativeDataRoot'));
        }
        $issueReferences = [];
        $submissionReferences = [];
        $authorReferences = [];
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
            $this->validateArticles($issueArticles, $submissionReferences, $authorReferences);
        }
        foreach ($this->children($this->requiredChild($root, 'issue_orders'), 'issue_order') as $order) {
            $sourceReference = trim($order->getAttribute('issue_ref'));
            if (!isset($issueReferences[$sourceReference])) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.unknownIssueOrderReferenceLine',
                    [
                        'sourceReference' => $sourceReference,
                        'line' => $order->getLineNo(),
                    ]
                ));
            }
        }
        $this->validateArticles($articles, $submissionReferences, $authorReferences);
        $this->validateAuthorMetadata($this->requiredChild($root, 'author_metadata'), $authorReferences);
        $this->validateHistoricalDates(
            $this->requiredChild($root, 'historical_dates'),
            $issueReferences,
            $submissionReferences
        );
        $this->validateFileReferences($root);
    }

    private function validateHistoricalDates(
        DOMElement $historicalDatesNode,
        array $issueReferences,
        array $submissionReferences
    ): void {
        $metadataIssueReferences = [];
        foreach ($this->children($this->requiredChild($historicalDatesNode, 'issues'), 'issue') as $issueNode) {
            $sourceReference = trim($issueNode->getAttribute('issue_ref'));
            if (!isset($issueReferences[$sourceReference])) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.unknownHistoricalIssueDateReferenceLine',
                    [
                        'sourceReference' => $sourceReference,
                        'line' => $issueNode->getLineNo(),
                    ]
                ));
            }
            $this->addUnique($metadataIssueReferences, $sourceReference, __('plugins.importexport.fullJournal.entity.historicalIssueDate'));
            $this->validateOptionalDateTime($issueNode, 'date_published');
        }
        $this->requireCompleteReferences($issueReferences, $metadataIssueReferences, __('plugins.importexport.fullJournal.entity.issueDate'));

        $metadataSubmissionReferences = [];
        foreach ($this->children(
            $this->requiredChild($historicalDatesNode, 'submissions'),
            'submission'
        ) as $submissionNode) {
            $sourceReference = trim($submissionNode->getAttribute('submission_ref'));
            if (!isset($submissionReferences[$sourceReference])) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.unknownHistoricalSubmissionDateReferenceLine',
                    [
                        'sourceReference' => $sourceReference,
                        'line' => $submissionNode->getLineNo(),
                    ]
                ));
            }
            $this->addUnique(
                $metadataSubmissionReferences,
                $sourceReference,
                __('plugins.importexport.fullJournal.entity.historicalSubmissionDate')
            );
            foreach (['date_submitted', 'date_last_activity', 'last_modified'] as $attribute) {
                $this->validateOptionalDateTime($submissionNode, $attribute);
            }
        }
        $this->requireCompleteReferences(
            $submissionReferences,
            $metadataSubmissionReferences,
            __('plugins.importexport.fullJournal.entity.submissionDate')
        );
    }

    private function validateOptionalDateTime(DOMElement $node, string $attribute): void
    {
        if (!$node->hasAttribute($attribute)) {
            return;
        }
        $value = $node->getAttribute($attribute);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
            $referenceAttribute = $node->hasAttribute('issue_ref') ? 'issue_ref' : 'submission_ref';
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.invalidHistoricalLine',
                [
                    'attribute' => $attribute,
                    'value' => $value,
                    'referenceAttribute' => $referenceAttribute,
                    'sourceReference' => $node->getAttribute($referenceAttribute),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
    }

    private function requireCompleteReferences(array $expected, array $actual, string $entity): void
    {
        foreach ($expected as $sourceReference => $unused) {
            if (!isset($actual[$sourceReference])) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.missingHistoricalReference',
                    [
                        'entity' => $entity,
                        'sourceReference' => $sourceReference,
                    ]
                ));
            }
        }
    }

    private function validateArticles(
        DOMElement $articles,
        array &$submissionReferences,
        array &$authorReferences
    ): void {
        foreach ($this->children($articles, 'article') as $article) {
            $sourceReference = $this->internalId($article, 'submission');
            if (!$article->hasAttribute('submission_progress')) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.missingSubmissionProgressSubmissionSourceRefLine',
                    [
                        'sourceReference' => $sourceReference,
                        'line' => $article->getLineNo(),
                    ]
                ));
            }
            $submissionProgress = $article->getAttribute('submission_progress');
            if (!in_array($submissionProgress, self::SUBMISSION_PROGRESS_VALUES, true)) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.invalidSubmissionProgressSubmissionSourceRefLine',
                    [
                        'submissionProgress' => $submissionProgress,
                        'sourceReference' => $sourceReference,
                        'line' => $article->getLineNo(),
                    ]
                ));
            }
            $this->addUnique(
                $submissionReferences,
                $sourceReference,
                'submission'
            );
            $publicationReferences = [];
            foreach ($this->children($article, 'publication') as $publication) {
                $this->addUnique(
                    $publicationReferences,
                    $this->internalId($publication, 'publication'),
                    'publication'
                );
                foreach ($this->children($publication, 'authors') as $authors) {
                    foreach ($this->children($authors, 'author') as $author) {
                        $this->addUnique(
                            $authorReferences,
                            trim($author->getAttribute('id')),
                            'author'
                        );
                    }
                }
            }
            $current = trim($article->getAttribute('current_publication_id'));
            if ($current === '' || !isset($publicationReferences[$current])) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.unknownCurrentPublicationIdSubmissionSourceRefLine',
                    [
                        'current' => $current,
                        'sourceReference' => $sourceReference,
                        'line' => $article->getLineNo(),
                    ]
                ));
            }
        }
    }

    private function validateAuthorMetadata(DOMElement $metadataNode, array $authorReferences): void
    {
        $metadataReferences = [];
        foreach ($this->children($metadataNode, 'author') as $authorNode) {
            $sourceReference = trim($authorNode->getAttribute('author_ref'));
            if (!isset($authorReferences[$sourceReference])) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.unknownAuthorMetadataReferenceLine',
                    [
                        'sourceReference' => $sourceReference,
                        'line' => $authorNode->getLineNo(),
                    ]
                ));
            }
            $this->addUnique($metadataReferences, $sourceReference, __('plugins.importexport.fullJournal.entity.authorMetadata'));
            $seen = [];
            foreach ($authorNode->childNodes as $valueNode) {
                if ($valueNode->nodeType === XML_TEXT_NODE && trim($valueNode->textContent) === '') {
                    continue;
                }
                if (!$valueNode instanceof DOMElement) {
                    throw new InvalidArgumentException(__(
                        'plugins.importexport.fullJournal.error.invalidAuthorMetadataNodeAuthorRefFoundLine',
                        [
                            'sourceReference' => $sourceReference,
                            'nodeName' => $valueNode->nodeName,
                            'line' => $valueNode->getLineNo(),
                        ]
                    ));
                }
                if (!in_array($valueNode->localName, ['preferred_public_name', 'competing_interests'], true)) {
                    throw new InvalidArgumentException(__(
                        'plugins.importexport.fullJournal.error.invalidAuthorMetadataElementAuthorRefFoundLine',
                        [
                            'sourceReference' => $sourceReference,
                            'localName' => $valueNode->localName,
                            'line' => $valueNode->getLineNo(),
                        ]
                    ));
                }
                $locale = trim($valueNode->getAttribute('locale'));
                $key = $valueNode->localName . ':' . $locale;
                if (preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale) !== 1) {
                    throw new InvalidArgumentException(__(
                        'plugins.importexport.fullJournal.error.invalidAuthorMetadataLocaleAuthorRefElementLocaleLine',
                        [
                            'sourceReference' => $sourceReference,
                            'localName' => $valueNode->localName,
                            'locale' => $locale,
                            'line' => $valueNode->getLineNo(),
                        ]
                    ));
                }
                if (isset($seen[$key])) {
                    throw new InvalidArgumentException(__(
                        'plugins.importexport.fullJournal.error.duplicatedAuthorMetadataLocaleAuthorRefElementLocaleLine',
                        [
                            'sourceReference' => $sourceReference,
                            'localName' => $valueNode->localName,
                            'locale' => $locale,
                            'line' => $valueNode->getLineNo(),
                        ]
                    ));
                }
                $seen[$key] = true;
            }
            if ($seen === []) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.authorMetadataEmpty',
                    [
                        'sourceReference' => $sourceReference,
                        'line' => $authorNode->getLineNo(),
                    ]
                ));
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
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.unsafeNativeFileReferenceLine',
                    [
                        'source' => $source,
                        'line' => $href->getLineNo(),
                    ]
                ));
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
            $message = $errors ? trim($errors[0]->message) : __('plugins.importexport.fullJournal.error.unknownSchemaError');
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.invalidOjsNativeXml',
                [
                    'message' => $message,
                ]
            ));
        }
    }

    private function internalId(DOMElement $parent, string $entity): string
    {
        foreach ($this->children($parent, 'id') as $id) {
            if ($id->getAttribute('type') === 'internal' && trim($id->textContent) !== '') {
                return trim($id->textContent);
            }
        }
        throw new InvalidArgumentException(__(
            'plugins.importexport.fullJournal.error.missingSourceReference',
            [
                'entity' => $entity,
            ]
        ));
    }

    private function addUnique(array &$references, string $reference, string $entity): void
    {
        if (isset($references[$reference])) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.duplicatedSourceReference',
                [
                    'entity' => $entity,
                    'reference' => $reference,
                ]
            ));
        }
        $references[$reference] = true;
    }

    private function requiredChild(DOMElement $parent, string $name): DOMElement
    {
        $matches = $this->children($parent, $name);
        if (count($matches) !== 1) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.expectedNativeDataElement',
                [
                    'name' => $name,
                ]
            ));
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
