<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeDataNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$context)
    {
        if (!$context instanceof Journal) {
            throw new InvalidArgumentException('Expected a journal for native data export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS('http://pkp.sfu.ca', 'native_data');
        $document->appendChild($root);
        $currentIssue = Repo::issue()->getCurrent((int) $context->getId());
        if ($currentIssue) {
            $root->setAttribute('current_issue_ref', (string) $currentIssue->getId());
        }
        $issuesNode = $document->createElementNS('http://pkp.sfu.ca', 'issues');
        $issueFilter = PKPImportExportFilter::getFilter(
            'issue=>full-journal-native-xml',
            $this->getDeployment()
        );
        foreach (Repo::issue()->getCollector()->filterByContextIds([(int) $context->getId()])->getMany() as $issue) {
            $record = $document->createElementNS('http://pkp.sfu.ca', 'issue_record');
            $record->setAttribute('source_ref', (string) $issue->getId());
            $customOrder = Repo::issue()->dao->getCustomIssueOrder((int) $context->getId(), (int) $issue->getId());
            if ($customOrder !== null) {
                $record->setAttribute('custom_order', (string) $customOrder);
            }
            $issueInput = [$issue];
            $issueDocument = $issueFilter->execute($issueInput);
            $record->appendChild($document->importNode($issueDocument->documentElement, true));
            $issuesNode->appendChild($record);
        }
        $root->appendChild($issuesNode);
        $submissionsNode = $document->createElementNS('http://pkp.sfu.ca', 'submissions');
        $submissionFilter = PKPImportExportFilter::getFilter(
            'article=>full-journal-native-xml',
            $this->getDeployment()
        );
        foreach (Repo::submission()->getCollector()
            ->filterByContextIds([(int) $context->getId()])
            ->getMany() as $submission) {
            $record = $document->createElementNS('http://pkp.sfu.ca', 'submission_record');
            $record->setAttribute('source_ref', (string) $submission->getId());
            $submissionInput = [$submission];
            $submissionDocument = $submissionFilter->execute($submissionInput);
            $article = $document->importNode($submissionDocument->documentElement, true);
            $this->addIssueReferences($article, $submission);
            $record->appendChild($article);
            $submissionsNode->appendChild($record);
        }
        $root->appendChild($submissionsNode);
        return $document;
    }

    private function addIssueReferences(DOMElement $article, $submission): void
    {
        $issueReferences = [];
        foreach ($submission->getData('publications') as $publication) {
            if ($publication->getData('issueId')) {
                $issueReferences[(string) $publication->getId()] = (string) $publication->getData('issueId');
            }
        }
        foreach ($article->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->localName !== 'publication') {
                continue;
            }
            $sourceReference = $this->internalId($child);
            if (isset($issueReferences[$sourceReference])) {
                $child->setAttribute('issue_ref', $issueReferences[$sourceReference]);
            }
        }
    }

    private function internalId(DOMElement $parent): string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement
                && $child->localName === 'id'
                && $child->getAttribute('type') === 'internal'
            ) {
                return trim($child->textContent);
            }
        }
        throw new InvalidArgumentException('Missing publication source reference');
    }
}
