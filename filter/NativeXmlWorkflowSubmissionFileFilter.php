<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use APP\facades\Repo;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\submissionFile\SubmissionFile;

class NativeXmlWorkflowSubmissionFileFilter extends NativeXmlSubmissionFileFilter
{
    public function getPluralElementName()
    {
        return 'workflow_files';
    }

    public function getSingularElementName()
    {
        return 'workflow_file';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $submissionReference = $this->required($node, 'submission_ref');
        $submissionId = $deployment->requireReference('submission', $submissionReference);
        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            throw new InvalidArgumentException('Workflow submission file submission was not imported');
        }
        $payload = $this->payload($node);
        $sourceSubmissionFileId = trim($payload->getAttribute('id'));
        if ($sourceSubmissionFileId === '') {
            throw new InvalidArgumentException('Missing workflow submission file source id');
        }
        $stageName = $payload->getAttribute('stage');
        $stageMapping = $deployment->getStageNameStageIdMapping();
        if (!isset($stageMapping[$stageName])) {
            throw new InvalidArgumentException('Invalid workflow submission file stage');
        }
        $fileStage = $stageMapping[$stageName];
        $reviewRoundId = null;
        if (in_array($fileStage, [
            SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
        ], true)) {
            $reviewRoundId = $deployment->requireReference(
                'review_round',
                $this->required($node, 'review_round_ref')
            );
            if ((int) DB::table('review_rounds')->where('review_round_id', $reviewRoundId)
                ->value('submission_id') !== $submissionId
            ) {
                throw new InvalidArgumentException('Workflow submission file review round belongs to another submission');
            }
            $payload->setAttribute('stage', 'submission');
        } elseif ($node->hasAttribute('review_round_ref')) {
            throw new InvalidArgumentException('Only review revisions may reference a review round');
        }

        $previousSubmission = $deployment->getSubmission();
        $deployment->setSubmission($submission);
        try {
            $submissionFile = parent::handleElement($payload);
        } finally {
            $deployment->setSubmission($previousSubmission);
        }
        if (!$submissionFile instanceof SubmissionFile) {
            throw new InvalidArgumentException('Workflow submission file could not be imported');
        }
        $deployment->mapReference(
            'submission_file',
            $sourceSubmissionFileId,
            (int) $submissionFile->getId()
        );
        if ($reviewRoundId !== null) {
            $submissionFile->setData('fileStage', $fileStage);
            $submissionFile->setData('assocType', Application::ASSOC_TYPE_REVIEW_ROUND);
            $submissionFile->setData('assocId', $reviewRoundId);
            Repo::submissionFile()->dao->update($submissionFile);
            $reviewRound = DB::table('review_rounds')->where('review_round_id', $reviewRoundId)->first();
            if (!$reviewRound) {
                throw new InvalidArgumentException('Workflow submission file review round was not imported');
            }
            DB::table('review_round_files')->updateOrInsert(
                [
                    'submission_id' => $submissionId,
                    'review_round_id' => $reviewRoundId,
                    'submission_file_id' => $submissionFile->getId(),
                ],
                ['stage_id' => (int) $reviewRound->stage_id]
            );
            $submissionFile = Repo::submissionFile()->get((int) $submissionFile->getId());
        }
        return $submissionFile;
    }

    private function payload($node): DOMElement
    {
        $matches = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'submission_file') {
                $matches[] = $child;
            }
        }
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('Expected exactly one workflow submission file payload');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $payload = $document->importNode($matches[0], true);
        $document->appendChild($payload);
        return $payload;
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException('Missing workflow submission file value: ' . $attribute);
        }
        return $value;
    }
}
