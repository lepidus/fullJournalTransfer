<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class ReviewFormNativeXmlFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML review form export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.native.filter.export.ReviewFormNativeXmlFilter';
    }

    public function &process(&$reviewForms)
    {
        $doc = new DOMDocument('1.0');
        $deployment = $this->getDeployment();

        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'review_forms');
        foreach ($reviewForms as $reviewForm) {
            $rootNode->appendChild($this->createReviewFormNode($doc, $reviewForm));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

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
