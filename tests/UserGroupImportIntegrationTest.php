<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\filter\NativeXmlUserGroupFilter;
use DOMDocument;
use PKP\filter\FilterGroup;
use PKP\plugins\importexport\users\PKPUserImportExportDeployment;
use PKP\security\Role;
use PKP\tests\DatabaseTestCase;
use PKP\userGroup\UserGroup;

class UserGroupImportIntegrationTest extends DatabaseTestCase
{
    private array $createdGroups = [];

    protected function getAffectedTables()
    {
        return [];
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdGroups) as $userGroup) {
            Repo::userGroup()->delete($userGroup);
        }
        parent::tearDown();
    }

    public function testItUsesRoleAndNameAndRestoresBooleanFlagsAndStagesInDestinationContext(): void
    {
        $context = Application::get()->getContextDAO()->getById(1);
        $this->assertNotNull($context);
        $name = 'Imported Group ' . bin2hex(random_bytes(6));
        $existingGroup = $this->createGroup($context->getId(), Role::ROLE_ID_AUTHOR, $name);
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML(
            '<user_group xmlns="http://pkp.sfu.ca" source_ref="group-77">'
            . '<role_id>' . Role::ROLE_ID_REVIEWER . '</role_id>'
            . '<context_id>999</context_id>'
            . '<is_default>false</is_default>'
            . '<show_title>false</show_title>'
            . '<permit_self_registration>true</permit_self_registration>'
            . '<permit_metadata_edit>false</permit_metadata_edit>'
            . '<name locale="en">' . $name . '</name>'
            . '<abbrev locale="en">IG</abbrev>'
            . '<stage_assignments>3:4</stage_assignments>'
            . '</user_group>'
        ));
        $filter = $this->createFilter($context);

        $importedGroup = $filter->handleElement($document->documentElement);
        $this->createdGroups[] = $importedGroup;

        $this->assertNotSame($existingGroup->getId(), $importedGroup->getId());
        $this->assertSame($context->getId(), $importedGroup->getContextId());
        $this->assertSame(Role::ROLE_ID_REVIEWER, $importedGroup->getRoleId());
        $this->assertFalse((bool) $importedGroup->getDefault());
        $this->assertFalse((bool) $importedGroup->getShowTitle());
        $this->assertTrue((bool) $importedGroup->getPermitSelfRegistration());
        $this->assertSame([3, 4], Repo::userGroup()->getAssignedStagesByUserGroupId(
            $context->getId(),
            $importedGroup->getId()
        )->values()->toArray());
        $this->assertSame(['group-77' => $importedGroup->getId()], $filter->getUserGroupIdMap());
    }

    private function createGroup(int $contextId, int $roleId, string $name): UserGroup
    {
        $userGroup = Repo::userGroup()->newDataObject();
        $userGroup->setContextId($contextId);
        $userGroup->setRoleId($roleId);
        $userGroup->setDefault(false);
        $userGroup->setShowTitle(true);
        $userGroup->setPermitSelfRegistration(false);
        $userGroup->setPermitMetadataEdit(false);
        $userGroup->setName($name, 'en');
        $userGroup->setAbbrev('IG', 'en');
        Repo::userGroup()->add($userGroup);
        $this->createdGroups[] = $userGroup;
        return $userGroup;
    }

    private function createFilter($context): NativeXmlUserGroupFilter
    {
        $group = new FilterGroup();
        $group->setSymbolic('full-journal-user-group-xml=>user-group');
        $group->setInputType('xml::schema(lib/pkp/plugins/importexport/users/pkp-users.xsd)');
        $group->setOutputType('class::lib.pkp.classes.security.UserGroup[]');
        $filter = new NativeXmlUserGroupFilter($group);
        $filter->setDeployment(new PKPUserImportExportDeployment($context, null));
        return $filter;
    }
}
