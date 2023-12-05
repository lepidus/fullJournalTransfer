<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
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
                'navigation_menu_assignment'
            ));
            $node->setAttribute('menu_item_id', $assignment->getMenuItemId());
            $node->setAttribute('parent_id', $assignment->getParentId());
            $node->setAttribute('seq', $assignment->getSequence());
        }
    }
}
