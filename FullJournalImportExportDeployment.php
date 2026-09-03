<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\plugins\importexport\fullJournalTransfer\filter\NativeXmlNativeDataFilter;
use APP\plugins\importexport\fullJournalTransfer\package\ArchiveManager;
use APP\plugins\importexport\fullJournalTransfer\persistence\WorkflowUserFinder;
use APP\plugins\importexport\fullJournalTransfer\transfer\ImportedResourceJournal;
use APP\plugins\importexport\fullJournalTransfer\transfer\TransferState;
use APP\plugins\importexport\native\NativeImportExportDeployment;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\PKPImportExportFilter;
use PKP\site\Site;
use PKP\submissionFile\SubmissionFile;
use PKP\user\Collector;
use Throwable;

class FullJournalImportExportDeployment extends NativeImportExportDeployment
{
    private ?ImportedResourceJournal $resourceJournal = null;
    private ?TransferState $transferState = null;
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
                    $this->warnAboutUnavailableLocales($journalXml, $progress);
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

    private function warnAboutUnavailableLocales(string $journalXml, ?callable $progress): void
    {
        if (!$progress) {
            return;
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        if (!$document->loadXML($journalXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return;
        }
        $root = $document->documentElement;
        if (!$root instanceof DOMElement) {
            return;
        }
        $sourceLocales = [];
        foreach ($root->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->localName !== 'locales') {
                continue;
            }
            foreach ($child->childNodes as $localeNode) {
                if ($localeNode instanceof DOMElement && $localeNode->localName === 'locale') {
                    $sourceLocales[] = trim($localeNode->getAttribute('code'));
                }
            }
        }
        $site = $this->getSite();
        if (!$site instanceof Site) {
            return;
        }
        $unavailableLocales = array_values(array_diff(array_unique($sourceLocales), $site->getSupportedLocales()));
        if ($unavailableLocales === []) {
            return;
        }
        $progress(
            'Warning: To migrate all localized metadata, the locales used by the source journal must be installed '
                . 'and enabled in the destination OJS site before import. Metadata in the following unavailable '
                . 'locales will not be imported: ' . implode(', ', $unavailableLocales) . '.'
        );
    }

    public function import($rootFilter, $importXml)
    {
        $this->resourceJournal()->reset();
        $this->historicalDatesNode = null;
        try {
            $this->runNativeImport($rootFilter, $importXml);
        } catch (Throwable $exception) {
            $this->compensateCreatedResources();
            throw $exception;
        }

        if ($this->isProcessFailed()) {
            $this->compensateCreatedResources();
        } else {
            $this->resourceJournal()->reset();
        }
    }

    public function recordCreatedFile(string $path): void
    {
        $this->resourceJournal()->recordFile($path);
    }

    public function recordCreatedDirectory(string $path): void
    {
        $this->resourceJournal()->recordDirectory($path);
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
        $workflowUserIds = (new WorkflowUserFinder())->findIds((int) $this->getContext()->getId());
        if ($workflowUserIds !== []) {
            $workflowUsers = Repo::user()->getCollector()
                ->filterByUserIds($workflowUserIds)
                ->filterByStatus(Collector::STATUS_ALL)
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

    public function importUsers(DOMElement $usersNode): array
    {
        $userGroupsNode = $this->getRequiredChild($usersNode, 'user_groups');
        $userListNode = $this->getRequiredChild($usersNode, 'users');
        $this->resetReferenceMap('user_group');
        $this->resetReferenceMap('user');
        $this->transferState()->resetUserConflicts();
        $userGroupFilter = PKPImportExportFilter::getFilter('full-journal-user-xml=>user-group', $this);
        $userGroupsDocument = $this->documentFor($userGroupsNode);
        $userGroupFilter->execute($userGroupsDocument);
        $userFilter = PKPImportExportFilter::getFilter('full-journal-user-xml=>user', $this);
        $usersDocument = $this->documentFor($userListNode);
        $userFilter->execute($usersDocument);
        return [
            'user_id_map' => $this->getReferenceMap('user'),
            'user_group_id_map' => $this->getReferenceMap('user_group'),
            'conflicts' => $this->getUserConflicts(),
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
        $this->transferState()->resetMetricRejections();
        $filter = PKPImportExportFilter::getFilter('full-journal-xml=>metrics', $this);
        $document = $this->documentFor($metricsNode);
        $filter->execute($document);
    }

    public function addMetricRejection(array $rejection): void
    {
        $this->transferState()->addMetricRejection($rejection);
    }

    public function getMetricRejections(): array
    {
        return $this->transferState()->getMetricRejections();
    }

    public function mapReference(string $entity, string $sourceReference, int $destinationId): void
    {
        $this->transferState()->mapReference($entity, $sourceReference, $destinationId);
    }

    public function getReferenceMap(string $entity): array
    {
        return $this->transferState()->getReferenceMap($entity);
    }

    public function resetReferenceMap(string $entity): void
    {
        $this->transferState()->resetReferenceMap($entity);
    }

    public function requireReference(string $entity, string $sourceReference): int
    {
        return $this->transferState()->requireReference($entity, $sourceReference);
    }

    public function addUserConflict(array $conflict): void
    {
        $this->transferState()->addUserConflict($conflict);
    }

    public function getUserConflicts(): array
    {
        return $this->transferState()->getUserConflicts();
    }

    public function getSite()
    {
        return Application::get()->getRequest()->getSite();
    }

    public function setCurrentReviewFormId(?int $reviewFormId): void
    {
        $this->transferState()->setCurrentReviewFormId($reviewFormId);
    }

    public function getCurrentReviewFormId(): ?int
    {
        return $this->transferState()->getCurrentReviewFormId();
    }

    public function setSubmissionsByIssue(array $submissionsByIssue): void
    {
        $this->transferState()->setSubmissionsByIssue($submissionsByIssue);
    }

    public function getSubmissionsForIssue(int $issueId): array
    {
        return $this->transferState()->getSubmissionsForIssue($issueId);
    }

    private function transferState(): TransferState
    {
        if (!$this->transferState) {
            $this->transferState = new TransferState();
        }
        return $this->transferState;
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

    private function compensateCreatedResources(): void
    {
        $publicFileManager = new PublicFileManager();
        $errors = $this->resourceJournal()->compensate(function (string $path) use ($publicFileManager): bool {
            return $publicFileManager->rmtree($path);
        });
        foreach ($errors as $error) {
            $this->addError(\PKP\core\PKPApplication::ASSOC_TYPE_NONE, 0, $error);
        }
    }

    private function resourceJournal(): ImportedResourceJournal
    {
        if (!$this->resourceJournal) {
            $this->resourceJournal = new ImportedResourceJournal();
        }
        return $this->resourceJournal;
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
