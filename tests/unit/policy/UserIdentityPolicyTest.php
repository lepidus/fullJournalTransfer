<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\policy;

use APP\plugins\importexport\fullJournalTransfer\policy\UserIdentityPolicy;
use Gettext\Loader\PoLoader;
use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\TestCase;

class UserIdentityPolicyTest extends TestCase
{
    /**
     * @dataProvider invalidEmails
     */
    public function testItIdentifiesTheUserAndFieldWhenRejectingAnInvalidEmail(string $email): void
    {
        $message = 'O usuário “source-editor” não pôde ser importado '
            . 'porque seu endereço de e-mail está vazio ou é inválido.';
        $translator = $this->createMock(Translator::class);
        $translator->expects($this->once())->method('get')->with(
            'plugins.importexport.fullJournal.error.invalidUserEmail',
            ['username' => 'source-editor'],
            null
        )->willReturn($message);
        $previousContainer = Container::getInstance();
        $container = new Container();
        $container->instance('translator', $translator);
        Container::setInstance($container);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        try {
            (new UserIdentityPolicy())->resolve(
                $email,
                'source-editor',
                static function (string $email): ?array {
                    self::fail('Invalid email must be rejected before looking up a user');
                },
                static function (string $username): bool {
                    self::fail('Invalid email must be rejected before checking a username');
                }
            );
        } finally {
            Container::setInstance($previousContainer);
        }
    }

    public function testItProvidesTheInvalidEmailMessageInBothPluginLocales(): void
    {
        $messages = [
            'pt_BR' => 'O usuário “{$username}” não pôde ser importado '
                . 'porque seu endereço de e-mail está vazio ou é inválido.',
            'en' => 'The user “{$username}” could not be imported because their email address is empty or invalid.',
        ];
        foreach ($messages as $locale => $message) {
            $translations = (new PoLoader())->loadFile(dirname(__DIR__, 3) . '/locale/' . $locale . '/locale.po');
            $translation = $translations->find(null, 'plugins.importexport.fullJournal.error.invalidUserEmail');
            $this->assertNotNull($translation);
            $this->assertSame($message, $translation->getTranslation());
        }
    }

    public static function invalidEmails(): array
    {
        return [[''], ['   '], ['invalid@'], ['one@example.com;two@example.com']];
    }

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
