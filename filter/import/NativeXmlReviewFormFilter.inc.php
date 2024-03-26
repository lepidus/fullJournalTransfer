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

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                switch ($childNode->tagName) {
                    case 'title':
                        $locale = $childNode->getAttribute('locale');
                        $reviewForm->setTitle($childNode->textContent, $locale);
                        break;
                    case 'description':
                        $locale = $childNode->getAttribute('locale');
                        $reviewForm->setDescription($childNode->textContent, $locale);
                        break;
                    case 'review_form_elements':
                        $reviewFormElementsNode = $childNode;
                        break;
                }
            }
        }

        $reviewFormDAO->insertObject($reviewForm);
        $deployment->setReviewFormDBId($node->getAttribute('id'), $reviewForm->getId());

        if ($reviewFormElementsNode) {
            $this->parseReviewFormElements($reviewFormElementsNode);
        }

        $deployment->setReviewForm($reviewForm);
        return $reviewForm;
    }

    public function parseReviewFormElements($node)
    {
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && $n->tagName  === 'review_form_element') {
                $this->parseReviewFormElement($n);
            }
        }
    }

    public function parseReviewFormElement($node)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>review-form-element');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $reviewFormElementsDocs = new DOMDocument();
        $reviewFormElementsDocs->appendChild($reviewFormElementsDocs->importNode($node, true));
        return $importFilter->execute($reviewFormElementsDocs);
    }
}
