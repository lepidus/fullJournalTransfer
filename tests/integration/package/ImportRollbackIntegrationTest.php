<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\integration\package;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\install\Installer;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\DatabaseTestCase;

class ImportRollbackIntegrationTest extends DatabaseTestCase
{
    private array $contexts = [];
    private array $fileIds = [];

    protected function getAffectedTables()
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $installer = (new \ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
        if (!$installer->installFilterConfig(dirname(__DIR__, 3) . '/filter/filterConfig.xml')) {
            throw new \RuntimeException('Import rollback filter configuration could not be installed');
        }
    }

    protected function tearDown(): void
    {
        $publicFileManager = new PublicFileManager();
        foreach (array_reverse($this->contexts) as $context) {
            $contextId = (int) $context->getId();
            Repo::submission()->deleteByContextId($contextId);
            Repo::section()->deleteByContextId($contextId);
            DAORegistry::getDAO('GenreDAO')->deleteByContextId($contextId);
            foreach ($this->metricTables() as $table) {
                DB::table($table)->where('context_id', $contextId)->delete();
            }
            $publicFileManager->rmtree($publicFileManager->getContextFilesPath($contextId));
            Application::get()->getContextDAO()->deleteObject($context);
        }
        foreach (array_unique($this->fileIds) as $fileId) {
            if (Services::get('file')->get($fileId)) {
                Services::get('file')->delete($fileId);
            }
        }
        parent::tearDown();
    }

    public function testItRejectsInvalidReferencesBeforeMovingFilesOrPersistingTheContext(): void
    {
        [$document] = $this->exportJournalWithFiles(['validated payload']);
        $path = $this->destinationPath($document, 'validation');
        $xpath = $this->xpath($document);
        $section = $xpath->query('//pkp:reference_data/pkp:sections/pkp:section')->item(0);
        $this->assertInstanceOf(DOMElement::class, $section);
        $section->setAttribute('review_form_ref', 'missing-review-form');
        $deployment = new CapturingRollbackDeployment(new Journal(), $this->importUser());
        $deployment->setImportPath((string) Config::getVar('files', 'files_dir'));

        $deployment->import('full-journal-xml=>journal', $document->saveXML());

        $this->assertTrue($deployment->isProcessFailed());
        $this->assertSame([], $deployment->createdFilePaths);
        $this->assertNull(Application::get()->getContextDAO()->getByPath($path));
        $publicFileManager = new PublicFileManager();
        $this->assertDirectoryDoesNotExist(
            $publicFileManager->getContextFilesPath((int) $deployment->getContext()->getId())
        );
    }

    public function testItCompensatesTheFirstFileWhenASecondFileCannotBeMoved(): void
    {
        [$document] = $this->exportJournalWithFiles(['first revision', 'second revision']);
        $path = $this->destinationPath($document, 'file-failure');
        $hrefs = $this->xpath($document)->query('//pkp:submission_file/pkp:file/pkp:href');
        $this->assertSame(2, $hrefs->length);
        $hrefs->item(1)->setAttribute('src', 'missing/second-revision.txt');
        $deployment = new CapturingRollbackDeployment(new Journal(), $this->importUser());
        $deployment->setImportPath((string) Config::getVar('files', 'files_dir'));

        $deployment->import('full-journal-xml=>journal', $document->saveXML());

        $this->assertTrue($deployment->isProcessFailed());
        $this->assertNotEmpty($deployment->createdFilePaths, $this->errorSummary($deployment));
        $this->assertCompensatedFiles($deployment->createdFilePaths);
        $this->assertNull(Application::get()->getContextDAO()->getByPath($path));
    }

    public function testItRollsBackWorkflowPersistenceAndCompensatesFilesWithoutSideEffects(): void
    {
        [$document, $submission] = $this->exportJournalWithFiles(['workflow payload']);
        $path = $this->destinationPath($document, 'workflow-failure');
        $this->appendInvalidReviewAssignment($document, (string) $submission->getId());
        $deployment = new CapturingRollbackDeployment(new Journal(), $this->importUser());
        $deployment->setImportPath((string) Config::getVar('files', 'files_dir'));
        $rounds = DB::table('review_rounds')->count();
        $effects = $this->sideEffectCounts();

        $deployment->import('full-journal-xml=>journal', $document->saveXML());

        $this->assertTrue($deployment->isProcessFailed());
        $this->assertSame($rounds, DB::table('review_rounds')->count());
        $this->assertSame($effects, $this->sideEffectCounts());
        $this->assertNotEmpty($deployment->createdFilePaths, $this->errorSummary($deployment));
        $this->assertCompensatedFiles($deployment->createdFilePaths);
        $this->assertNull(Application::get()->getContextDAO()->getByPath($path));
        $this->assertSafeErrors($deployment, 'Missing mapped user reference');
    }

    public function testItRollsBackMetricsAndCompensatesFilesAfterALateFailure(): void
    {
        [$document] = $this->exportJournalWithFiles(['metrics payload']);
        $path = $this->destinationPath($document, 'metrics-failure');
        $loadId = 'rollback-' . bin2hex(random_bytes(4)) . '.log';
        $this->appendInvalidSubmissionMetric($document, $loadId);
        $deployment = new CapturingRollbackDeployment(new Journal(), $this->importUser());
        $deployment->setImportPath((string) Config::getVar('files', 'files_dir'));
        $effects = $this->sideEffectCounts();

        $deployment->import('full-journal-xml=>journal', $document->saveXML());

        $this->assertTrue($deployment->isProcessFailed());
        $this->assertSame(0, DB::table('metrics_context')->where('load_id', $loadId)->count());
        $this->assertSame($effects, $this->sideEffectCounts());
        $this->assertNotEmpty($deployment->createdFilePaths, $this->errorSummary($deployment));
        $this->assertCompensatedFiles($deployment->createdFilePaths);
        $this->assertNull(Application::get()->getContextDAO()->getByPath($path));
        $this->assertSafeErrors($deployment, 'Missing mapped submission reference');
    }

    public function testItRejectsASecondImportWithoutDuplicatingTheContext(): void
    {
        $path = 'conflict-' . bin2hex(random_bytes(4));
        $xml = $this->minimalJournalXml($path);
        $first = new FullJournalImportExportDeployment(new Journal(), null);

        $first->import('full-journal-xml=>journal', $xml);

        $this->assertFalse($first->isProcessFailed(), $this->errorSummary($first));
        $created = Application::get()->getContextDAO()->getByPath($path);
        $this->assertInstanceOf(Journal::class, $created);
        $this->contexts[] = $created;
        $second = new FullJournalImportExportDeployment(new Journal(), null);

        $second->import('full-journal-xml=>journal', $xml);

        $this->assertTrue($second->isProcessFailed());
        $this->assertSame(1, DB::table('journals')->where('path', $path)->count());
        $this->assertFalse((bool) Application::get()->getContextDAO()->getByPath($path)->getEnabled());
    }

    private function exportJournalWithFiles(array $contents): array
    {
        $context = $this->createContext();
        $section = $this->createSection($context);
        $submission = $this->createSubmission($context, $section);
        $genre = $this->createGenre($context);
        $this->createSubmissionFile($context, $submission, (int) $genre->getId(), $contents);
        $this->setRequestContext($context);
        $document = (new FullJournalImportExportDeployment($context, null))->exportContextData();
        return [$document, $submission];
    }

    private function createContext(): Journal
    {
        $context = Application::get()->getContextDAO()->newDataObject();
        $context->setPath('rollback-source-' . bin2hex(random_bytes(4)));
        $context->setPrimaryLocale('en');
        $context->setEnabled(false);
        $context->setSequence(1);
        $context->setData('supportedLocales', ['en']);
        $context->setData('supportedFormLocales', ['en']);
        $context->setData('supportedSubmissionLocales', ['en']);
        $context->setData('name', ['en' => 'Rollback Source Journal']);
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

    private function createSubmission(Journal $context, \APP\section\Section $section): \APP\submission\Submission
    {
        $submission = Repo::submission()->newDataObject();
        $submission->setData('contextId', (int) $context->getId());
        $submission->setData('locale', 'en');
        $submission->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
        $submission->setData('status', \APP\submission\Submission::STATUS_QUEUED);
        $submission->setData('submissionProgress', '');
        $publication = Repo::publication()->newDataObject();
        $publication->setData('sectionId', $section->getId());
        $publication->setData('locale', 'en');
        $publication->setData('title', ['en' => 'Rollback Article']);
        $publication->setData('status', \APP\submission\Submission::STATUS_QUEUED);
        $publication->setData('accessStatus', 0);
        $submissionId = Repo::submission()->add($submission, $publication, $context);
        return Repo::submission()->get($submissionId);
    }

    private function createGenre(Journal $context)
    {
        $genre = DAORegistry::getDAO('GenreDAO')->newDataObject();
        $genre->setContextId((int) $context->getId());
        $genre->setKey('ROLLBACK_' . bin2hex(random_bytes(4)));
        $genre->setCategory(1);
        $genre->setDependent(false);
        $genre->setSupplementary(false);
        $genre->setRequired(true);
        $genre->setSequence(1);
        $genre->setEnabled(true);
        $genre->setName('Manuscript', 'en');
        $genre->setId(DAORegistry::getDAO('GenreDAO')->insertObject($genre));
        return $genre;
    }

    private function createSubmissionFile(
        Journal $context,
        \APP\submission\Submission $submission,
        int $genreId,
        array $contents
    ): SubmissionFile {
        $fileIds = [];
        foreach ($contents as $content) {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'full-journal-rollback-');
            file_put_contents($temporaryPath, $content);
            $fileId = Services::get('file')->add(
                $temporaryPath,
                Repo::submissionFile()->getSubmissionDir((int) $context->getId(), (int) $submission->getId())
                    . DIRECTORY_SEPARATOR . 'revision-' . bin2hex(random_bytes(4)) . '.txt'
            );
            unlink($temporaryPath);
            $fileIds[] = $fileId;
            $this->fileIds[] = $fileId;
        }
        $submissionFile = Repo::submissionFile()->newDataObject();
        $submissionFile->setData('submissionId', $submission->getId());
        $submissionFile->setData('fileId', array_shift($fileIds));
        $submissionFile->setData('genreId', $genreId);
        $submissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_SUBMISSION);
        $submissionFile->setData('viewable', true);
        $submissionFile->setData('createdAt', date('Y-m-d H:i:s'));
        $submissionFile->setData('updatedAt', date('Y-m-d H:i:s'));
        $submissionFile->setData('name', ['en' => 'manuscript.txt']);
        $submissionFile->setId(Repo::submissionFile()->dao->insert($submissionFile));
        foreach ($fileIds as $fileId) {
            $submissionFile->setData('fileId', $fileId);
            $submissionFile->setData('updatedAt', date('Y-m-d H:i:s'));
            Repo::submissionFile()->dao->update($submissionFile);
            $submissionFile = Repo::submissionFile()->get((int) $submissionFile->getId());
        }
        return $submissionFile;
    }

    private function destinationPath(DOMDocument $document, string $prefix): string
    {
        $path = $prefix . '-' . bin2hex(random_bytes(4));
        $document->documentElement->setAttribute('url_path', $path);
        return $path;
    }

    private function appendInvalidReviewAssignment(DOMDocument $document, string $submissionReference): void
    {
        $reviewRounds = $this->xpath($document)->query('//pkp:workflow_history/pkp:review_rounds')->item(0);
        $this->assertInstanceOf(DOMElement::class, $reviewRounds);
        $round = $document->createElementNS('http://pkp.sfu.ca', 'review_round');
        $round->setAttribute('source_ref', 'round-1');
        $round->setAttribute('submission_ref', $submissionReference);
        $round->setAttribute('stage_id', (string) WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
        $round->setAttribute('round', '1');
        $round->setAttribute('status', '1');
        $assignment = $document->createElementNS('http://pkp.sfu.ca', 'review_assignment');
        $assignment->setAttribute('source_ref', 'review-1');
        $assignment->setAttribute('submission_ref', $submissionReference);
        $assignment->setAttribute('review_round_ref', 'round-1');
        $assignment->setAttribute('reviewer_ref', 'missing-user');
        $round->appendChild($assignment);
        $reviewRounds->appendChild($round);
    }

    private function appendInvalidSubmissionMetric(DOMDocument $document, string $loadId): void
    {
        $xpath = $this->xpath($document);
        $contextMetrics = $xpath->query('//pkp:metrics/pkp:context_metrics')->item(0);
        $submissionMetrics = $xpath->query('//pkp:metrics/pkp:submission_metrics')->item(0);
        $this->assertInstanceOf(DOMElement::class, $contextMetrics);
        $this->assertInstanceOf(DOMElement::class, $submissionMetrics);
        $contextMetric = $document->createElementNS('http://pkp.sfu.ca', 'context_metric');
        $contextMetric->setAttribute('load_id', $loadId);
        $contextMetric->setAttribute('date', '2026-08-12');
        $contextMetric->setAttribute('metric', '5');
        $contextMetrics->appendChild($contextMetric);
        $submissionMetric = $document->createElementNS('http://pkp.sfu.ca', 'submission_metric');
        $submissionMetric->setAttribute('load_id', $loadId);
        $submissionMetric->setAttribute('submission_ref', 'missing-submission');
        $submissionMetric->setAttribute('assoc_type', (string) Application::ASSOC_TYPE_SUBMISSION);
        $submissionMetric->setAttribute('date', '2026-08-12');
        $submissionMetric->setAttribute('metric', '7');
        $submissionMetrics->appendChild($submissionMetric);
    }

    private function minimalJournalXml(string $path): string
    {
        return '<journal xmlns="http://pkp.sfu.ca" primary_locale="en" url_path="' . $path . '" '
            . 'sequence="1" source_enabled="true"><locales><locale code="en" enabled_for_ui="true" '
            . 'enabled_for_forms="true" '
            . 'form_order="1" enabled_for_submissions="true" submission_order="1"/></locales>'
            . '<context_settings><setting name="name" type="string" locale="en">Imported Journal</setting>'
            . '<setting name="contactName" type="string">Editorial Team</setting>'
            . '<setting name="contactEmail" type="string">editor@example.com</setting>'
            . '</context_settings></journal>';
    }

    private function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        return $xpath;
    }

    private function assertCompensatedFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    private function assertSafeErrors(
        FullJournalImportExportDeployment $deployment,
        string $expectedMessage
    ): void {
        $errors = json_encode($deployment->getProcessedObjectsErrors(PKPApplication::ASSOC_TYPE_NONE));
        $this->assertIsString($errors);
        $this->assertStringContainsString($expectedMessage, $errors);
        $this->assertStringNotContainsString('<journal', $errors);
        $this->assertStringNotContainsString('editor@example.com', $errors);
    }

    private function errorSummary(FullJournalImportExportDeployment $deployment): string
    {
        return (string) json_encode($deployment->getProcessedObjectsErrors(PKPApplication::ASSOC_TYPE_NONE));
    }

    private function sideEffectCounts(): array
    {
        return [
            'notifications' => DB::table('notifications')->count(),
            'email_log' => DB::table('email_log')->count(),
            'event_log' => DB::table('event_log')->count(),
        ];
    }

    private function metricTables(): array
    {
        return [
            'metrics_context',
            'metrics_submission',
            'metrics_issue',
            'metrics_counter_submission_daily',
            'metrics_counter_submission_monthly',
            'metrics_counter_submission_institution_daily',
            'metrics_counter_submission_institution_monthly',
            'metrics_submission_geo_daily',
            'metrics_submission_geo_monthly',
        ];
    }

    private function importUser(): \PKP\user\User
    {
        $user = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($user);
        return $user;
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

class CapturingRollbackDeployment extends FullJournalImportExportDeployment
{
    public array $createdFilePaths = [];

    public function recordCreatedFile(string $path): void
    {
        $this->createdFilePaths[] = $path;
        parent::recordCreatedFile($path);
    }
}
