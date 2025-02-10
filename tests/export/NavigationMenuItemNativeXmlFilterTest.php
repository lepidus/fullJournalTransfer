<?php

import('lib.pkp.classes.navigationMenu.NavigationMenuItem');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.NavigationMenuItemNativeXmlFilter');

class NavigationMenuItemNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getNativeImportExportFilterClass()
    {
        return NavigationMenuItemNativeXmlFilter::class;
    }

    protected function getSymbolicFilterGroup()
    {
        return 'navigation-menu-item=>native-xml';
    }

    public function testCreateNavigationMenuItemNode()
    {
        $navMenuItemExportFilter = $this->getNativeImportExportFilter();
        $deployment = $navMenuItemExportFilter->getDeployment();

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedNavMenuItemNode = $doc->createElementNS($deployment->getNamespace(), 'navigation_menu_item');
        $expectedNavMenuItemNode->setAttribute('id', 226);
        $expectedNavMenuItemNode->setAttribute('type', NMI_TYPE_ABOUT);
        $expectedNavMenuItemNode->setAttribute('path', 'testItem');
        $expectedNavMenuItemNode->setAttribute('title_locale_key', 'navigation.about');
        $navMenuItemExportFilter->createLocalizedNodes(
            $doc,
            $expectedNavMenuItemNode,
            'title',
            ['en_US' => 'Test Nav Menu Item Title']
        );
        $navMenuItemExportFilter->createLocalizedNodes(
            $doc,
            $expectedNavMenuItemNode,
            'content',
            ['en_US' => 'Test Nav Menu Item Content']
        );
        $navMenuItemExportFilter->createLocalizedNodes(
            $doc,
            $expectedNavMenuItemNode,
            'remote_url',
            ['en_US' => 'http://path/to/page']
        );

        $navigationMenuItem = new NavigationMenuItem();
        $navigationMenuItem->setId(226);
        $navigationMenuItem->setType(NMI_TYPE_ABOUT);
        $navigationMenuItem->setPath('testItem');
        $navigationMenuItem->setTitleLocaleKey('navigation.about');
        $navigationMenuItem->setTitle('Test Nav Menu Item Title', 'en_US');
        $navigationMenuItem->setContent('Test Nav Menu Item Content', 'en_US');
        $navigationMenuItem->setRemoteUrl('http://path/to/page', 'en_US');

        $actualNavMenuItemNode = $navMenuItemExportFilter->createNavigationMenuItemNode($doc, $navigationMenuItem);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedNavMenuItemNode),
            $doc->saveXML($actualNavMenuItemNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompleteNavigationMenuItemXml()
    {
        $navigationMenuItemExportFilter = $this->getNativeImportExportFilter();

        $navigationMenuItem = new NavigationMenuItem();
        $navigationMenuItem->setId(226);
        $navigationMenuItem->setType(NMI_TYPE_ABOUT);
        $navigationMenuItem->setPath('testItem');
        $navigationMenuItem->setTitleLocaleKey('navigation.about');
        $navigationMenuItem->setTitle('Test Nav Menu Item Title', 'en_US');
        $navigationMenuItem->setContent('Test Nav Menu Item Content', 'en_US');
        $navigationMenuItem->setRemoteUrl('http://path/to/page', 'en_US');
        $navigationMenuItems = [$navigationMenuItem];

        $doc = $navigationMenuItemExportFilter->execute($navigationMenuItems);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('navigationMenuItem.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
