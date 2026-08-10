<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeExportFilter;

class GenreNativeXmlFilter extends NativeExportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function append(DOMDocument $document, DOMElement $root, Journal $context): void
    {
        $container = $document->createElementNS('http://pkp.sfu.ca', 'genres');
        $genreDao = DAORegistry::getDAO('GenreDAO');
        foreach ($genreDao->getByContextId($context->getId())->toArray() as $genre) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'genre');
            $node->setAttribute('source_ref', (string) $genre->getId());
            $node->setAttribute('key', (string) $genre->getKey());
            $node->setAttribute('category', (string) $genre->getCategory());
            $node->setAttribute('dependent', $genre->getDependent() ? 'true' : 'false');
            $node->setAttribute('supplementary', $genre->getSupplementary() ? 'true' : 'false');
            $node->setAttribute('required', $genre->getRequired() ? 'true' : 'false');
            $node->setAttribute('sequence', (string) $genre->getSequence());
            $node->setAttribute('enabled', $genre->getEnabled() ? 'true' : 'false');
            $this->appendLocalized($document, $node, 'name', $genre->getName(null));
            $container->appendChild($node);
        }
        $root->appendChild($container);
    }
}
