<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\plugins\importexport\fullJournalTransfer\NativeDataReferenceValidator;
use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NativeDataReferenceValidatorTest extends TestCase
{
    public function testItAcceptsNativeIssuesAndArticles(): void
    {
        (new NativeDataReferenceValidator())->validate($this->nativeData('', $this->issue(), ''));
        $this->addToAssertionCount(1);
    }

    public function testItRejectsUnknownIssueOrderReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown issue order reference');
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData('<issue_order issue_ref="2" position="1"/>', $this->issue(), '')
        );
    }

    public function testItRejectsDuplicatedNativeIssueReferences(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicated issue source reference');
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData('', $this->issue() . $this->issue(), '')
        );
    }

    public function testItRejectsAnIssueWithoutNativeArticles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid OJS Native XML');
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData('', '<issue><id type="internal">1</id><issue_identification/></issue>', '')
        );
    }

    public function testItRejectsUnsafeExternalFileReferences(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsafe native file reference');
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData('', $this->issue(), $this->article('../private.txt'))
        );
    }

    private function nativeData(string $orders, string $issues, string $articles): \DOMElement
    {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML(
            '<native_data xmlns="http://pkp.sfu.ca"><issue_orders>' . $orders . '</issue_orders>'
            . '<issues>' . $issues . '</issues><articles>' . $articles . '</articles></native_data>'
        ));
        return $document->documentElement;
    }

    private function issue(): string
    {
        return '<issue><id type="internal">1</id><issue_identification/><articles/></issue>';
    }

    private function article(string $source): string
    {
        return '<article current_publication_id="20" stage="submission"><id type="internal">10</id>'
            . '<submission_file id="30" stage="submission"><name locale="en">File</name>'
            . '<file id="40"><href src="' . $source . '"/></file></submission_file>'
            . '<publication section_ref="ART" version="1"><id type="internal">20</id>'
            . '<title locale="en">Article</title></publication></article>';
    }
}
