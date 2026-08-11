<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class ReferenceDataNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$context)
    {
        if (!$context instanceof Journal) {
            throw new \InvalidArgumentException('Expected a journal for reference data export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS('http://pkp.sfu.ca', 'reference_data');
        $document->appendChild($root);
        $formDao = DAORegistry::getDAO('ReviewFormDAO');
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $entities = [
            'review-form=>full-journal-xml' => $formDao
                ->getByAssocId(Application::ASSOC_TYPE_JOURNAL, $context->getId())->toArray(),
            'genre=>full-journal-xml' => $genreDao->getByContextId($context->getId())->toArray(),
            'section=>full-journal-xml' => Repo::section()->getCollector()
                ->filterByContextIds([(int) $context->getId()])->getMany()->toArray(),
        ];
        foreach ($entities as $group => $objects) {
            $filter = PKPImportExportFilter::getFilter($group, $this->getDeployment());
            $childDocument = $filter->execute($objects);
            if ($childDocument->documentElement instanceof DOMElement) {
                $root->appendChild($document->importNode($childDocument->documentElement, true));
            }
        }
        return $document;
    }
}
