<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.NavigationMenuNativeXmlFilter');

class NavigationMenuNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getNativeImportExportFilterClass()
    {
        return NavigationMenuNativeXmlFilter::class;
    }

    protected function getSymbolicFilterGroup()
    {
        return 'navigation-menu=>native-xml';
    }

    protected function getAffectedTables()
    {
        return [
            'navigation_menus',
            'navigation_menu_items',
            'navigation_menu_item_settings',
            'navigation_menu_item_assignments',
            'navigation_menu_item_assignment_settings'
        ];
    }

    public function testAddNavigationMenuAssignments()
    {
        $navMenuExportFilter = $this->getNativeImportExportFilter();
        $deployment = $navMenuExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $navigationMenuDAO = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenu = $navigationMenuDAO->newDataObject();
        $navigationMenu->setTitle('Test Menu');
        $navigationMenu->setAreaName('primary_user');
        $navigationMenuId = $navigationMenuDAO->insertObject($navigationMenu);

        $navigationMenuItemDAO = DAORegistry::getDAO('NavigationMenuItemDAO');
        $navigationMenuItem = $navigationMenuItemDAO->newDataObject();
        $navigationMenuItem = new NavigationMenuItem();
        $navigationMenuItem->setType(NMI_TYPE_CUSTOM);
        $navigationMenuItem->setPath('childItem');
        $navigationMenuItem->setTitle('Child Item', 'en_US');
        $childMenuItemId = $navigationMenuItemDAO->insertObject($navigationMenuItem);

        $navigationMenuItemDAO = DAORegistry::getDAO('NavigationMenuItemDAO');
        $navigationMenuItem = $navigationMenuItemDAO->newDataObject();
        $navigationMenuItem->setPath('parentItem');
        $navigationMenuItem->setTitle('Parent Item', 'en_US');
        $parentMenuItemId = $navigationMenuItemDAO->insertObject($navigationMenuItem);

        $navigationMenuItemAssignmentDAO = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
        $assignment = $navigationMenuItemAssignmentDAO->newDataObject();
        $assignment->setMenuId($navigationMenuId);
        $assignment->setMenuItemId($childMenuItemId);
        $assignment->setParentId($parentMenuItemId);
        $assignment->setSequence(2);
        $navigationMenuItemAssignmentDAO->insertObject($assignment);

        $actualNavMenuNode = $doc->createElementNS($deployment->getNamespace(), 'navigation_menu');
        $navMenuExportFilter->addNavigationMenuAssignments($doc, $actualNavMenuNode, $navigationMenu);

        $expectedNavMenuNode = $doc->createElementNS($deployment->getNamespace(), 'navigation_menu');
        $expectedNavMenuNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'navigation_menu_assignment'
        ));
        $node->setAttribute('menu_item_id', $childMenuItemId);
        $node->setAttribute('parent_id', $parentMenuItemId);
        $node->setAttribute('seq', 2);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedNavMenuNode),
            $doc->saveXML($actualNavMenuNode),
            "actual xml is equal to expected xml"
        );
    }
}
