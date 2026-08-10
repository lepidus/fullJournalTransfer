<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\filter\UserXmlPKPUserFilter;
use DOMDocument;
use PKP\filter\FilterGroup;
use PKP\plugins\importexport\users\PKPUserImportExportDeployment;
use PKP\tests\DatabaseTestCase;
use PKP\user\User;

class UserImportIntegrationTest extends DatabaseTestCase
{
    private ?User $createdUser = null;

    protected function getAffectedTables()
    {
        return [];
    }

    protected function tearDown(): void
    {
        if ($this->createdUser && $this->createdUser->getId()) {
            Repo::user()->delete($this->createdUser);
        }
        parent::tearDown();
    }

    public function testItMapsAnEmailMatchWithoutOverwritingTheGlobalProfile(): void
    {
        $context = Application::get()->getContextDAO()->getById(1);
        $this->assertNotNull($context);
        $suffix = bin2hex(random_bytes(6));
        $destinationUsername = 'h5-destination-' . $suffix;
        $sourceUsername = 'h5-source-' . $suffix;
        $email = 'h5-' . $suffix . '@example.com';
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

        $group = new FilterGroup();
        $group->setSymbolic('full-journal-user-xml=>user');
        $group->setInputType('xml::schema(lib/pkp/plugins/importexport/users/pkp-users.xsd)');
        $group->setOutputType('class::classes.users.User[]');
        $filter = new UserXmlPKPUserFilter($group);
        $filter->setDeployment(new PKPUserImportExportDeployment($context, null));
        $document = new DOMDocument();
        $password = password_hash('source-password', PASSWORD_BCRYPT);
        $this->assertTrue($document->loadXML(
            '<user xmlns="http://pkp.sfu.ca">'
            . '<givenname locale="en">Source</givenname>'
            . '<familyname locale="en">Profile</familyname>'
            . '<email> ' . mb_strtoupper($email) . ' </email>'
            . '<username>' . $sourceUsername . '</username>'
            . '<password encryption="bcrypt"><value>' . $password . '</value></password>'
            . '</user>'
        ));

        $importedUser = $filter->parseUser($document->documentElement);
        $persistedUser = Repo::user()->get($existingUserId, true);

        $this->assertSame($existingUserId, $importedUser->getId());
        $this->assertSame('Destination', $persistedUser->getGivenName('en'));
        $this->assertSame($destinationUsername, $persistedUser->getUsername());
        $this->assertSame([$sourceUsername => $existingUserId], $filter->getUserIdMap());
        $this->assertSame('email_match', $filter->getConflicts()[0]['type']);
    }
}
