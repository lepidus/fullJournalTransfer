<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use PKP\plugins\importexport\users\filter\UserGroupNativeXmlFilter as BaseUserGroupNativeXmlFilter;

class UserGroupNativeXmlFilter extends BaseUserGroupNativeXmlFilter
{
    public function createUserGroupNode($doc, $userGroup)
    {
        $node = parent::createUserGroupNode($doc, $userGroup);
        $node->setAttribute('source_ref', (string) $userGroup->getId());
        return $node;
    }
}
