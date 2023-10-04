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

        $announcementNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', $announcement->getId()));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');

        if ($announcement->getTypeId()) {
            $announcementNode->setAttribute('type_id', $announcement->getTypeId());
        }

        $announcementNode->setAttribute('date_expire', $announcement->getDateExpire());
        $announcementNode->setAttribute('date_posted', $announcement->getDatetimePosted());

        $this->createLocalizedNodes($doc, $announcementNode, 'title', $announcement->getTitle(null));
        $this->createLocalizedNodes($doc, $announcementNode, 'descriptionShort', $announcement->getDescriptionShort(null));
        $this->createLocalizedNodes($doc, $announcementNode, 'description', $announcement->getDescription(null));

        return $announcementNode;
    }
}
