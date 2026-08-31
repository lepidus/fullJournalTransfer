<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlArticleFilter extends \APP\plugins\importexport\native\filter\NativeXmlArticleFilter
{
    public function populateObject($submission, $node)
    {
        $submission = parent::populateObject($submission, $node);
        $submission->setData('submissionProgress', $node->getAttribute('submission_progress'));
        return $submission;
    }

    public function handleElement($node)
    {
        $sourceReference = $this->internalId($node);
        $publicationReferences = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'publication') {
                $publicationReferences[$child->getAttribute('version')] = $this->internalId($child);
            }
        }
        $submission = parent::handleElement($node);
        $this->getDeployment()->mapReference('submission', $sourceReference, (int) $submission->getId());
        foreach (Repo::publication()->getCollector()
            ->filterBySubmissionIds([(int) $submission->getId()])
            ->getMany() as $publication) {
            $sourcePublication = $publicationReferences[(string) $publication->getData('version')] ?? null;
            if ($sourcePublication === null) {
                throw new InvalidArgumentException('Imported publication version was not found');
            }
            $this->getDeployment()->mapReference(
                'publication',
                $sourcePublication,
                (int) $publication->getId()
            );
        }
        return $submission;
    }

    public function getImportFilter($elementName)
    {
        if ($elementName === 'publication') {
            return PKPImportExportFilter::getFilter(
                'full-journal-native-xml=>publication',
                $this->getDeployment()
            );
        }
        if ($elementName === 'submission_file') {
            return PKPImportExportFilter::getFilter(
                'full-journal-native-xml=>submission-file',
                $this->getDeployment()
            );
        }
        return parent::getImportFilter($elementName);
    }

    private function internalId(DOMElement $node): string
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement
                && $child->localName === 'id'
                && $child->getAttribute('type') === 'internal'
                && trim($child->textContent) !== ''
            ) {
                return trim($child->textContent);
            }
        }
        throw new InvalidArgumentException('Missing native source reference');
    }
}
