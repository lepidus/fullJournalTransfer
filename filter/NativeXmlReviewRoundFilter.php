<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlReviewRoundFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'review_rounds';
    }

    public function getSingularElementName()
    {
        return 'review_round';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $sourceReference = $this->requiredReference($node, 'source_ref');
        $stageId = $this->positiveInteger($node, 'stage_id');
        if (!in_array($stageId, [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW], true)) {
            throw new InvalidArgumentException('Invalid review round stage');
        }
        $id = DB::table('review_rounds')->insertGetId([
            'submission_id' => $deployment->requireReference(
                'submission',
                $this->requiredReference($node, 'submission_ref')
            ),
            'stage_id' => $stageId,
            'round' => $this->positiveInteger($node, 'round'),
            'status' => $this->nonNegativeInteger($node, 'status'),
        ], 'review_round_id');
        $deployment->mapReference('review_round', $sourceReference, (int) $id);
        $assignments = $this->assignmentsDocument($node);
        if ($assignments) {
            $filter = PKPImportExportFilter::getFilter(
                'full-journal-workflow-xml=>review-assignment',
                $deployment
            );
            $filter->execute($assignments);
        }
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'review_round_file') {
                $submissionId = $deployment->requireReference(
                    'submission',
                    $this->requiredReference($node, 'submission_ref')
                );
                $submissionFileId = $deployment->requireReference(
                    'submission_file',
                    $this->requiredReference($child, 'submission_file_ref')
                );
                if ((int) DB::table('submission_files')
                    ->where('submission_file_id', $submissionFileId)
                    ->value('submission_id') !== $submissionId
                ) {
                    throw new InvalidArgumentException('Review round file does not belong to the review submission');
                }
                DB::table('review_round_files')->insert([
                    'submission_id' => $submissionId,
                    'review_round_id' => $id,
                    'stage_id' => $this->positiveInteger($child, 'stage_id'),
                    'submission_file_id' => $submissionFileId,
                ]);
            }
        }
        return DAORegistry::getDAO('ReviewRoundDAO')->getById((int) $id);
    }

    private function requiredReference($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException('Missing review round reference: ' . $attribute);
        }
        return $value;
    }

    private function positiveInteger($node, string $attribute): int
    {
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new InvalidArgumentException('Invalid review round integer: ' . $attribute);
        }
        return $value;
    }

    private function nonNegativeInteger($node, string $attribute): int
    {
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            throw new InvalidArgumentException('Invalid review round integer: ' . $attribute);
        }
        return $value;
    }

    private function assignmentsDocument($node): ?\DOMDocument
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'review_assignments');
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'review_assignment') {
                $root->appendChild($document->importNode($child, true));
            }
        }
        if (!$root->hasChildNodes()) {
            return null;
        }
        $document->appendChild($root);
        return $document;
    }
}
