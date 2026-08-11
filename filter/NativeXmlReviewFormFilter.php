<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use DOMDocument;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlReviewFormFilter extends NativeImportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function getPluralElementName()
    {
        return 'review_forms';
    }

    public function getSingularElementName()
    {
        return 'review_form';
    }

    public function handleElement($formNode)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $formDao = DAORegistry::getDAO('ReviewFormDAO');
        $sourceReference = $this->sourceReference(
            $formNode,
            $deployment->getReferenceMap('review_form')
        );
        $form = $formDao->newDataObject();
        $form->setAssocType(Application::ASSOC_TYPE_JOURNAL);
        $form->setAssocId($context->getId());
        $form->setSequence($this->floatAttribute($formNode, 'sequence'));
        $form->setActive($this->booleanAttribute($formNode, 'active') ? 1 : 0);
        $this->applyLocalized($formNode, 'title', [$form, 'setTitle'], true);
        $this->applyLocalized($formNode, 'description', [$form, 'setDescription'], false);
        $formId = $formDao->insertObject($form);
        $deployment->mapReference('review_form', $sourceReference, $formId);
        $elements = $this->requiredContainer($formNode, 'review_form_elements');
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($elements, true));
        $deployment->setCurrentReviewFormId($formId);
        try {
            PKPImportExportFilter::getFilter('full-journal-xml=>review-form-element', $deployment)
                ->execute($document);
        } finally {
            $deployment->setCurrentReviewFormId(null);
        }
        return $form;
    }
}
