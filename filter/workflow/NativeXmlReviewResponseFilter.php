<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\workflow;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlReviewResponseFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'review_responses';
    }

    public function getSingularElementName()
    {
        return 'review_response';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $reviewId = $deployment->requireReference('review_assignment', $this->required($node, 'review_ref'));
        $elementId = $deployment->requireReference(
            'review_form_element',
            $this->required($node, 'element_ref')
        );
        $reviewFormId = DB::table('review_assignments')->where('review_id', $reviewId)->value('review_form_id');
        if ($reviewFormId === null || (int) DB::table('review_form_elements')
            ->where('review_form_element_id', $elementId)->value('review_form_id') !== (int) $reviewFormId
        ) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.reviewResponseFormMismatch'));
        }
        $type = $this->required($node, 'type');
        if (!in_array($type, ['string', 'int', 'object'], true)) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.invalidReviewResponseTypeReviewRefElementRefLine',
                [
                    'type' => $type,
                    'reviewRef' => $node->getAttribute('review_ref'),
                    'elementRef' => $node->getAttribute('element_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        if (!$node->hasAttribute('is_null') || $node->getAttribute('is_null') === '') {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.missingReviewResponseAttributeNullReviewRefElementRefLine',
                [
                    'reviewRef' => $node->getAttribute('review_ref'),
                    'elementRef' => $node->getAttribute('element_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        $isNull = $node->getAttribute('is_null');
        if (!in_array($isNull, ['true', 'false'], true)) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.invalidReviewResponseNullReviewRefElementRefLine',
                [
                    'isNull' => $isNull,
                    'reviewRef' => $node->getAttribute('review_ref'),
                    'elementRef' => $node->getAttribute('element_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        if ($isNull === 'true' && $node->textContent !== '') {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.nullReviewResponseContainsText'));
        }
        DB::table('review_form_responses')->insert([
            'review_form_element_id' => $elementId,
            'review_id' => $reviewId,
            'response_type' => $type,
            'response_value' => $isNull === 'true' ? null : $node->textContent,
        ]);
        return DAORegistry::getDAO('ReviewFormResponseDAO')->getReviewFormResponse($reviewId, $elementId);
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.missingReviewResponseAttributeReviewRefLine',
                [
                    'attribute' => $attribute,
                    'reviewRef' => $node->getAttribute('review_ref'),
                    'line' => $node->getLineNo(),
                ]
            ));
        }
        return $value;
    }
}
