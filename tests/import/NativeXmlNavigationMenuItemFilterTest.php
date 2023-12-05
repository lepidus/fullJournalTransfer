<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlNavigationMenuItemFilter');

class NativeXmlNavigationMenuItemFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>navigation-menu-item';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlNavigationMenuItemFilter::class;
    }

    public function testHandleNavigationMenuItemElement()
    {
        $navMenuItemImportFilter = $this->getNativeImportExportFilter();
        $deployment = $navMenuItemImportFilter->getDeployment();

        $journal = new Journal();
        $journal->setId(58);
        $deployment->setContext($journal);

        $navigationMenuItemDAO = DAORegistry::getDAO('NavigationMenuItemDAO');

        $doc = $this->getSampleXml('navigationMenuItem.xml');

        $expectedNavigationMenuItemData = [
            'contextId' => $journal->getId(),
            'type' => NMI_TYPE_ABOUT,
            'path' => 'testItem',
            'titleLocaleKey' => 'navigation.about',
            'title' => ['en_US' => 'Test Nav Menu Item Title'],
            'content' => ['en_US' => 'Test Nav Menu Item Content'],
            'remoteUrl' => ['en_US' => 'http://path/to/page']
        ];

        $navMenuItemNodeList = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'navigation_menu_item');

        $navigationMenuItem = $navMenuItemImportFilter->handleElement($navMenuItemNodeList->item(0));
        $navigationMenuItemId = array_pop($navigationMenuItem->_data);

        $this->assertEquals($expectedNavigationMenuItemData, $navigationMenuItem->_data);

        $insertedNavigationMenuItem = $navigationMenuItemDAO->getById($navigationMenuItemId);
        $expectedNavigationMenuItemData['id'] = $navigationMenuItemId;

        $this->assertEquals($expectedNavigationMenuItemData, $insertedNavigationMenuItem->_data);
    }
}
