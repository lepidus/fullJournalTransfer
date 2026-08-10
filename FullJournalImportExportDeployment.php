<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\filter\GenreNativeXmlFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\JournalNativeXmlFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\NativeXmlGenreFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\NativeXmlJournalFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\NativeXmlReferenceDataFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\NativeXmlReviewFormFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\NativeXmlSectionFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\NativeXmlUserGroupFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\PKPUserUserXmlFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\ReferenceDataNativeXmlFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\ReviewFormNativeXmlFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\SectionNativeXmlFilter;
use APP\plugins\importexport\fullJournalTransfer\filter\UserXmlPKPUserFilter;
use APP\plugins\importexport\native\NativeImportExportDeployment;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\filter\FilterGroup;
use PKP\plugins\importexport\users\PKPUserImportExportDeployment;

class FullJournalImportExportDeployment extends NativeImportExportDeployment
{
    private array $createdFiles = [];

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
        $this->runNativeImport($rootFilter, $importXml);

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
        $filter = new JournalNativeXmlFilter($this->createEntityFilterGroup('journal=>xml'));
        $context = $this->getContext();
        return $filter->process($context);
    }

    public function importContextData(DOMElement $root): void
    {
        $filter = new NativeXmlJournalFilter($this->createEntityFilterGroup('xml=>journal'));
        $filter->hydrate($root, $this->getContext());
    }

    public function createContextData(DOMElement $root): \APP\journal\Journal
    {
        $filter = new NativeXmlJournalFilter($this->createEntityFilterGroup('xml=>journal'));
        $context = $filter->handleElement($root);
        $this->setContext($context);
        return $context;
    }

    public function exportUsers(): DOMDocument
    {
        $filter = new PKPUserUserXmlFilter($this->createFilterGroup(
            'user=>full-journal-user-xml',
            'class::lib.pkp.classes.user.User[]',
            'xml::schema(lib/pkp/plugins/importexport/users/pkp-users.xsd)'
        ));
        $filter->setDeployment(new PKPUserImportExportDeployment($this->getContext(), $this->getUser()));
        $users = Repo::user()->getCollector()
            ->filterByContextIds([$this->getContext()->getId()])
            ->getMany()
            ->toArray();
        $nativeDocument = $filter->process($users);
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
        $userDeployment = new PKPUserImportExportDeployment($this->getContext(), $this->getUser());
        $userGroupFilter = new NativeXmlUserGroupFilter($this->createFilterGroup(
            'full-journal-user-group-xml=>user-group',
            'xml::schema(lib/pkp/plugins/importexport/users/pkp-users.xsd)',
            'class::lib.pkp.classes.security.UserGroup[]'
        ));
        $userGroupFilter->setDeployment($userDeployment);
        foreach ($userGroupsNode->childNodes as $userGroupNode) {
            if ($userGroupNode instanceof DOMElement && $userGroupNode->localName === 'user_group') {
                $userGroupFilter->handleElement($userGroupNode);
            }
        }
        $userFilter = new UserXmlPKPUserFilter($this->createFilterGroup(
            'full-journal-user-xml=>user',
            'xml::schema(lib/pkp/plugins/importexport/users/pkp-users.xsd)',
            'class::classes.users.User[]'
        ));
        $userFilter->setDeployment($userDeployment);
        $userFilter->setUserGroupIdMap($userGroupFilter->getUserGroupIdMap());
        foreach ($userListNode->childNodes as $userNode) {
            if ($userNode instanceof DOMElement && $userNode->localName === 'user') {
                $userFilter->parseUser($userNode);
            }
        }
        return [
            'user_id_map' => $userFilter->getUserIdMap(),
            'user_group_id_map' => $userGroupFilter->getUserGroupIdMap(),
            'conflicts' => $userFilter->getConflicts(),
        ];
    }

    public function exportReferenceData(): DOMDocument
    {
        $filter = new ReferenceDataNativeXmlFilter(
            $this->createEntityFilterGroup('reference-data=>xml'),
            new ReviewFormNativeXmlFilter($this->createEntityFilterGroup('review-form=>xml')),
            new GenreNativeXmlFilter($this->createEntityFilterGroup('genre=>xml')),
            new SectionNativeXmlFilter($this->createEntityFilterGroup('section=>xml'))
        );
        $context = $this->getContext();
        return $filter->process($context);
    }

    public function importReferenceData(DOMElement $referenceDataNode): array
    {
        $filter = new NativeXmlReferenceDataFilter(
            $this->createEntityFilterGroup('xml=>reference-data'),
            new NativeXmlReviewFormFilter($this->createEntityFilterGroup('xml=>review-form')),
            new NativeXmlGenreFilter($this->createEntityFilterGroup('xml=>genre')),
            new NativeXmlSectionFilter($this->createEntityFilterGroup('xml=>section')),
            new PackageReferenceValidator(),
            new DefaultContextDataCleaner()
        );
        return $filter->importAll($referenceDataNode, $this->getContext());
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

    private function createFilterGroup(string $symbolic, string $inputType, string $outputType): FilterGroup
    {
        $group = new FilterGroup();
        $group->setSymbolic($symbolic);
        $group->setInputType($inputType);
        $group->setOutputType($outputType);
        return $group;
    }

    private function createEntityFilterGroup(string $symbolic): FilterGroup
    {
        return $this->createFilterGroup($symbolic, 'mixed', 'mixed');
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
}
