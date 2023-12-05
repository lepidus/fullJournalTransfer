<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlNavigationMenuFilter');

class NativeXmlNavigationMenuFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>navigation-menu';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlNavigationMenuFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['navigation_menus', 'navigation_menu_item_assignments', 'navigation_menu_item_assignment_settings'];
    }

    public function testParseNavigationMenuItemAssignments()
    {
        $navMenuImportFilter = $this->getNativeImportExportFilter();
        $deployment = $navMenuImportFilter->getDeployment();

        $navigationMenuItemDAO = DAORegistry::getDAO('NavigationMenuItemDAO');
        $navigationMenuItem = $navigationMenuItemDAO->newDataObject();
        $navigationMenuItem->setType(NMI_TYPE_CUSTOM);
        $navigationMenuItem->setPath('childItem');
        $navigationMenuItem->setTitle('Child Item', 'en_US');
        $navigationMenuItemId = $navigationMenuItemDAO->insertObject($navigationMenuItem);
        $deployment->setNavigationMenuItemDBId(564, $navigationMenuItemId);

        $expectedNavMenuItemAssignmentData = [
            'menuId' => 86,
            'menuItemId' => $navigationMenuItemId,
            'parentId' => 0,
            'seq' => 5,
            'title' =>  $navigationMenuItem->getTitle(null)
        ];

        $doc = $this->getSampleXml('navigationMenu.xml');
        $navMenuNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'navigation_menu');
        $assignmentNodeList = $doc->getElementsByTagNameNS(
            $deployment->getNamespace(),
            'navigation_menu_item_assignment'
        );
        $navMenuImportFilter->parseNavigationMenuItemAssignments($assignmentNodeList->item(0), 86);

        $navigationMenuItemAssignmentDAO = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
        $assignments = $navigationMenuItemAssignmentDAO->getByMenuId(86)->toArray();
        $assignment = array_shift($assignments);
        unset($assignment->_data['id']);

        $this->assertEquals($expectedNavMenuItemAssignmentData, $assignment->_data);
    }

    public function testHandleNavigationMenuElement()
    {
        $navMenuImportFilter = $this->getNativeImportExportFilter();
        $deployment = $navMenuImportFilter->getDeployment();

        $journal = new Journal();
        $journal->setId(58);
        $deployment->setContext($journal);

        $navigationMenuDAO = DAORegistry::getDAO('NavigationMenuDAO');

        $doc = $this->getSampleXml('navigationMenu.xml');

        $expectedNavigationMenuData = [
            'contextId' => $journal->getId(),
            'title' => 'Test Navigation Menu Title',
            'areaName' => 'primary navigation'
        ];

        $navMenuNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'navigation_menu');

        $navigationMenu = $navMenuImportFilter->handleElement($navMenuNodeList->item(0));
        $navigationMenuId = array_pop($navigationMenu->_data);

        $this->assertEquals($expectedNavigationMenuData, $navigationMenu->_data);

        $insertedNavigationMenu = $navigationMenuDAO->getById($navigationMenuId);
        $expectedNavigationMenuData['id'] = $navigationMenuId;

        $this->assertEquals($expectedNavigationMenuData, $insertedNavigationMenu->_data);
    }
}
