<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

abstract class NativeXmlMetricFilter extends NativeImportFilter
{
    protected function required($node, string $attribute): string
    {
        $value = trim($node->getAttribute($attribute));
        if ($value === '') {
            throw new InvalidArgumentException('Missing metric value: ' . $attribute);
        }
        return $value;
    }

    protected function date($node): string
    {
        $value = $this->required($node, 'date');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid metric date');
        }
        return $value;
    }

    protected function nonNegativeInteger($node, string $attribute): int
    {
        $value = $this->required($node, $attribute);
        if (preg_match('/^(0|[1-9][0-9]*)$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid metric value: ' . $attribute);
        }
        return (int) $value;
    }

    protected function optionalInteger($node, string $attribute): ?int
    {
        if (!$node->hasAttribute($attribute)) {
            return null;
        }
        return $this->nonNegativeInteger($node, $attribute);
    }

    protected function optionalReference($node, string $entity, string $attribute): ?int
    {
        if (!$node->hasAttribute($attribute)) {
            return null;
        }
        return $this->getDeployment()->requireReference($entity, $this->required($node, $attribute));
    }

    protected function month($node): int
    {
        $value = $this->required($node, 'month');
        $month = \DateTimeImmutable::createFromFormat('!Ym', $value);
        if (!$month || $month->format('Ym') !== $value) {
            throw new InvalidArgumentException('Invalid metric month');
        }
        return (int) $value;
    }

    protected function monthRange(int $month): array
    {
        $start = \DateTimeImmutable::createFromFormat('!Ym', (string) $month);
        if (!$start) {
            throw new InvalidArgumentException('Invalid metric month');
        }
        return [$start->format('Y-m-d'), $start->modify('last day of this month')->format('Y-m-d')];
    }
}
