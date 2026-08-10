<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\UserIdentityPolicy;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\users\filter\UserXmlPKPUserFilter as BaseUserXmlPKPUserFilter;

class UserXmlPKPUserFilter extends BaseUserXmlPKPUserFilter
{
    private array $userIdMap = [];
    private array $conflicts = [];
    private array $userGroupIdMap = [];

    public function parseUser($node)
    {
        $emailNode = $this->getDirectChild($node, 'email');
        $usernameNode = $this->getDirectChild($node, 'username');
        if (!$emailNode || !$usernameNode) {
            return parent::parseUser($node);
        }

        $sourceUsername = $usernameNode->textContent;
        $sourceReference = $this->getSourceReference($node, $sourceUsername);
        $userGroupIds = $this->resolveUserGroupIds($node);
        $decision = (new UserIdentityPolicy())->resolve(
            $emailNode->textContent,
            $sourceUsername,
            static function (string $email): ?array {
                $user = Repo::user()->getByEmail($email, true);
                return $user ? ['id' => $user->getId(), 'username' => $user->getUsername()] : null;
            },
            static fn (string $username): bool => Repo::user()->getByUsername($username, true) !== null
        );

        $emailNode->textContent = $decision['email'];
        $usernameNode->textContent = $decision['username'];
        $user = parent::parseUser($node);
        if ($user && $user->getId()) {
            $this->userIdMap[$sourceReference] = $user->getId();
            foreach ($userGroupIds as $userGroupId) {
                if (!Repo::userGroup()->userInGroup($user->getId(), $userGroupId)) {
                    Repo::userGroup()->assignUserToGroup($user->getId(), $userGroupId);
                }
            }
        }
        if ($decision['conflict'] !== null) {
            $this->conflicts[] = [
                'source_ref' => $sourceReference,
                'type' => $decision['conflict'],
                'effective_user_id' => $user?->getId(),
                'effective_username' => $user?->getUsername(),
            ];
        }
        return $user;
    }

    public function getUserIdMap(): array
    {
        return $this->userIdMap;
    }

    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    public function setUserGroupIdMap(array $userGroupIdMap): void
    {
        $this->userGroupIdMap = $userGroupIdMap;
    }

    private function getDirectChild(DOMElement $node, string $name): ?DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                return $child;
            }
        }
        return null;
    }

    private function getSourceReference(DOMElement $node, string $fallback): string
    {
        $idNode = $this->getDirectChild($node, 'id');
        return $idNode && trim($idNode->textContent) !== '' ? trim($idNode->textContent) : $fallback;
    }

    private function resolveUserGroupIds(DOMElement $node): array
    {
        $contextId = $this->getDeployment()->getContext()->getId();
        $userGroupIds = [];
        $references = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'user_group_ref') {
                $references[] = $child;
            }
        }
        foreach ($references as $reference) {
            $sourceReference = trim($reference->getAttribute('source_ref'));
            if ($sourceReference === '' || !isset($this->userGroupIdMap[$sourceReference])) {
                throw new InvalidArgumentException('Unknown imported user group reference');
            }
            $userGroupId = (int) $this->userGroupIdMap[$sourceReference];
            if (!Repo::userGroup()->contextHasGroup($contextId, $userGroupId)) {
                throw new InvalidArgumentException('Imported user group reference is outside the destination context');
            }
            $userGroupIds[] = $userGroupId;
            $node->removeChild($reference);
        }
        return array_values(array_unique($userGroupIds));
    }
}
