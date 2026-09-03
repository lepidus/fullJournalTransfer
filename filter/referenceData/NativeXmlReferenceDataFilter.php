<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\referenceData;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\transfer\DefaultContextDataCleaner;
use APP\plugins\importexport\fullJournalTransfer\validation\PackageReferenceValidator;
use DOMDocument;
use Illuminate\Support\Facades\DB;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlReferenceDataFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'reference_data_collection';
    }

    public function getSingularElementName()
    {
        return 'reference_data';
    }

    public function handleElement($root)
    {
        (new PackageReferenceValidator())->validateReferenceData($root);
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        return DB::transaction(function () use ($root, $context, $deployment): Journal {
            (new DefaultContextDataCleaner())->cleanReferenceData($context);
            $groups = [
                'review_forms' => 'full-journal-xml=>review-form',
                'genres' => 'full-journal-xml=>genre',
                'sections' => 'full-journal-xml=>section',
            ];
            foreach ($groups as $elementName => $filterGroup) {
                $nodes = $root->getElementsByTagNameNS($deployment->getNamespace(), $elementName);
                if ($nodes->length !== 1) {
                    throw new \InvalidArgumentException('Expected exactly one ' . $elementName . ' element');
                }
                $document = new DOMDocument('1.0', 'UTF-8');
                $document->appendChild($document->importNode($nodes->item(0), true));
                PKPImportExportFilter::getFilter($filterGroup, $deployment)->execute($document);
            }
            return $context;
        });
    }
}
