<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

trait NativeXmlReferenceDataFilterTrait
{
    private function appendLocalized(DOMDocument $document, DOMElement $parent, string $name, $values): void
    {
        foreach ((array) $values as $locale => $value) {
            if ($value === null) {
                continue;
            }
            $node = $document->createElementNS('http://pkp.sfu.ca', $name);
            $node->setAttribute('locale', (string) $locale);
            $node->appendChild($document->createTextNode((string) $value));
            $parent->appendChild($node);
        }
    }

    private function applyLocalized(DOMElement $parent, string $name, callable $setter, bool $required): void
    {
        $nodes = $this->children($parent, $name);
        if ($required && $nodes === []) {
            throw new InvalidArgumentException('Missing localized reference data: ' . $name);
        }
        $locales = [];
        foreach ($nodes as $node) {
            $locale = $this->localeAttribute($node);
            if (isset($locales[$locale])) {
                throw new InvalidArgumentException('Duplicated localized reference data: ' . $name);
            }
            $locales[$locale] = true;
            $setter($node->textContent, $locale);
        }
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

    private function requiredContainer(DOMElement $parent, string $name): DOMElement
    {
        $matches = $this->children($parent, $name);
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('Expected exactly one reference data container: ' . $name);
        }
        return $matches[0];
    }

    private function sourceReference(DOMElement $node, array $map): string
    {
        $reference = $this->requiredAttribute($node, 'source_ref');
        if (isset($map[$reference])) {
            throw new InvalidArgumentException('Duplicated source reference in reference data');
        }
        return $reference;
    }

    private function requiredAttribute(DOMElement $node, string $name): string
    {
        $value = trim($node->getAttribute($name));
        if ($value === '') {
            throw new InvalidArgumentException('Missing reference data attribute: ' . $name);
        }
        return $value;
    }

    private function booleanAttribute(DOMElement $node, string $name): bool
    {
        $value = $this->requiredAttribute($node, $name);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException('Invalid boolean reference data attribute: ' . $name);
        }
        return $value === 'true';
    }

    private function integerAttribute(DOMElement $node, string $name): int
    {
        $value = $this->requiredAttribute($node, $name);
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Invalid integer reference data attribute: ' . $name);
        }
        return (int) $value;
    }

    private function floatAttribute(DOMElement $node, string $name): float
    {
        $value = $this->requiredAttribute($node, $name);
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Invalid numeric reference data attribute: ' . $name);
        }
        return (float) $value;
    }

    private function localeAttribute(DOMElement $node): string
    {
        $locale = $this->requiredAttribute($node, 'locale');
        if (preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale) !== 1) {
            throw new InvalidArgumentException('Invalid locale in reference data');
        }
        return $locale;
    }
}
