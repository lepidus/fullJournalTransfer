<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\workflow;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlReviewCommentFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'review_comments';
    }

    public function getSingularElementName()
    {
        return 'review_comment';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $reviewId = $deployment->requireReference('review_assignment', $this->required($node, 'review_ref'));
        $submissionId = (int) DB::table('review_assignments')
            ->where('review_id', $reviewId)->value('submission_id');
        $id = DB::table('submission_comments')->insertGetId([
            'comment_type' => 1,
            'role_id' => $this->integer($node, 'role_id'),
            'submission_id' => $submissionId,
            'assoc_id' => $reviewId,
            'author_id' => $deployment->requireReference('user', $this->required($node, 'author_ref')),
            'comment_title' => $this->required($node, 'title'),
            'comments' => $node->textContent,
            'date_posted' => $this->date($node, 'date_posted'),
            'date_modified' => $this->date($node, 'date_modified'),
            'viewable' => $this->boolean($node, 'viewable'),
        ], 'comment_id');
        return DAORegistry::getDAO('SubmissionCommentDAO')->getById((int) $id);
    }

    private function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing review comment attribute "%s" for review_ref "%s" at line %d',
                $attribute,
                $node->getAttribute('review_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function integer($node, string $attribute): int
    {
        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
        if ($value === false) {
            throw new InvalidArgumentException(sprintf(
                'Invalid review comment %s "%s" for review_ref "%s" at line %d; expected an integer',
                $attribute,
                $node->getAttribute($attribute),
                $node->getAttribute('review_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function date($node, string $attribute): string
    {
        $value = $this->required($node, $attribute);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException(sprintf(
                'Invalid review comment %s "%s" for review_ref "%s" at line %d',
                $attribute,
                $value,
                $node->getAttribute('review_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function boolean($node, string $attribute): int
    {
        $value = $this->required($node, $attribute);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid review comment %s "%s" for review_ref "%s" at line %d; expected "true" or "false"',
                $attribute,
                $value,
                $node->getAttribute('review_ref'),
                $node->getLineNo()
            ));
        }
        return $value === 'true' ? 1 : 0;
    }
}
