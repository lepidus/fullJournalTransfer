<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\workflow;

use APP\plugins\importexport\fullJournalTransfer\persistence\HistoricalDiscussionPersistenceAdapter;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlDiscussionNoteFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'discussion_notes';
    }

    public function getSingularElementName()
    {
        return 'discussion_note';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $sourceReference = $this->required($node, 'source_ref');
        $note = (new HistoricalDiscussionPersistenceAdapter())->insertNote([
            'discussion_id' => $deployment->requireReference(
                'discussion',
                $this->required($node, 'discussion_ref')
            ),
            'user_id' => $deployment->requireReference('user', $this->required($node, 'user_ref')),
            'date_created' => $this->requiredDate($node, 'date_created'),
            'date_modified' => $this->optionalDate($node, 'date_modified'),
            'title' => $this->text($node, 'title'),
            'contents' => $this->text($node, 'contents'),
        ]);
        $deployment->mapReference('discussion_note', $sourceReference, (int) $note->getId());
        $attachments = $this->childrenDocument($node, 'discussion_attachment', 'discussion_attachments');
        if ($attachments) {
            $filter = PKPImportExportFilter::getFilter(
                'full-journal-workflow-xml=>discussion-attachment',
                $deployment
            );
            $filter->execute($attachments);
        }
        return $note;
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing discussion note attribute "%s" for source_ref "%s" at line %d',
                $attribute,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function requiredDate($node, string $attribute): string
    {
        $value = $this->required($node, $attribute);
        return $this->date($node, $attribute, $value);
    }

    private function optionalDate($node, string $attribute): ?string
    {
        $value = trim($node->getAttribute($attribute));
        return $value === '' ? null : $this->date($node, $attribute, $value);
    }

    private function date(DOMElement $node, string $attribute, string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException(sprintf(
                'Invalid discussion note %s "%s" for source_ref "%s" at line %d',
                $attribute,
                $value,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function text(DOMElement $parent, string $name): string
    {
        $matches = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $matches[] = $child;
            }
        }
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('Expected exactly one discussion note element: ' . $name);
        }
        return $matches[0]->textContent;
    }

    private function childrenDocument(DOMElement $parent, string $element, string $container): ?DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', $container);
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $element) {
                $root->appendChild($document->importNode($child, true));
            }
        }
        if (!$root->hasChildNodes()) {
            return null;
        }
        $document->appendChild($root);
        return $document;
    }
}
