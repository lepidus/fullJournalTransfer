<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\plugins\importexport\fullJournalTransfer\NativeDataReferenceValidator;
use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NativeDataReferenceValidatorTest extends TestCase
{
    public function testItAcceptsMappedIssuesAndSubmissionPublications(): void
    {
        $validator = new NativeDataReferenceValidator();

        $validator->validate($this->nativeData(
            '<issues><issue_record source_ref="issue-1"><issue/></issue_record></issues>',
            '<submissions><submission_record source_ref="submission-1"><article current_publication_id="20">'
            . '<publication issue_ref="issue-1"><id type="internal">20</id></publication>'
            . '</article></submission_record></submissions>',
            'issue-1'
        ));

        $this->addToAssertionCount(1);
    }

    public function testItRejectsUnknownCurrentIssueBeforeImport(): void
    {
        $validator = new NativeDataReferenceValidator();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown current issue reference');

        $validator->validate($this->nativeData('<issues/>', '<submissions/>', 'missing-issue'));
    }

    public function testItRejectsUnknownPublicationIssueBeforeImport(): void
    {
        $validator = new NativeDataReferenceValidator();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown issue reference in publication');

        $validator->validate($this->nativeData(
            '<issues><issue_record source_ref="issue-1"><issue/></issue_record></issues>',
            '<submissions><submission_record source_ref="submission-1"><article current_publication_id="20">'
            . '<publication issue_ref="missing-issue"><id type="internal">20</id></publication>'
            . '</article></submission_record></submissions>'
        ));
    }

    public function testItRejectsUnknownCurrentPublicationBeforeImport(): void
    {
        $validator = new NativeDataReferenceValidator();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown current publication reference');

        $validator->validate($this->nativeData(
            '<issues/>',
            '<submissions><submission_record source_ref="submission-1"><article current_publication_id="21">'
            . '<publication><id type="internal">20</id></publication>'
            . '</article></submission_record></submissions>'
        ));
    }

    public function testItRejectsDuplicatedTypedReferences(): void
    {
        $validator = new NativeDataReferenceValidator();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicated issue source reference');

        $validator->validate($this->nativeData(
            '<issues><issue_record source_ref="issue-1"><issue/></issue_record>'
            . '<issue_record source_ref="issue-1"><issue/></issue_record></issues>',
            '<submissions/>'
        ));
    }

    public function testItRejectsInvalidFileChecksumBeforeImport(): void
    {
        $validator = new NativeDataReferenceValidator();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File checksum does not match its payload');

        $validator->validate($this->nativeData(
            '<issues/>',
            '<submissions><submission_record source_ref="submission-1">'
            . '<article current_publication_id="20"><submission_file id="30">'
            . '<file id="40" checksum="' . str_repeat('a', 64) . '">'
            . '<embed encoding="base64">' . base64_encode('payload') . '</embed></file>'
            . '</submission_file><publication><id type="internal">20</id></publication>'
            . '</article></submission_record></submissions>'
        ));
    }

    private function nativeData(string $issues, string $submissions, ?string $currentIssueRef = null): \DOMElement
    {
        $attribute = $currentIssueRef === null ? '' : ' current_issue_ref="' . $currentIssueRef . '"';
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML(
            '<native_data xmlns="http://pkp.sfu.ca"' . $attribute . '>'
            . $issues . $submissions . '</native_data>'
        ));
        return $document->documentElement;
    }
}
