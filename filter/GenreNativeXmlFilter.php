<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use DOMElement;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class GenreNativeXmlFilter extends NativeExportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function &process(&$genres)
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $container = $document->createElementNS('http://pkp.sfu.ca', 'genres');
        $document->appendChild($container);
        foreach ($genres as $genre) {
            $container->appendChild($this->createGenreNode($document, $genre));
        }
        return $document;
    }

    public function createGenreNode(DOMDocument $document, $genre): DOMElement
    {
        $node = $document->createElementNS('http://pkp.sfu.ca', 'genre');
        $node->setAttribute('source_ref', (string) $genre->getId());
        $node->setAttribute('key', (string) $genre->getKey());
        $node->setAttribute('category', (string) $genre->getCategory());
        $node->setAttribute('dependent', $genre->getDependent() ? 'true' : 'false');
        $node->setAttribute('supplementary', $genre->getSupplementary() ? 'true' : 'false');
        $node->setAttribute('required', $genre->getRequired() ? 'true' : 'false');
        $node->setAttribute('sequence', (string) $genre->getSequence());
        $node->setAttribute('enabled', $genre->getEnabled() ? 'true' : 'false');
        $this->createLocalizedNodes($document, $node, 'name', $genre->getName(null));
        return $node;
    }
}
