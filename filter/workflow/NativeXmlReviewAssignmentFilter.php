<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\workflow;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

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
            throw new InvalidArgumentException(sprintf(
                'Review assignment source_ref "%s" submission_ref "%s" does not belong to review_round_ref "%s" '
                    . 'at line %d',
                $sourceReference,
                $node->getAttribute('submission_ref'),
                $node->getAttribute('review_round_ref'),
                $node->getLineNo()
            ));
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
        foreach ([
            'review_response' => ['review_responses', 'full-journal-workflow-xml=>review-response'],
            'review_comment' => ['review_comments', 'full-journal-workflow-xml=>review-comment'],
            'review_file' => ['review_files', 'full-journal-workflow-xml=>review-file'],
        ] as $element => [$container, $group]) {
            $document = $this->childrenDocument($node, $element, $container);
            if ($document) {
                $filter = PKPImportExportFilter::getFilter($group, $deployment);
                $filter->execute($document);
            }
        }
        return DAORegistry::getDAO('ReviewAssignmentDAO')->getById((int) $reviewId);
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing review assignment attribute "%s" for source_ref "%s" at line %d',
                $attribute,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
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
            throw new InvalidArgumentException(sprintf(
                'Invalid review assignment %s "%s" for source_ref "%s" at line %d; expected an integer',
                $attribute,
                $node->getAttribute($attribute),
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
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
            throw new InvalidArgumentException(sprintf(
                'Invalid review assignment %s "%s" for source_ref "%s" at line %d',
                $attribute,
                $value,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function childrenDocument($node, string $element, string $container): ?DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', $container);
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $element) {
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
