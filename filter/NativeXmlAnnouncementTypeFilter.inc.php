<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');
import('lib.pkp.classes.services.PKPSchemaService');

class NativeXmlAnnouncementTypeFilter extends NativeImportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML announcement type import');
        parent::__construct($filterGroup);
    }

    public function getPluralElementName()
    {
        return 'announcement_types';
    }

    public function getSingularElementName()
    {
        return 'announcement_type';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.NativeXmlAnnouncementTypeFilter';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $announcementTypeDAO = $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO');
        $announcementType = $announcementTypeDAO->newDataObject();

        $announcementType->setAssocType(Application::get()->getContextAssocType());
        $announcementType->setAssocId($context->getId());

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName == 'name') {
                $announcementType->setName($n->textContent, $n->getAttribute('locale'));
            }
        }

        $announcementTypeId = $announcementTypeDAO->insertObject($announcementType);
        return $announcementType;
    }
}
