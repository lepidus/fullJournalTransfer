<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class NavigationMenuNativeXmlFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML navigation menu export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.NavigationMenuNativeXmlFilter';
    }

    public function &process(&$navigationMenus)
    {
        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'navigation_menus');
        foreach ($navigationMenus as $navigationMenu) {
            $rootNode->appendChild($this->createNavigationMenuNode($doc, $navigationMenu));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    public function createNavigationMenuNode($doc, $navigationMenu)
    {
        $deployment = $this->getDeployment();

        $navigationMenuNode = $doc->createElementNS($deployment->getNamespace(), 'navigation_menu');
        $navigationMenuNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'title',
            htmlspecialchars($navigationMenu->getTitle(), ENT_COMPAT, 'UTF-8')
        ));
        $navigationMenuNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'area_name',
            htmlspecialchars($navigationMenu->getAreaName(), ENT_COMPAT, 'UTF-8')
        ));

        $this->addNavigationMenuAssignments($doc, $navigationMenuNode, $navigationMenu);

        return $navigationMenuNode;
    }

    public function addNavigationMenuAssignments($doc, $navigationMenuNode, $navigationMenu)
    {
        $deployment = $this->getDeployment();

        $navigationMenuItemAssignmentDAO = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
        $assignments = $navigationMenuItemAssignmentDAO->getByMenuId($navigationMenu->getId())->toArray();

        foreach ($assignments as $assignment) {
            $navigationMenuNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'navigation_menu_item_assignment'
            ));
            $node->setAttribute('menu_item_id', $assignment->getMenuItemId());
            $node->setAttribute('parent_id', $assignment->getParentId());
            $node->setAttribute('seq', $assignment->getSequence());
        }
    }
}
