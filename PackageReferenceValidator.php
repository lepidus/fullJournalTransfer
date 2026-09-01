<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use DOMElement;
use InvalidArgumentException;

class PackageReferenceValidator
{
    public function validateReferenceData(DOMElement $node): void
    {
        if ($node->localName !== 'reference_data') {
            throw new InvalidArgumentException('Invalid reference data root');
        }
        $reviewFormReferences = $this->collectReferences(
            $this->requiredContainer($node, 'review_forms'),
            'review_form'
        );
        foreach ($this->children($node, 'sections') as $sections) {
            foreach ($this->children($sections, 'section') as $section) {
                $reference = trim($section->getAttribute('review_form_ref'));
                if ($reference !== '' && !isset($reviewFormReferences[$reference])) {
                    throw new InvalidArgumentException(sprintf(
                        'Unknown review_form_ref "%s" in section source_ref "%s" at line %d',
                        $reference,
                        trim($section->getAttribute('source_ref')),
                        $section->getLineNo()
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
                throw new InvalidArgumentException(sprintf(
                    'Missing source_ref in reference data element "%s" at line %d',
                    $elementName,
                    $element->getLineNo()
                ));
            }
            if (isset($references[$reference])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicated %s source_ref "%s" at line %d',
                    $elementName,
                    $reference,
                    $element->getLineNo()
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
            throw new InvalidArgumentException('Expected exactly one reference data container: ' . $name);
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
