<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
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
        return 'plugins.importexport.fullJournalTransfer.filter.export.ReviewFormNativeXmlFilter';
    }

    public function &process(&$reviewForms)
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
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
        $reviewFormNode->setAttribute('id', $reviewForm->getId());
        $reviewFormNode->setAttribute('seq', $reviewForm->getSequence());
        $reviewFormNode->setAttribute('is_active', $reviewForm->getActive());

        $this->createLocalizedNodes($doc, $reviewFormNode, 'title', $reviewForm->getTitle(null));
        $this->createLocalizedNodes($doc, $reviewFormNode, 'description', $reviewForm->getDescription(null));
        $this->addReviewFormElements($doc, $reviewFormNode, $reviewForm);

        return $reviewFormNode;
    }

    public function addReviewFormElements($doc, $reviewFormNode, $reviewForm)
    {
        $filterDAO = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDAO->getObjectsByGroup('review-form-element=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setDeployment($this->getDeployment());

        $reviewFormElementDAO = DAORegistry::getDAO('ReviewFormElementDAO');
        $reviewFormElements = $reviewFormElementDAO->getByReviewFormId($reviewForm->getId())->toArray();
        $reviewFormElementsDoc = $exportFilter->execute($reviewFormElements);
        if ($reviewFormElementsDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($reviewFormElementsDoc->documentElement, true);
            $reviewFormNode->appendChild($clone);
        }
    }
}
