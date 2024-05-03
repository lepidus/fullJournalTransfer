<?php

/**
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('plugins.importexport.native.filter.NativeXmlPublicationFilter');

class NativeXmlExtendedPublicationFilter extends NativeXmlPublicationFilter
{
    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlExtendedPublicationFilter';
    }

    public function handleChildElement($n, $publication)
    {
        $deployment = $this->getDeployment();
        if ($n->tagName == 'article_galley') {
            $articleGalleys = $this->parseArticleGalley($n, $publication);
            $articleGalley = array_shift($articleGalleys);
            $articleGalleyIdNodeList = $n->getElementsByTagNameNS($deployment->getNamespace(), 'id');
            for ($i = 0; $i < $articleGalleyIdNodeList->count(); $i++) {
                $articleGalleyIdNode = $articleGalleyIdNodeList->item($i);
                if ($articleGalleyIdNode->getAttribute('type') == 'internal') {
                    $deployment->setRepresentationDBId($articleGalleyIdNode->textContent, $articleGalley->getId());
                }
            }
            return;
        }
        parent::handleChildElement($n, $publication);
    }
}
