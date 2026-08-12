<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlReviewAssignmentFilter extends NativeImportFilter
{
    private const INTEGER_FIELDS = [
        'stage_id', 'review_method', 'round', 'step', 'recommendation', 'declined', 'cancelled', 'quality',
        'reminder_was_automatic', 'considered', 'request_resent',
    ];
    private const DATE_FIELDS = [
        'date_assigned', 'date_notified', 'date_confirmed', 'date_completed', 'date_acknowledged', 'date_due',
        'date_response_due', 'date_rated', 'last_modified', 'date_reminded',
    ];

    public function getPluralElementName()
    {
        return 'review_assignments';
    }

    public function getSingularElementName()
    {
        return 'review_assignment';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $sourceReference = $this->required($node, 'source_ref');
        $submissionId = $deployment->requireReference('submission', $this->required($node, 'submission_ref'));
        $reviewRoundId = $deployment->requireReference(
            'review_round',
            $this->required($node, 'review_round_ref')
        );
        if ((int) DB::table('review_rounds')->where('review_round_id', $reviewRoundId)->value('submission_id')
            !== $submissionId
        ) {
            throw new InvalidArgumentException('Review assignment does not belong to its review round submission');
        }
        $values = [
            'submission_id' => $submissionId,
            'reviewer_id' => $deployment->requireReference('user', $this->required($node, 'reviewer_ref')),
            'review_round_id' => $reviewRoundId,
            'competing_interests' => $node->hasAttribute('competing_interests')
                ? $node->getAttribute('competing_interests') : null,
            'review_form_id' => $node->hasAttribute('review_form_ref')
                ? $deployment->requireReference('review_form', $this->required($node, 'review_form_ref')) : null,
        ];
        foreach (self::INTEGER_FIELDS as $field) {
            $values[$field] = $this->integer($node, $field);
        }
        foreach (self::DATE_FIELDS as $field) {
            $values[$field] = $this->date($node, $field);
        }
        $reviewId = DB::table('review_assignments')->insertGetId($values, 'review_id');
        $deployment->mapReference('review_assignment', $sourceReference, (int) $reviewId);
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if ($child->localName === 'review_response') {
                $type = $this->required($child, 'type');
                if (!in_array($type, ['string', 'int', 'object'], true)) {
                    throw new InvalidArgumentException('Invalid review response type');
                }
                $elementId = $deployment->requireReference(
                    'review_form_element',
                    $this->required($child, 'element_ref')
                );
                if ($values['review_form_id'] === null
                    || (int) DB::table('review_form_elements')
                        ->where('review_form_element_id', $elementId)
                        ->value('review_form_id') !== $values['review_form_id']
                ) {
                    throw new InvalidArgumentException('Review response does not belong to the assigned review form');
                }
                DB::table('review_form_responses')->insert([
                    'review_form_element_id' => $elementId,
                    'review_id' => $reviewId,
                    'response_type' => $type,
                    'response_value' => $child->textContent,
                ]);
            }
            if ($child->localName === 'review_comment') {
                DB::table('submission_comments')->insert([
                    'comment_type' => 1,
                    'role_id' => $this->integer($child, 'role_id'),
                    'submission_id' => $submissionId,
                    'assoc_id' => $reviewId,
                    'author_id' => $deployment->requireReference('user', $this->required($child, 'author_ref')),
                    'comment_title' => $this->required($child, 'title'),
                    'comments' => $child->textContent,
                    'date_posted' => $this->date($child, 'date_posted'),
                    'date_modified' => $this->date($child, 'date_modified'),
                    'viewable' => $this->boolean($child, 'viewable'),
                ]);
            }
            if ($child->localName === 'review_file') {
                $submissionFileId = $deployment->requireReference(
                    'submission_file',
                    $this->required($child, 'submission_file_ref')
                );
                $this->requireSubmissionFile($submissionFileId, $submissionId);
                DB::table('review_files')->insert([
                    'review_id' => $reviewId,
                    'submission_file_id' => $submissionFileId,
                ]);
            }
        }
        return DAORegistry::getDAO('ReviewAssignmentDAO')->getById((int) $reviewId);
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException('Missing review assignment value: ' . $attribute);
        }
        return $value;
    }

    private function integer($node, string $attribute): ?int
    {
        if (!$node->hasAttribute($attribute)) {
            return null;
        }
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
        if ($value === false) {
            throw new InvalidArgumentException('Invalid review assignment integer: ' . $attribute);
        }
        return $value;
    }

    private function date($node, string $attribute): ?string
    {
        if (!$node->hasAttribute($attribute) || $node->getAttribute($attribute) === '') {
            return null;
        }
        $value = $node->getAttribute($attribute);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException('Invalid review assignment date: ' . $attribute);
        }
        return $value;
    }

    private function boolean($node, string $attribute): int
    {
        $value = $this->required($node, $attribute);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException('Invalid review comment boolean');
        }
        return $value === 'true' ? 1 : 0;
    }

    private function requireSubmissionFile(int $submissionFileId, int $submissionId): void
    {
        if ((int) DB::table('submission_files')
            ->where('submission_file_id', $submissionFileId)
            ->value('submission_id') !== $submissionId
        ) {
            throw new InvalidArgumentException('Review file does not belong to the review submission');
        }
    }
}
