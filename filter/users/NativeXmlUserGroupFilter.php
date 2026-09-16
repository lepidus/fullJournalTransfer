<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\users;

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
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.userGroupSourceReferenceRequired',
                [
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        $roleId = (int) $this->requiredText($node, 'role_id');
        if (!in_array($roleId, self::ALLOWED_ROLES, true)) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.unacceptableRoleIdUserGroupSourceRefLine',
                [
                    'roleId' => $roleId,
                    'sourceReference' => $sourceReference,
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        $names = $this->localizedValues($node, 'name');
        $abbreviations = $this->localizedValues($node, 'abbrev');
        if ($names === [] || $abbreviations === []) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.userGroupLocalizedNamesRequired'));
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
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.userGroupSaveFailed'));
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
                    throw new InvalidArgumentException(__(
                        'plugins.importexport.fullJournal.error.invalidLocalizedLocaleUserGroupSourceRefLine',
                        [
                            'elementName' => $elementName,
                            'locale' => $locale,
                            'sourceRef' => $node->getAttribute('source_ref'),
                            'line' => $child->getLineNo(),
                        ]
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
        throw new InvalidArgumentException(__(
            'plugins.importexport.fullJournal.error.missingUserGroupElementSourceRefLine',
            [
                'elementName' => $elementName,
                'sourceRef' => $node->getAttribute('source_ref'),
                'line' => $node->getLineNo(),
            ]
        ));
    }

    private function booleanValue(DOMElement $node, string $elementName): bool
    {
        $value = $this->requiredText($node, $elementName);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.invalidUserGroupSourceRefLineExpectedTrueOrFalse',
                [
                    'elementName' => $elementName,
                    'value' => $value,
                    'sourceRef' => $node->getAttribute('source_ref'),
                    'line' => $node->getLineNo(),
                ]
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
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.invalidWorkflowStageUserGroupSourceRefLine',
                    [
                        'stage' => $stage,
                        'sourceRef' => $node->getAttribute('source_ref'),
                        'line' => $node->getLineNo(),
                    ]
                ));
            }
            $stageIds[] = $stageId;
        }
        sort($stageIds);
        return array_values(array_unique($stageIds));
    }
}
