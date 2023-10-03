<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class AnnouncementNativeXmlFilter extends NativeExportFilter
{
    public function createAnnouncementNode($doc, $announcement)
    {
        $deployment = $this->getDeployment();

        $announcementNode = $doc->createElementNS($deployment->getNamespace(), 'announcement');

        if ($announcement->getTypeId()) {
            $announcementNode->setAttribute('type_id', $announcement->getTypeId());
        }

        $announcementNode->setAttribute('assoc_type', $announcement->getAssocType());
        $announcementNode->setAttribute('assoc_id', $announcement->getAssocId());
        $announcementNode->setAttribute('date_expire', $announcement->getDateExpire());
        $announcementNode->setAttribute('date_posted', $announcement->getDatePosted());

        $announcementNode->setAttribute('id', $announcement->getId());

        $this->createLocalizedNodes($doc, $announcementNode, 'title', $announcement->getTitle(null));
        $this->createLocalizedNodes($doc, $announcementNode, 'descriptionShort', $announcement->getDescriptionShort(null));
        $this->createLocalizedNodes($doc, $announcementNode, 'description', $announcement->getDescription(null));

        return $announcementNode;
    }
}
