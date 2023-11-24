<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class PluginNativeXmlFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML plugin export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.PluginNativeXmlFilter';
    }

    public function createPluginNode($doc, $plugin)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $pluginSettingsDAO = DAORegistry::getDAO('PluginSettingsDAO');
        $settings = $pluginSettingsDAO->getPluginSettings($context->getId(), $plugin->getName());

        $pluginNode = $doc->createElementNS($deployment->getNamespace(), 'plugin');
        $pluginNode->setAttribute('name', $plugin->getName());
        foreach ($settings as $name => $value) {
            $pluginNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'plugin_setting',
                htmlspecialchars($value, ENT_COMPAT, 'UTF-8')
            ));
            $node->setAttribute('name', $name);
        }

        return $pluginNode;
    }
}
