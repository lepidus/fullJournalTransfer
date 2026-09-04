<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\policy;

use InvalidArgumentException;

class UserIdentityPolicy
{
    public function resolve(string $email, string $username, callable $findByEmail, callable $usernameExists): array
    {
        $normalizedEmail = mb_strtolower(trim($email));
        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email is required to resolve an imported user');
        }

        $existingUser = $findByEmail($normalizedEmail);
        if ($existingUser !== null) {
            if (!isset($existingUser['id'], $existingUser['username'])) {
                throw new InvalidArgumentException('The existing user identity is incomplete');
            }
            return [
                'user_id' => (int) $existingUser['id'],
                'username' => (string) $existingUser['username'],
                'email' => $normalizedEmail,
                'conflict' => trim($username) === $existingUser['username'] ? null : 'email_match',
            ];
        }

        $baseUsername = mb_strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '', $username));
        if ($baseUsername === '') {
            $baseUsername = 'user';
        }
        $resolvedUsername = $baseUsername;
        for ($suffix = 1; $usernameExists($resolvedUsername); $suffix++) {
            $resolvedUsername = $baseUsername . $suffix;
        }

        return [
            'user_id' => null,
            'username' => $resolvedUsername,
            'email' => $normalizedEmail,
            'conflict' => $resolvedUsername === $baseUsername ? null : 'username_collision',
        ];
    }
}
