<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class AnnouncementTypeNativeXmlFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML announcement type export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.AnnouncementTypeNativeXmlFilter';
    }

    public function &process(&$announcementTypes)
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'announcement_types');
        foreach ($announcementTypes as $announcementType) {
            $rootNode->appendChild($this->createAnnouncementTypeNode($doc, $announcementType));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    public function createAnnouncementTypeNode($doc, $announcementType)
    {
        $deployment = $this->getDeployment();

        $announcementTypeNode = $doc->createElementNS($deployment->getNamespace(), 'announcement_type');
        $this->createLocalizedNodes($doc, $announcementTypeNode, 'name', $announcementType->getName(null));

        return $announcementTypeNode;
    }
}
