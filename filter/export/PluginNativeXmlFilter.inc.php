<?php

/**
 * Copyright (c) 2014-2023 Lepidus Tecnologia
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
            $pluginNode = $this->createPluginNode($doc, $plugin);
            if ($pluginNode) {
                $rootNode->appendChild($pluginNode);
            }
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

        if (empty($settings)) {
            return;
        }

        $pluginNode = $doc->createElementNS($deployment->getNamespace(), 'plugin');
        $pluginNode->setAttribute('plugin_name', $plugin->getName());
        foreach ($settings as $name => $value) {
            switch (gettype($value)) {
                case 'string':
                    $nodeValue = htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
                    break;
                case 'boolean':
                    $nodeValue = $value;
                    break;
                case 'array':
                    $nodeValue = htmlspecialchars(join(':', $value), ENT_COMPAT, 'UTF-8');
                    break;
            }
            $pluginNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'plugin_setting',
                $nodeValue
            ));
            $node->setAttribute('setting_name', $name);
        }

        return $pluginNode;
    }
}
