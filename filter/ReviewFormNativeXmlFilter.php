<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

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
        $elementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $container = $document->createElementNS('http://pkp.sfu.ca', 'review_forms');
        $document->appendChild($container);
        foreach ($forms as $form) {
            $formNode = $document->createElementNS('http://pkp.sfu.ca', 'review_form');
            $formNode->setAttribute('source_ref', (string) $form->getId());
            $formNode->setAttribute('sequence', (string) $form->getSequence());
            $formNode->setAttribute('active', $form->getActive() ? 'true' : 'false');
            $this->appendLocalized($document, $formNode, 'title', $form->getTitle(null));
            $this->appendLocalized($document, $formNode, 'description', $form->getDescription(null));
            $filter = PKPImportExportFilter::getFilter(
                'review-form-element=>full-journal-xml',
                $this->getDeployment()
            );
            $elements = $elementDao->getByReviewFormId($form->getId())->toArray();
            $elementsDocument = $filter->execute($elements);
            if ($elementsDocument->documentElement instanceof DOMElement) {
                $formNode->appendChild($document->importNode($elementsDocument->documentElement, true));
            }
            $container->appendChild($formNode);
        }
        return $document;
    }
}
