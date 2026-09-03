<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\validation;

use APP\plugins\importexport\fullJournalTransfer\validation\PackageReferenceValidator;
use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PackageReferenceValidatorTest extends TestCase
{
    public function testItReportsTheDuplicatedEntityReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicated genre source_ref "genre-1" at line 1');

        (new PackageReferenceValidator())->validateReferenceData($this->referenceData(
            '<genres><genre source_ref="genre-1"/><genre source_ref="genre-1"/></genres>'
        ));
    }

    public function testItReportsTheEntityMissingItsSourceReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing source_ref in reference data element "section" at line 1');

        (new PackageReferenceValidator())->validateReferenceData($this->referenceData(
            '<genres/><sections><section/></sections>'
        ));
    }

    private function referenceData(string $content): \DOMElement
    {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML(
            '<reference_data><review_forms/>' . $content
            . (str_contains($content, '<sections>') ? '' : '<sections/>')
            . '</reference_data>'
        ));
        return $document->documentElement;
    }
}
