<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\workflow;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

class NativeXmlStageAssignmentFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'stage_assignments';
    }

    public function getSingularElementName()
    {
        return 'stage_assignment';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $sourceReference = $this->requiredReference($node, 'source_ref');
        $id = DB::table('stage_assignments')->insertGetId([
            'submission_id' => $deployment->requireReference(
                'submission',
                $this->requiredReference($node, 'submission_ref')
            ),
            'user_group_id' => $deployment->requireReference(
                'user_group',
                $this->requiredReference($node, 'user_group_ref')
            ),
            'user_id' => $deployment->requireReference('user', $this->requiredReference($node, 'user_ref')),
            'date_assigned' => $this->requiredDate($node, 'date_assigned'),
            'recommend_only' => $this->boolean($node, 'recommend_only'),
            'can_change_metadata' => $this->boolean($node, 'can_change_metadata'),
        ], 'stage_assignment_id');
        $deployment->mapReference('stage_assignment', $sourceReference, (int) $id);
        return DAORegistry::getDAO('StageAssignmentDAO')->getById((int) $id);
    }

    private function requiredReference($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing stage assignment attribute "%s" for source_ref "%s" at line %d',
                $attribute,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function requiredDate($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException(sprintf(
                'Invalid stage assignment %s "%s" for source_ref "%s" at line %d',
                $attribute,
                $value,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value;
    }

    private function boolean($node, string $attribute): int
    {
        $value = $node->getAttribute($attribute);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid stage assignment %s "%s" for source_ref "%s" at line %d; expected "true" or "false"',
                $attribute,
                $value,
                $node->getAttribute('source_ref'),
                $node->getLineNo()
            ));
        }
        return $value === 'true' ? 1 : 0;
    }
}
