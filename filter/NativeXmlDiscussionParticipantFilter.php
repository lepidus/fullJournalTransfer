<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\plugins\importexport\fullJournalTransfer\HistoricalDiscussionPersistenceAdapter;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlDiscussionParticipantFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'discussion_participants';
    }

    public function getSingularElementName()
    {
        return 'discussion_participant';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $discussionId = $deployment->requireReference('discussion', $this->required($node, 'discussion_ref'));
        $userId = $deployment->requireReference('user', $this->required($node, 'user_ref'));
        (new HistoricalDiscussionPersistenceAdapter())->insertParticipant($discussionId, $userId);
        return (object) ['discussion_id' => $discussionId, 'user_id' => $userId];
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException('Missing discussion participant value: ' . $attribute);
        }
        return $value;
    }
}
