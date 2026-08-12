<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\importexport\native\NativeImportExportDeployment;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\PKPImportExportFilter;
use Throwable;

class FullJournalImportExportDeployment extends NativeImportExportDeployment
{
    private array $createdFiles = [];
    private array $referenceMaps = [];
    private array $userConflicts = [];
    private ?int $currentReviewFormId = null;
    private array $submissionsByIssue = [];

    public function importPackage(
        string $archivePath,
        string $applicationVersion,
        string $rootFilter,
        ?ArchiveManager $archiveManager = null
    ): bool {
        $archiveManager = $archiveManager ?? new ArchiveManager();

        return $archiveManager->withExtractedPackage(
            $archivePath,
            $applicationVersion,
            function (string $stagingPath) use ($rootFilter): bool {
                $journalXml = file_get_contents($stagingPath . DIRECTORY_SEPARATOR . 'journal.xml');
                if ($journalXml === false) {
                    throw new \RuntimeException('The journal XML could not be read');
                }

                $this->setImportPath($stagingPath);
                try {
                    $this->import($rootFilter, $journalXml);
                    return !$this->isProcessFailed();
                } finally {
                    $this->setImportPath('');
                }
            }
        );
    }

    public function import($rootFilter, $importXml)
    {
        $this->createdFiles = [];
        try {
            $this->runNativeImport($rootFilter, $importXml);
        } catch (Throwable $exception) {
            $this->compensateCreatedFiles();
            throw $exception;
        }

        if ($this->isProcessFailed()) {
            $this->compensateCreatedFiles();
        } else {
            $this->createdFiles = [];
        }
    }

    public function recordCreatedFile(string $path): void
    {
        if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR) {
            throw new \InvalidArgumentException('A created file journal entry must use an absolute path');
        }
        $this->createdFiles[] = $path;
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
        $users = Repo::user()->getCollector()
            ->filterByContextIds([$this->getContext()->getId()])
            ->getMany()
            ->toArray();
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
        $this->referenceMaps = [];
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
        $filter = PKPImportExportFilter::getFilter('full-journal-xml=>native-data', $this);
        $document = $this->documentFor($nativeDataNode);
        $filter->execute($document);
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
            throw new InvalidArgumentException('Missing mapped ' . $entity . ' reference');
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
