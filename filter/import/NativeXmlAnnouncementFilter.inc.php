<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');
import('lib.pkp.classes.services.PKPSchemaService');

class NativeXmlAnnouncementFilter extends NativeImportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML announcement import');
        parent::__construct($filterGroup);
    }

    public function getPluralElementName()
    {
        return 'announcements';
    }

    public function getSingularElementName()
    {
        return 'announcement';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlAnnouncementFilter';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $announcementDAO = $announcementDao = DAORegistry::getDAO('AnnouncementDAO');
        $announcement = $announcementDAO->newDataObject();

        $announcement->setAssocType(Application::get()->getContextAssocType());
        $announcement->setAssocId($context->getId());

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                switch ($n->tagName) {
                    case 'id':
                        $this->parseIdentifier($n, $announcement);
                        break;
                    case 'title':
                        $locale = $n->getAttribute('locale');
                        $announcement->setTitle($n->textContent, $locale);
                        break;
                    case 'description_short':
                        $locale = $n->getAttribute('locale');
                        $announcement->setDescriptionShort($n->textContent, $locale);
                        break;
                    case 'description':
                        $locale = $n->getAttribute('locale');
                        $announcement->setDescription($n->textContent, $locale);
                        break;
                    case 'date_expire':
                        $announcement->setDateExpire(strftime('%Y-%m-%d', strtotime($n->textContent)));
                        break;
                    case 'date_posted':
                        $announcement->setDatePosted(strftime('%Y-%m-%d %H:%M:%S', strtotime($n->textContent)));
                        break;
                    case 'announcement_type_ref':
                        $announcementTypes = DAORegistry::getDAO('AnnouncementTypeDAO')->getByAssoc(
                            $context->getAssocType(),
                            $context->getId()
                        );
                        foreach ($announcementTypes as $announcementType) {
                            if (in_array($n->textContent, $announcementType->getName(null))) {
                                $announcement->setTypeId($announcementType->getId());
                            }
                        }
                        break;
                }
            }
        }

        $announcementDAO->insertObject($announcement);
        return $announcement;
    }

    public function parseIdentifier($element, $announcement)
    {
        $deployment = $this->getDeployment();
        $advice = $element->getAttribute('advice');
        switch ($element->getAttribute('type')) {
            case 'internal':
                assert(!$advice || $advice == 'ignore');
                break;
        }
    }
}
