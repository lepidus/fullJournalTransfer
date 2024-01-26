<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlNavigationMenuFilter extends NativeImportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML navigation menu import');
        parent::__construct($filterGroup);
    }

    public function getPluralElementName()
    {
        return 'navigation-menus';
    }

    public function getSingularElementName()
    {
        return 'navigation-menu';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlNavigationMenuFilter';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $navigationMenuDAO = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenu = $navigationMenuDAO->newDataObject();
        $navigationMenu->setContextId($context->getId());

        $tagMethodMapping = [
            'title' => 'setTitle',
            'area_name' => 'setAreaName',
        ];

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                $tagName = $childNode->tagName;
                if (array_key_exists($tagName, $tagMethodMapping)) {
                    $method = $tagMethodMapping[$tagName];
                    $navigationMenu->$method($childNode->textContent);
                }
            }
        }

        $navigationMenuId = $navigationMenuDAO->insertObject($navigationMenu);
        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement') && $childNode->tagName == 'navigation_menu_item_assignment') {
                $this->parseNavigationMenuItemAssignments($childNode, $navigationMenuId);
            }
        }

        return $navigationMenu;
    }

    public function parseNavigationMenuItemAssignments($node, $navigationMenuId)
    {
        $deployment = $this->getDeployment();

        $oldMenuItemId = $node->getAttribute('menu_item_id');
        $oldPatentId = $node->getAttribute('parent_id');

        $navigationMenuItemAssignmentDAO = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
        $assignment = $navigationMenuItemAssignmentDAO->newDataObject();
        $assignment->setMenuId($navigationMenuId);
        $assignment->setMenuItemId($deployment->getNavigationMenuItemDBId($oldMenuItemId));
        $assignment->setParentId($deployment->getNavigationMenuItemDBId($oldPatentId));
        $assignment->setSequence($node->getAttribute('seq'));
        $navigationMenuItemAssignmentDAO->insertObject($assignment);
    }
}
