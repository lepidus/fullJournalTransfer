<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\journal\Journal;
use InvalidArgumentException;
use JsonException;

class JournalSettingsPolicy
{
    private const LOCALIZED_SETTINGS = [
        'name',
        'acronym',
        'abbreviation',
        'about',
        'description',
        'editorialTeam',
        'authorInformation',
        'librarianInformation',
        'readerInformation',
        'privacyStatement',
        'openAccessPolicy',
        'contactAffiliation',
        'copyrightHolderOther',
        'copyrightNotice',
        'licenseTerms',
        'authorGuidelines',
        'beginSubmissionHelp',
        'contributorsHelp',
        'detailsHelp',
        'forTheEditorsHelp',
        'uploadFilesHelp',
        'competingInterests',
        'reviewGuidelines',
        'reviewHelp',
    ];

    private const STRING_SETTINGS = [
        'contactEmail',
        'contactName',
        'contactPhone',
        'mailingAddress',
        'country',
        'onlineIssn',
        'printIssn',
        'publisherInstitution',
        'publisherUrl',
        'supportEmail',
        'supportName',
        'supportPhone',
        'doiPrefix',
        'doiIssueSuffixPattern',
        'doiPublicationSuffixPattern',
        'doiRepresentationSuffixPattern',
        'licenseUrl',
        'copySubmissionAckAddress',
    ];

    private const INTEGER_SETTINGS = [
        'itemsPerPage',
        'numPageLinks',
        'numWeeksPerResponse',
        'numWeeksPerReview',
        'numDaysBeforeInviteReminder',
        'numDaysBeforeSubmitReminder',
    ];

    private const BOOLEAN_SETTINGS = [
        'enableOai',
        'enableDois',
        'doiVersioning',
        'disableSubmissions',
        'copySubmissionAckPrimaryContact',
        'submitWithCategories',
        'requireAuthorCompetingInterests',
        'restrictReviewerFileAccess',
        'reviewerAccessKeysEnabled',
        'rateReviewerOnQuality',
        'notifyAllAuthors',
    ];

    private const ENUM_SETTINGS = [
        'doiSuffixType' => ['default', 'customPattern', 'customId'],
        'doiCreationTime' => ['copyEditCreationTime', 'publicationCreationTime', 'neverCreationTime'],
        'copyrightYearBasis' => ['issue', 'submission'],
        'copyrightHolderType' => ['author', 'context', 'other'],
        'submissionAcknowledgement' => ['', 'submittingAuthor', 'allAuthors'],
        'agencies' => ['0', 'enable', 'request', 'require'],
        'citations' => ['0', 'enable', 'request', 'require'],
        'coverage' => ['0', 'enable', 'request', 'require'],
        'dataAvailability' => ['0', 'enable', 'request', 'require'],
        'disciplines' => ['0', 'enable', 'request', 'require'],
        'keywords' => ['0', 'enable', 'request', 'require'],
        'languages' => ['0', 'enable', 'request', 'require'],
        'rights' => ['0', 'enable', 'request', 'require'],
        'subjects' => ['0', 'enable', 'request', 'require'],
    ];

    private const INTEGER_ENUM_SETTINGS = [
        'publishingMode' => [
            Journal::PUBLISHING_MODE_OPEN,
            Journal::PUBLISHING_MODE_SUBSCRIPTION,
            Journal::PUBLISHING_MODE_NONE,
        ],
        'defaultReviewMode' => [1, 2, 3],
    ];

    private const STRING_LIST_SETTINGS = [
        'enabledDoiTypes' => ['publication', 'representation', 'issue'],
    ];

    /** @return array<string, array{type: string, localized: bool}> */
    public function definitions(): array
    {
        $definitions = [];
        foreach (self::LOCALIZED_SETTINGS as $property) {
            $definitions[$property] = ['type' => 'string', 'localized' => true];
        }
        foreach (self::STRING_SETTINGS as $property) {
            $definitions[$property] = ['type' => 'string', 'localized' => false];
        }
        foreach (array_keys(self::ENUM_SETTINGS) as $property) {
            $definitions[$property] = ['type' => 'string', 'localized' => false];
        }
        foreach (self::INTEGER_SETTINGS as $property) {
            $definitions[$property] = ['type' => 'integer', 'localized' => false];
        }
        foreach (array_keys(self::INTEGER_ENUM_SETTINGS) as $property) {
            $definitions[$property] = ['type' => 'integer', 'localized' => false];
        }
        foreach (self::BOOLEAN_SETTINGS as $property) {
            $definitions[$property] = ['type' => 'boolean', 'localized' => false];
        }
        foreach (array_keys(self::STRING_LIST_SETTINGS) as $property) {
            $definitions[$property] = ['type' => 'string-list', 'localized' => false];
        }
        return $definitions;
    }

    /** @return array{type: string, localized: bool} */
    public function definition(string $property): array
    {
        $definitions = $this->definitions();
        if (!isset($definitions[$property])) {
            throw new InvalidArgumentException('Context setting is not allowed: ' . $property);
        }
        return $definitions[$property];
    }

    /** @return array{string, string} */
    public function encode(string $property, $value): array
    {
        $definition = $this->definition($property);
        $this->validateValue($property, $value, $definition['type']);
        if ($definition['type'] === 'boolean') {
            return [$definition['type'], $value ? 'true' : 'false'];
        }
        if ($definition['type'] === 'string-list') {
            return [
                $definition['type'],
                json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ];
        }
        return [$definition['type'], (string) $value];
    }

    public function decode(string $property, string $type, string $payload)
    {
        $definition = $this->definition($property);
        if ($type !== $definition['type']) {
            throw new InvalidArgumentException('Invalid type for context setting: ' . $property);
        }
        if ($type === 'boolean') {
            if (!in_array($payload, ['true', 'false'], true)) {
                throw new InvalidArgumentException('Invalid value for context setting: ' . $property);
            }
            $value = $payload === 'true';
        } elseif ($type === 'integer') {
            if (filter_var($payload, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('Invalid value for context setting: ' . $property);
            }
            $value = (int) $payload;
        } elseif ($type === 'string-list') {
            try {
                $value = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('Invalid JSON context setting: ' . $property, 0, $exception);
            }
        } else {
            $value = $payload;
        }
        $this->validateValue($property, $value, $type);
        return $value;
    }

    public function validateJournal(Journal $journal): void
    {
        if ($journal->getData('enableDois') === true && empty($journal->getData('doiPrefix'))) {
            throw new InvalidArgumentException('The DOI prefix is required when DOIs are enabled');
        }
        if ($journal->getData('doiSuffixType') !== 'customPattern') {
            return;
        }
        $patterns = [
            'publication' => 'doiPublicationSuffixPattern',
            'representation' => 'doiRepresentationSuffixPattern',
            'issue' => 'doiIssueSuffixPattern',
        ];
        foreach ($journal->getData('enabledDoiTypes') ?? [] as $doiType) {
            $property = $patterns[$doiType];
            $pattern = $journal->getData($property);
            if (!is_string($pattern) || $pattern === '') {
                throw new InvalidArgumentException('The DOI suffix pattern is required: ' . $property);
            }
            if (preg_match('/%(?![jxviYapgf])/', $pattern) === 1) {
                throw new InvalidArgumentException('Invalid value for context setting: ' . $property);
            }
        }
    }

    private function validateValue(string $property, $value, string $type): void
    {
        if ($property === 'publishingMode' && $value === Journal::PUBLISHING_MODE_SUBSCRIPTION) {
            throw new InvalidArgumentException(
                'Subscription publishing mode is not supported because subscriptions are not transferred.'
            );
        }
        $valid = ($type === 'string' && is_string($value))
            || ($type === 'integer' && is_int($value))
            || ($type === 'boolean' && is_bool($value));
        if ($type === 'string-list') {
            $valid = is_array($value)
                && ($value === [] || array_keys($value) === range(0, count($value) - 1))
                && count($value) === count(array_unique($value, SORT_REGULAR));
            if (is_array($value)) {
                foreach ($value as $item) {
                    $valid = $valid && is_string($item);
                }
            }
        }
        if (!$valid || !$this->passesPropertyValidation($property, $value)) {
            throw new InvalidArgumentException('Invalid value for context setting: ' . $property);
        }
    }

    private function passesPropertyValidation(string $property, $value): bool
    {
        if ($value === '' && in_array($property, [
            'contactEmail',
            'supportEmail',
            'publisherUrl',
            'licenseUrl',
            'onlineIssn',
            'printIssn',
            'doiPrefix',
        ], true)) {
            return true;
        }
        if (isset(self::ENUM_SETTINGS[$property])) {
            return in_array($value, self::ENUM_SETTINGS[$property], true);
        }
        if (isset(self::INTEGER_ENUM_SETTINGS[$property])) {
            return in_array($value, self::INTEGER_ENUM_SETTINGS[$property], true);
        }
        if (isset(self::STRING_LIST_SETTINGS[$property])) {
            return array_diff($value, self::STRING_LIST_SETTINGS[$property]) === [];
        }
        if (in_array($property, ['itemsPerPage', 'numPageLinks'], true)) {
            return $value >= 1;
        }
        if (in_array($property, [
            'numWeeksPerResponse',
            'numWeeksPerReview',
            'numDaysBeforeInviteReminder',
            'numDaysBeforeSubmitReminder',
        ], true)) {
            return $value >= 0;
        }
        if ($property === 'doiPrefix') {
            return preg_match('/^10\.[0-9]{4,7}$/', $value) === 1;
        }
        if (in_array($property, ['contactEmail', 'supportEmail'], true)) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                || preg_match('/^[^@\s]+@localhost$/', $value) === 1;
        }
        if (in_array($property, ['publisherUrl', 'licenseUrl'], true)) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        }
        if (in_array($property, ['onlineIssn', 'printIssn'], true)) {
            return preg_match('/^[0-9]{4}-[0-9]{3}[0-9X]$/', $value) === 1;
        }
        if ($property === 'country') {
            return preg_match('/^[A-Z]{2}$/', $value) === 1;
        }
        return true;
    }
}
