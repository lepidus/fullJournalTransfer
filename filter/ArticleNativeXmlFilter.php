<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use APP\submission\Submission;
use DOMDocument;
use DOMElement;
use PKP\core\PKPApplication;
use PKP\plugins\importexport\PKPImportExportFilter;
use PKP\submissionFile\SubmissionFile;

class ArticleNativeXmlFilter extends \APP\plugins\importexport\native\filter\ArticleNativeXmlFilter
{
    public function addFiles(DOMDocument $document, DOMElement $submissionNode, Submission $submission): void
    {
        $excludedStages = [
            SubmissionFile::SUBMISSION_FILE_QUERY,
            SubmissionFile::SUBMISSION_FILE_NOTE,
            SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT,
            SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
            SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
            SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
        ];
        foreach (Repo::submissionFile()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->includeDependentFiles()
            ->getMany() as $submissionFile) {
            if (in_array($submissionFile->getData('fileStage'), $excludedStages, true)) {
                $this->getDeployment()->addWarning(
                    PKPApplication::ASSOC_TYPE_SUBMISSION,
                    $submission->getId(),
                    __('plugins.importexport.native.error.submissionFileSkipped', ['id' => $submissionFile->getId()])
                );
                continue;
            }
            $filter = PKPImportExportFilter::getFilter(
                'submission-file=>full-journal-native-xml',
                $this->getDeployment(),
                $this->opts
            );
            $fileDocument = $filter->execute($submissionFile, true);
            if ($fileDocument && $fileDocument->documentElement instanceof DOMElement) {
                $submissionNode->appendChild($document->importNode($fileDocument->documentElement, true));
            }
        }
    }
}
