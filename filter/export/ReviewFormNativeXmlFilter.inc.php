<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class ReviewFormNativeXmlFilter extends NativeExportFilter
{
    public function createReviewFormNode($doc, $reviewForm)
    {
        $deployment = $this->getDeployment();

        $reviewFormNode = $doc->createElementNS($deployment->getNamespace(), 'review_form');
        $reviewFormNode->setAttribute('seq', $reviewForm->getSequence());
        $reviewFormNode->setAttribute('is_active', $reviewForm->getActive());

        $this->createLocalizedNodes($doc, $reviewFormNode, 'title', $reviewForm->getTitle(null));
        $this->createLocalizedNodes($doc, $reviewFormNode, 'description', $reviewForm->getDescription(null));

        return $reviewFormNode;
    }
}
