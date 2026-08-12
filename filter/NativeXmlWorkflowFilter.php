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
            foreach ([
                'stage_assignments' => 'full-journal-workflow-xml=>stage-assignment',
                'review_rounds' => 'full-journal-workflow-xml=>review-round',
                'discussions' => 'full-journal-workflow-xml=>discussion',
                'editorial_decisions' => 'full-journal-workflow-xml=>editorial-decision',
            ] as $element => $group) {
                $filter = PKPImportExportFilter::getFilter($group, $deployment);
                $document = $this->documentFor($this->requiredChild($root, $element));
                $filter->execute($document);
            }
            return $deployment->getContext();
        });
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

    private function documentFor(DOMElement $element): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($element, true));
        return $document;
    }
}
