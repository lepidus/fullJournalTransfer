<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\users\filter\NativeXmlUserGroupFilter as BaseNativeXmlUserGroupFilter;
use PKP\security\Role;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\userGroup\UserGroup;

class NativeXmlUserGroupFilter extends BaseNativeXmlUserGroupFilter
{
    private const ALLOWED_ROLES = [
        Role::ROLE_ID_MANAGER,
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_AUTHOR,
        Role::ROLE_ID_REVIEWER,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_READER,
        Role::ROLE_ID_SUBSCRIPTION_MANAGER,
    ];

    private array $userGroupIdMap = [];

    public function handleElement($node)
    {
        $context = $this->getDeployment()->getContext();
        $sourceReference = trim($node->getAttribute('source_ref'));
        if ($sourceReference === '') {
            throw new InvalidArgumentException(sprintf(
                'An imported user group must have a source_ref at line %d',
                $node->getLineNo()
            ));
        }
        $roleId = (int) $this->requiredText($node, 'role_id');
        if (!in_array($roleId, self::ALLOWED_ROLES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unacceptable role_id "%d" for user group source_ref "%s" at line %d',
                $roleId,
                $sourceReference,
                $node->getLineNo()
            ));
        }
        $names = $this->localizedValues($node, 'name');
        $abbreviations = $this->localizedValues($node, 'abbrev');
        if ($names === [] || $abbreviations === []) {
            throw new InvalidArgumentException('An imported user group must have localized names and abbreviations');
        }

        $userGroup = $this->findMatchingGroup($context->getId(), $roleId, $names);
        $params = [
            'contextId' => $context->getId(),
            'roleId' => $roleId,
            'isDefault' => $this->booleanValue($node, 'is_default'),
            'showTitle' => $this->booleanValue($node, 'show_title'),
            'permitSelfRegistration' => $this->booleanValue($node, 'permit_self_registration'),
            'permitMetadataEdit' => $this->booleanValue($node, 'permit_metadata_edit'),
            'name' => $names,
            'abbrev' => $abbreviations,
        ];
        if ($userGroup) {
            Repo::userGroup()->edit($userGroup, $params);
            $userGroup = Repo::userGroup()->get($userGroup->getId(), $context->getId());
        } else {
            $userGroup = Repo::userGroup()->newDataObject($params);
            $userGroupId = Repo::userGroup()->add($userGroup);
            $userGroup = Repo::userGroup()->get($userGroupId, $context->getId());
        }
        if (!$userGroup) {
            throw new InvalidArgumentException('The imported user group could not be persisted');
        }

        UserGroupStage::withContextId($context->getId())
            ->withUserGroupId($userGroup->getId())
            ->delete();
        foreach ($this->stageIds($node) as $stageId) {
            UserGroupStage::create([
                'contextId' => $context->getId(),
                'userGroupId' => $userGroup->getId(),
                'stageId' => $stageId,
            ]);
        }
        $this->userGroupIdMap[$sourceReference] = $userGroup->getId();
        $this->getDeployment()->mapReference('user_group', $sourceReference, $userGroup->getId());
        return $userGroup;
    }

    public function getUserGroupIdMap(): array
    {
        return $this->userGroupIdMap;
    }

    private function findMatchingGroup(int $contextId, int $roleId, array $names): ?UserGroup
    {
        $groups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByRoleIds([$roleId])
            ->getMany();
        foreach ($groups as $group) {
            $matches = true;
            foreach ($names as $locale => $name) {
                if ($group->getName($locale) !== $name) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $group;
            }
        }
        return null;
    }

    private function localizedValues(DOMElement $node, string $elementName): array
    {
        $values = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $elementName) {
                $locale = trim($child->getAttribute('locale'));
                if ($locale === '' || isset($values[$locale])) {
                    throw new InvalidArgumentException(sprintf(
                        'Invalid localized %s locale "%s" for user group source_ref "%s" at line %d',
                        $elementName,
                        $locale,
                        $node->getAttribute('source_ref'),
                        $child->getLineNo()
                    ));
                }
                $values[$locale] = $child->textContent;
            }
        }
        return $values;
    }

    private function requiredText(DOMElement $node, string $elementName): string
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $elementName) {
                return trim($child->textContent);
            }
        }
        throw new InvalidArgumentException(sprintf(
            'Missing user group element "%s" for source_ref "%s" at line %d',
            $elementName,
            $node->getAttribute('source_ref'),
            $node->getLineNo()
        ));
    }

    private function booleanValue(DOMElement $node, string $elementName): bool
    {
        $value = $this->requiredText($node, $elementName);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid user group %s "%s" for source_ref "%s" at line %d; expected "true" or "false"',
                $elementName,
                $value,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value === 'true';
    }

    private function stageIds(DOMElement $node): array
    {
        $stageIds = [];
        $value = $this->requiredText($node, 'stage_assignments');
        foreach ($value === '' ? [] : explode(':', $value) as $stage) {
            $stageId = (int) $stage;
            if ($stageId < WORKFLOW_STAGE_ID_SUBMISSION || $stageId > WORKFLOW_STAGE_ID_PRODUCTION) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid workflow stage "%s" for user group source_ref "%s" at line %d',
                    $stage,
                    $node->getAttribute('source_ref'),
                    $node->getLineNo()
                ));
            }
            $stageIds[] = $stageId;
        }
        sort($stageIds);
        return array_values(array_unique($stageIds));
    }
}
