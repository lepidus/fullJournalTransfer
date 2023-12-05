<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.NavigationMenuNativeXmlFilter');
import('plugins.importexport.fullJournalTransfer.tests.components.NavigationMenuComponent');

class NavigationMenuNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    use NavigationMenuComponent;

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

    protected function getMockedDAOs()
    {
        return ['NavigationMenuItemAssignmentDAO'];
    }

    public function testAddNavigationMenuAssignments()
    {
        $navMenuExportFilter = $this->getNativeImportExportFilter();
        $deployment = $navMenuExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        [$navigationMenuId, $navigationMenu] = $this->getNavigationMenuId();
        $childMenuItemId = $this->getChildNavigationMenuItemId();
        $parentMenuItemId = $this->getParentNavigationMenuItemId();
        $this->insertNavigationMenuItemAssignment($navigationMenuId, $childMenuItemId, $parentMenuItemId);

        $actualNavMenuNode = $doc->createElementNS($deployment->getNamespace(), 'navigation_menu');
        $navMenuExportFilter->addNavigationMenuAssignments($doc, $actualNavMenuNode, $navigationMenu);

        $expectedNavMenuNode = $doc->createElementNS($deployment->getNamespace(), 'navigation_menu');
        $expectedNavMenuNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'navigation_menu_item_assignment'
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

    public function testCreateNavigationMenuNode()
    {
        $navMenuExportFilter = $this->getNativeImportExportFilter();
        $deployment = $navMenuExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedNavMenuNode = $doc->createElementNS($deployment->getNamespace(), 'navigation_menu');
        $expectedNavMenuNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'title',
            htmlspecialchars('Test Navigation Menu Title', ENT_COMPAT, 'UTF-8')
        ));
        $expectedNavMenuNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'area_name',
            htmlspecialchars('primary_navigation', ENT_COMPAT, 'UTF-8')
        ));

        $navigationMenuDAO = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenu = $navigationMenuDAO->newDataObject();
        $navigationMenu->setTitle('Test Navigation Menu Title');
        $navigationMenu->setAreaName('primary_navigation');

        $actualNavMenuNode = $navMenuExportFilter->createNavigationMenuNode($doc, $navigationMenu);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedNavMenuNode),
            $doc->saveXML($actualNavMenuNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteNavigationMenuItemXml()
    {
        $navigationMenuExportFilter = $this->getNativeImportExportFilter();

        $navigationMenuItemAssignmentDAO = $this->getMockBuilder(NavigationMenuItemAssignmentDAO::class)
            ->setMethods(['getByMenuId'])
            ->getMock();

        $assignment = $navigationMenuItemAssignmentDAO->newDataObject();
        $assignment->setMenuItemId(564);
        $assignment->setParentId(723);
        $assignment->setSequence(5);

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$assignment]));

        $navigationMenuItemAssignmentDAO->expects($this->any())
            ->method('getByMenuId')
            ->will($this->returnValue($mockResult));

        DAORegistry::registerDAO('NavigationMenuItemAssignmentDAO', $navigationMenuItemAssignmentDAO);

        $navigationMenuDAO = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenu = $navigationMenuDAO->newDataObject();
        $navigationMenu->setTitle('Test Navigation Menu Title');
        $navigationMenu->setAreaName('primary navigation');
        $navigationMenus = [$navigationMenu];

        $doc = $navigationMenuExportFilter->execute($navigationMenus);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('navigationMenu.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
