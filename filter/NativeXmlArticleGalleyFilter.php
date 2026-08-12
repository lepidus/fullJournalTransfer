<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMElement;
use InvalidArgumentException;

class NativeXmlArticleGalleyFilter extends \APP\plugins\importexport\native\filter\NativeXmlArticleGalleyFilter
{
    public function handleElement($node)
    {
        $sourceReference = $this->internalId($node);
        $galley = parent::handleElement($node);
        $this->getDeployment()->mapReference('article_galley', $sourceReference, (int) $galley->getId());
        return $galley;
    }

    private function internalId(DOMElement $node): string
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'id' && $child->getAttribute('type') === 'internal') {
                return trim($child->textContent);
            }
        }
        throw new InvalidArgumentException('Article galley source reference is missing');
    }
}
