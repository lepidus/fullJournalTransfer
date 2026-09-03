<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\plugins\importexport\fullJournalTransfer\PackageIntegrity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PackageIntegrityTest extends TestCase
{
    public function testItCountsEverySupportedEntityInTheJournalXml(): void
    {
        $xml = '<journal>'
            . '<users><user_groups><user_group/></user_groups><users><user/></users></users>'
            . '<review_forms><review_form/></review_forms>'
            . '<review_form_elements><review_form_element/></review_form_elements>'
            . '<extended_issue/><extended_article><publication/></extended_article>'
            . '<submission_file/><workflow_file/><stage_assignment/><review_round/><review_assignment/>'
            . '<review_response/><review_comment/><review_round_file/><review_file/>'
            . '<discussion/><discussion_participant/><discussion_note/><discussion_attachment/>'
            . '<editorial_decision/><context_metric/><issue_metric/><submission_metric/>'
            . '<geo_metric granularity="daily"/><geo_metric granularity="monthly"/>'
            . '<counter_metric granularity="daily"/><counter_metric granularity="monthly"/>'
            . '<institution_metric granularity="daily"/><institution_metric granularity="monthly"/>'
            . '</journal>';

        $this->assertSame([
            'users' => 1,
            'user_groups' => 1,
            'review_forms' => 1,
            'review_form_elements' => 1,
            'issues' => 1,
            'submissions' => 1,
            'publications' => 1,
            'submission_files' => 2,
            'stage_assignments' => 1,
            'review_rounds' => 1,
            'review_assignments' => 1,
            'review_form_responses' => 1,
            'review_comments' => 1,
            'review_round_files' => 1,
            'review_files' => 1,
            'discussions' => 1,
            'discussion_participants' => 1,
            'discussion_notes' => 1,
            'discussion_attachments' => 1,
            'editorial_decisions' => 1,
            'metrics_context' => 1,
            'metrics_issue' => 1,
            'metrics_submission' => 1,
            'metrics_geo_daily' => 1,
            'metrics_geo_monthly' => 1,
            'metrics_counter_daily' => 1,
            'metrics_counter_monthly' => 1,
            'metrics_institution_daily' => 1,
            'metrics_institution_monthly' => 1,
        ], PackageIntegrity::countXml($xml));
    }

    public function testItReportsTheEntityAndSourceWhenCountsDiffer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The integrity count for review_rounds in imported journal is invalid: expected 2, found 1'
        );

        PackageIntegrity::assertCounts(['review_rounds' => 2], ['review_rounds' => 1], 'imported journal');
    }
}
