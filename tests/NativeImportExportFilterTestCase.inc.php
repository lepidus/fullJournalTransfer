<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportDeployment');

abstract class NativeImportExportFilterTestCase extends DatabaseTestCase
{
    abstract protected function getSymbolicFilterGroup();

    abstract protected function getNativeImportExportFilterClass();

    protected function getNativeImportExportFilter($context = null)
    {
        $filterGroupDAO = DAORegistry::getDAO('FilterGroupDAO');
        $filterGroup = $filterGroupDAO->getObjectBySymbolic($this->getSymbolicFilterGroup());

        $nativeImportExportFilterClass = $this->getNativeImportExportFilterClass();
        $nativeImportExportFilter = new $nativeImportExportFilterClass($filterGroup);

        if (!$context) {
            $context = Application::getContextDAO()->newDataObject();
        }

        $deployment = new FullJournalImportExportDeployment($context);
        $nativeImportExportFilter->setDeployment($deployment);

        return $nativeImportExportFilter;
    }

    protected function getSampleXml($sampleFile)
    {
        $fileContent = file_get_contents(__DIR__ . '/fixtures/' . $sampleFile);
        $xml = new DOMDocument('1.0');
        $xml->loadXML($fileContent);

        return $xml;
    }
}
