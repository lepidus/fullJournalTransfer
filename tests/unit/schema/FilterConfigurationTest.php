<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\schema;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use PKP\core\DataObject;

class FilterConfigurationTest extends TestCase
{
    public function testEveryRegisteredFilterTransformsAPkpEntity(): void
    {
        $document = new DOMDocument();
        $this->assertTrue($document->load(dirname(__DIR__, 3) . '/filter/filterConfig.xml'));

        $invalidGroups = [];
        foreach ($document->getElementsByTagName('filterGroup') as $group) {
            $inputType = $group->getAttribute('inputType');
            $outputType = $group->getAttribute('outputType');
            if (!$this->isPkpEntityType($inputType) && !$this->isPkpEntityType($outputType)) {
                $invalidGroups[] = $group->getAttribute('symbolic');
            }
        }

        $this->assertSame([], $invalidGroups, 'Generic data must be handled by its parent entity filter');
    }

    private function isPkpEntityType(string $type): bool
    {
        if (preg_match('/^class::([^\[]+)(?:\[\])?$/', $type, $matches) !== 1) {
            return false;
        }
        return is_a($matches[1], DataObject::class, true);
    }
}
