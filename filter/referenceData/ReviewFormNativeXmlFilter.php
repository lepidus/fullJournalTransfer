<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\referenceData;

use DOMDocument;
use DOMElement;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class ReviewFormNativeXmlFilter extends NativeExportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function &process(&$forms)
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $container = $document->createElementNS('http://pkp.sfu.ca', 'review_forms');
        $document->appendChild($container);
        foreach ($forms as $form) {
            $container->appendChild($this->createReviewFormNode($document, $form));
        }
        return $document;
    }

    public function createReviewFormNode(DOMDocument $document, $form): DOMElement
    {
        $formNode = $document->createElementNS('http://pkp.sfu.ca', 'review_form');
        $formNode->setAttribute('source_ref', (string) $form->getId());
        $formNode->setAttribute('sequence', (string) $form->getSequence());
        $formNode->setAttribute('active', $form->getActive() ? 'true' : 'false');
        $this->createLocalizedNodes($document, $formNode, 'title', $form->getTitle(null));
        $this->createLocalizedNodes($document, $formNode, 'description', $form->getDescription(null));
        $this->addReviewFormElements($document, $formNode, $form);
        return $formNode;
    }

    public function addReviewFormElements(DOMDocument $document, DOMElement $formNode, $form): void
    {
        $elementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $filter = PKPImportExportFilter::getFilter(
            'review-form-element=>full-journal-xml',
            $this->getDeployment()
        );
        $elements = $elementDao->getByReviewFormId($form->getId())->toArray();
        $elementsDocument = $filter->execute($elements);
        if ($elementsDocument->documentElement instanceof DOMElement) {
            $formNode->appendChild($document->importNode($elementsDocument->documentElement, true));
        }
    }
}
