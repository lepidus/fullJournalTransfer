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

    public function testItRejectsUnknownAuthorMetadataReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown author metadata reference');
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData(
                '',
                $this->issue(),
                '',
                '<author author_ref="99"><preferred_public_name locale="en">Ada</preferred_public_name></author>'
            )
        );
    }

    public function testItRejectsDuplicatedAuthorMetadataReferences(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicated author metadata source reference');
        $metadata = '<author author_ref="50"><preferred_public_name locale="en">Ada</preferred_public_name></author>';
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData('', $this->issue(), $this->articleWithAuthor(), $metadata . $metadata)
        );
    }

    public function testItRejectsDuplicatedLocalizedAuthorMetadata(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid localized author metadata');
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData(
                '',
                $this->issue(),
                $this->articleWithAuthor(),
                '<author author_ref="50"><competing_interests locale="en">First</competing_interests>'
                . '<competing_interests locale="en">Second</competing_interests></author>'
            )
        );
    }

    public function testItRejectsUnknownHistoricalSubmissionDateReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown historical submission date reference');
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData(
                '',
                $this->issue(),
                $this->articleWithAuthor(),
                '',
                '<issues><issue issue_ref="1"/></issues><submissions>'
                . '<submission submission_ref="99"/></submissions>'
            )
        );
    }

    public function testItRejectsInvalidHistoricalDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid historical date: date_published');
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData(
                '',
                $this->issue(),
                '',
                '',
                '<issues><issue issue_ref="1" date_published="2024-02-30 12:00:00"/></issues>'
                . '<submissions/>'
            )
        );
    }

    public function testItRejectsMissingHistoricalSubmissionDateReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing historical submission date reference: 10');
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData(
                '',
                $this->issue(),
                $this->articleWithAuthor(),
                '',
                '<issues><issue issue_ref="1"/></issues><submissions/>'
            )
        );
    }

    public function testItRejectsMissingSubmissionProgress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing submission progress');
        $article = str_replace(' submission_progress=""', '', $this->articleWithAuthor());
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData('', $this->issue(), $article)
        );
    }

    public function testItRejectsInvalidSubmissionProgress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid submission progress');
        $article = str_replace('submission_progress=""', 'submission_progress="unknown"', $this->articleWithAuthor());
        (new NativeDataReferenceValidator())->validate(
            $this->nativeData('', $this->issue(), $article)
        );
    }

    private function nativeData(
        string $orders,
        string $issues,
        string $articles,
        string $authorMetadata = '',
        ?string $historicalDates = null
    ): \DOMElement {
        if ($historicalDates === null) {
            $historicalDates = '<issues><issue issue_ref="1"/></issues><submissions>'
                . ($articles === '' ? '' : '<submission submission_ref="10"/>')
                . '</submissions>';
        }
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML(
            '<native_data xmlns="http://pkp.sfu.ca"><issue_orders>' . $orders . '</issue_orders>'
            . '<issues>' . $issues . '</issues><articles>' . $articles . '</articles>'
            . '<author_metadata>' . $authorMetadata . '</author_metadata>'
            . '<historical_dates>' . $historicalDates . '</historical_dates></native_data>'
        ));
        return $document->documentElement;
    }

    private function issue(): string
    {
        return '<issue><id type="internal">1</id><issue_identification/><articles/></issue>';
    }

    private function article(string $source): string
    {
        return '<article current_publication_id="20" stage="submission" submission_progress="">'
            . '<id type="internal">10</id>'
            . '<submission_file id="30" stage="submission"><name locale="en">File</name>'
            . '<file id="40"><href src="' . $source . '"/></file></submission_file>'
            . '<publication section_ref="ART" version="1"><id type="internal">20</id>'
            . '<title locale="en">Article</title></publication></article>';
    }

    private function articleWithAuthor(): string
    {
        return '<article current_publication_id="20" stage="submission" submission_progress="">'
            . '<id type="internal">10</id>'
            . '<publication section_ref="ART" version="1"><id type="internal">20</id>'
            . '<title locale="en">Article</title><authors><author user_group_ref="Author" seq="1" id="50">'
            . '<givenname locale="en">Ada</givenname><email>ada@example.com</email></author></authors>'
            . '</publication></article>';
    }
}
