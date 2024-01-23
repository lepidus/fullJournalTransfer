<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class ReviewAssignmentNativeXmlFilter extends NativeExportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML review assignment export');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.ReviewAssignmentNativeXmlFilter';
    }

    public function &process(&$reviewAssignments)
    {
        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'review_assignments');
        foreach ($reviewAssignments as $reviewAssignment) {
            $rootNode->appendChild($this->createReviewAssignmentNode($doc, $reviewAssignment));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    public function createReviewAssignmentNode($doc, $reviewAssignment)
    {
        $deployment = $this->getDeployment();

        $userDAO = DAORegistry::getDAO('UserDAO');
        $reviewer = $userDAO->getById($reviewAssignment->getReviewerId());

        $reviewAssignmentNode = $doc->createElementNS($deployment->getNamespace(), 'review_assignment');

        foreach ($this->getAttributesMapping() as $attr) {
            $attributeData = $reviewAssignment->getData($this->snakeCaseToCamelCase($attr));
            if ($attributeData) {
                $reviewAssignmentNode->setAttribute($attr, $attributeData);
            }
        }
        $reviewAssignmentNode->setAttribute('reviewer', $reviewer->getUsername());

        $this->addDates($doc, $reviewAssignmentNode, $reviewAssignment);

        foreach ($this->getBooleanNodeNameMapping() as $nodeName) {
            $this->createBooleanNode(
                $doc,
                $reviewAssignmentNode,
                $nodeName,
                $reviewAssignment->getData($this->snakeCaseToCamelCase($nodeName))
            );
        }

        return $reviewAssignmentNode;
    }

    public function addDates($doc, $reviewAssignmentNode, $reviewAssignment)
    {
        foreach ($this->getDateNodeNameMapping() as $prop) {
            $this->createDateNode(
                $doc,
                $reviewAssignmentNode,
                $prop,
                $reviewAssignment->getData($this->snakeCaseToCamelCase($prop)),
                '%Y-%m-%d %H:%M:%S'
            );
        }
    }

    public function createDateNode($doc, $parentNode, $name, $value, $format)
    {
        if ($value === '') {
            return;
        }
        $deployment = $this->getDeployment();
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            $name,
            strftime($format, strtotime($value))
        ));
    }

    public function createBooleanNode($doc, $parentNode, $name, $value)
    {
        $deployment = $this->getDeployment();
        $parentNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            $name,
            $value == true ? 'true' : 'false'
        ));
    }

    private function getAttributesMapping()
    {
        return [
            'id',
            'submission_id',
            'review_form_id',
            'review_round_id',
            'stage_id',
            'quality',
            'recommendation',
            'round',
            'review_method',
            'competing_interests'
        ];
    }

    private function getDateNodeNameMapping()
    {
        return [
            'date_rated',
            'date_reminded',
            'date_assigned',
            'date_notified',
            'date_confirmed',
            'date_completed',
            'date_acknowledged',
            'date_due',
            'date_response_due',
            'last_modified',
        ];
    }

    private function getBooleanNodeNameMapping()
    {
        return [
            'declined',
            'cancelled',
            'reminder_was_automatic',
            'unconsidered'
        ];
    }

    private function snakeCaseToCamelCase($string)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
    }
}
