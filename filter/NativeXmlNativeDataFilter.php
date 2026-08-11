<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\NativeDataReferenceValidator;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlNativeDataFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'native_data_collection';
    }

    public function getSingularElementName()
    {
        return 'native_data';
    }

    public function handleElement($root)
    {
        (new NativeDataReferenceValidator())->validate($root);
        $deployment = $this->getDeployment();
        return DB::transaction(function () use ($root, $deployment): Journal {
            foreach (
                ['issue', 'issue_galley', 'submission', 'publication', 'author', 'article_galley',
                    'submission_file', 'file'] as $entity
            ) {
                $deployment->resetReferenceMap($entity);
            }
            $deployment->setAuthorDBIds([]);
            $deployment->setSubmissionFileDBIds([]);
            $deployment->setFileDBIds([]);
            $issues = $this->requiredChild($root, 'issues');
            $issueFilter = PKPImportExportFilter::getFilter(
                'full-journal-native-xml=>mapped-issue',
                $deployment
            );
            $customIssueOrders = [];
            foreach ($this->children($issues, 'issue_record') as $record) {
                $sourceReference = $record->getAttribute('source_ref');
                $issueDocument = $this->documentFor($this->requiredChild($record, 'issue'));
                $imported = $issueFilter->execute($issueDocument);
                $issue = $imported[0] ?? null;
                if (!$issue) {
                    throw new InvalidArgumentException('Issue import did not return an entity');
                }
                $deployment->mapReference('issue', $sourceReference, (int) $issue->getId());
                $customOrder = trim($record->getAttribute('custom_order'));
                if ($customOrder !== '') {
                    $customIssueOrders[(int) $issue->getId()] = (int) $customOrder;
                }
            }
            foreach ($customIssueOrders as $issueId => $customOrder) {
                Repo::issue()->dao->insertCustomIssueOrder(
                    (int) $deployment->getContext()->getId(),
                    $issueId,
                    $customOrder
                );
            }
            $currentIssueReference = trim($root->getAttribute('current_issue_ref'));
            if ($currentIssueReference !== '') {
                $currentIssueId = $deployment->requireReference('issue', $currentIssueReference);
                Repo::issue()->updateCurrent(
                    (int) $deployment->getContext()->getId(),
                    Repo::issue()->get($currentIssueId)
                );
            }
            $deployment->setIssue(null);
            $submissions = $this->requiredChild($root, 'submissions');
            $submissionFilter = PKPImportExportFilter::getFilter(
                'full-journal-native-xml=>article',
                $deployment
            );
            foreach ($this->children($submissions, 'submission_record') as $record) {
                $article = $this->requiredChild($record, 'article');
                $articleDocument = $this->documentFor($article);
                $imported = $submissionFilter->execute($articleDocument);
                $submission = $imported[0] ?? null;
                if (!$submission) {
                    throw new InvalidArgumentException('Submission import did not return an entity');
                }
                $deployment->mapReference(
                    'submission',
                    $record->getAttribute('source_ref'),
                    (int) $submission->getId()
                );
                $this->mapPublications($article, $submission);
            }
            foreach ((array) $deployment->getAuthorDBIds() as $sourceId => $destinationId) {
                $deployment->mapReference('author', (string) $sourceId, (int) $destinationId);
            }
            foreach ((array) $deployment->getSubmissionFileDBIds() as $sourceId => $destinationId) {
                $deployment->mapReference('submission_file', (string) $sourceId, (int) $destinationId);
            }
            foreach ((array) $deployment->getFileDBIds() as $sourceId => $destinationId) {
                $deployment->mapReference('file', (string) $sourceId, (int) $destinationId);
            }
            return $deployment->getContext();
        });
    }

    private function mapPublications(DOMElement $article, $submission): void
    {
        $deployment = $this->getDeployment();
        $destinationByVersion = [];
        foreach (Repo::publication()->getCollector()
            ->filterBySubmissionIds([(int) $submission->getId()])
            ->getMany() as $publication) {
            $destinationByVersion[(string) $publication->getData('version')] = $publication;
        }
        foreach ($this->children($article, 'publication') as $publicationNode) {
            $version = $publicationNode->getAttribute('version');
            $publication = $destinationByVersion[$version] ?? null;
            if (!$publication) {
                throw new InvalidArgumentException('Imported publication version was not found');
            }
            $sourceReference = $this->internalId($publicationNode);
            $deployment->mapReference('publication', $sourceReference, (int) $publication->getId());
            $issueReference = trim($publicationNode->getAttribute('issue_ref'));
            if ($issueReference !== '') {
                $expectedIssueId = $deployment->requireReference('issue', $issueReference);
                if ((int) $publication->getData('issueId') !== $expectedIssueId) {
                    throw new InvalidArgumentException('Imported publication issue relation is invalid');
                }
            }
        }
    }

    private function internalId(DOMElement $parent): string
    {
        foreach ($this->children($parent, 'id') as $id) {
            if ($id->getAttribute('type') === 'internal' && trim($id->textContent) !== '') {
                return trim($id->textContent);
            }
        }
        throw new InvalidArgumentException('Missing publication source reference');
    }

    private function requiredChild(DOMElement $parent, string $name): DOMElement
    {
        $matches = $this->children($parent, $name);
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('Expected exactly one native data element: ' . $name);
        }
        return $matches[0];
    }

    private function children(DOMElement $parent, string $name): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $children[] = $child;
            }
        }
        return $children;
    }

    private function documentFor(DOMElement $element): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($element, true));
        return $document;
    }

}
