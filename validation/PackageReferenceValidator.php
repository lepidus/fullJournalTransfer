<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\validation;

use DOMElement;
use InvalidArgumentException;

class PackageReferenceValidator
{
    public function validateReferenceData(DOMElement $node): void
    {
        if ($node->localName !== 'reference_data') {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.invalidReferenceDataRoot'));
        }
        $reviewFormReferences = $this->collectReferences(
            $this->requiredContainer($node, 'review_forms'),
            'review_form'
        );
        foreach ($this->children($node, 'sections') as $sections) {
            foreach ($this->children($sections, 'section') as $section) {
                $reference = trim($section->getAttribute('review_form_ref'));
                if ($reference !== '' && !isset($reviewFormReferences[$reference])) {
                    throw new InvalidArgumentException(__(
                        'plugins.importexport.fullJournal.error.unknownReviewFormRefSectionSourceRefLine',
                        [
                            'reference' => $reference,
                            'sourceRef' => trim($section->getAttribute('source_ref')),
                            'line' => $section->getLineNo(),
                        ]
                    ));
                }
            }
        }
        $this->collectReferences($this->requiredContainer($node, 'genres'), 'genre');
        $this->collectReferences($this->requiredContainer($node, 'sections'), 'section');
    }

    private function collectReferences(DOMElement $container, string $elementName): array
    {
        $references = [];
        foreach ($this->children($container, $elementName) as $element) {
            $reference = trim($element->getAttribute('source_ref'));
            if ($reference === '') {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.missingSourceRefReferenceDataElementLine',
                    [
                        'elementName' => $elementName,
                        'line' => $element->getLineNo(),
                    ]
                ));
            }
            if (isset($references[$reference])) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.duplicatedSourceRefLine',
                    [
                        'localName' => $elementName,
                        'reference' => $reference,
                        'line' => $element->getLineNo(),
                    ]
                ));
            }
            $references[$reference] = true;
        }
        return $references;
    }

    private function requiredContainer(DOMElement $parent, string $name): DOMElement
    {
        $matches = $this->children($parent, $name);
        if (count($matches) !== 1) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.expectedReferenceDataContainer',
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
