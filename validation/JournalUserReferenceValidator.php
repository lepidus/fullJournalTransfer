<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\validation;

use DOMElement;
use DOMXPath;
use InvalidArgumentException;

class JournalUserReferenceValidator
{
    private const REFERENCE_ATTRIBUTES = ['user_ref', 'reviewer_ref', 'editor_ref', 'author_ref'];

    public function validate(DOMElement $root): void
    {
        if ($root->localName !== 'journal') {
            throw new InvalidArgumentException('Invalid journal root');
        }
        $document = $root->ownerDocument;
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $users = [];
        foreach ($xpath->query('/pkp:journal/pkp:users/pkp:users/pkp:user') ?: [] as $user) {
            $sourceReference = trim($user->getAttribute('source_ref'));
            if ($sourceReference === '') {
                throw new InvalidArgumentException('Missing user source reference');
            }
            if (isset($users[$sourceReference])) {
                throw new InvalidArgumentException('Duplicated user source reference: ' . $sourceReference);
            }
            $users[$sourceReference] = true;
        }
        foreach ($xpath->query('/pkp:journal/pkp:workflow_history//*') ?: [] as $node) {
            foreach (self::REFERENCE_ATTRIBUTES as $attribute) {
                if (!$node->hasAttribute($attribute)) {
                    continue;
                }
                $sourceReference = trim($node->getAttribute($attribute));
                if ($sourceReference === '' || !isset($users[$sourceReference])) {
                    throw new InvalidArgumentException('Unknown workflow user reference: ' . $sourceReference);
                }
            }
        }
    }
}
