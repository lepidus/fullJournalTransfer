<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMElement;
use InvalidArgumentException;

trait NativeXmlReferenceDataFilterTrait
{
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
                throw new InvalidArgumentException(sprintf(
                    'Duplicated localized %s locale "%s" for %s source_ref "%s" at line %d',
                    $name,
                    $locale,
                    $parent->localName,
                    $parent->getAttribute('source_ref'),
                    $node->getLineNo()
                ));
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
            throw new InvalidArgumentException(sprintf(
                'Duplicated %s source_ref "%s" at line %d',
                $node->localName,
                $reference,
                $node->getLineNo()
            ));
        }
        return $reference;
    }

    private function requiredAttribute(DOMElement $node, string $name): string
    {
        $value = trim($node->getAttribute($name));
        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing reference data attribute "%s" in %s source_ref "%s" at line %d',
                $name,
                $node->localName,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function booleanAttribute(DOMElement $node, string $name): bool
    {
        $value = $this->requiredAttribute($node, $name);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s value "%s" in %s source_ref "%s" at line %d; expected "true" or "false"',
                $name,
                $value,
                $node->localName,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value === 'true';
    }

    private function integerAttribute(DOMElement $node, string $name): int
    {
        $value = $this->requiredAttribute($node, $name);
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException(sprintf(
                'Invalid integer %s "%s" in %s source_ref "%s" at line %d',
                $name,
                $value,
                $node->localName,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return (int) $value;
    }

    private function floatAttribute(DOMElement $node, string $name): float
    {
        $value = $this->requiredAttribute($node, $name);
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid numeric %s "%s" in %s source_ref "%s" at line %d',
                $name,
                $value,
                $node->localName,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return (float) $value;
    }

    private function localeAttribute(DOMElement $node): string
    {
        $locale = $this->requiredAttribute($node, 'locale');
        if (preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid locale "%s" in localized %s for %s source_ref "%s" at line %d',
                $locale,
                $node->localName,
                $node->parentNode instanceof DOMElement ? $node->parentNode->localName : 'reference data',
                $node->parentNode instanceof DOMElement ? $node->parentNode->getAttribute('source_ref') : '',
                $node->getLineNo()
            ));
        }
        return $locale;
    }
}
