<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\referenceData;

use DOMElement;
use InvalidArgumentException;

trait NativeXmlReferenceDataFilterTrait
{
    private function applyLocalized(DOMElement $parent, string $name, callable $setter, bool $required): void
    {
        $nodes = $this->children($parent, $name);
        if ($required && $nodes === []) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.missingLocalizedReferenceData',
                [
                    'name' => $name,
                ]
            ));
        }
        $locales = [];
        foreach ($nodes as $node) {
            $locale = $this->localeAttribute($node);
            if (isset($locales[$locale])) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.duplicatedLocalizedLocaleSourceRefLine',
                    [
                        'name' => $name,
                        'locale' => $locale,
                        'localName' => $parent->localName,
                        'sourceRef' => $parent->getAttribute('source_ref'),
                        'line' => $node->getLineNo(),
                    ]
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
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.expectedReferenceDataContainer',
                [
                    'name' => $name,
                ]
            ));
        }
        return $matches[0];
    }

    private function sourceReference(DOMElement $node, array $map): string
    {
        $reference = $this->requiredAttribute($node, 'source_ref');
        if (isset($map[$reference])) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.duplicatedSourceRefLine',
                [
                    'localName' => $node->localName,
                    'reference' => $reference,
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        return $reference;
    }

    private function requiredAttribute(DOMElement $node, string $name): string
    {
        $value = trim($node->getAttribute($name));
        if ($value === '') {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.missingReferenceDataAttributeSourceRefLine',
                [
                    'name' => $name,
                    'localName' => $node->localName,
                    'sourceRef' => $node->getAttribute('source_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        return $value;
    }

    private function booleanAttribute(DOMElement $node, string $name): bool
    {
        $value = $this->requiredAttribute($node, $name);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.invalidValueSourceRefLineExpectedTrueOrFalse',
                [
                    'name' => $name,
                    'value' => $value,
                    'localName' => $node->localName,
                    'sourceRef' => $node->getAttribute('source_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        return $value === 'true';
    }

    private function integerAttribute(DOMElement $node, string $name): int
    {
        $value = $this->requiredAttribute($node, $name);
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.invalidIntegerSourceRefLine',
                [
                    'name' => $name,
                    'value' => $value,
                    'localName' => $node->localName,
                    'sourceRef' => $node->getAttribute('source_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        return (int) $value;
    }

    private function floatAttribute(DOMElement $node, string $name): float
    {
        $value = $this->requiredAttribute($node, $name);
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.invalidNumericSourceRefLine',
                [
                    'name' => $name,
                    'value' => $value,
                    'localName' => $node->localName,
                    'sourceRef' => $node->getAttribute('source_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        return (float) $value;
    }

    private function localeAttribute(DOMElement $node): string
    {
        $locale = $this->requiredAttribute($node, 'locale');
        if (preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale) !== 1) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.invalidLocaleLocalizedSourceRefLine',
                [
                    'locale' => $locale,
                    'localName' => $node->localName,
                    'parentElement' => $node->parentNode instanceof DOMElement ? $node->parentNode->localName : __('plugins.importexport.fullJournal.entity.referenceData'),
                    'parentSourceRef' => $node->parentNode instanceof DOMElement ? $node->parentNode->getAttribute('source_ref') : '',
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        return $locale;
    }
}
