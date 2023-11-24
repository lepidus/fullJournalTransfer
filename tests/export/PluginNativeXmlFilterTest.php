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

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedPluginNode = $doc->createElementNS($deployment->getNamespace(), 'plugin');
        $expectedPluginNode->setAttribute('name', 'testPlugin');

        $expectedPluginNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'plugin_setting',
            '#1E6292'
        ));
        $node->setAttribute('name', 'baseColour');
        $expectedPluginNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'plugin_setting',
            true
        ));
        $node->setAttribute('name', 'enabled');

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
}
