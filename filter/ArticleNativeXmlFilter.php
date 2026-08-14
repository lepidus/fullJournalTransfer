<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use APP\submission\Submission;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\PKPImportExportFilter;

class ArticleNativeXmlFilter extends \APP\plugins\importexport\native\filter\ArticleNativeXmlFilter
{
    public function addPublications($document, $submissionNode, $submission)
    {
        $filter = PKPImportExportFilter::getFilter(
            'publication=>native-xml',
            $this->getDeployment()
        );
        foreach ($submission->getData('publications') as $publication) {
            $deployment = $this->getDeployment();
            $issue = $deployment->getIssue();
            $deployment->setIssue(null);
            try {
                $publicationDocument = $filter->execute($publication);
            } finally {
                $deployment->setIssue($issue);
            }
            if ($publicationDocument && $publicationDocument->documentElement instanceof DOMElement) {
                $submissionNode->appendChild($document->importNode($publicationDocument->documentElement, true));
            }
        }
    }

    public function addFiles(DOMDocument $document, DOMElement $submissionNode, Submission $submission): void
    {
        $submissionFiles = Repo::submissionFile()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->includeDependentFiles()
            ->getMany();
        foreach ($this->orderSubmissionFiles($submissionFiles) as $submissionFile) {
            $filter = PKPImportExportFilter::getFilter(
                'submission-file=>full-journal-native-xml',
                $this->getDeployment(),
                array_merge($this->opts, ['no-embed' => true])
            );
            $fileDocument = $filter->execute($submissionFile, true);
            if ($fileDocument && $fileDocument->documentElement instanceof DOMElement) {
                $submissionNode->appendChild($document->importNode($fileDocument->documentElement, true));
            }
        }
    }

    private function orderSubmissionFiles(iterable $submissionFiles): array
    {
        $pending = [];
        foreach ($submissionFiles as $submissionFile) {
            $pending[(int) $submissionFile->getId()] = $submissionFile;
        }
        $allIds = array_fill_keys(array_keys($pending), true);
        $resolved = [];
        $ordered = [];
        while ($pending !== []) {
            $progress = false;
            foreach ($pending as $id => $submissionFile) {
                $sourceId = (int) $submissionFile->getData('sourceSubmissionFileId');
                if ($sourceId && !isset($allIds[$sourceId])) {
                    throw new InvalidArgumentException('A source submission file is missing from the journal export');
                }
                if ($sourceId && !isset($resolved[$sourceId])) {
                    continue;
                }
                $ordered[] = $submissionFile;
                $resolved[$id] = true;
                unset($pending[$id]);
                $progress = true;
            }
            if (!$progress) {
                throw new InvalidArgumentException('Submission file dependencies contain a cycle');
            }
        }
        return $ordered;
    }
}
