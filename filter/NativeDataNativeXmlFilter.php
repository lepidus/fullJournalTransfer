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
        return $document;
    }
}
