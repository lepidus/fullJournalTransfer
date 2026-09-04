<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\integration\workflow;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\decision\Decision;
use PKP\install\Installer;
use PKP\observers\events\DecisionAdded;
use PKP\plugins\importexport\PKPImportExportFilter;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\DatabaseTestCase;
use PKP\userGroup\UserGroup;

class WorkflowFilterIntegrationTest extends DatabaseTestCase
{
    private array $contexts = [];
    private array $fileIds = [];
    private array $userGroups = [];
    private array $users = [];

    protected function getAffectedTables()
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $installer = (new \ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
        if (!$installer->installFilterConfig(dirname(__DIR__, 3) . '/filter/filterConfig.xml')) {
            throw new \RuntimeException('Workflow filter configuration could not be installed');
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->contexts) as $context) {
            Repo::submission()->deleteByContextId((int) $context->getId());
            Repo::section()->deleteByContextId((int) $context->getId());
            DAORegistry::getDAO('ReviewFormDAO')->deleteByAssoc(Application::ASSOC_TYPE_JOURNAL, $context->getId());
            Application::get()->getContextDAO()->deleteObject($context);
        }
        foreach (array_reverse($this->userGroups) as $userGroup) {
            Repo::userGroup()->delete($userGroup);
        }
        foreach (array_reverse($this->users) as $user) {
            Repo::user()->delete($user);
        }
        foreach (array_unique($this->fileIds) as $fileId) {
            if (Services::get('file')->get($fileId)) {
                Services::get('file')->delete($fileId);
            }
        }
        parent::tearDown();
    }

    public function testItRestoresStageAssignmentsAndOrderedReviewRoundsWithHistoricalDates(): void
    {
        Event::fake();
        Queue::fake();
        $source = $this->createContext('workflow-source');
        $destination = $this->createContext('workflow-destination');
        $sourceSection = $this->createSection($source);
        $destinationSection = $this->createSection($destination);
        $sourceGroup = $this->createUserGroup($source, 'Workflow Editors');
        $destinationGroup = $this->createUserGroup($destination, 'Workflow Editors');
        $user = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($user);
        Repo::userGroup()->assignUserToGroup((int) $user->getId(), (int) $sourceGroup->getId());
        Repo::userGroup()->assignUserToGroup((int) $user->getId(), (int) $destinationGroup->getId());
        $this->setRequestContext($source);
        $submission = $this->createSubmission($source, $sourceSection, 'Workflow article');
        $assignedAt = '2024-02-03 04:05:06';
        DB::table('stage_assignments')->insert([
            'submission_id' => $submission->getId(),
            'user_group_id' => $sourceGroup->getId(),
            'user_id' => $user->getId(),
            'date_assigned' => $assignedAt,
            'recommend_only' => 1,
            'can_change_metadata' => 1,
        ]);
        DB::table('review_rounds')->insert([
            [
                'submission_id' => $submission->getId(),
                'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
                'round' => 2,
                'status' => 4,
            ],
            [
                'submission_id' => $submission->getId(),
                'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
                'round' => 1,
                'status' => 2,
            ],
        ]);

        $sourceDeployment = new FullJournalImportExportDeployment($source, null);
        $nativeData = $sourceDeployment->exportNativeData();
        $workflow = $sourceDeployment->exportWorkflow();
        $xpath = new \DOMXPath($workflow);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $this->assertSame($assignedAt, $xpath->evaluate('string(//pkp:stage_assignment/@date_assigned)'));
        $this->assertSame(
            ['1', '2'],
            array_map(
                fn (\DOMElement $node): string => $node->getAttribute('round'),
                iterator_to_array($xpath->query('//pkp:review_round'))
            )
        );

        $destinationDeployment = new FullJournalImportExportDeployment($destination, null);
        $this->setRequestContext($destination);
        $destinationDeployment->mapReference('section', (string) $sourceSection->getId(), $destinationSection->getId());
        $maps = $destinationDeployment->importNativeData($nativeData->documentElement);
        $destinationDeployment->mapReference('user', (string) $user->getId(), $user->getId());
        $destinationDeployment->mapReference(
            'user_group',
            (string) $sourceGroup->getId(),
            $destinationGroup->getId()
        );
        $destinationDeployment->importWorkflow($workflow->documentElement);
        $importedSubmissionId = $maps['submission_id_map'][(string) $submission->getId()];

        $assignment = DB::table('stage_assignments')->where('submission_id', $importedSubmissionId)->first();
        $this->assertSame($assignedAt, $assignment->date_assigned);
        $this->assertSame(1, (int) $assignment->recommend_only);
        $this->assertSame(1, (int) $assignment->can_change_metadata);
        $this->assertSame(
            [1, 2],
            DB::table('review_rounds')
                ->where('submission_id', $importedSubmissionId)
                ->orderBy('round')
                ->pluck('round')
                ->map(fn ($round): int => (int) $round)
                ->all()
        );
    }

    public function testItExportsADisabledWorkflowUserWithoutUserGroupReferences(): void
    {
        $source = $this->createContext('historical-user-source');
        $sourceSection = $this->createSection($source);
        $sourceGroup = $this->createUserGroup($source, 'Historical Editors');
        $suffix = bin2hex(random_bytes(6));
        $user = Repo::user()->newDataObject();
        $user->setUsername('disabled-workflow-' . $suffix);
        $user->setEmail('disabled-workflow-' . $suffix . '@example.com');
        $user->setPassword(password_hash('disabled-password', PASSWORD_BCRYPT));
        $user->setGivenName('Disabled', 'en');
        $user->setFamilyName('Workflow', 'en');
        $user->setDateRegistered('2026-08-10 00:00:00');
        $user->setMustChangePassword(false);
        $user->setDisabled(true);
        $user->setInlineHelp(false);
        Repo::user()->add($user);
        $this->users[] = $user;
        Repo::userGroup()->assignUserToGroup((int) $user->getId(), (int) $sourceGroup->getId());
        $this->assertTrue(Repo::userGroup()->userInGroup((int) $user->getId(), (int) $sourceGroup->getId()));
        $this->setRequestContext($source);
        $submission = $this->createSubmission($source, $sourceSection, 'Historical assignment');
        DB::table('stage_assignments')->insert([
            'submission_id' => $submission->getId(),
            'user_group_id' => $sourceGroup->getId(),
            'user_id' => $user->getId(),
            'date_assigned' => '2024-02-03 04:05:06',
            'recommend_only' => 0,
            'can_change_metadata' => 0,
        ]);

        $deployment = new FullJournalImportExportDeployment($source, null);
        $users = $deployment->exportUsers();
        $workflow = $deployment->exportWorkflow();
        $usersXpath = new \DOMXPath($users);
        $usersXpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $workflowXpath = new \DOMXPath($workflow);
        $workflowXpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $userReference = (string) $user->getId();

        $this->assertSame(
            $userReference,
            $usersXpath->evaluate('string(//pkp:user[@source_ref="' . $userReference . '"]/@source_ref)')
        );
        $this->assertSame(
            'true',
            $usersXpath->evaluate('string(//pkp:user[@source_ref="' . $userReference . '"]/pkp:password/@is_disabled)')
        );
        $this->assertSame(
            0,
            $usersXpath->query('//pkp:user[@source_ref="' . $userReference . '"]/pkp:user_group_ref')->length
        );
        $this->assertSame(
            $userReference,
            $workflowXpath->evaluate('string(//pkp:stage_assignment/@user_ref)')
        );
        $this->assertTrue(Repo::userGroup()->userInGroup((int) $user->getId(), (int) $sourceGroup->getId()));
    }

    public function testItRestoresReviewAssignmentsResponsesAndCommentsWithoutSideEffects(): void
    {
        Event::fake();
        Queue::fake();
        $source = $this->createContext('review-source');
        $destination = $this->createContext('review-destination');
        $sourceSection = $this->createSection($source);
        $destinationSection = $this->createSection($destination);
        $reviewer = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($reviewer);
        $this->setRequestContext($source);
        $submission = $this->createSubmission($source, $sourceSection, 'Reviewed article');
        $sourceGenre = $this->createGenre($source, 'Review File');
        $destinationGenre = $this->createGenre($destination, 'Review File');
        $submissionFile = $this->createSubmissionFile($source, $submission, (int) $sourceGenre->getId());
        [$sourceFormId, $sourceElementId] = $this->createReviewForm($source);
        [$destinationFormId, $destinationElementId] = $this->createReviewForm($destination);
        $sourceEmptyElementId = $this->createReviewFormElement($sourceFormId, 2, 'Empty response');
        $sourceNullElementId = $this->createReviewFormElement($sourceFormId, 3, 'Null response');
        $destinationEmptyElementId = $this->createReviewFormElement($destinationFormId, 2, 'Empty response');
        $destinationNullElementId = $this->createReviewFormElement($destinationFormId, 3, 'Null response');
        $roundId = DB::table('review_rounds')->insertGetId([
            'submission_id' => $submission->getId(),
            'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'round' => 1,
            'status' => 7,
        ], 'review_round_id');
        $dates = [
            'date_assigned' => '2024-01-01 10:00:00',
            'date_notified' => '2024-01-02 10:00:00',
            'date_confirmed' => '2024-01-03 10:00:00',
            'date_completed' => '2024-01-04 10:00:00',
            'date_acknowledged' => '2024-01-05 10:00:00',
            'date_due' => '2024-01-06 10:00:00',
            'date_response_due' => '2024-01-03 10:00:00',
            'last_modified' => '2024-01-04 11:00:00',
            'date_rated' => '2024-01-07 10:00:00',
            'date_reminded' => '2024-01-03 12:00:00',
        ];
        $reviewId = DB::table('review_assignments')->insertGetId(array_merge($dates, [
            'submission_id' => $submission->getId(),
            'reviewer_id' => $reviewer->getId(),
            'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'review_method' => 2,
            'round' => 1,
            'step' => 3,
            'competing_interests' => 'None',
            'recommendation' => 1,
            'declined' => 0,
            'cancelled' => 0,
            'quality' => 5,
            'reminder_was_automatic' => 1,
            'review_form_id' => $sourceFormId,
            'review_round_id' => $roundId,
            'considered' => 1,
            'request_resent' => 0,
        ]), 'review_id');
        DB::table('review_form_responses')->insert([
            [
                'review_form_element_id' => $sourceElementId,
                'review_id' => $reviewId,
                'response_type' => 'string',
                'response_value' => 'Detailed response',
            ],
            [
                'review_form_element_id' => $sourceEmptyElementId,
                'review_id' => $reviewId,
                'response_type' => 'string',
                'response_value' => '',
            ],
            [
                'review_form_element_id' => $sourceNullElementId,
                'review_id' => $reviewId,
                'response_type' => 'string',
                'response_value' => null,
            ],
        ]);
        DB::table('review_round_files')->insert([
            'submission_id' => $submission->getId(),
            'review_round_id' => $roundId,
            'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'submission_file_id' => $submissionFile->getId(),
        ]);
        DB::table('review_files')->insert([
            'review_id' => $reviewId,
            'submission_file_id' => $submissionFile->getId(),
        ]);
        DB::table('submission_comments')->insert([
            [
                'comment_type' => 1,
                'role_id' => Role::ROLE_ID_REVIEWER,
                'submission_id' => $submission->getId(),
                'assoc_id' => $reviewId,
                'author_id' => $reviewer->getId(),
                'comment_title' => 'Public review',
                'comments' => 'Visible to the author',
                'date_posted' => '2024-01-04 10:30:00',
                'date_modified' => '2024-01-04 10:31:00',
                'viewable' => 1,
            ],
            [
                'comment_type' => 1,
                'role_id' => Role::ROLE_ID_REVIEWER,
                'submission_id' => $submission->getId(),
                'assoc_id' => $reviewId,
                'author_id' => $reviewer->getId(),
                'comment_title' => 'Private review',
                'comments' => 'Visible only to editors',
                'date_posted' => '2024-01-04 10:32:00',
                'date_modified' => null,
                'viewable' => 0,
            ],
        ]);

        $sourceDeployment = new FullJournalImportExportDeployment($source, null);
        $nativeData = $sourceDeployment->exportNativeData();
        $workflow = $sourceDeployment->exportWorkflow();
        $xpath = new \DOMXPath($workflow);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $this->assertSame(1, $xpath->query('//pkp:review_assignment')->length);
        $this->assertSame(3, $xpath->query('//pkp:review_response')->length);
        $this->assertSame('false', $xpath->evaluate(
            'string(//pkp:review_response[@element_ref="' . $sourceElementId . '"]/@is_null)'
        ));
        $this->assertSame('false', $xpath->evaluate(
            'string(//pkp:review_response[@element_ref="' . $sourceEmptyElementId . '"]/@is_null)'
        ));
        $this->assertSame('true', $xpath->evaluate(
            'string(//pkp:review_response[@element_ref="' . $sourceNullElementId . '"]/@is_null)'
        ));
        $this->assertSame(2, $xpath->query('//pkp:review_comment')->length);
        $this->assertSame(1, $xpath->query('//pkp:review_round_file')->length);
        $this->assertSame(1, $xpath->query('//pkp:review_file')->length);

        $destinationDeployment = new FullJournalImportExportDeployment($destination, $reviewer);
        $this->setRequestContext($destination);
        $destinationDeployment->setImportPath((string) Config::getVar('files', 'files_dir'));
        $destinationDeployment->mapReference('section', (string) $sourceSection->getId(), $destinationSection->getId());
        $destinationDeployment->mapReference('genre', (string) $sourceGenre->getId(), $destinationGenre->getId());
        $maps = $destinationDeployment->importNativeData($nativeData->documentElement);
        $this->fileIds = array_merge($this->fileIds, array_values($maps['file_id_map']));
        $destinationDeployment->mapReference('user', (string) $reviewer->getId(), $reviewer->getId());
        $destinationDeployment->mapReference('review_form', (string) $sourceFormId, $destinationFormId);
        $destinationDeployment->mapReference(
            'review_form_element',
            (string) $sourceElementId,
            $destinationElementId
        );
        $destinationDeployment->mapReference(
            'review_form_element',
            (string) $sourceEmptyElementId,
            $destinationEmptyElementId
        );
        $destinationDeployment->mapReference(
            'review_form_element',
            (string) $sourceNullElementId,
            $destinationNullElementId
        );
        $notifications = DB::table('notifications')->count();
        $emailLogs = DB::table('email_log')->count();
        $eventLogs = DB::table('event_log')->count();
        $workflowMaps = $destinationDeployment->importWorkflow($workflow->documentElement);
        $importedReviewId = $workflowMaps['review_assignment_id_map'][(string) $reviewId];
        $importedSubmissionId = $maps['submission_id_map'][(string) $submission->getId()];

        $imported = DB::table('review_assignments')->where('review_id', $importedReviewId)->first();
        $this->assertSame($dates['date_assigned'], $imported->date_assigned);
        $this->assertSame($destinationFormId, (int) $imported->review_form_id);
        $this->assertSame($importedSubmissionId, (int) $imported->submission_id);
        $this->assertSame(
            'Detailed response',
            DB::table('review_form_responses')->where('review_id', $importedReviewId)->value('response_value')
        );
        $this->assertSame(
            '',
            DB::table('review_form_responses')
                ->where('review_id', $importedReviewId)
                ->where('review_form_element_id', $destinationEmptyElementId)
                ->value('response_value')
        );
        $this->assertNull(
            DB::table('review_form_responses')
                ->where('review_id', $importedReviewId)
                ->where('review_form_element_id', $destinationNullElementId)
                ->value('response_value')
        );
        $this->assertSame(
            [0, 1],
            DB::table('submission_comments')
                ->where('assoc_id', $importedReviewId)
                ->orderBy('viewable')
                ->pluck('viewable')
                ->map(fn ($viewable): int => (int) $viewable)
                ->all()
        );
        $this->assertSame(
            '2024-01-04 10:32:00',
            DB::table('submission_comments')
                ->where('assoc_id', $importedReviewId)
                ->where('comment_title', 'Private review')
                ->value('date_modified')
        );
        $this->assertSame($notifications, DB::table('notifications')->count());
        $this->assertSame($emailLogs, DB::table('email_log')->count());
        $this->assertSame($eventLogs, DB::table('event_log')->count());
        $importedSubmissionFileId = $maps['submission_file_id_map'][(string) $submissionFile->getId()];
        $this->assertTrue(DB::table('review_round_files')
            ->where('review_round_id', $workflowMaps['review_round_id_map'][(string) $roundId])
            ->where('submission_file_id', $importedSubmissionFileId)
            ->exists());
        $this->assertTrue(DB::table('review_files')
            ->where('review_id', $importedReviewId)
            ->where('submission_file_id', $importedSubmissionFileId)
            ->exists());
    }

    public function testItRejectsInvalidReviewResponseNullMarkers(): void
    {
        foreach ($this->invalidReviewResponseNullMarkers() as [$nullAttribute, $content, $expectedMessage]) {
            $this->assertInvalidReviewResponseNullMarker($nullAttribute, $content, $expectedMessage);
        }
    }

    private function assertInvalidReviewResponseNullMarker(
        string $nullAttribute,
        string $content,
        string $expectedMessage
    ): void {
        $destination = $this->createContext('response-marker');
        $section = $this->createSection($destination);
        $submission = $this->createSubmission($destination, $section, 'Review response marker');
        [$formId, $elementId] = $this->createReviewForm($destination);
        $reviewer = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($reviewer);
        $roundId = DB::table('review_rounds')->insertGetId([
            'submission_id' => $submission->getId(),
            'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'round' => 1,
            'status' => 1,
        ], 'review_round_id');
        $reviewId = DB::table('review_assignments')->insertGetId([
            'submission_id' => $submission->getId(),
            'reviewer_id' => $reviewer->getId(),
            'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'review_method' => 2,
            'round' => 1,
            'step' => 1,
            'declined' => 0,
            'cancelled' => 0,
            'reminder_was_automatic' => 0,
            'review_form_id' => $formId,
            'review_round_id' => $roundId,
            'considered' => 0,
            'request_resent' => 0,
        ], 'review_id');
        $deployment = new FullJournalImportExportDeployment($destination, null);
        $deployment->mapReference('review_assignment', 'review-1', (int) $reviewId);
        $deployment->mapReference('review_form_element', 'element-1', $elementId);
        $filter = PKPImportExportFilter::getFilter('full-journal-workflow-xml=>review-response', $deployment);
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML(
            '<review_responses xmlns="http://pkp.sfu.ca"><review_response review_ref="review-1" '
            . 'element_ref="element-1" type="string"' . $nullAttribute . '>' . $content
            . '</review_response></review_responses>'
        ));

        try {
            $filter->execute($document);
            $this->fail('Invalid review response null marker was accepted');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame($expectedMessage, $exception->getMessage());
        }
    }

    public function invalidReviewResponseNullMarkers(): array
    {
        return [
            'missing marker' => [
                '',
                '',
                'Missing review response attribute "is_null" for review_ref "review-1" and element_ref "element-1" '
                    . 'at line 1',
            ],
            'invalid marker' => [
                ' is_null="invalid"',
                '',
                'Invalid review response is_null "invalid" for review_ref "review-1" and element_ref "element-1" '
                    . 'at line 1',
            ],
            'null response with text' => [
                ' is_null="true"',
                'Unexpected response',
                'Null review response must not contain text',
            ],
        ];
    }

    public function testItRestoresReviewRevisionWithoutChangingHistoricalActivity(): void
    {
        Event::fake();
        Queue::fake();
        $source = $this->createContext('revision-source');
        $destination = $this->createContext('revision-destination');
        $sourceSection = $this->createSection($source);
        $destinationSection = $this->createSection($destination);
        $sourceGenre = $this->createGenre($source, 'Review Revision');
        $destinationGenre = $this->createGenre($destination, 'Review Revision');
        $this->setRequestContext($source);
        $submission = $this->createSubmission($source, $sourceSection, 'Review revision article');
        $roundId = DB::table('review_rounds')->insertGetId([
            'submission_id' => $submission->getId(),
            'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'round' => 1,
            'status' => 1,
        ], 'review_round_id');
        $revision = $this->createSubmissionFile(
            $source,
            $submission,
            (int) $sourceGenre->getId(),
            SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            Application::ASSOC_TYPE_REVIEW_ROUND,
            (int) $roundId
        );
        $historicalDateLastActivity = '2020-03-04 05:06:07';
        DB::table('submissions')
            ->where('submission_id', $submission->getId())
            ->update(['date_last_activity' => $historicalDateLastActivity]);

        $sourceDeployment = new FullJournalImportExportDeployment($source, null);
        $nativeData = $sourceDeployment->exportNativeData();
        $workflow = $sourceDeployment->exportWorkflow();
        $nativeXpath = new \DOMXPath($nativeData);
        $nativeXpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $workflowXpath = new \DOMXPath($workflow);
        $workflowXpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $this->assertSame(0, $nativeXpath->query('//pkp:submission_file[@stage="review_revision"]')->length);
        $this->assertSame(1, $workflowXpath->query('//pkp:workflow_file/pkp:submission_file')->length);
        $this->assertSame(
            (string) $roundId,
            $workflowXpath->evaluate('string(//pkp:workflow_file/@review_round_ref)')
        );

        $this->setRequestContext($destination);
        $importUser = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($importUser);
        $destinationDeployment = new FullJournalImportExportDeployment($destination, $importUser);
        $destinationDeployment->setImportPath((string) Config::getVar('files', 'files_dir'));
        $destinationDeployment->mapReference(
            'section',
            (string) $sourceSection->getId(),
            (int) $destinationSection->getId()
        );
        $destinationDeployment->mapReference(
            'genre',
            (string) $sourceGenre->getId(),
            (int) $destinationGenre->getId()
        );
        $nativeMaps = $destinationDeployment->importNativeData($nativeData->documentElement);
        $importedSubmissionId = $nativeMaps['submission_id_map'][(string) $submission->getId()];
        $this->assertSame(
            $historicalDateLastActivity,
            DB::table('submissions')->where('submission_id', $importedSubmissionId)->value('date_last_activity')
        );
        $workflowMaps = $destinationDeployment->importWorkflow($workflow->documentElement);
        $this->fileIds = array_merge(
            $this->fileIds,
            array_values($destinationDeployment->getReferenceMap('file'))
        );
        $this->assertSame(
            $historicalDateLastActivity,
            DB::table('submissions')->where('submission_id', $importedSubmissionId)->value('date_last_activity')
        );

        $importedRoundId = $workflowMaps['review_round_id_map'][(string) $roundId];
        $importedRevisionId = $destinationDeployment->getReferenceMap('submission_file')[(string) $revision->getId()];
        $importedRevision = Repo::submissionFile()->get($importedRevisionId);
        $this->assertNotNull($importedRevision);
        $this->assertSame(
            $importedSubmissionId,
            (int) $importedRevision->getData('submissionId')
        );
        $this->assertSame(SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION, $importedRevision->getFileStage());
        $this->assertSame(Application::ASSOC_TYPE_REVIEW_ROUND, (int) $importedRevision->getData('assocType'));
        $this->assertSame($importedRoundId, (int) $importedRevision->getData('assocId'));
        $this->assertTrue(DB::table('review_round_files')
            ->where('review_round_id', $importedRoundId)
            ->where('submission_file_id', $importedRevisionId)
            ->exists());
    }

    public function testItRestoresHistoricalDecisionsWithoutRepeatingEditorialEffects(): void
    {
        Event::fake();
        Queue::fake();
        $source = $this->createContext('decision-source');
        $destination = $this->createContext('decision-destination');
        $sourceSection = $this->createSection($source);
        $destinationSection = $this->createSection($destination);
        $editor = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($editor);
        $this->setRequestContext($source);
        $submission = $this->createSubmission($source, $sourceSection, 'Decision history article');
        $reviewRoundId = DB::table('review_rounds')->insertGetId([
            'submission_id' => $submission->getId(),
            'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'round' => 1,
            'status' => 4,
        ], 'review_round_id');
        $decidedAt = '2021-06-07 08:09:10';
        DB::table('edit_decisions')->insert([
            'submission_id' => $submission->getId(),
            'editor_id' => $editor->getId(),
            'decision' => Decision::PENDING_REVISIONS,
            'date_decided' => $decidedAt,
            'review_round_id' => $reviewRoundId,
            'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'round' => 1,
        ]);
        DB::table('edit_decisions')->insert([
            'submission_id' => $submission->getId(),
            'editor_id' => $editor->getId(),
            'decision' => Decision::INITIAL_DECLINE,
            'date_decided' => '2021-06-08 09:10:11',
            'review_round_id' => null,
            'stage_id' => WORKFLOW_STAGE_ID_SUBMISSION,
            'round' => null,
        ]);

        $sourceDeployment = new FullJournalImportExportDeployment($source, null);
        $nativeData = $sourceDeployment->exportNativeData();
        $workflow = $sourceDeployment->exportWorkflow();
        $xpath = new \DOMXPath($workflow);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $this->assertSame(2, $xpath->query('//pkp:editorial_decision')->length);
        $this->assertSame($decidedAt, $xpath->evaluate('string(//pkp:editorial_decision/@date_decided)'));
        $this->assertSame(1, $xpath->query('//pkp:editorial_decision[not(@review_round_ref) and not(@round)]')->length);

        $destinationDeployment = new FullJournalImportExportDeployment($destination, $editor);
        $this->setRequestContext($destination);
        $destinationDeployment->mapReference('section', (string) $sourceSection->getId(), $destinationSection->getId());
        $maps = $destinationDeployment->importNativeData($nativeData->documentElement);
        $destinationDeployment->mapReference('user', (string) $editor->getId(), (int) $editor->getId());
        $notifications = DB::table('notifications')->count();
        $emailLogs = DB::table('email_log')->count();
        $eventLogs = DB::table('event_log')->count();
        $importedSubmissionId = $maps['submission_id_map'][(string) $submission->getId()];
        $submissionBefore = Repo::submission()->get($importedSubmissionId);
        $this->assertNotNull($submissionBefore);
        $destinationDeployment->importWorkflow($workflow->documentElement);

        $decisions = DB::table('edit_decisions')->where('submission_id', $importedSubmissionId)
            ->orderBy('date_decided')->get();
        $this->assertCount(2, $decisions);
        $decision = $decisions[0];
        $this->assertNotNull($decision);
        $this->assertSame($decidedAt, $decision->date_decided);
        $this->assertSame((int) $editor->getId(), (int) $decision->editor_id);
        $this->assertSame(Decision::PENDING_REVISIONS, (int) $decision->decision);
        $this->assertSame(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, (int) $decision->stage_id);
        $this->assertSame(1, (int) $decision->round);
        $this->assertSame(Decision::INITIAL_DECLINE, (int) $decisions[1]->decision);
        $this->assertNull($decisions[1]->review_round_id);
        $this->assertNull($decisions[1]->round);
        $this->assertSame($notifications, DB::table('notifications')->count());
        $this->assertSame($emailLogs, DB::table('email_log')->count());
        $this->assertSame($eventLogs, DB::table('event_log')->count());
        $submissionAfter = Repo::submission()->get($importedSubmissionId);
        $this->assertNotNull($submissionAfter);
        $this->assertSame($submissionBefore->getData('stageId'), $submissionAfter->getData('stageId'));
        $this->assertSame($submissionBefore->getData('status'), $submissionAfter->getData('status'));
        Event::assertNotDispatched(DecisionAdded::class);
    }

    public function testItRestoresDiscussionsParticipantsNotesAndAttachmentsWithoutSideEffects(): void
    {
        Event::fake();
        Queue::fake();
        $source = $this->createContext('discussion-source');
        $destination = $this->createContext('discussion-destination');
        $sourceSection = $this->createSection($source);
        $destinationSection = $this->createSection($destination);
        $sourceGenre = $this->createGenre($source, 'Editorial Discussion');
        $destinationGenre = $this->createGenre($destination, 'Editorial Discussion');
        $participant = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($participant);
        $this->setRequestContext($source);
        $submission = $this->createSubmission($source, $sourceSection, 'Discussed article');
        $queryId = DB::table('queries')->insertGetId([
            'assoc_type' => Application::ASSOC_TYPE_SUBMISSION,
            'assoc_id' => $submission->getId(),
            'stage_id' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'closed' => 1,
            'seq' => 2.5,
        ], 'query_id');
        DB::table('query_participants')->insert([
            'query_id' => $queryId,
            'user_id' => $participant->getId(),
        ]);
        $firstNoteId = DB::table('notes')->insertGetId([
            'assoc_type' => Application::ASSOC_TYPE_QUERY,
            'assoc_id' => $queryId,
            'user_id' => $participant->getId(),
            'date_created' => '2022-01-02 03:04:05',
            'date_modified' => '2022-01-03 04:05:06',
            'title' => 'Editorial question',
            'contents' => 'Please clarify the analysis.',
        ], 'note_id');
        DB::table('notes')->insert([
            'assoc_type' => Application::ASSOC_TYPE_QUERY,
            'assoc_id' => $queryId,
            'user_id' => $participant->getId(),
            'date_created' => '2022-01-04 05:06:07',
            'date_modified' => '2022-01-05 06:07:08',
            'title' => 'Editorial response',
            'contents' => 'The analysis was clarified.',
        ]);
        $attachment = $this->createSubmissionFile(
            $source,
            $submission,
            (int) $sourceGenre->getId(),
            SubmissionFile::SUBMISSION_FILE_NOTE,
            Application::ASSOC_TYPE_NOTE,
            $firstNoteId
        );

        $sourceDeployment = new FullJournalImportExportDeployment($source, null);
        $nativeData = $sourceDeployment->exportNativeData();
        $workflow = $sourceDeployment->exportWorkflow();
        $xpath = new \DOMXPath($workflow);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $this->assertSame(1, $xpath->query('//pkp:discussion')->length);
        $this->assertSame(1, $xpath->query('//pkp:discussion_participant')->length);
        $this->assertSame(2, $xpath->query('//pkp:discussion_note')->length);
        $this->assertSame(1, $xpath->query('//pkp:discussion_attachment[@submission_file_ref]')->length);

        $destinationDeployment = new FullJournalImportExportDeployment($destination, $participant);
        $this->setRequestContext($destination);
        $destinationDeployment->setImportPath((string) Config::getVar('files', 'files_dir'));
        $destinationDeployment->mapReference('section', (string) $sourceSection->getId(), $destinationSection->getId());
        $destinationDeployment->mapReference('genre', (string) $sourceGenre->getId(), $destinationGenre->getId());
        $nativeMaps = $destinationDeployment->importNativeData($nativeData->documentElement);
        $destinationDeployment->mapReference('user', (string) $participant->getId(), (int) $participant->getId());
        $notifications = DB::table('notifications')->count();
        $emailLogs = DB::table('email_log')->count();
        $eventLogs = DB::table('event_log')->count();
        $workflowMaps = $destinationDeployment->importWorkflow($workflow->documentElement);
        $importedSubmissionId = $nativeMaps['submission_id_map'][(string) $submission->getId()];
        $importedQueryId = $workflowMaps['discussion_id_map'][(string) $queryId];

        $discussion = DB::table('queries')->where('query_id', $importedQueryId)->first();
        $this->assertNotNull($discussion);
        $this->assertSame($importedSubmissionId, (int) $discussion->assoc_id);
        $this->assertSame(WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, (int) $discussion->stage_id);
        $this->assertSame(1, (int) $discussion->closed);
        $this->assertSame(2.5, (float) $discussion->seq);
        $this->assertSame(
            [(int) $participant->getId()],
            DB::table('query_participants')->where('query_id', $importedQueryId)->pluck('user_id')
                ->map(fn ($userId): int => (int) $userId)->all()
        );
        $notes = DB::table('notes')->where('assoc_type', Application::ASSOC_TYPE_QUERY)
            ->where('assoc_id', $importedQueryId)->orderBy('date_created')->get();
        $this->assertCount(2, $notes);
        $this->assertSame('2022-01-02 03:04:05', $notes[0]->date_created);
        $this->assertSame('2022-01-03 04:05:06', $notes[0]->date_modified);
        $this->assertSame('Editorial response', $notes[1]->title);
        $importedAttachmentId = $workflowMaps['discussion_attachment_id_map'][(string) $attachment->getId()];
        $importedAttachment = Repo::submissionFile()->get($importedAttachmentId);
        $this->assertNotNull($importedAttachment);
        $this->assertSame($importedSubmissionId, (int) $importedAttachment->getData('submissionId'));
        $this->assertSame(Application::ASSOC_TYPE_NOTE, (int) $importedAttachment->getData('assocType'));
        $this->assertSame((int) $notes[0]->note_id, (int) $importedAttachment->getData('assocId'));
        $this->assertSame($notifications, DB::table('notifications')->count());
        $this->assertSame($emailLogs, DB::table('email_log')->count());
        $this->assertSame($eventLogs, DB::table('event_log')->count());
    }

    public function testItRejectsUnknownWorkflowReferencesWithoutPartialWrites(): void
    {
        $destination = $this->createContext('invalid-workflow');
        $section = $this->createSection($destination);
        $submission = $this->createSubmission($destination, $section, 'Workflow sentinel');
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML(
            '<workflow_history xmlns="http://pkp.sfu.ca"><stage_assignments/><review_rounds>'
            . '<review_round source_ref="round-1" submission_ref="submission-1" stage_id="3" round="1" status="1">'
            . '<review_assignment source_ref="review-1" submission_ref="submission-1" '
            . 'review_round_ref="round-1" reviewer_ref="missing-user" stage_id="3" review_method="2" '
            . 'round="1" step="1" declined="0" cancelled="0" reminder_was_automatic="0" '
            . 'considered="0" request_resent="0"/>'
            . '</review_round></review_rounds><workflow_files/><discussions/><editorial_decisions/></workflow_history>'
        ));
        $rounds = DB::table('review_rounds')->count();
        $assignments = DB::table('review_assignments')->count();
        $deployment = new FullJournalImportExportDeployment($destination, null);
        $deployment->mapReference('submission', 'submission-1', (int) $submission->getId());

        try {
            $deployment->importWorkflow($document->documentElement);
            $this->fail('Unknown reviewer reference was accepted');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Missing mapped user reference: "missing-user"', $exception->getMessage());
        }

        $this->assertSame($rounds, DB::table('review_rounds')->count());
        $this->assertSame($assignments, DB::table('review_assignments')->count());
    }

    public function testItRejectsUnknownDiscussionParticipantWithoutPartialWrites(): void
    {
        $destination = $this->createContext('invalid-discussion');
        $section = $this->createSection($destination);
        $submission = $this->createSubmission($destination, $section, 'Discussion sentinel');
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML(
            '<workflow_history xmlns="http://pkp.sfu.ca"><stage_assignments/><review_rounds/><workflow_files/>'
            . '<discussions><discussion source_ref="discussion-1" submission_ref="submission-1" '
            . 'stage_id="3" closed="false" sequence="1"><discussion_participant '
            . 'discussion_ref="discussion-1" user_ref="missing-user"/></discussion></discussions>'
            . '<editorial_decisions/></workflow_history>'
        ));
        $discussions = DB::table('queries')->count();
        $participants = DB::table('query_participants')->count();
        $deployment = new FullJournalImportExportDeployment($destination, null);
        $deployment->mapReference('submission', 'submission-1', (int) $submission->getId());

        try {
            $deployment->importWorkflow($document->documentElement);
            $this->fail('Unknown discussion participant was accepted');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Missing mapped user reference: "missing-user"', $exception->getMessage());
        }

        $this->assertSame($discussions, DB::table('queries')->count());
        $this->assertSame($participants, DB::table('query_participants')->count());
    }

    public function testItRejectsReviewAssignmentFromAnotherSubmission(): void
    {
        $destination = $this->createContext('cross-workflow');
        $section = $this->createSection($destination);
        $roundSubmission = $this->createSubmission($destination, $section, 'Round submission');
        $reviewSubmission = $this->createSubmission($destination, $section, 'Review submission');
        $reviewer = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($reviewer);
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML(
            '<workflow_history xmlns="http://pkp.sfu.ca"><stage_assignments/><review_rounds>'
            . '<review_round source_ref="round-1" submission_ref="submission-1" stage_id="3" round="1" status="1">'
            . '<review_assignment source_ref="review-1" submission_ref="submission-2" '
            . 'review_round_ref="round-1" reviewer_ref="user-1" stage_id="3" review_method="2" '
            . 'round="1" step="1" declined="0" cancelled="0" reminder_was_automatic="0" '
            . 'considered="0" request_resent="0"/>'
            . '</review_round></review_rounds><workflow_files/><discussions/><editorial_decisions/></workflow_history>'
        ));
        $rounds = DB::table('review_rounds')->count();
        $deployment = new FullJournalImportExportDeployment($destination, null);
        $deployment->mapReference('submission', 'submission-1', (int) $roundSubmission->getId());
        $deployment->mapReference('submission', 'submission-2', (int) $reviewSubmission->getId());
        $deployment->mapReference('user', 'user-1', (int) $reviewer->getId());

        try {
            $deployment->importWorkflow($document->documentElement);
            $this->fail('A review assignment from another submission was accepted');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(
                'Review assignment source_ref "review-1" submission_ref "submission-2" does not belong to '
                    . 'review_round_ref "round-1" at line 1',
                $exception->getMessage()
            );
        }

        $this->assertSame($rounds, DB::table('review_rounds')->count());
    }

    private function createContext(string $label): Journal
    {
        $context = Application::get()->getContextDAO()->newDataObject();
        $context->setPath(substr($label, 0, 12) . '-' . bin2hex(random_bytes(4)));
        $context->setPrimaryLocale('en');
        $context->setEnabled(false);
        $context->setSequence(1);
        $context->setData('supportedLocales', ['en']);
        $context->setData('supportedFormLocales', ['en']);
        $context->setData('supportedSubmissionLocales', ['en']);
        $context->setData('name', ['en' => 'Workflow Test Journal']);
        $context->setData('contactName', 'Editorial Team');
        $context->setData('contactEmail', 'editor@example.com');
        Application::get()->getContextDAO()->insertObject($context);
        $this->contexts[] = $context;
        return $context;
    }

    private function createSection(Journal $context): \APP\section\Section
    {
        $section = Repo::section()->newDataObject();
        $section->setContextId((int) $context->getId());
        $section->setSequence(1);
        $section->setTitle('Articles', 'en');
        $section->setAbbrev('ART', 'en');
        $section->setEditorRestricted(false);
        $section->setMetaIndexed(true);
        $section->setMetaReviewed(true);
        $section->setAbstractsNotRequired(true);
        $section->setHideTitle(false);
        $section->setHideAuthor(false);
        $section->setIsInactive(false);
        $section->setAbstractWordCount(0);
        $section->setId(Repo::section()->add($section));
        return $section;
    }

    private function createSubmission(
        Journal $context,
        \APP\section\Section $section,
        string $title
    ): \APP\submission\Submission {
        $submission = Repo::submission()->newDataObject();
        $submission->setData('contextId', (int) $context->getId());
        $submission->setData('locale', 'en');
        $submission->setData('stageId', WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
        $submission->setData('status', \APP\submission\Submission::STATUS_QUEUED);
        $submission->setData('submissionProgress', '');
        $publication = Repo::publication()->newDataObject();
        $publication->setData('sectionId', $section->getId());
        $publication->setData('locale', 'en');
        $publication->setData('title', ['en' => $title]);
        $publication->setData('status', \APP\submission\Submission::STATUS_QUEUED);
        $publication->setData('accessStatus', 0);
        return Repo::submission()->get(Repo::submission()->add($submission, $publication, $context));
    }

    private function createUserGroup(Journal $context, string $name): UserGroup
    {
        $group = Repo::userGroup()->newDataObject();
        $group->setContextId((int) $context->getId());
        $group->setRoleId(Role::ROLE_ID_SUB_EDITOR);
        $group->setDefault(false);
        $group->setShowTitle(true);
        $group->setPermitSelfRegistration(false);
        $group->setPermitMetadataEdit(true);
        $group->setName($name, 'en');
        $group->setAbbrev('WE', 'en');
        Repo::userGroup()->add($group);
        $this->userGroups[] = $group;
        return $group;
    }

    private function createReviewForm(Journal $context): array
    {
        $formDao = DAORegistry::getDAO('ReviewFormDAO');
        $elementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $form = $formDao->newDataObject();
        $form->setAssocType(Application::ASSOC_TYPE_JOURNAL);
        $form->setAssocId($context->getId());
        $form->setSequence(1);
        $form->setActive(1);
        $form->setTitle('Workflow Review Form', 'en');
        $form->setDescription('Workflow review form', 'en');
        $formId = $formDao->insertObject($form);
        $elementId = $this->createReviewFormElement($formId, 1, 'Review response');
        return [$formId, $elementId];
    }

    private function createReviewFormElement(int $formId, int $sequence, string $question): int
    {
        $elementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $element = $elementDao->newDataObject();
        $element->setReviewFormId($formId);
        $element->setSequence($sequence);
        $element->setElementType(1);
        $element->setRequired(true);
        $element->setIncluded(true);
        $element->setQuestion($question, 'en');
        $element->setDescription('Review response description', 'en');
        return $elementDao->insertObject($element);
    }

    private function createGenre(Journal $context, string $name)
    {
        $genre = DAORegistry::getDAO('GenreDAO')->newDataObject();
        $genre->setContextId((int) $context->getId());
        $genre->setKey('WORKFLOW_' . bin2hex(random_bytes(4)));
        $genre->setCategory(1);
        $genre->setDependent(false);
        $genre->setSupplementary(false);
        $genre->setRequired(false);
        $genre->setSequence(1);
        $genre->setEnabled(true);
        $genre->setName($name, 'en');
        $genre->setId(DAORegistry::getDAO('GenreDAO')->insertObject($genre));
        return $genre;
    }

    private function createSubmissionFile(
        Journal $context,
        \APP\submission\Submission $submission,
        int $genreId,
        int $fileStage = SubmissionFile::SUBMISSION_FILE_SUBMISSION,
        ?int $assocType = null,
        ?int $assocId = null
    ): SubmissionFile {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'workflow-review-');
        file_put_contents($temporaryPath, 'review file');
        $fileId = Services::get('file')->add(
            $temporaryPath,
            Repo::submissionFile()->getSubmissionDir((int) $context->getId(), (int) $submission->getId())
                . DIRECTORY_SEPARATOR . 'review-' . bin2hex(random_bytes(4)) . '.txt'
        );
        unlink($temporaryPath);
        $this->fileIds[] = $fileId;
        $submissionFile = Repo::submissionFile()->newDataObject();
        $submissionFile->setData('submissionId', $submission->getId());
        $submissionFile->setData('fileId', $fileId);
        $submissionFile->setData('genreId', $genreId);
        $submissionFile->setData('fileStage', $fileStage);
        $submissionFile->setData('assocType', $assocType);
        $submissionFile->setData('assocId', $assocId);
        $submissionFile->setData('viewable', true);
        $submissionFile->setData('createdAt', date('Y-m-d H:i:s'));
        $submissionFile->setData('updatedAt', date('Y-m-d H:i:s'));
        $submissionFile->setData('name', ['en' => 'review.txt']);
        $submissionFile->setId(Repo::submissionFile()->dao->insert($submissionFile));
        return $submissionFile;
    }

    private function setRequestContext(Journal $context): void
    {
        $router = new class ($context) extends \APP\core\PageRouter {
            private Journal $context;

            public function __construct(Journal $context)
            {
                $this->context = $context;
            }

            public function getContext($request, $forceReload = false): Journal
            {
                return $this->context;
            }
        };
        Application::get()->getRequest()->setRouter($router);
    }
}
