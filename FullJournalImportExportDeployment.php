<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\plugins\importexport\fullJournalTransfer\filter\NativeXmlNativeDataFilter;
use APP\plugins\importexport\native\NativeImportExportDeployment;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\PKPImportExportFilter;
use PKP\submissionFile\SubmissionFile;
use Throwable;

class FullJournalImportExportDeployment extends NativeImportExportDeployment
{
    private array $createdFiles = [];
    private array $createdDirectories = [];
    private array $referenceMaps = [];
    private array $userConflicts = [];
    private ?int $currentReviewFormId = null;
    private array $submissionsByIssue = [];
    private array $metricRejections = [];
    private ?DOMElement $historicalDatesNode = null;

    public function getStageNameStageIdMapping()
    {
        return array_merge(parent::getStageNameStageIdMapping(), [
            'internal_review_file' => SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
            'internal_review_revision' => SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
        ]);
    }

    public function importPackage(
        string $archivePath,
        string $applicationVersion,
        string $rootFilter,
        ?ArchiveManager $archiveManager = null,
        ?callable $progress = null
    ): bool {
        $archiveManager = $archiveManager ?? new ArchiveManager();

        return $archiveManager->withExtractedPackage(
            $archivePath,
            $applicationVersion,
            function (string $stagingPath) use ($rootFilter, $progress): bool {
                $journalXml = file_get_contents($stagingPath . DIRECTORY_SEPARATOR . 'journal.xml');
                if ($journalXml === false) {
                    throw new \RuntimeException('The journal XML could not be read');
                }

                $this->setImportPath($stagingPath);
                try {
                    if ($progress) {
                        $progress('Importing journal data...');
                    }
                    $this->import($rootFilter, $journalXml);
                    return !$this->isProcessFailed();
                } finally {
                    $this->setImportPath('');
                }
            },
            $progress
        );
    }

    public function import($rootFilter, $importXml)
    {
        $this->createdFiles = [];
        $this->createdDirectories = [];
        $this->historicalDatesNode = null;
        try {
            $this->runNativeImport($rootFilter, $importXml);
        } catch (Throwable $exception) {
            $this->compensateCreatedFiles();
            $this->compensateCreatedDirectories();
            throw $exception;
        }

        if ($this->isProcessFailed()) {
            $this->compensateCreatedFiles();
            $this->compensateCreatedDirectories();
        } else {
            $this->createdFiles = [];
            $this->createdDirectories = [];
        }
    }

    public function recordCreatedFile(string $path): void
    {
        if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR) {
            throw new \InvalidArgumentException('A created file journal entry must use an absolute path');
        }
        $this->createdFiles[] = $path;
    }

    public function recordCreatedDirectory(string $path): void
    {
        if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR) {
            throw new \InvalidArgumentException('A created directory journal entry must use an absolute path');
        }
        $this->createdDirectories[] = $path;
    }

    public function exportContextData(): DOMDocument
    {
        $filter = PKPImportExportFilter::getFilter('journal=>full-journal-xml', $this);
        $context = $this->getContext();
        return $filter->execute($context);
    }

    public function importContextData(DOMElement $root): void
    {
        $filter = PKPImportExportFilter::getFilter('full-journal-xml=>journal', $this);
        $filter->hydrate($root, $this->getContext());
    }

    public function createContextData(DOMElement $root): \APP\journal\Journal
    {
        $filter = PKPImportExportFilter::getFilter('full-journal-xml=>journal', $this);
        $context = $filter->handleElement($root);
        $this->setContext($context);
        return $context;
    }

    public function exportUsers(): DOMDocument
    {
        $filter = PKPImportExportFilter::getFilter('user=>full-journal-user-xml', $this);
        $usersById = [];
        $contextUsers = Repo::user()->getCollector()
            ->filterByContextIds([$this->getContext()->getId()])
            ->getMany();
        foreach ($contextUsers as $user) {
            $usersById[(int) $user->getId()] = $user;
        }
        $workflowUserIds = $this->getWorkflowUserIds((int) $this->getContext()->getId());
        if ($workflowUserIds !== []) {
            $workflowUsers = Repo::user()->getCollector()
                ->filterByUserIds($workflowUserIds)
                ->getMany();
            foreach ($workflowUsers as $user) {
                $usersById[(int) $user->getId()] = $user;
            }
        }
        $missingUserIds = array_diff($workflowUserIds, array_keys($usersById));
        if ($missingUserIds !== []) {
            throw new InvalidArgumentException('Workflow references a missing user: ' . reset($missingUserIds));
        }
        ksort($usersById, SORT_NUMERIC);
        $users = array_values($usersById);
        $nativeDocument = $filter->execute($users);
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS($this->getNamespace(), 'users');
        $document->appendChild($root);
        foreach ($nativeDocument->documentElement->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $root->appendChild($document->importNode($child, true));
            }
        }
        return $document;
    }

    private function getWorkflowUserIds(int $contextId): array
    {
        $userIds = [];
        $queries = [
            DB::table('stage_assignments as assignment')
                ->join('submissions as submission', 'submission.submission_id', '=', 'assignment.submission_id')
                ->where('submission.context_id', $contextId)
                ->pluck('assignment.user_id'),
            DB::table('review_assignments as assignment')
                ->join('review_rounds as round', function ($join): void {
                    $join->on('round.review_round_id', '=', 'assignment.review_round_id')
                        ->on('round.submission_id', '=', 'assignment.submission_id');
                })
                ->join('submissions as submission', 'submission.submission_id', '=', 'round.submission_id')
                ->where('submission.context_id', $contextId)
                ->pluck('assignment.reviewer_id'),
            DB::table('submission_comments as comment')
                ->join('review_assignments as assignment', function ($join): void {
                    $join->on('assignment.review_id', '=', 'comment.assoc_id')
                        ->on('assignment.submission_id', '=', 'comment.submission_id');
                })
                ->join('review_rounds as round', function ($join): void {
                    $join->on('round.review_round_id', '=', 'assignment.review_round_id')
                        ->on('round.submission_id', '=', 'assignment.submission_id');
                })
                ->join('submissions as submission', 'submission.submission_id', '=', 'round.submission_id')
                ->where('comment.comment_type', 1)
                ->where('submission.context_id', $contextId)
                ->pluck('comment.author_id'),
            DB::table('query_participants as participant')
                ->join('queries as discussion', 'discussion.query_id', '=', 'participant.query_id')
                ->join('submissions as submission', 'submission.submission_id', '=', 'discussion.assoc_id')
                ->where('discussion.assoc_type', Application::ASSOC_TYPE_SUBMISSION)
                ->where('submission.context_id', $contextId)
                ->pluck('participant.user_id'),
            DB::table('notes as note')
                ->join('queries as discussion', 'discussion.query_id', '=', 'note.assoc_id')
                ->join('submissions as submission', 'submission.submission_id', '=', 'discussion.assoc_id')
                ->where('note.assoc_type', Application::ASSOC_TYPE_QUERY)
                ->where('discussion.assoc_type', Application::ASSOC_TYPE_SUBMISSION)
                ->where('submission.context_id', $contextId)
                ->pluck('note.user_id'),
            DB::table('edit_decisions as decision')
                ->join('submissions as submission', 'submission.submission_id', '=', 'decision.submission_id')
                ->where('submission.context_id', $contextId)
                ->pluck('decision.editor_id'),
        ];
        foreach ($queries as $queryUserIds) {
            foreach ($queryUserIds as $userId) {
                if ((int) $userId > 0) {
                    $userIds[(int) $userId] = true;
                }
            }
        }
        $userIds = array_keys($userIds);
        sort($userIds, SORT_NUMERIC);
        return $userIds;
    }

    public function importUsers(DOMElement $usersNode): array
    {
        $userGroupsNode = $this->getRequiredChild($usersNode, 'user_groups');
        $userListNode = $this->getRequiredChild($usersNode, 'users');
        $this->referenceMaps['user_group'] = [];
        $this->referenceMaps['user'] = [];
        $this->userConflicts = [];
        $userGroupFilter = PKPImportExportFilter::getFilter('full-journal-user-xml=>user-group', $this);
        $userGroupsDocument = $this->documentFor($userGroupsNode);
        $userGroupFilter->execute($userGroupsDocument);
        $userFilter = PKPImportExportFilter::getFilter('full-journal-user-xml=>user', $this);
        $usersDocument = $this->documentFor($userListNode);
        $userFilter->execute($usersDocument);
        return [
            'user_id_map' => $this->getReferenceMap('user'),
            'user_group_id_map' => $this->getReferenceMap('user_group'),
            'conflicts' => $this->userConflicts,
        ];
    }

    public function exportReferenceData(): DOMDocument
    {
        $filter = PKPImportExportFilter::getFilter('reference-data=>full-journal-xml', $this);
        $context = $this->getContext();
        return $filter->execute($context);
    }

    public function importReferenceData(DOMElement $referenceDataNode): array
    {
        foreach (['review_form', 'review_form_element', 'genre', 'section'] as $entity) {
            $this->resetReferenceMap($entity);
        }
        $filter = PKPImportExportFilter::getFilter('full-journal-xml=>reference-data', $this);
        $document = $this->documentFor($referenceDataNode);
        $filter->execute($document);
        return [
            'review_form_id_map' => $this->getReferenceMap('review_form'),
            'review_form_element_id_map' => $this->getReferenceMap('review_form_element'),
            'genre_id_map' => $this->getReferenceMap('genre'),
            'section_id_map' => $this->getReferenceMap('section'),
        ];
    }

    public function exportNativeData(): DOMDocument
    {
        $filter = PKPImportExportFilter::getFilter('native-data=>full-journal-xml', $this);
        $context = $this->getContext();
        return $filter->execute($context);
    }

    public function importNativeData(DOMElement $nativeDataNode): array
    {
        $this->historicalDatesNode = null;
        $filter = PKPImportExportFilter::getFilter('full-journal-xml=>native-data', $this);
        $document = $this->documentFor($nativeDataNode);
        $filter->execute($document);
        foreach ($document->documentElement->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'historical_dates') {
                $this->historicalDatesNode = $child;
                break;
            }
        }
        return [
            'issue_id_map' => $this->getReferenceMap('issue'),
            'submission_id_map' => $this->getReferenceMap('submission'),
            'publication_id_map' => $this->getReferenceMap('publication'),
            'author_id_map' => $this->getReferenceMap('author'),
            'issue_galley_id_map' => $this->getReferenceMap('issue_galley'),
            'article_galley_id_map' => $this->getReferenceMap('article_galley'),
            'submission_file_id_map' => $this->getReferenceMap('submission_file'),
            'file_id_map' => $this->getReferenceMap('file'),
        ];
    }

    public function exportWorkflow(): DOMDocument
    {
        $filter = PKPImportExportFilter::getFilter('workflow=>full-journal-xml', $this);
        $context = $this->getContext();
        return $filter->execute($context);
    }

    public function importWorkflow(DOMElement $workflowNode): array
    {
        $filter = PKPImportExportFilter::getFilter('full-journal-xml=>workflow', $this);
        $document = $this->documentFor($workflowNode);
        $filter->execute($document);
        $this->restoreHistoricalDates();
        return [
            'stage_assignment_id_map' => $this->getReferenceMap('stage_assignment'),
            'review_round_id_map' => $this->getReferenceMap('review_round'),
            'review_assignment_id_map' => $this->getReferenceMap('review_assignment'),
            'discussion_id_map' => $this->getReferenceMap('discussion'),
            'discussion_note_id_map' => $this->getReferenceMap('discussion_note'),
            'discussion_attachment_id_map' => $this->getReferenceMap('discussion_attachment'),
            'editorial_decision_id_map' => $this->getReferenceMap('editorial_decision'),
        ];
    }

    private function restoreHistoricalDates(): void
    {
        if (!$this->historicalDatesNode) {
            return;
        }
        $filter = PKPImportExportFilter::getFilter('full-journal-xml=>native-data', $this);
        if (!$filter instanceof NativeXmlNativeDataFilter) {
            throw new InvalidArgumentException('The native data filter cannot restore historical dates');
        }
        $filter->restoreHistoricalDates($this->historicalDatesNode);
    }

    public function exportMetrics(): DOMDocument
    {
        $filter = PKPImportExportFilter::getFilter('metrics=>full-journal-xml', $this);
        $context = $this->getContext();
        return $filter->execute($context);
    }

    public function importMetrics(DOMElement $metricsNode): void
    {
        $this->metricRejections = [];
        $filter = PKPImportExportFilter::getFilter('full-journal-xml=>metrics', $this);
        $document = $this->documentFor($metricsNode);
        $filter->execute($document);
    }

    public function addMetricRejection(array $rejection): void
    {
        $this->metricRejections[] = $rejection;
    }

    public function getMetricRejections(): array
    {
        return $this->metricRejections;
    }

    public function mapReference(string $entity, string $sourceReference, int $destinationId): void
    {
        $this->referenceMaps[$entity][$sourceReference] = $destinationId;
    }

    public function getReferenceMap(string $entity): array
    {
        return $this->referenceMaps[$entity] ?? [];
    }

    public function resetReferenceMap(string $entity): void
    {
        $this->referenceMaps[$entity] = [];
    }

    public function requireReference(string $entity, string $sourceReference): int
    {
        $destinationId = $this->referenceMaps[$entity][$sourceReference] ?? null;
        if (!is_int($destinationId)) {
            throw new InvalidArgumentException(sprintf(
                'Missing mapped %s reference: "%s"',
                $entity,
                $sourceReference
            ));
        }
        return $destinationId;
    }

    public function addUserConflict(array $conflict): void
    {
        $this->userConflicts[] = $conflict;
    }

    public function getUserConflicts(): array
    {
        return $this->userConflicts;
    }

    public function getSite()
    {
        return Application::get()->getRequest()->getSite();
    }

    public function setCurrentReviewFormId(?int $reviewFormId): void
    {
        $this->currentReviewFormId = $reviewFormId;
    }

    public function getCurrentReviewFormId(): ?int
    {
        return $this->currentReviewFormId;
    }

    public function setSubmissionsByIssue(array $submissionsByIssue): void
    {
        $this->submissionsByIssue = $submissionsByIssue;
    }

    public function getSubmissionsForIssue(int $issueId): array
    {
        return $this->submissionsByIssue[$issueId] ?? [];
    }

    protected function runNativeImport($rootFilter, $importXml): void
    {
        if (is_string($importXml)) {
            $document = new DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $document->loadXML($importXml);
            $importXml = $document;
        }
        parent::import($rootFilter, $importXml);
    }

    private function compensateCreatedFiles(): void
    {
        foreach (array_reverse($this->createdFiles) as $path) {
            if ((is_file($path) || is_link($path)) && !unlink($path)) {
                $this->addError(\PKP\core\PKPApplication::ASSOC_TYPE_NONE, 0, 'Failed to compensate an imported file');
            }
        }
        $this->createdFiles = [];
    }

    private function compensateCreatedDirectories(): void
    {
        $publicFileManager = new PublicFileManager();
        foreach (array_reverse($this->createdDirectories) as $path) {
            if (is_dir($path) && !$publicFileManager->rmtree($path)) {
                $this->addError(
                    \PKP\core\PKPApplication::ASSOC_TYPE_NONE,
                    0,
                    'Failed to compensate an imported directory'
                );
            }
        }
        $this->createdDirectories = [];
    }

    private function getRequiredChild(DOMElement $parent, string $name): DOMElement
    {
        $matches = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $matches[] = $child;
            }
        }
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('Expected exactly one ' . $name . ' element');
        }
        return $matches[0];
    }

    private function documentFor(DOMElement $element): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($element, true));
        return $document;
    }
}
