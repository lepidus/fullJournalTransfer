<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use DOMDocument;
use DOMXPath;
use PKP\plugins\importexport\PKPImportExportFilter;
use PKP\tests\DatabaseTestCase;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class UserImportIntegrationTest extends DatabaseTestCase
{
    private ?User $createdUser = null;
    private ?UserGroup $createdGroup = null;

    protected function getAffectedTables()
    {
        return [];
    }

    protected function tearDown(): void
    {
        if ($this->createdUser && $this->createdUser->getId()) {
            Repo::user()->delete($this->createdUser);
        }
        if ($this->createdGroup && $this->createdGroup->getId()) {
            Repo::userGroup()->delete($this->createdGroup);
        }
        parent::tearDown();
    }

    public function testItMapsAnEmailMatchWithoutOverwritingTheGlobalProfile(): void
    {
        $context = Application::get()->getContextDAO()->getById(1);
        $this->assertNotNull($context);
        $suffix = bin2hex(random_bytes(6));
        $destinationUsername = 'destination-' . $suffix;
        $sourceUsername = 'source-' . $suffix;
        $sourceReference = '987654';
        $email = 'transfer-' . $suffix . '@example.com';
        $existingUser = Repo::user()->newDataObject();
        $existingUser->setUsername($destinationUsername);
        $existingUser->setEmail($email);
        $existingUser->setPassword(password_hash('destination-password', PASSWORD_BCRYPT));
        $existingUser->setGivenName('Destination', 'en');
        $existingUser->setFamilyName('Editor', 'en');
        $existingUser->setDateRegistered('2026-08-10 00:00:00');
        $existingUser->setMustChangePassword(false);
        $existingUser->setDisabled(false);
        $existingUser->setInlineHelp(false);
        $existingUserId = Repo::user()->add($existingUser);
        $this->createdUser = $existingUser;

        $deployment = new FullJournalImportExportDeployment($context, null);
        $filter = PKPImportExportFilter::getFilter('full-journal-user-xml=>user', $deployment);
        $document = new DOMDocument();
        $password = password_hash('source-password', PASSWORD_BCRYPT);
        $this->assertTrue($document->loadXML(
            '<user xmlns="http://pkp.sfu.ca" source_ref="' . $sourceReference . '">'
            . '<givenname locale="en">Source</givenname>'
            . '<familyname locale="en">Profile</familyname>'
            . '<email> ' . mb_strtoupper($email) . ' </email>'
            . '<username>' . $sourceUsername . '</username>'
            . '<password encryption="bcrypt"><value>' . $password . '</value></password>'
            . '</user>'
        ));

        $users = $filter->execute($document);
        $importedUser = $users[0];
        $persistedUser = Repo::user()->get($existingUserId, true);

        $this->assertSame($existingUserId, $importedUser->getId());
        $this->assertSame('Destination', $persistedUser->getGivenName('en'));
        $this->assertSame($destinationUsername, $persistedUser->getUsername());
        $this->assertSame([$sourceReference => $existingUserId], $deployment->getReferenceMap('user'));
        $this->assertSame('email_match', $deployment->getUserConflicts()[0]['type']);
    }

    public function testItAssignsTheEffectiveUserByRemappedGroupReference(): void
    {
        $context = Application::get()->getContextDAO()->getById(1);
        $this->assertNotNull($context);
        $suffix = bin2hex(random_bytes(6));
        $email = 'membership-' . $suffix . '@example.com';
        $user = Repo::user()->newDataObject();
        $user->setUsername('membership-' . $suffix);
        $user->setEmail($email);
        $user->setPassword(password_hash('destination-password', PASSWORD_BCRYPT));
        $user->setGivenName('Membership', 'en');
        $user->setFamilyName('User', 'en');
        $user->setDateRegistered('2026-08-10 00:00:00');
        $user->setMustChangePassword(false);
        $user->setDisabled(false);
        $user->setInlineHelp(false);
        $userId = Repo::user()->add($user);
        $this->createdUser = $user;
        $group = Repo::userGroup()->newDataObject();
        $group->setContextId($context->getId());
        $group->setRoleId(1048576);
        $group->setDefault(false);
        $group->setShowTitle(false);
        $group->setPermitSelfRegistration(false);
        $group->setPermitMetadataEdit(false);
        $group->setName('Imported Membership ' . $suffix, 'en');
        $group->setAbbrev('IM', 'en');
        $groupId = Repo::userGroup()->add($group);
        $this->createdGroup = $group;
        $deployment = new FullJournalImportExportDeployment($context, null);
        $deployment->mapReference('user_group', 'group-source-9', $groupId);
        $filter = PKPImportExportFilter::getFilter('full-journal-user-xml=>user', $deployment);
        $document = new DOMDocument();
        $password = password_hash('source-password', PASSWORD_BCRYPT);
        $this->assertTrue($document->loadXML(
            '<user xmlns="http://pkp.sfu.ca">'
            . '<givenname locale="en">Source</givenname>'
            . '<familyname locale="en">Profile</familyname>'
            . '<email>' . $email . '</email>'
            . '<username>source-' . $suffix . '</username>'
            . '<password encryption="bcrypt"><value>' . $password . '</value></password>'
            . '<user_group_ref source_ref="group-source-9">Translated name is not an identity</user_group_ref>'
            . '</user>'
        ));

        $users = $filter->execute($document);
        $importedUser = $users[0];

        $this->assertSame($userId, $importedUser->getId());
        $this->assertTrue(Repo::userGroup()->userInGroup($userId, $groupId));
        $exportedDocument = (new FullJournalImportExportDeployment($context, null))->exportUsers();
        $xpath = new DOMXPath($exportedDocument);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $this->assertSame('users', $exportedDocument->documentElement->localName);
        $this->assertSame(
            (string) $userId,
            $xpath->evaluate('string(//pkp:user[@source_ref="' . $userId . '"]/@source_ref)')
        );
        $this->assertSame((string) $groupId, $xpath->evaluate(
            'string(//pkp:user_group[pkp:name="Imported Membership ' . $suffix . '"]/@source_ref)'
        ));
        $this->assertSame(
            (string) $groupId,
            $xpath->evaluate('string(//pkp:user[@source_ref="' . $userId . '"]/pkp:user_group_ref/@source_ref)')
        );
    }

    public function testItDoesNotExportDisabledUsersWithOnlyContextMembership(): void
    {
        $context = Application::get()->getContextDAO()->getById(1);
        $this->assertNotNull($context);
        $locale = $context->getPrimaryLocale();
        $suffix = bin2hex(random_bytes(6));
        $user = Repo::user()->newDataObject();
        $user->setUsername('disabled-membership-' . $suffix);
        $user->setEmail('disabled-membership-' . $suffix . '@example.com');
        $user->setPassword(password_hash('disabled-password', PASSWORD_BCRYPT));
        $user->setGivenName('Disabled', 'en');
        $user->setFamilyName('Member', 'en');
        $user->setDateRegistered('2026-08-10 00:00:00');
        $user->setMustChangePassword(false);
        $user->setDisabled(true);
        $user->setInlineHelp(false);
        $userId = Repo::user()->add($user);
        $this->createdUser = $user;
        $group = Repo::userGroup()->newDataObject();
        $group->setContextId($context->getId());
        $group->setRoleId(1048576);
        $group->setDefault(false);
        $group->setShowTitle(false);
        $group->setPermitSelfRegistration(false);
        $group->setPermitMetadataEdit(false);
        $group->setName('Disabled Membership ' . $suffix, $locale);
        $group->setAbbrev('DM', $locale);
        $groupId = Repo::userGroup()->add($group);
        $this->createdGroup = $group;
        Repo::userGroup()->assignUserToGroup($userId, $groupId);

        $exportedDocument = (new FullJournalImportExportDeployment($context, null))->exportUsers();
        $xpath = new DOMXPath($exportedDocument);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');

        $this->assertSame(0, $xpath->query('//pkp:user[@source_ref="' . $userId . '"]')->length);
    }

}
