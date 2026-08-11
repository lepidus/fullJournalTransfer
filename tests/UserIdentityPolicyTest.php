<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\plugins\importexport\fullJournalTransfer\UserIdentityPolicy;
use PHPUnit\Framework\TestCase;

class UserIdentityPolicyTest extends TestCase
{
    public function testItAssociatesAnExistingUserByNormalizedEmail(): void
    {
        $policy = new UserIdentityPolicy();

        $decision = $policy->resolve(
            ' Editor@Example.COM ',
            'source-editor',
            static fn (string $email): ?array => $email === 'editor@example.com'
                ? ['id' => 41, 'username' => 'destination-editor']
                : null,
            static fn (string $username): bool => $username === 'source-editor'
        );

        $this->assertSame(41, $decision['user_id']);
        $this->assertSame('destination-editor', $decision['username']);
        $this->assertSame('email_match', $decision['conflict']);
    }

    public function testItGeneratesADeterministicUsernameOnlyForANewUserCollision(): void
    {
        $policy = new UserIdentityPolicy();

        $decision = $policy->resolve(
            'new@example.com',
            'New Editor',
            static fn (string $email): ?array => null,
            static fn (string $username): bool => in_array($username, ['neweditor', 'neweditor1'], true)
        );

        $this->assertNull($decision['user_id']);
        $this->assertSame('neweditor2', $decision['username']);
        $this->assertSame('username_collision', $decision['conflict']);
    }
}
