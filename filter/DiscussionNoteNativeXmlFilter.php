<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use APP\facades\Repo;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class DiscussionNoteNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$notes)
    {
        if (!is_array($notes)) {
            throw new InvalidArgumentException('Expected discussion notes for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'discussion_notes');
        $document->appendChild($root);
        foreach ($notes as $note) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'discussion_note');
            $node->setAttribute('source_ref', (string) $note->note_id);
            $node->setAttribute('discussion_ref', (string) $note->assoc_id);
            $node->setAttribute('user_ref', (string) $note->user_id);
            $node->setAttribute('date_created', (string) $note->date_created);
            if ($note->date_modified !== null) {
                $node->setAttribute('date_modified', (string) $note->date_modified);
            }
            $this->addText($document, $node, 'title', $note->title);
            $this->addText($document, $node, 'contents', $note->contents);
            $attachments = Repo::submissionFile()->getCollector()
                ->filterByAssoc(Application::ASSOC_TYPE_NOTE, [(int) $note->note_id])
                ->getMany()
                ->values()
                ->all();
            $filter = PKPImportExportFilter::getFilter(
                'discussion-attachment=>full-journal-workflow-xml',
                $this->getDeployment()
            );
            $attachmentDocument = $filter->execute($attachments);
            foreach ($attachmentDocument->documentElement->childNodes as $attachmentNode) {
                if ($attachmentNode instanceof DOMElement) {
                    $node->appendChild($document->importNode($attachmentNode, true));
                }
            }
            $root->appendChild($node);
        }
        return $document;
    }

    private function addText(DOMDocument $document, DOMElement $parent, string $name, ?string $value): void
    {
        $node = $document->createElementNS('http://pkp.sfu.ca', $name);
        $node->appendChild($document->createTextNode($value ?? ''));
        $parent->appendChild($node);
    }
}
