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

    public function &process(&$plugins)
    {
        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'plugins');
        foreach ($plugins as $plugin) {
            $rootNode->appendChild($this->createPluginNode($doc, $plugin));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    public function createPluginNode($doc, $plugin)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $pluginSettingsDAO = DAORegistry::getDAO('PluginSettingsDAO');
        $settings = $pluginSettingsDAO->getPluginSettings($context->getId(), $plugin->getName());

        $pluginNode = $doc->createElementNS($deployment->getNamespace(), 'plugin');
        $pluginNode->setAttribute('plugin_name', $plugin->getName());
        foreach ($settings as $name => $value) {
            $pluginNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'plugin_setting',
                htmlspecialchars($value, ENT_COMPAT, 'UTF-8')
            ));
            $node->setAttribute('setting_name', $name);
        }

        return $pluginNode;
    }
}
