<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class ReviewFormElementNativeXmlFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML review form element export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.ReviewFormElementNativeXmlFilter';
    }

    public function &process(&$reviewFormElements)
    {
        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'review_form_elements');
        foreach ($reviewFormElements as $reviewFormElement) {
            $rootNode->appendChild($this->createReviewFormElementNode($doc, $reviewFormElement));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    public function createReviewFormElementNode($doc, $reviewFormElement)
    {
        $deployment = $this->getDeployment();

        $reviewFormElementNode = $doc->createElementNS($deployment->getNamespace(), 'review_form_element');
        $reviewFormElementNode->setAttribute('seq', $reviewFormElement->getSequence());
        $reviewFormElementNode->setAttribute('element_type', $reviewFormElement->getElementType());
        $reviewFormElementNode->setAttribute('required', $reviewFormElement->getRequired());
        $reviewFormElementNode->setAttribute('included', $reviewFormElement->getIncluded());

        $this->createLocalizedNodes(
            $doc,
            $reviewFormElementNode,
            'question',
            $reviewFormElement->getQuestion(null)
        );
        $this->createLocalizedNodes(
            $doc,
            $reviewFormElementNode,
            'description',
            $reviewFormElement->getDescription(null)
        );
        if ($reviewFormElement->getPossibleResponses(null)) {
            $this->addPossibleResponsesNode(
                $doc,
                $reviewFormElementNode,
                $reviewFormElement->getPossibleResponses(null)
            );
        }

        return $reviewFormElementNode;
    }

    public function addPossibleResponsesNode($doc, $reviewFormElementNode, $possibleResponses)
    {
        $deployment = $this->getDeployment();

        foreach ($possibleResponses as $locale => $values) {
            $reviewFormElementNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'possible_responses'));
            $node->setAttribute('locale', $locale);
            foreach ($values as $possibleResponse) {
                $node->appendChild($childNode = $doc->createElementNS(
                    $deployment->getNamespace(),
                    'possible_response',
                    htmlspecialchars($possibleResponse, ENT_COMPAT, 'UTF-8')
                ));
            }
        }
    }
}
