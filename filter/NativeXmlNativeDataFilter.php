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
            foreach (['issue', 'issue_galley', 'submission', 'publication', 'author', 'article_galley',
                'submission_file', 'file'] as $entity) {
                $deployment->resetReferenceMap($entity);
            }
            $deployment->setAuthorDBIds([]);
            $deployment->setSubmissionFileDBIds([]);
            $deployment->setFileDBIds([]);

            $issueFilter = PKPImportExportFilter::getFilter(
                'full-journal-native-xml=>mapped-issue',
                $deployment
            );
            $articleNodes = [];
            foreach ($this->children($this->requiredChild($root, 'issues'), 'issue') as $issueNode) {
                $issueDocument = $this->documentFor($issueNode);
                $articles = $this->requiredChild($issueDocument->documentElement, 'articles');
                foreach ($this->children($articles, 'article') as $articleNode) {
                    $articleNodes[] = $articleNode->cloneNode(true);
                    $articles->removeChild($articleNode);
                }
                $issueFilter->execute($issueDocument);
            }
            foreach ($this->children($this->requiredChild($root, 'issue_orders'), 'issue_order') as $order) {
                Repo::issue()->dao->insertCustomIssueOrder(
                    (int) $deployment->getContext()->getId(),
                    $deployment->requireReference('issue', trim($order->getAttribute('issue_ref'))),
                    (int) $order->getAttribute('position')
                );
            }

            $deployment->setIssue(null);
            $articleFilter = PKPImportExportFilter::getFilter(
                'full-journal-native-xml=>article',
                $deployment
            );
            foreach ($this->children($this->requiredChild($root, 'articles'), 'article') as $articleNode) {
                $articleNodes[] = $articleNode;
            }
            foreach ($articleNodes as $articleNode) {
                $articleDocument = $this->documentFor($articleNode);
                $articleFilter->execute($articleDocument);
            }
            foreach ((array) $deployment->getAuthorDBIds() as $sourceId => $destinationId) {
                $deployment->mapReference('author', (string) $sourceId, (int) $destinationId);
            }
            $this->importAuthorMetadata($this->requiredChild($root, 'author_metadata'));
            $this->importHistoricalDates($this->requiredChild($root, 'historical_dates'));
            foreach ((array) $deployment->getSubmissionFileDBIds() as $sourceId => $destinationId) {
                $deployment->mapReference('submission_file', (string) $sourceId, (int) $destinationId);
            }
            foreach ((array) $deployment->getFileDBIds() as $sourceId => $destinationId) {
                $deployment->mapReference('file', (string) $sourceId, (int) $destinationId);
            }
            return $deployment->getContext();
        });
    }

    private function importHistoricalDates(DOMElement $historicalDatesNode): void
    {
        $deployment = $this->getDeployment();
        $contextId = (int) $deployment->getContext()->getId();
        foreach ($this->children($this->requiredChild($historicalDatesNode, 'issues'), 'issue') as $issueNode) {
            $issueId = $deployment->requireReference('issue', trim($issueNode->getAttribute('issue_ref')));
            $issue = Repo::issue()->get($issueId);
            if (!$issue || (int) $issue->getJournalId() !== $contextId) {
                throw new InvalidArgumentException('Mapped issue does not exist in the imported context');
            }
            DB::table('issues')
                ->where('issue_id', $issueId)
                ->where('journal_id', $contextId)
                ->update(['date_published' => $this->optionalAttribute($issueNode, 'date_published')]);
        }
        foreach ($this->children(
            $this->requiredChild($historicalDatesNode, 'submissions'),
            'submission'
        ) as $submissionNode) {
            $submissionId = $deployment->requireReference(
                'submission',
                trim($submissionNode->getAttribute('submission_ref'))
            );
            $submission = Repo::submission()->get($submissionId);
            if (!$submission || (int) $submission->getData('contextId') !== $contextId) {
                throw new InvalidArgumentException('Mapped submission does not exist in the imported context');
            }
            DB::table('submissions')
                ->where('submission_id', $submissionId)
                ->where('context_id', $contextId)
                ->update([
                    'date_submitted' => $this->optionalAttribute($submissionNode, 'date_submitted'),
                    'date_last_activity' => $this->optionalAttribute($submissionNode, 'date_last_activity'),
                    'last_modified' => $this->optionalAttribute($submissionNode, 'last_modified'),
                ]);
        }
    }

    private function optionalAttribute(DOMElement $node, string $name): ?string
    {
        return $node->hasAttribute($name) ? $node->getAttribute($name) : null;
    }

    private function importAuthorMetadata(DOMElement $metadataNode): void
    {
        $deployment = $this->getDeployment();
        $supportedLocales = $deployment->getContext()->getSupportedSubmissionLocales();
        foreach ($this->children($metadataNode, 'author') as $authorNode) {
            $sourceId = trim($authorNode->getAttribute('author_ref'));
            $author = Repo::author()->get($deployment->requireReference('author', $sourceId));
            if (!$author) {
                throw new InvalidArgumentException('Mapped author does not exist: ' . $sourceId);
            }
            $properties = [];
            foreach ([
                'preferred_public_name' => 'preferredPublicName',
                'competing_interests' => 'competingInterests',
            ] as $elementName => $property) {
                foreach ($this->children($authorNode, $elementName) as $valueNode) {
                    $locale = $valueNode->getAttribute('locale');
                    if (in_array($locale, $supportedLocales, true)) {
                        $properties[$property][$locale] = $valueNode->textContent;
                    }
                }
            }
            if ($properties !== []) {
                Repo::author()->edit($author, $properties);
            }
        }
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
