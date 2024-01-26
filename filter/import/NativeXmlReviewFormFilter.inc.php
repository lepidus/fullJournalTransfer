<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlReviewFormFilter extends NativeImportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML review form import');
        parent::__construct($filterGroup);
    }

    public function getPluralElementName()
    {
        return 'review_forms';
    }

    public function getSingularElementName()
    {
        return 'review_form';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlReviewFormFilter';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $reviewFormDAO = DAORegistry::getDAO('ReviewFormDAO');
        $reviewForm = $reviewFormDAO->newDataObject();

        $reviewForm->setAssocType($context->getAssocType());
        $reviewForm->setAssocId($context->getId());

        if ($node->getAttribute('is_active')) {
            $reviewForm->setActive($node->getAttribute('is_active'));
        }
        if ($node->getAttribute('seq')) {
            $reviewForm->setSequence($node->getAttribute('seq'));
        }

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                switch ($n->tagName) {
                    case 'title':
                        $locale = $n->getAttribute('locale');
                        $reviewForm->setTitle($n->textContent, $locale);
                        break;
                    case 'description':
                        $locale = $n->getAttribute('locale');
                        $reviewForm->setDescription($n->textContent, $locale);
                        break;
                }
            }
        }

        $reviewFormDAO->insertObject($reviewForm);
        return $reviewForm;
    }
}
