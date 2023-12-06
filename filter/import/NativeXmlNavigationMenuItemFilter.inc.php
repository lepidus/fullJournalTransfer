<?php

/**
 * Copyright (c) 2014-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlNavigationMenuItemFilter extends NativeImportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML navigation menu item import');
        parent::__construct($filterGroup);
    }

    public function getPluralElementName()
    {
        return 'navigation-menu-items';
    }

    public function getSingularElementName()
    {
        return 'navigation-menu-item';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlNavigationMenuItemFilter';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $navigationMenuItemDAO = DAORegistry::getDAO('NavigationMenuItemDAO');
        $navigationMenuItem = $navigationMenuItemDAO->newDataObject();
        $navigationMenuItem->setContextId($context->getId());
        $navigationMenuItem->setType($node->getAttribute('type'));
        $navigationMenuItem->setPath($node->getAttribute('path'));
        $navigationMenuItem->setTitleLocaleKey($node->getAttribute('title_locale_key'));

        $tagMethodMapping = [
            'title' => 'setTitle',
            'content' => 'setContent',
            'remote_url' => 'setRemoteUrl',
        ];

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                $tagName = $childNode->tagName;
                if (array_key_exists($tagName, $tagMethodMapping)) {
                    $method = $tagMethodMapping[$tagName];
                    $navigationMenuItem->$method($childNode->textContent, $childNode->getAttribute('locale'));
                }
            }
        }

        $navigationMenuItemId = $navigationMenuItemDAO->insertObject($navigationMenuItem);
        $deployment->setNavigationMenuItemDBId($node->getAttribute('id'), $navigationMenuItem->getId());

        return $navigationMenuItem;
    }
}
