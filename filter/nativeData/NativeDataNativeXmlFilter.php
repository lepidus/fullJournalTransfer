<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\nativeData;

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

        $issues = Repo::issue()->getCollector()
            ->filterByContextIds([(int) $context->getId()])
            ->getMany()
            ->values()
            ->toArray();
        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([(int) $context->getId()])
            ->getMany()
            ->values()
            ->toArray();
        $assignments = [];
        $submissionsByIssue = [];
        foreach ($submissions as $submission) {
            $issueId = $submission->getCurrentPublication()?->getData('issueId');
            if (!$issueId) {
                foreach ($submission->getData('publications') as $publication) {
                    if ($publication->getData('issueId')) {
                        $issueId = $publication->getData('issueId');
                        break;
                    }
                }
            }
            if ($issueId) {
                $assignments[(int) $submission->getId()] = (int) $issueId;
                $submissionsByIssue[(int) $issueId][] = $submission;
            }
        }
        $this->getDeployment()->setSubmissionsByIssue($submissionsByIssue);

        $orders = $document->createElementNS('http://pkp.sfu.ca', 'issue_orders');
        foreach ($issues as $issue) {
            $customOrder = Repo::issue()->dao->getCustomIssueOrder((int) $context->getId(), (int) $issue->getId());
            if ($customOrder !== null) {
                $order = $document->createElementNS('http://pkp.sfu.ca', 'issue_order');
                $order->setAttribute('issue_ref', (string) $issue->getId());
                $order->setAttribute('position', (string) $customOrder);
                $orders->appendChild($order);
            }
        }
        $root->appendChild($orders);

        $issuesNode = $document->createElementNS('http://pkp.sfu.ca', 'issues');
        $issueFilter = PKPImportExportFilter::getFilter('issue=>full-journal-native-xml', $this->getDeployment());
        foreach ($issues as $issue) {
            $issueInput = [$issue];
            $issueDocument = $issueFilter->execute($issueInput);
            $issuesNode->appendChild($document->importNode($issueDocument->documentElement, true));
        }
        $root->appendChild($issuesNode);

        $standalone = array_values(array_filter(
            $submissions,
            fn ($submission) => !isset($assignments[(int) $submission->getId()])
        ));
        $articleFilter = PKPImportExportFilter::getFilter(
            'article=>full-journal-native-xml',
            $this->getDeployment(),
            ['no-embed' => true]
        );
        $articleFilter->setIncludeSubmissionsNode(true);
        $articlesDocument = $articleFilter->execute($standalone);
        if ($articlesDocument->documentElement instanceof DOMElement) {
            $root->appendChild($document->importNode($articlesDocument->documentElement, true));
        }
        $this->addAuthorMetadata($document, $root, $submissions);
        $this->addHistoricalDates($document, $root, $issues, $submissions);
        return $document;
    }

    private function addHistoricalDates(
        DOMDocument $document,
        DOMElement $root,
        array $issues,
        array $submissions
    ): void {
        $historicalDatesNode = $document->createElementNS('http://pkp.sfu.ca', 'historical_dates');
        $issuesNode = $document->createElementNS('http://pkp.sfu.ca', 'issues');
        $issuesById = [];
        foreach ($issues as $issue) {
            $issuesById[(int) $issue->getId()] = $issue;
        }
        ksort($issuesById, SORT_NUMERIC);
        foreach ($issuesById as $issue) {
            $issueNode = $document->createElementNS('http://pkp.sfu.ca', 'issue');
            $issueNode->setAttribute('issue_ref', (string) $issue->getId());
            $this->setOptionalAttribute($issueNode, 'date_published', $issue->getDatePublished());
            $issuesNode->appendChild($issueNode);
        }
        $historicalDatesNode->appendChild($issuesNode);

        $submissionsNode = $document->createElementNS('http://pkp.sfu.ca', 'submissions');
        $submissionsById = [];
        foreach ($submissions as $submission) {
            $submissionsById[(int) $submission->getId()] = $submission;
        }
        ksort($submissionsById, SORT_NUMERIC);
        foreach ($submissionsById as $submission) {
            $submissionNode = $document->createElementNS('http://pkp.sfu.ca', 'submission');
            $submissionNode->setAttribute('submission_ref', (string) $submission->getId());
            $this->setOptionalAttribute($submissionNode, 'date_submitted', $submission->getData('dateSubmitted'));
            $this->setOptionalAttribute(
                $submissionNode,
                'date_last_activity',
                $submission->getData('dateLastActivity')
            );
            $this->setOptionalAttribute($submissionNode, 'last_modified', $submission->getData('lastModified'));
            $submissionsNode->appendChild($submissionNode);
        }
        $historicalDatesNode->appendChild($submissionsNode);
        $root->appendChild($historicalDatesNode);
    }

    private function setOptionalAttribute(DOMElement $node, string $name, $value): void
    {
        if ($value !== null) {
            $node->setAttribute($name, (string) $value);
        }
    }

    private function addAuthorMetadata(DOMDocument $document, DOMElement $root, array $submissions): void
    {
        $authors = [];
        foreach ($submissions as $submission) {
            foreach ($submission->getData('publications') as $publication) {
                foreach ($publication->getData('authors') as $author) {
                    $authors[(int) $author->getId()] = $author;
                }
            }
        }
        ksort($authors, SORT_NUMERIC);

        $metadataNode = $document->createElementNS('http://pkp.sfu.ca', 'author_metadata');
        foreach ($authors as $author) {
            $authorNode = $document->createElementNS('http://pkp.sfu.ca', 'author');
            $authorNode->setAttribute('author_ref', (string) $author->getId());
            $this->addLocalizedAuthorMetadata(
                $document,
                $authorNode,
                'preferred_public_name',
                $author->getData('preferredPublicName')
            );
            $this->addLocalizedAuthorMetadata(
                $document,
                $authorNode,
                'competing_interests',
                $author->getData('competingInterests')
            );
            if ($authorNode->hasChildNodes()) {
                $metadataNode->appendChild($authorNode);
            }
        }
        $root->appendChild($metadataNode);
    }

    private function addLocalizedAuthorMetadata(
        DOMDocument $document,
        DOMElement $authorNode,
        string $elementName,
        $values
    ): void {
        foreach (is_array($values) ? $values : [] as $locale => $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }
            $valueNode = $document->createElementNS('http://pkp.sfu.ca', $elementName);
            $valueNode->setAttribute('locale', (string) $locale);
            $valueNode->appendChild($document->createTextNode($value));
            $authorNode->appendChild($valueNode);
        }
    }
}
