<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\JournalSettingsPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JournalSettingsPolicyTest extends TestCase
{
    public function testItsDefinitionsMatchThePositiveTransferContract(): void
    {
        $expected = [
            'name', 'acronym', 'abbreviation', 'about', 'description', 'editorialTeam',
            'authorInformation', 'librarianInformation', 'readerInformation', 'privacyStatement',
            'openAccessPolicy', 'contactAffiliation', 'contactEmail', 'contactName', 'contactPhone',
            'mailingAddress', 'country', 'onlineIssn', 'printIssn', 'publisherInstitution', 'publisherUrl',
            'supportEmail', 'supportName', 'supportPhone', 'enableOai', 'itemsPerPage', 'numPageLinks',
            'enableDois', 'enabledDoiTypes', 'doiPrefix', 'doiSuffixType', 'doiIssueSuffixPattern',
            'doiPublicationSuffixPattern', 'doiRepresentationSuffixPattern', 'doiVersioning',
            'doiCreationTime', 'copyrightYearBasis', 'copyrightHolderType', 'copyrightHolderOther',
            'copyrightNotice', 'licenseTerms', 'licenseUrl', 'rights', 'publishingMode',
            'disableSubmissions', 'authorGuidelines', 'beginSubmissionHelp', 'contributorsHelp',
            'detailsHelp', 'forTheEditorsHelp', 'uploadFilesHelp', 'submissionAcknowledgement',
            'copySubmissionAckAddress', 'copySubmissionAckPrimaryContact', 'submitWithCategories',
            'agencies', 'citations', 'competingInterests', 'coverage', 'dataAvailability', 'disciplines',
            'keywords', 'languages', 'subjects', 'requireAuthorCompetingInterests', 'defaultReviewMode',
            'numWeeksPerResponse', 'numWeeksPerReview', 'restrictReviewerFileAccess',
            'reviewerAccessKeysEnabled', 'reviewGuidelines', 'reviewHelp', 'numDaysBeforeInviteReminder',
            'numDaysBeforeSubmitReminder', 'rateReviewerOnQuality', 'notifyAllAuthors',
        ];
        $actual = array_keys((new JournalSettingsPolicy())->definitions());
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider settingRoundTripProvider
     */
    public function testItRoundTripsEachTransportType(string $property, $value, string $type, string $payload): void
    {
        $policy = new JournalSettingsPolicy();

        $this->assertSame([$type, $payload], $policy->encode($property, $value));
        $this->assertSame($value, $policy->decode($property, $type, $payload));
    }

    public function settingRoundTripProvider(): array
    {
        return [
            'string' => ['contactName', 'Editorial Team', 'string', 'Editorial Team'],
            'empty nullable string' => ['licenseUrl', '', 'string', ''],
            'integer' => ['itemsPerPage', 25, 'integer', '25'],
            'boolean' => ['enableOai', true, 'boolean', 'true'],
            'enum' => ['doiSuffixType', 'customPattern', 'string', 'customPattern'],
            'string list' => [
                'enabledDoiTypes',
                ['publication', 'issue'],
                'string-list',
                '["publication","issue"]',
            ],
        ];
    }

    /**
     * @dataProvider invalidSettingProvider
     */
    public function testItRejectsUnknownSettingsAndInvalidTypesOrValues(
        string $property,
        $value,
        string $message
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new JournalSettingsPolicy())->encode($property, $value);
    }

    public function invalidSettingProvider(): array
    {
        return [
            'unknown setting' => ['apiKey', 'secret', 'Context setting is not allowed: apiKey'],
            'invalid integer' => ['itemsPerPage', '25', 'Invalid value for context setting: itemsPerPage'],
            'invalid enum' => ['doiSuffixType', 'serialized', 'Invalid value for context setting: doiSuffixType'],
            'invalid list shape' => [
                'enabledDoiTypes',
                ['first' => 'publication'],
                'Invalid value for context setting: enabledDoiTypes',
            ],
            'invalid list item' => [
                'enabledDoiTypes',
                ['publication', 'galley'],
                'Invalid value for context setting: enabledDoiTypes',
            ],
        ];
    }

    public function testItRejectsSerializedDataForAStringList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON context setting: enabledDoiTypes');

        (new JournalSettingsPolicy())->decode(
            'enabledDoiTypes',
            'string-list',
            'a:1:{i:0;s:11:"publication";}'
        );
    }

    public function testItRejectsSubscriptionPublishingMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Subscription publishing mode is not supported because subscriptions are not transferred.'
        );

        (new JournalSettingsPolicy())->encode('publishingMode', Journal::PUBLISHING_MODE_SUBSCRIPTION);
    }

    public function testItRejectsAnUnknownDoiSuffixPatternToken(): void
    {
        $journal = new Journal();
        $journal->setData('enableDois', true);
        $journal->setData('doiPrefix', '10.1234');
        $journal->setData('doiSuffixType', 'customPattern');
        $journal->setData('enabledDoiTypes', ['publication']);
        $journal->setData('doiPublicationSuffixPattern', '%unknown');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for context setting: doiPublicationSuffixPattern');

        (new JournalSettingsPolicy())->validateJournal($journal);
    }
}
