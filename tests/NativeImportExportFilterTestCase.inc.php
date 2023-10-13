<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportDeployment');

abstract class NativeImportExportFilterTestCase extends DatabaseTestCase
{
    protected $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Application::getContextDAO()->newDataObject();
        $this->context->_data = $this->getContextData();
    }

    protected function getContextData()
    {
        return [];
    }

    protected function getNativeImportExportFilter()
    {
        $filterGroupDAO = DAORegistry::getDAO('FilterGroupDAO');
        $filterGroup = $filterGroupDAO->getObjectBySymbolic($this->getSymbolicFilterGroup());

        $nativeImportExportFilterClass = $this->getNativeImportExportFilterClass();
        $nativeImportExportFilter = new $nativeImportExportFilterClass($filterGroup);

        $deployment = new FullJournalImportExportDeployment($this->context);
        $nativeImportExportFilter->setDeployment($deployment);

        return $nativeImportExportFilter;
    }

    abstract protected function getSymbolicFilterGroup();

    abstract protected function getNativeImportExportFilterClass();

    protected function getSampleXml($sampleFile)
    {
        $fileContent = file_get_contents(__DIR__ . '/fixtures/' . $sampleFile);
        $xml = new DOMDocument('1.0');
        $xml->loadXML($fileContent);

        return $xml;
    }
}
