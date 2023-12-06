<?php

import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlPluginFilter');

class NativeXmlPluginFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>plugin';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlPluginFilter::class;
    }

    protected function getAffectedTables()
    {
        return ['plugin_settings'];
    }

    public function testHandlePluginElement()
    {
        $pluginImportFilter = $this->getNativeImportExportFilter();
        $deployment = $pluginImportFilter->getDeployment();
        $pluginSettingsDAO = DAORegistry::getDAO('PluginSettingsDAO');

        $doc = $this->getSampleXml('plugin.xml');

        $expectedPluginSettings = [
            'enabled' => 1,
            'someSetting' => 'Test Value',
            'anotherSetting' => 'Another test value'
        ];

        $pluginNode = $doc->getElementsByTagNameNS($deployment->getNamespace(), 'plugin');

        $pluginSettings = $pluginImportFilter->handleElement($pluginNode->item(0));

        $this->assertEquals($expectedPluginSettings, $pluginSettings);
        $settings = $pluginSettingsDAO->getPluginSettings(0, 'testPlugin');

        $this->assertEquals($expectedPluginSettings, $settings);
    }
}
