<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlWorkflowFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'workflow_history_collection';
    }

    public function getSingularElementName()
    {
        return 'workflow_history';
    }

    public function handleElement($root)
    {
        return DB::transaction(function () use ($root) {
            $deployment = $this->getDeployment();
            $deployment->resetReferenceMap('stage_assignment');
            $deployment->resetReferenceMap('review_round');
            $deployment->resetReferenceMap('review_assignment');
            $deployment->resetReferenceMap('discussion');
            $deployment->resetReferenceMap('discussion_note');
            $deployment->resetReferenceMap('discussion_attachment');
            $deployment->resetReferenceMap('editorial_decision');
            $this->importContainer(
                $root,
                'stage_assignments',
                'full-journal-workflow-xml=>stage-assignment'
            );
            $reviewRounds = $this->requiredChild($root, 'review_rounds');
            $this->importElement($reviewRounds, 'full-journal-workflow-xml=>review-round');
            $this->importContainer(
                $root,
                'workflow_files',
                'full-journal-workflow-xml=>workflow-submission-file'
            );
            $this->importReviewRoundChildren($reviewRounds);
            $this->importContainer($root, 'discussions', 'full-journal-workflow-xml=>discussion');
            $this->importContainer(
                $root,
                'editorial_decisions',
                'full-journal-workflow-xml=>editorial-decision'
            );
            return $deployment->getContext();
        });
    }

    private function importReviewRoundChildren(DOMElement $reviewRounds): void
    {
        foreach ($this->children($reviewRounds, 'review_round') as $reviewRound) {
            foreach ([
                'review_assignment' => ['review_assignments', 'full-journal-workflow-xml=>review-assignment'],
                'review_round_file' => ['review_round_files', 'full-journal-workflow-xml=>review-round-file'],
            ] as $element => [$container, $group]) {
                $document = $this->childrenDocument($reviewRound, $element, $container);
                if ($document) {
                    $filter = PKPImportExportFilter::getFilter($group, $this->getDeployment());
                    $filter->execute($document);
                }
            }
        }
    }

    private function importContainer(DOMElement $root, string $element, string $group): void
    {
        $this->importElement($this->requiredChild($root, $element), $group);
    }

    private function importElement(DOMElement $element, string $group): void
    {
        $filter = PKPImportExportFilter::getFilter($group, $this->getDeployment());
        $document = $this->documentFor($element);
        $filter->execute($document);
    }

    private function requiredChild(DOMElement $parent, string $name): DOMElement
    {
        $matches = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $matches[] = $child;
            }
        }
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('Expected exactly one workflow element: ' . $name);
        }
        return $matches[0];
    }

    private function childrenDocument(DOMElement $parent, string $element, string $container): ?DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', $container);
        foreach ($this->children($parent, $element) as $child) {
            $root->appendChild($document->importNode($child, true));
        }
        if (!$root->hasChildNodes()) {
            return null;
        }
        $document->appendChild($root);
        return $document;
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

    private function documentFor(DOMElement $element): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($element, true));
        return $document;
    }
}
