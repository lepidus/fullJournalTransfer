<?php

import('lib.pkp.classes.plugins.Plugin');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.PluginNativeXmlFilter');

class PluginNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getNativeImportExportFilterClass()
    {
        return PluginNativeXmlFilter::class;
    }

    protected function getSymbolicFilterGroup()
    {
        return 'plugin=>native-xml';
    }

    protected function getAffectedTables()
    {
        return ['plugin_settings'];
    }

    public function testCreatePluginNode()
    {
        $pluginExportFilter = $this->getNativeImportExportFilter();
        $deployment = $pluginExportFilter->getDeployment();

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedPluginNode = $doc->createElementNS($deployment->getNamespace(), 'plugin');
        $expectedPluginNode->setAttribute('plugin_name', 'testPlugin');

        $expectedPluginNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'plugin_setting',
            '#1E6292'
        ));
        $node->setAttribute('setting_name', 'baseColour');
        $expectedPluginNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'plugin_setting',
            true
        ));
        $node->setAttribute('setting_name', 'enabled');

        $mockPlugin = $this->getMockBuilder(Plugin::class)
            ->getMockForAbstractClass();

        $mockPlugin->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('testPlugin'));

        $mockPlugin->updateSetting(0, 'enabled', true);
        $mockPlugin->updateSetting(0, 'baseColour', '#1E6292');

        $actualPluginNode = $pluginExportFilter->createPluginNode($doc, $mockPlugin);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedPluginNode),
            $doc->saveXML($actualPluginNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testCreateCompletePluginXml()
    {
        $pluginExportFilter = $this->getNativeImportExportFilter();

        $mockPlugin = $this->getMockBuilder(Plugin::class)
            ->getMockForAbstractClass();

        $mockPlugin->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('testPlugin'));

        $mockPlugin->updateSetting(0, 'enabled', true);
        $mockPlugin->updateSetting(0, 'someSetting', 'Test Value');
        $mockPlugin->updateSetting(0, 'anotherSetting', 'Another test value');

        $plugins = [$mockPlugin];

        $doc = $pluginExportFilter->execute($plugins);

        $this->assertXmlStringEqualsXmlString(
            $this->getSampleXml('plugin.xml')->saveXml(),
            $doc->saveXML(),
            "actual xml is equal to expected xml"
        );
    }
}
