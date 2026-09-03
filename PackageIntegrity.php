<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

class PackageIntegrity
{
    private const ENTITY_XPATHS = [
        'users' => '//*[local-name()="users"]/*[local-name()="users"]/*[local-name()="user"]',
        'user_groups' => '//*[local-name()="users"]/*[local-name()="user_groups"]/*[local-name()="user_group"]',
        'review_forms' => '//*[local-name()="review_forms"]/*[local-name()="review_form"]',
        'review_form_elements' => '//*[local-name()="review_form_elements"]/*[local-name()="review_form_element"]',
        'issues' => '//*[local-name()="extended_issue"]',
        'submissions' => '//*[local-name()="extended_article"]',
        'publications' => '//*[local-name()="extended_article"]/*[local-name()="publication"]',
        'submission_files' => '//*[local-name()="submission_file"] | //*[local-name()="workflow_file"]',
        'stage_assignments' => '//*[local-name()="stage_assignment"]',
        'review_rounds' => '//*[local-name()="review_round"]',
        'review_assignments' => '//*[local-name()="review_assignment"]',
        'review_form_responses' => '//*[local-name()="review_response"]',
        'review_comments' => '//*[local-name()="review_comment"]',
        'review_round_files' => '//*[local-name()="review_round_file"]',
        'review_files' => '//*[local-name()="review_file"]',
        'discussions' => '//*[local-name()="discussion"]',
        'discussion_participants' => '//*[local-name()="discussion_participant"]',
        'discussion_notes' => '//*[local-name()="discussion_note"]',
        'discussion_attachments' => '//*[local-name()="discussion_attachment"]',
        'editorial_decisions' => '//*[local-name()="editorial_decision"]',
        'metrics_context' => '//*[local-name()="context_metric"]',
        'metrics_issue' => '//*[local-name()="issue_metric"]',
        'metrics_submission' => '//*[local-name()="submission_metric"]',
        'metrics_geo_daily' => '//*[local-name()="geo_metric" and @granularity="daily"]',
        'metrics_geo_monthly' => '//*[local-name()="geo_metric" and @granularity="monthly"]',
        'metrics_counter_daily' => '//*[local-name()="counter_metric" and @granularity="daily"]',
        'metrics_counter_monthly' => '//*[local-name()="counter_metric" and @granularity="monthly"]',
        'metrics_institution_daily' => '//*[local-name()="institution_metric" and @granularity="daily"]',
        'metrics_institution_monthly' => '//*[local-name()="institution_metric" and @granularity="monthly"]',
    ];

    public static function isSupported(string $name): bool
    {
        return isset(self::ENTITY_XPATHS[$name]);
    }

    /** @return array<string, int> */
    public static function countDocument(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $counts = [];
        foreach (self::ENTITY_XPATHS as $name => $expression) {
            $count = $xpath->evaluate('count(' . $expression . ')');
            if (!is_float($count)) {
                throw new InvalidArgumentException('The journal XML integrity could not be calculated');
            }
            $counts[$name] = (int) $count;
        }
        return $counts;
    }

    /** @return array<string, int> */
    public static function countXml(string $xml): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new InvalidArgumentException('The journal XML integrity could not be calculated');
        }
        return self::countDocument($document);
    }

    /**
     * @param array<string, int> $expected
     * @param array<string, int> $actual
     */
    public static function assertCounts(array $expected, array $actual, string $source): void
    {
        foreach ($expected as $name => $count) {
            if (($actual[$name] ?? null) !== $count) {
                throw new InvalidArgumentException(sprintf(
                    'The integrity count for %s in %s is invalid: expected %d, found %s',
                    $name,
                    $source,
                    $count,
                    isset($actual[$name]) ? (string) $actual[$name] : 'missing'
                ));
            }
        }
    }
}
