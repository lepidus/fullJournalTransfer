<?php

/**
 * Copyright (c) 2014-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class AnnouncementNativeXmlFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML announcement export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.AnnouncementNativeXmlFilter';
    }

    public function &process(&$announcements)
    {
        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'announcements');
        foreach ($announcements as $announcement) {
            $rootNode->appendChild($this->createAnnouncementNode($doc, $announcement));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    public function createAnnouncementNode($doc, $announcement)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $announcementNode = $doc->createElementNS($deployment->getNamespace(), 'announcement');

        $announcementNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'id',
            $announcement->getId()
        ));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');

        $this->addDates($doc, $announcementNode, $announcement);

        $this->createLocalizedNodes(
            $doc,
            $announcementNode,
            'title',
            $announcement->getTitle(null)
        );
        $this->createLocalizedNodes(
            $doc,
            $announcementNode,
            'description_short',
            $announcement->getDescriptionShort(null)
        );
        $this->createLocalizedNodes(
            $doc,
            $announcementNode,
            'description',
            $announcement->getDescription(null)
        );

        if ($announcement->getTypeId()) {
            $announcementTypeDAO = DAORegistry::getDAO('AnnouncementTypeDAO');
            $announcementType = $announcementTypeDAO->getById($announcement->getTypeId());
            $announcementNode->appendChild($doc->createElementNS(
                $deployment->getNamespace(),
                'announcement_type_ref',
                htmlspecialchars($announcementType->getName($context->getPrimaryLocale()), ENT_COMPAT, 'UTF-8')
            ));
        }

        return $announcementNode;
    }

    public function addDates($doc, $announcementNode, $announcement)
    {
        $deployment = $this->getDeployment();

        if ($announcement->getDateExpire()) {
            $announcementNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'date_expire',
                strftime('%Y-%m-%d', strtotime($announcement->getDateExpire()))
            ));
        }

        if ($announcement->getDatePosted()) {
            $announcementNode->appendChild($node = $doc->createElementNS(
                $deployment->getNamespace(),
                'date_posted',
                strftime('%Y-%m-%d %H:%M:%S', strtotime($announcement->getDatetimePosted()))
            ));
        }
    }
}
