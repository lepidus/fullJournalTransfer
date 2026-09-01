<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\plugins\importexport\fullJournalTransfer\HistoricalDiscussionPersistenceAdapter;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlDiscussionFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'discussions';
    }

    public function getSingularElementName()
    {
        return 'discussion';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $sourceReference = $this->required($node, 'source_ref');
        $discussion = (new HistoricalDiscussionPersistenceAdapter())->insertDiscussion([
            'submission_id' => $deployment->requireReference(
                'submission',
                $this->required($node, 'submission_ref')
            ),
            'stage_id' => $this->positiveInteger($node, 'stage_id'),
            'closed' => $this->boolean($node, 'closed'),
            'sequence' => $this->number($node, 'sequence'),
        ]);
        $deployment->mapReference('discussion', $sourceReference, (int) $discussion->getId());
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'discussion_participant') {
                $this->importParticipant($child);
            }
        }
        $notes = $this->childrenDocument($node, 'discussion_note', 'discussion_notes');
        if ($notes) {
            PKPImportExportFilter::getFilter(
                'full-journal-workflow-xml=>discussion-note',
                $deployment
            )->execute($notes);
        }
        return $discussion;
    }

    private function importParticipant(DOMElement $node): void
    {
        $deployment = $this->getDeployment();
        $discussionId = $deployment->requireReference('discussion', $this->required($node, 'discussion_ref'));
        $userId = $deployment->requireReference('user', $this->required($node, 'user_ref'));
        (new HistoricalDiscussionPersistenceAdapter())->insertParticipant($discussionId, $userId);
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing discussion attribute "%s" for reference "%s" at line %d',
                $attribute,
                $this->reference($node),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function positiveInteger($node, string $attribute): int
    {
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid discussion %s "%s" for reference "%s" at line %d; expected a positive integer',
                $attribute,
                $node->getAttribute($attribute),
                $this->reference($node),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function boolean($node, string $attribute): int
    {
        $value = $node->getAttribute($attribute);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid discussion %s "%s" for reference "%s" at line %d; expected "true" or "false"',
                $attribute,
                $value,
                $this->reference($node),
                $node->getLineNo()
            ));
        }
        return $value === 'true' ? 1 : 0;
    }

    private function number($node, string $attribute): float
    {
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_FLOAT);
        if ($value === false || $value < 0) {
            throw new InvalidArgumentException(sprintf(
                'Invalid discussion %s "%s" for reference "%s" at line %d; expected a non-negative number',
                $attribute,
                $node->getAttribute($attribute),
                $this->reference($node),
                $node->getLineNo()
            ));
        }
        return (float) $value;
    }

    private function reference(DOMElement $node): string
    {
        return $node->hasAttribute('source_ref')
            ? $node->getAttribute('source_ref')
            : $node->getAttribute('discussion_ref');
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
