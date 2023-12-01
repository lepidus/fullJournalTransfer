<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class NavigationMenuItemNativeXmlFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML navigation menu item export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.NavigationMenuItemNativeXmlFilter';
    }

    public function &process(&$navigationMenuItems)
    {
        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'navigation_menu_items');
        foreach ($navigationMenuItems as $navigationMenuItem) {
            $rootNode->appendChild($this->createNavigationMenuItemNode($doc, $navigationMenuItem));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    public function createNavigationMenuItemNode($doc, $navigationMenuItem)
    {
        $deployment = $this->getDeployment();

        $navigationMenuItemNode = $doc->createElementNS($deployment->getNamespace(), 'navigation_menu_item');

        $navigationMenuItemNode->setAttribute('type', $navigationMenuItem->getType());
        $navigationMenuItemNode->setAttribute('path', $navigationMenuItem->getPath());
        $navigationMenuItemNode->setAttribute('title_locale_key', $navigationMenuItem->getTitleLocaleKey());

        $this->createLocalizedNodes(
            $doc,
            $navigationMenuItemNode,
            'title',
            $navigationMenuItem->getTitle(null)
        );
        $this->createLocalizedNodes(
            $doc,
            $navigationMenuItemNode,
            'content',
            $navigationMenuItem->getContent(null)
        );
        $this->createLocalizedNodes(
            $doc,
            $navigationMenuItemNode,
            'remote_url',
            $navigationMenuItem->getRemoteUrl(null)
        );

        return $navigationMenuItemNode;
    }
}
