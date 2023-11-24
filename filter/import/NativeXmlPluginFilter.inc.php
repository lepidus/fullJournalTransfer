<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlPluginFilter extends NativeImportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML plugin import');
        parent::__construct($filterGroup);
    }

    public function getPluralElementName()
    {
        return 'plugins';
    }

    public function getSingularElementName()
    {
        return 'plugin';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlPluginFilter';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $pluginSettingsDAO = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginName = $node->getAttribute('plugin_name');

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName == 'plugin_setting') {
                $pluginSettingsDAO->updateSetting(
                    $context->getId(),
                    $pluginName,
                    $n->getAttribute('setting_name'),
                    $n->textContent
                );
            }
        }

        return $pluginSettingsDAO->getPluginSettings($context->getId(), $pluginName);
    }
}
