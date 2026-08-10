<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\UserIdentityPolicy;
use DOMElement;
use PKP\plugins\importexport\users\filter\UserXmlPKPUserFilter as BaseUserXmlPKPUserFilter;

class UserXmlPKPUserFilter extends BaseUserXmlPKPUserFilter
{
    private array $userIdMap = [];
    private array $conflicts = [];

    public function parseUser($node)
    {
        $emailNode = $this->getDirectChild($node, 'email');
        $usernameNode = $this->getDirectChild($node, 'username');
        if (!$emailNode || !$usernameNode) {
            return parent::parseUser($node);
        }

        $sourceUsername = $usernameNode->textContent;
        $sourceReference = $this->getSourceReference($node, $sourceUsername);
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
}
