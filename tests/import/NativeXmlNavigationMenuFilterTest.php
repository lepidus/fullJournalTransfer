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
        return ['navigation_menus'];
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
