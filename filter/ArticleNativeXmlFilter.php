<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\transfer\SubmissionFileTransferPlanner;
use APP\publication\Publication;
use APP\submission\Submission;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\PKPImportExportFilter;
use RuntimeException;

class ArticleNativeXmlFilter extends \APP\plugins\importexport\native\filter\ArticleNativeXmlFilter
{
    public function addPublications($document, $submissionNode, $submission)
    {
        $filter = PKPImportExportFilter::getFilter(
            'publication=>native-xml',
            $this->getDeployment()
        );
        foreach ($submission->getData('publications') as $publication) {
            $this->requireLocalizedTitle($publication, $submission);
            $deployment = $this->getDeployment();
            $issue = $deployment->getIssue();
            $deployment->setIssue(null);
            try {
                $publicationDocument = $filter->execute($publication);
            } finally {
                $deployment->setIssue($issue);
            }
            if ($publicationDocument && $publicationDocument->documentElement instanceof DOMElement) {
                $this->replaceSerializedPublicationTitlesWithRawValues($publicationDocument, $publication);
                $submissionNode->appendChild($document->importNode($publicationDocument->documentElement, true));
                continue;
            }
            throw new RuntimeException(sprintf(
                'Publication %d from submission %d could not be exported',
                $publication->getId(),
                $submission->getId()
            ));
        }
    }

    private function requireLocalizedTitle(Publication $publication, Submission $submission): void
    {
        $titles = $publication->getData('title');
        if (is_array($titles)) {
            foreach ($titles as $title) {
                if (is_string($title) && trim($title) !== '') {
                    return;
                }
            }
        }
        throw new InvalidArgumentException(sprintf(
            'Publication %d from submission %d has no localized title',
            $publication->getId(),
            $submission->getId()
        ));
    }

    private function replaceSerializedPublicationTitlesWithRawValues(
        DOMDocument $document,
        Publication $publication
    ): void {
        $titles = $publication->getData('title');
        foreach ($document->documentElement->childNodes as $node) {
            if (!$node instanceof DOMElement || $node->localName !== 'title') {
                continue;
            }
            $locale = $node->getAttribute('locale');
            if (!is_array($titles) || !isset($titles[$locale]) || !is_string($titles[$locale])) {
                throw new RuntimeException(sprintf(
                    'Publication %d has no raw title for locale %s',
                    $publication->getId(),
                    $locale
                ));
            }
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }
            $node->appendChild($document->createTextNode($titles[$locale]));
        }
    }

    public function addFiles(DOMDocument $document, DOMElement $submissionNode, Submission $submission): void
    {
        $submissionFiles = Repo::submissionFile()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->includeDependentFiles()
            ->getMany();
        $partition = (new SubmissionFileTransferPlanner())->partition($submissionFiles);
        foreach ($partition['native'] as $submissionFile) {
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

}
