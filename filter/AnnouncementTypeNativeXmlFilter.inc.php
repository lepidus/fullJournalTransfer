<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class AnnouncementTypeNativeXmlFilter extends NativeExportFilter
{
    public function createAnnouncementTypeNode($doc, $announcementType)
    {
        $deployment = $this->getDeployment();

        $announcementTypeNode = $doc->createElementNS($deployment->getNamespace(), 'announcementType');
        $this->createLocalizedNodes($doc, $announcementTypeNode, 'name', $announcementType->getName(null));

        return $announcementTypeNode;
    }
}
