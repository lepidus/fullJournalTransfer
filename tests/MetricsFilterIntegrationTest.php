<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\file\IssueFileManager;
use APP\issue\IssueFile;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\install\Installer;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\DatabaseTestCase;

class MetricsFilterIntegrationTest extends DatabaseTestCase
{
    private array $contexts = [];
    private array $fileIds = [];
    private array $issueIds = [];

    protected function getAffectedTables()
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $installer = (new \ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
        if (!$installer->installFilterConfig(__DIR__ . '/../filter/filterConfig.xml')) {
            throw new \RuntimeException('Metrics filter configuration could not be installed');
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->contexts) as $context) {
            $this->setRequestContext($context);
            Repo::submission()->deleteByContextId((int) $context->getId());
            foreach ($this->issueIds as $issueId) {
                $issue = Repo::issue()->get($issueId);
                if ($issue && (int) $issue->getJournalId() === (int) $context->getId()) {
                    (new IssueFileManager($issueId))->deleteIssueTree();
                }
            }
            Repo::issue()->deleteByContextId((int) $context->getId());
            Repo::section()->deleteByContextId((int) $context->getId());
            DAORegistry::getDAO('GenreDAO')->deleteByContextId((int) $context->getId());
            Application::get()->getContextDAO()->deleteObject($context);
        }
        foreach (array_unique($this->fileIds) as $fileId) {
            if (Services::get('file')->get($fileId)) {
                Services::get('file')->delete($fileId);
            }
        }
        parent::tearDown();
    }

    public function testItTransfersContextMetricsOnceUsingTheDestinationContext(): void
    {
        $source = $this->createContext('metrics-source');
        $destination = $this->createContext('metrics-destination');
        DB::table('metrics_context')->insert([
            'load_id' => 'usage-20240101.log',
            'context_id' => $source->getId(),
            'date' => '2024-01-01',
            'metric' => 7,
        ]);

        $document = (new FullJournalImportExportDeployment($source, null))->exportMetrics();
        $deployment = new FullJournalImportExportDeployment($destination, null);
        $deployment->importMetrics($document->documentElement);
        $deployment->importMetrics($document->documentElement);

        $metrics = DB::table('metrics_context')
            ->where('context_id', $destination->getId())
            ->get();
        $this->assertCount(1, $metrics);
        $this->assertSame('usage-20240101.log', $metrics[0]->load_id);
        $this->assertSame('2024-01-01', $metrics[0]->date);
        $this->assertSame(7, (int) $metrics[0]->metric);
    }

    public function testItRemapsSubmissionAndIssueMetricsWithoutDuplicates(): void
    {
        $source = $this->createContext('content-source');
        $destination = $this->createContext('content-destination');
        $sourceSection = $this->createSection($source);
        $destinationSection = $this->createSection($destination);
        $sourceIssue = $this->createIssue($source, 'Source issue');
        $destinationIssue = $this->createIssue($destination, 'Destination issue');
        $sourceSubmission = $this->createSubmission($source, $sourceSection, 'Source article');
        $destinationSubmission = $this->createSubmission($destination, $destinationSection, 'Destination article');
        DB::table('metrics_submission')->insert([
            'load_id' => 'usage-20240201.log',
            'context_id' => $source->getId(),
            'submission_id' => $sourceSubmission->getId(),
            'representation_id' => null,
            'submission_file_id' => null,
            'file_type' => null,
            'assoc_type' => Application::ASSOC_TYPE_SUBMISSION,
            'date' => '2024-02-01',
            'metric' => 11,
        ]);
        DB::table('metrics_issue')->insert([
            'load_id' => 'usage-20240201.log',
            'context_id' => $source->getId(),
            'issue_id' => $sourceIssue->getId(),
            'issue_galley_id' => null,
            'date' => '2024-02-01',
            'metric' => 13,
        ]);

        $document = (new FullJournalImportExportDeployment($source, null))->exportMetrics();
        $deployment = new FullJournalImportExportDeployment($destination, null);
        $deployment->mapReference('submission', (string) $sourceSubmission->getId(), (int) $destinationSubmission->getId());
        $deployment->mapReference('issue', (string) $sourceIssue->getId(), (int) $destinationIssue->getId());
        $deployment->importMetrics($document->documentElement);
        $deployment->importMetrics($document->documentElement);

        $submissionMetric = DB::table('metrics_submission')
            ->where('context_id', $destination->getId())
            ->get();
        $issueMetric = DB::table('metrics_issue')
            ->where('context_id', $destination->getId())
            ->get();
        $this->assertCount(1, $submissionMetric);
        $this->assertSame((int) $destinationSubmission->getId(), (int) $submissionMetric[0]->submission_id);
        $this->assertSame(11, (int) $submissionMetric[0]->metric);
        $this->assertCount(1, $issueMetric);
        $this->assertSame((int) $destinationIssue->getId(), (int) $issueMetric[0]->issue_id);
        $this->assertSame(13, (int) $issueMetric[0]->metric);
    }

    public function testItRebuildsGeoAndCounterMonthlyMetricsFromDailyRecords(): void
    {
        $source = $this->createContext('aggregate-source');
        $destination = $this->createContext('aggregate-destination');
        $sourceSubmission = $this->createSubmission($source, $this->createSection($source), 'Source article');
        $destinationSubmission = $this->createSubmission(
            $destination,
            $this->createSection($destination),
            'Destination article'
        );
        DB::table('metrics_submission_geo_daily')->insert([
            'load_id' => 'usage-20240301.log',
            'context_id' => $source->getId(),
            'submission_id' => $sourceSubmission->getId(),
            'country' => 'BR',
            'region' => 'AM',
            'city' => 'Manaus',
            'date' => '2024-03-01',
            'metric' => 5,
            'metric_unique' => 3,
        ]);
        DB::table('metrics_submission_geo_monthly')->insert([
            'context_id' => $source->getId(),
            'submission_id' => $sourceSubmission->getId(),
            'country' => 'BR',
            'region' => 'AM',
            'city' => 'Manaus',
            'month' => 202403,
            'metric' => 999,
            'metric_unique' => 999,
        ]);
        $counter = [
            'load_id' => 'usage-20240301.log',
            'context_id' => $source->getId(),
            'submission_id' => $sourceSubmission->getId(),
            'date' => '2024-03-01',
            'metric_investigations' => 7,
            'metric_investigations_unique' => 4,
            'metric_requests' => 5,
            'metric_requests_unique' => 2,
        ];
        DB::table('metrics_counter_submission_daily')->insert($counter);
        unset($counter['load_id'], $counter['date']);
        $counter['month'] = 202403;
        $counter['metric_investigations'] = 999;
        $counter['metric_investigations_unique'] = 999;
        $counter['metric_requests'] = 999;
        $counter['metric_requests_unique'] = 999;
        DB::table('metrics_counter_submission_monthly')->insert($counter);

        $document = (new FullJournalImportExportDeployment($source, null))->exportMetrics();
        $deployment = new FullJournalImportExportDeployment($destination, null);
        $deployment->mapReference('submission', (string) $sourceSubmission->getId(), (int) $destinationSubmission->getId());
        $deployment->importMetrics($document->documentElement);
        $deployment->importMetrics($document->documentElement);

        $geo = DB::table('metrics_submission_geo_monthly')
            ->where('context_id', $destination->getId())
            ->first();
        $counter = DB::table('metrics_counter_submission_monthly')
            ->where('context_id', $destination->getId())
            ->first();
        $this->assertSame(5, (int) $geo->metric);
        $this->assertSame(3, (int) $geo->metric_unique);
        $this->assertSame(7, (int) $counter->metric_investigations);
        $this->assertSame(4, (int) $counter->metric_investigations_unique);
        $this->assertSame(5, (int) $counter->metric_requests);
        $this->assertSame(2, (int) $counter->metric_requests_unique);
        $this->assertSame(1, DB::table('metrics_submission_geo_daily')
            ->where('context_id', $destination->getId())->count());
        $this->assertSame(1, DB::table('metrics_counter_submission_daily')
            ->where('context_id', $destination->getId())->count());
    }

    public function testItMapsInstitutionMetricsByRorAndReportsUnsafeRecords(): void
    {
        $source = $this->createContext('institution-source');
        $destination = $this->createContext('institution-destination');
        $sourceSubmission = $this->createSubmission($source, $this->createSection($source), 'Source article');
        $destinationSubmission = $this->createSubmission(
            $destination,
            $this->createSection($destination),
            'Destination article'
        );
        $sourceInstitutionId = DB::table('institutions')->insertGetId([
            'context_id' => $source->getId(),
            'ror' => 'https://ror.org/01abcde12',
            'deleted_at' => null,
        ], 'institution_id');
        $unsafeInstitutionId = DB::table('institutions')->insertGetId([
            'context_id' => $source->getId(),
            'ror' => null,
            'deleted_at' => null,
        ], 'institution_id');
        $destinationInstitutionId = DB::table('institutions')->insertGetId([
            'context_id' => $destination->getId(),
            'ror' => 'https://ror.org/01abcde12',
            'deleted_at' => null,
        ], 'institution_id');
        foreach ([$sourceInstitutionId => 9, $unsafeInstitutionId => 12] as $institutionId => $investigations) {
            DB::table('metrics_counter_submission_institution_daily')->insert([
                'load_id' => 'usage-20240401.log',
                'context_id' => $source->getId(),
                'submission_id' => $sourceSubmission->getId(),
                'institution_id' => $institutionId,
                'date' => '2024-04-01',
                'metric_investigations' => $investigations,
                'metric_investigations_unique' => 4,
                'metric_requests' => 6,
                'metric_requests_unique' => 3,
            ]);
        }
        DB::table('metrics_counter_submission_institution_monthly')->insert([
            'context_id' => $source->getId(),
            'submission_id' => $sourceSubmission->getId(),
            'institution_id' => $sourceInstitutionId,
            'month' => 202404,
            'metric_investigations' => 999,
            'metric_investigations_unique' => 999,
            'metric_requests' => 999,
            'metric_requests_unique' => 999,
        ]);

        $document = (new FullJournalImportExportDeployment($source, null))->exportMetrics();
        $deployment = new FullJournalImportExportDeployment($destination, null);
        $deployment->mapReference('submission', (string) $sourceSubmission->getId(), (int) $destinationSubmission->getId());
        $deployment->importMetrics($document->documentElement);

        $daily = DB::table('metrics_counter_submission_institution_daily')
            ->where('context_id', $destination->getId())
            ->get();
        $monthly = DB::table('metrics_counter_submission_institution_monthly')
            ->where('context_id', $destination->getId())
            ->first();
        $this->assertCount(1, $daily);
        $this->assertSame($destinationInstitutionId, (int) $daily[0]->institution_id);
        $this->assertSame(9, (int) $monthly->metric_investigations);
        $this->assertSame([
            [
                'family' => 'counter_submission_institution',
                'granularity' => 'daily',
                'source_institution_ref' => (string) $unsafeInstitutionId,
                'reason' => 'Institution metric has no stable ROR key',
            ],
        ], $deployment->getMetricRejections());
    }

    public function testItRemapsRepresentationFileAndIssueGalleyDimensions(): void
    {
        $source = $this->createContext('dimension-source');
        $destination = $this->createContext('dimension-destination');
        $sourceSubmission = $this->createSubmission($source, $this->createSection($source), 'Source article');
        $destinationSubmission = $this->createSubmission(
            $destination,
            $this->createSection($destination),
            'Destination article'
        );
        $sourceFile = $this->createSubmissionFile($source, $sourceSubmission, $this->createGenre($source));
        $destinationFile = $this->createSubmissionFile(
            $destination,
            $destinationSubmission,
            $this->createGenre($destination)
        );
        $sourceGalley = $this->createArticleGalley($sourceSubmission, $sourceFile);
        $destinationGalley = $this->createArticleGalley($destinationSubmission, $destinationFile);
        $sourceIssue = $this->createIssue($source, 'Source issue');
        $destinationIssue = $this->createIssue($destination, 'Destination issue');
        $sourceIssueGalley = $this->createIssueGalley($sourceIssue);
        $destinationIssueGalley = $this->createIssueGalley($destinationIssue);
        DB::table('metrics_submission')->insert([
            'load_id' => 'usage-20240501.log',
            'context_id' => $source->getId(),
            'submission_id' => $sourceSubmission->getId(),
            'representation_id' => $sourceGalley,
            'submission_file_id' => $sourceFile->getId(),
            'file_type' => 2,
            'assoc_type' => Application::ASSOC_TYPE_SUBMISSION_FILE,
            'date' => '2024-05-01',
            'metric' => 17,
        ]);
        DB::table('metrics_issue')->insert([
            'load_id' => 'usage-20240501.log',
            'context_id' => $source->getId(),
            'issue_id' => $sourceIssue->getId(),
            'issue_galley_id' => $sourceIssueGalley,
            'date' => '2024-05-01',
            'metric' => 19,
        ]);

        $document = (new FullJournalImportExportDeployment($source, null))->exportMetrics();
        $deployment = new FullJournalImportExportDeployment($destination, null);
        foreach ([
            'submission' => [$sourceSubmission->getId(), $destinationSubmission->getId()],
            'article_galley' => [$sourceGalley, $destinationGalley],
            'submission_file' => [$sourceFile->getId(), $destinationFile->getId()],
            'issue' => [$sourceIssue->getId(), $destinationIssue->getId()],
            'issue_galley' => [$sourceIssueGalley, $destinationIssueGalley],
        ] as $entity => [$sourceId, $destinationId]) {
            $deployment->mapReference($entity, (string) $sourceId, (int) $destinationId);
        }
        $deployment->importMetrics($document->documentElement);

        $submissionMetric = DB::table('metrics_submission')
            ->where('context_id', $destination->getId())
            ->first();
        $issueMetric = DB::table('metrics_issue')
            ->where('context_id', $destination->getId())
            ->first();
        $this->assertSame($destinationGalley, (int) $submissionMetric->representation_id);
        $this->assertSame((int) $destinationFile->getId(), (int) $submissionMetric->submission_file_id);
        $this->assertSame(2, (int) $submissionMetric->file_type);
        $this->assertSame($destinationIssueGalley, (int) $issueMetric->issue_galley_id);
    }

    public function testItImportsMonthlyAggregatesOnceWhenDailyRecordsAreUnavailable(): void
    {
        $destination = $this->createContext('monthly-destination');
        $submission = $this->createSubmission(
            $destination,
            $this->createSection($destination),
            'Destination article'
        );
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML(
            '<metrics xmlns="http://pkp.sfu.ca"><context_metrics/><submission_metrics/><issue_metrics/>'
            . '<geo_metrics><geo_metric granularity="monthly" month="202406" submission_ref="submission-1" '
            . 'country="" region="" city="" metric="21" metric_unique="14"/></geo_metrics>'
            . '<counter_metrics><counter_metric granularity="monthly" month="202406" '
            . 'submission_ref="submission-1" metric_investigations="25" metric_investigations_unique="15" '
            . 'metric_requests="20" metric_requests_unique="10"/></counter_metrics><institution_metrics/></metrics>'
        ));
        $deployment = new FullJournalImportExportDeployment($destination, null);
        $deployment->mapReference('submission', 'submission-1', (int) $submission->getId());

        $deployment->importMetrics($document->documentElement);
        $deployment->importMetrics($document->documentElement);

        $geo = DB::table('metrics_submission_geo_monthly')
            ->where('context_id', $destination->getId())
            ->get();
        $counter = DB::table('metrics_counter_submission_monthly')
            ->where('context_id', $destination->getId())
            ->get();
        $this->assertCount(1, $geo);
        $this->assertSame('', $geo[0]->country);
        $this->assertSame(21, (int) $geo[0]->metric);
        $this->assertSame(14, (int) $geo[0]->metric_unique);
        $this->assertCount(1, $counter);
        $this->assertSame(25, (int) $counter[0]->metric_investigations);
        $this->assertSame(10, (int) $counter[0]->metric_requests_unique);
    }

    public function testItRejectsAnUnmappedRequiredDimensionWithoutPartialMetrics(): void
    {
        $destination = $this->createContext('unmapped-destination');
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML(
            '<metrics xmlns="http://pkp.sfu.ca"><context_metrics>'
            . '<context_metric load_id="usage.log" date="2024-07-01" metric="3"/></context_metrics>'
            . '<submission_metrics><submission_metric load_id="usage.log" submission_ref="missing" '
            . 'assoc_type="1048585" date="2024-07-01" metric="5"/></submission_metrics>'
            . '<issue_metrics/><geo_metrics/><counter_metrics/><institution_metrics/></metrics>'
        ));
        $deployment = new FullJournalImportExportDeployment($destination, null);

        try {
            $deployment->importMetrics($document->documentElement);
            $this->fail('An unmapped required metric dimension was accepted');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Missing mapped submission reference', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('metrics_context')->where('context_id', $destination->getId())->count());
        $this->assertSame(0, DB::table('metrics_submission')->where('context_id', $destination->getId())->count());
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
        $context->setData('name', ['en' => 'Metrics Test Journal']);
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

    private function createIssue(Journal $context, string $title): \APP\issue\Issue
    {
        $issue = Repo::issue()->newDataObject();
        $issue->setJournalId((int) $context->getId());
        $issue->setVolume(1);
        $issue->setNumber('1');
        $issue->setYear(2024);
        $issue->setPublished(1);
        $issue->setAccessStatus(1);
        $issue->setShowVolume(1);
        $issue->setShowNumber(1);
        $issue->setShowYear(1);
        $issue->setShowTitle(1);
        $issue->setTitle($title, 'en');
        $issue->setId(Repo::issue()->add($issue));
        $this->issueIds[] = (int) $issue->getId();
        return $issue;
    }

    private function createSubmission(
        Journal $context,
        \APP\section\Section $section,
        string $title
    ): \APP\submission\Submission {
        $submission = Repo::submission()->newDataObject();
        $submission->setData('contextId', (int) $context->getId());
        $submission->setData('locale', 'en');
        $submission->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
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

    private function createGenre(Journal $context): int
    {
        $genre = DAORegistry::getDAO('GenreDAO')->newDataObject();
        $genre->setContextId((int) $context->getId());
        $genre->setKey('METRIC_' . bin2hex(random_bytes(4)));
        $genre->setCategory(1);
        $genre->setDependent(false);
        $genre->setSupplementary(false);
        $genre->setRequired(false);
        $genre->setSequence(1);
        $genre->setEnabled(true);
        $genre->setName('Metric file', 'en');
        return (int) DAORegistry::getDAO('GenreDAO')->insertObject($genre);
    }

    private function createSubmissionFile(
        Journal $context,
        \APP\submission\Submission $submission,
        int $genreId
    ): SubmissionFile {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'metric-file-');
        file_put_contents($temporaryPath, 'metric file');
        $fileId = Services::get('file')->add(
            $temporaryPath,
            Repo::submissionFile()->getSubmissionDir((int) $context->getId(), (int) $submission->getId())
                . DIRECTORY_SEPARATOR . 'metric-' . bin2hex(random_bytes(4)) . '.txt'
        );
        unlink($temporaryPath);
        $this->fileIds[] = $fileId;
        $submissionFile = Repo::submissionFile()->newDataObject();
        $submissionFile->setData('submissionId', $submission->getId());
        $submissionFile->setData('fileId', $fileId);
        $submissionFile->setData('genreId', $genreId);
        $submissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_PROOF);
        $submissionFile->setData('viewable', true);
        $submissionFile->setData('createdAt', date('Y-m-d H:i:s'));
        $submissionFile->setData('updatedAt', date('Y-m-d H:i:s'));
        $submissionFile->setData('name', ['en' => 'metric.txt']);
        $submissionFile->setId(Repo::submissionFile()->dao->insert($submissionFile));
        return $submissionFile;
    }

    private function createArticleGalley(
        \APP\submission\Submission $submission,
        SubmissionFile $submissionFile
    ): int {
        $galley = Repo::galley()->newDataObject();
        $galley->setData('publicationId', $submission->getCurrentPublication()->getId());
        $galley->setData('label', 'Text');
        $galley->setData('locale', 'en');
        $galley->setData('seq', 1);
        $galley->setData('submissionFileId', $submissionFile->getId());
        $galley->setData('isApproved', true);
        return (int) Repo::galley()->dao->insert($galley);
    }

    private function createIssueGalley(\APP\issue\Issue $issue): int
    {
        $manager = new IssueFileManager((int) $issue->getId());
        $fileName = 'metric-' . bin2hex(random_bytes(4)) . '.pdf';
        $path = $manager->getFilesDir() . 'public' . DIRECTORY_SEPARATOR . $fileName;
        $manager->mkdirtree(dirname($path));
        $manager->writeFile($path, 'metric issue file');
        $fileDao = DAORegistry::getDAO('IssueFileDAO');
        $file = $fileDao->newDataObject();
        $file->setIssueId((int) $issue->getId());
        $file->setServerFileName($fileName);
        $file->setFileType('application/pdf');
        $file->setFileSize(17);
        $file->setContentType(IssueFile::ISSUE_FILE_PUBLIC);
        $file->setOriginalFileName('metric.pdf');
        $file->setDateUploaded(date('Y-m-d H:i:s'));
        $file->setDateModified(date('Y-m-d H:i:s'));
        $fileId = $fileDao->insertObject($file);
        $galleyDao = DAORegistry::getDAO('IssueGalleyDAO');
        $galley = $galleyDao->newDataObject();
        $galley->setIssueId((int) $issue->getId());
        $galley->setFileId($fileId);
        $galley->setLocale('en');
        $galley->setLabel('PDF');
        $galley->setSequence(1);
        return (int) $galleyDao->insertObject($galley);
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
