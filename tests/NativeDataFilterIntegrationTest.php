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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\observers\events\BatchMetadataChanged;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\DatabaseTestCase;
use PKP\userGroup\UserGroup;

class NativeDataFilterIntegrationTest extends DatabaseTestCase
{
    private array $contexts = [];
    private array $fileIds = [];
    private array $issueIds = [];
    private array $userGroups = [];

    protected function getAffectedTables()
    {
        return [];
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->contexts) as $context) {
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
        foreach (array_reverse($this->userGroups) as $userGroup) {
            Repo::userGroup()->delete($userGroup);
        }
        foreach (array_unique($this->fileIds) as $fileId) {
            if (Services::get('file')->get($fileId)) {
                Services::get('file')->delete($fileId);
            }
        }
        parent::tearDown();
    }

    public function testItTransfersIssuesCurrentIssueAndCustomOrderingWithTypedIds(): void
    {
        $source = $this->createContext('source');
        $destination = $this->createContext('destination');
        $firstIssue = $this->createIssue($source, 1, 'First issue');
        $secondIssue = $this->createIssue($source, 2, 'Second issue');
        Repo::issue()->dao->insertCustomIssueOrder((int) $source->getId(), (int) $firstIssue->getId(), 2);
        Repo::issue()->dao->insertCustomIssueOrder((int) $source->getId(), (int) $secondIssue->getId(), 1);
        Repo::issue()->updateCurrent((int) $source->getId(), $secondIssue);

        $document = (new FullJournalImportExportDeployment($source, null))->exportNativeData();
        $maps = (new FullJournalImportExportDeployment($destination, null))->importNativeData(
            $document->documentElement
        );

        $firstImportedId = $maps['issue_id_map'][(string) $firstIssue->getId()];
        $secondImportedId = $maps['issue_id_map'][(string) $secondIssue->getId()];
        $this->assertSame(2, Repo::issue()->dao->getCustomIssueOrder((int) $destination->getId(), $firstImportedId));
        $this->assertSame(1, Repo::issue()->dao->getCustomIssueOrder((int) $destination->getId(), $secondImportedId));
        $this->assertSame($secondImportedId, Repo::issue()->getCurrent((int) $destination->getId())->getId());
        $this->assertSame('First issue', Repo::issue()->get($firstImportedId)->getTitle('en'));
        $this->assertSame([], $maps['submission_id_map']);
    }

    public function testItTransfersSubmissionsInsideAndOutsideIssuesWithAllPublications(): void
    {
        Event::fake([BatchMetadataChanged::class]);
        Queue::fake();
        $source = $this->createContext('content-source');
        $destination = $this->createContext('content-destination');
        $section = $this->createSection($source);
        $this->createSection($destination);
        $issue = $this->createIssue($source, 3, 'Content issue');
        $scheduled = $this->createSubmission($source, $section, 'Scheduled article', $issue->getId());
        $firstPublication = $scheduled->getCurrentPublication();
        $secondPublication = Repo::publication()->newDataObject();
        $secondPublication->setData('submissionId', $scheduled->getId());
        $secondPublication->setData('sectionId', $section->getId());
        $secondPublication->setData('issueId', $issue->getId());
        $secondPublication->setData('locale', 'en');
        $secondPublication->setData('title', ['en' => 'Scheduled article revised']);
        $secondPublication->setData('status', \APP\submission\Submission::STATUS_SCHEDULED);
        $secondPublication->setData('accessStatus', 0);
        $secondPublication->setData('version', 2);
        $secondPublication->setData('pages', '1-10');
        $secondPublication->stampModified();
        $secondPublicationId = Repo::publication()->dao->insert($secondPublication);
        Repo::submission()->edit($scheduled, ['currentPublicationId' => $secondPublicationId]);
        $unassigned = $this->createSubmission($source, $section, 'Unassigned article', null);

        $this->setRequestContext($source);
        $document = (new FullJournalImportExportDeployment($source, null))->exportNativeData();
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $this->assertSame(0, $xpath->query('//pkp:issue_record|//pkp:submission_record')->length);
        $this->assertSame(1, $xpath->query('/pkp:native_data/pkp:issues/pkp:issue/pkp:articles/pkp:article')->length);
        $this->assertSame(1, $xpath->query('/pkp:native_data/pkp:articles/pkp:article')->length);
        $this->assertSame(1, $xpath->query(
            '/pkp:native_data/pkp:issues/pkp:issue/pkp:articles/pkp:article/pkp:id[@type="internal" and text()="'
            . $scheduled->getId() . '"]'
        )->length);
        $this->assertSame(1, $xpath->query(
            '/pkp:native_data/pkp:articles/pkp:article/pkp:id[@type="internal" and text()="'
            . $unassigned->getId() . '"]'
        )->length);
        $this->assertSame(0, $xpath->query('//pkp:publication[@issue_ref]')->length);
        $this->assertSame(2, $xpath->query('//pkp:publication/pkp:issue_identification')->length);
        $this->setRequestContext($destination);
        $maps = (new FullJournalImportExportDeployment($destination, null))->importNativeData(
            $document->documentElement
        );

        $scheduledImported = Repo::submission()->get($maps['submission_id_map'][(string) $scheduled->getId()]);
        $unassignedImported = Repo::submission()->get($maps['submission_id_map'][(string) $unassigned->getId()]);
        $issueImportedId = $maps['issue_id_map'][(string) $issue->getId()];
        $this->assertCount(2, $scheduledImported->getData('publications'));
        $this->assertSame(
            $maps['publication_id_map'][(string) $secondPublicationId],
            $scheduledImported->getCurrentPublication()->getId()
        );
        $this->assertSame($issueImportedId, $scheduledImported->getCurrentPublication()->getData('issueId'));
        $this->assertSame('1-10', $scheduledImported->getCurrentPublication()->getData('pages'));
        $this->assertNull($unassignedImported->getCurrentPublication()->getData('issueId'));
        $this->assertArrayHasKey((string) $firstPublication->getId(), $maps['publication_id_map']);
        $this->assertSame('Unassigned article', $unassignedImported->getCurrentPublication()->getData('title', 'en'));
    }

    public function testItTransfersAuthorsGalleysAndFileRevisionsWithChecksumsAndTypedIds(): void
    {
        Event::fake([BatchMetadataChanged::class]);
        Queue::fake();
        $source = $this->createContext('assets-source');
        $destination = $this->createContext('assets-destination');
        $sourceSection = $this->createSection($source);
        $this->createSection($destination);
        $groupName = 'Transfer Authors ' . bin2hex(random_bytes(4));
        $sourceGroup = $this->createUserGroup($source, $groupName);
        $this->createUserGroup($destination, $groupName);
        $sourceGenre = $this->createGenre($source, 'Transfer Manuscript');
        $this->createGenre($destination, 'Transfer Manuscript');
        $submission = $this->createSubmission($source, $sourceSection, 'Article with assets', null);
        $publication = $submission->getCurrentPublication();
        $author = Repo::author()->newDataObject();
        $author->setData('publicationId', $publication->getId());
        $author->setData('userGroupId', $sourceGroup->getId());
        $author->setData('givenName', ['en' => 'Ada']);
        $author->setData('familyName', ['en' => 'Lovelace']);
        $author->setData('email', 'ada@example.com');
        $author->setData('seq', 1);
        $author->setData('includeInBrowse', true);
        $authorId = Repo::author()->add($author);
        $submissionFile = $this->createSubmissionFile(
            $source,
            $submission,
            (int) $sourceGenre->getId(),
            ['first revision', 'second revision']
        );
        $galley = Repo::galley()->newDataObject();
        $galley->setData('publicationId', $publication->getId());
        $galley->setData('label', 'Text');
        $galley->setData('locale', 'en');
        $galley->setData('seq', 1);
        $galley->setData('submissionFileId', $submissionFile->getId());
        $galley->setData('isApproved', true);
        $galleyId = Repo::galley()->dao->insert($galley);

        $this->setRequestContext($source);
        $document = (new FullJournalImportExportDeployment($source, null))->exportNativeData();
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $this->assertSame(0, $xpath->query('//pkp:submission_file/pkp:file[@checksum]')->length);
        $this->assertSame(2, $xpath->query('//pkp:submission_file/pkp:file/pkp:href')->length);
        $this->assertSame(0, $xpath->query('//pkp:submission_file/pkp:file/pkp:embed')->length);
        $this->setRequestContext($destination);
        $importUser = Repo::user()->getCollector()->getMany()->first();
        $this->assertNotNull($importUser);
        $deployment = new FullJournalImportExportDeployment($destination, $importUser);
        $deployment->setImportPath((string) Config::getVar('files', 'files_dir'));
        $maps = $deployment->importNativeData($document->documentElement);
        $this->fileIds = array_merge($this->fileIds, array_values($maps['file_id_map']));

        $importedSubmissionFile = Repo::submissionFile()->get(
            $maps['submission_file_id_map'][(string) $submissionFile->getId()]
        );
        $importedAuthor = Repo::author()->get($maps['author_id_map'][(string) $authorId]);
        $importedGalley = Repo::galley()->get($maps['article_galley_id_map'][(string) $galleyId]);
        $sourceRevisions = Repo::submissionFile()->getRevisions((int) $submissionFile->getId());
        $importedRevisions = Repo::submissionFile()->getRevisions((int) $importedSubmissionFile->getId());
        $this->assertSame('Ada', $importedAuthor->getGivenName('en'));
        $this->assertSame('Lovelace', $importedAuthor->getFamilyName('en'));
        $this->assertSame($importedSubmissionFile->getId(), $importedGalley->getData('submissionFileId'));
        $this->assertSame(SubmissionFile::SUBMISSION_FILE_SUBMISSION, $importedSubmissionFile->getFileStage());
        $this->assertCount(2, $importedRevisions);
        foreach ($sourceRevisions as $sourceRevision) {
            $destinationFileId = $maps['file_id_map'][(string) $sourceRevision->fileId];
            $this->assertSame(
                $this->fileChecksum((int) $sourceRevision->fileId),
                $this->fileChecksum($destinationFileId)
            );
        }
    }

    public function testItTransfersIssueGalleyFileWithChecksumAndTypedId(): void
    {
        $source = $this->createContext('issue-galley-source');
        $destination = $this->createContext('issue-galley-destination');
        $issue = $this->createIssue($source, 4, 'Issue with galley');
        [$galleyId, $sourcePath] = $this->createIssueGalley($issue, 'issue galley payload');

        $document = (new FullJournalImportExportDeployment($source, null))->exportNativeData();
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $this->assertSame(0, $xpath->query('//pkp:issue_galley/pkp:issue_file[@checksum]')->length);
        $maps = (new FullJournalImportExportDeployment($destination, null))->importNativeData(
            $document->documentElement
        );
        $importedIssueId = $maps['issue_id_map'][(string) $issue->getId()];
        $this->issueIds[] = $importedIssueId;
        $importedGalley = DAORegistry::getDAO('IssueGalleyDAO')->getById(
            $maps['issue_galley_id_map'][(string) $galleyId]
        );
        $importedFile = DAORegistry::getDAO('IssueFileDAO')->getById($importedGalley->getFileId());
        $manager = new IssueFileManager($importedIssueId);
        $importedPath = $manager->getFilesDir() . $manager->contentTypeToPath($importedFile->getContentType())
            . DIRECTORY_SEPARATOR . $importedFile->getServerFileName();
        $this->assertFileExists($importedPath);
        $this->assertSame(hash_file('sha256', $sourcePath), hash_file('sha256', $importedPath));
        $this->assertSame('PDF', $importedGalley->getLabel());
    }

    public function testItComposesReferenceAndNativeEntityFiltersAtTheJournalRoot(): void
    {
        Event::fake([BatchMetadataChanged::class]);
        Queue::fake();
        $source = $this->createContext('root-source');
        $section = $this->createSection($source);
        $issue = $this->createIssue($source, 5, 'Root issue');
        $this->createSubmission($source, $section, 'Root article', $issue->getId());
        $this->setRequestContext($source);

        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $this->assertSame(1, $xpath->query('/pkp:journal/pkp:users')->length);
        $this->assertSame(1, $xpath->query('/pkp:journal/pkp:reference_data')->length);
        $this->assertSame(1, $xpath->query('/pkp:journal/pkp:native_data')->length);
        $this->assertTrue($document->schemaValidate(dirname(__DIR__) . '/fullJournal.xsd'));
        $document->documentElement->setAttribute('url_path', 'root-imported-' . bin2hex(random_bytes(4)));
        $deployment = new FullJournalImportExportDeployment($source, null);
        $created = $deployment->createContextData($document->documentElement);
        $this->contexts[] = $created;

        $this->assertCount(
            1,
            Repo::section()->getCollector()->filterByContextIds([(int) $created->getId()])->getMany()->toArray()
        );
        $this->assertCount(
            1,
            Repo::issue()->getCollector()->filterByContextIds([(int) $created->getId()])->getMany()->toArray()
        );
        $this->assertCount(
            1,
            Repo::submission()->getCollector()->filterByContextIds([(int) $created->getId()])->getMany()->toArray()
        );
    }

    private function createContext(string $label): Journal
    {
        $context = Application::get()->getContextDAO()->newDataObject();
        $context->setPath(
            'native-' . substr((string) preg_replace('/[^a-z0-9]+/', '', $label), 0, 5)
                . '-' . bin2hex(random_bytes(4))
        );
        $context->setPrimaryLocale('en');
        $context->setEnabled(false);
        $context->setSequence(1);
        $context->setData('supportedLocales', ['en']);
        $context->setData('supportedFormLocales', ['en']);
        $context->setData('supportedSubmissionLocales', ['en']);
        $context->setData('name', ['en' => 'Native Data Test Journal']);
        $context->setData('contactName', 'Editorial Team');
        $context->setData('contactEmail', 'editor@example.com');
        Application::get()->getContextDAO()->insertObject($context);
        $this->contexts[] = $context;
        return $context;
    }

    private function createIssue(Journal $context, int $number, string $title): \APP\issue\Issue
    {
        $issue = Repo::issue()->newDataObject();
        $issue->setJournalId((int) $context->getId());
        $issue->setVolume(1);
        $issue->setNumber((string) $number);
        $issue->setYear(2026);
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
        string $title,
        ?int $issueId
    ): \APP\submission\Submission {
        $submission = Repo::submission()->newDataObject();
        $submission->setData('contextId', (int) $context->getId());
        $submission->setData('locale', 'en');
        $submission->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
        $submission->setData('status', \APP\submission\Submission::STATUS_QUEUED);
        $submission->setData('submissionProgress', '');
        $publication = Repo::publication()->newDataObject();
        $publication->setData('sectionId', $section->getId());
        $publication->setData('issueId', $issueId);
        $publication->setData('locale', 'en');
        $publication->setData('title', ['en' => $title]);
        $publication->setData('status', \APP\submission\Submission::STATUS_QUEUED);
        $publication->setData('accessStatus', 0);
        $submissionId = Repo::submission()->add($submission, $publication, $context);
        return Repo::submission()->get($submissionId);
    }

    private function createUserGroup(Journal $context, string $name): UserGroup
    {
        $group = Repo::userGroup()->newDataObject();
        $group->setContextId((int) $context->getId());
        $group->setRoleId(Role::ROLE_ID_AUTHOR);
        $group->setDefault(false);
        $group->setShowTitle(true);
        $group->setPermitSelfRegistration(false);
        $group->setPermitMetadataEdit(false);
        $group->setName($name, 'en');
        $group->setAbbrev('TA', 'en');
        Repo::userGroup()->add($group);
        $this->userGroups[] = $group;
        return $group;
    }

    private function createGenre(Journal $context, string $name)
    {
        $genre = DAORegistry::getDAO('GenreDAO')->newDataObject();
        $genre->setContextId((int) $context->getId());
        $genre->setKey('TRANSFER_' . bin2hex(random_bytes(4)));
        $genre->setCategory(1);
        $genre->setDependent(false);
        $genre->setSupplementary(false);
        $genre->setRequired(true);
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
        array $contents
    ): SubmissionFile {
        $fileIds = [];
        foreach ($contents as $index => $content) {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'full-journal-test-');
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

    private function fileChecksum(int $fileId): string
    {
        $file = Services::get('file')->get($fileId);
        $path = rtrim((string) Config::getVar('files', 'files_dir'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $file->path;
        return (string) hash_file('sha256', $path);
    }

    private function createIssueGalley(\APP\issue\Issue $issue, string $content): array
    {
        $manager = new IssueFileManager((int) $issue->getId());
        $fileName = 'galley-' . bin2hex(random_bytes(4)) . '.pdf';
        $path = $manager->getFilesDir() . 'public' . DIRECTORY_SEPARATOR . $fileName;
        $manager->mkdirtree(dirname($path));
        $manager->writeFile($path, $content);
        $fileDao = DAORegistry::getDAO('IssueFileDAO');
        $file = $fileDao->newDataObject();
        $file->setIssueId((int) $issue->getId());
        $file->setServerFileName($fileName);
        $file->setFileType('application/pdf');
        $file->setFileSize(strlen($content));
        $file->setContentType(IssueFile::ISSUE_FILE_PUBLIC);
        $file->setOriginalFileName('issue.pdf');
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
        $galleyId = $galleyDao->insertObject($galley);
        return [$galleyId, $path];
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
