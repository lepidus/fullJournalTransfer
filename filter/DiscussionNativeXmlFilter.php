<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class DiscussionNativeXmlFilter extends NativeExportFilter
{
    public function &process(&$discussions)
    {
        if (!is_array($discussions)) {
            throw new InvalidArgumentException('Expected discussions for export');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://pkp.sfu.ca', 'discussions');
        $document->appendChild($root);
        foreach ($discussions as $discussion) {
            $node = $document->createElementNS('http://pkp.sfu.ca', 'discussion');
            $node->setAttribute('source_ref', (string) $discussion->query_id);
            $node->setAttribute('submission_ref', (string) $discussion->assoc_id);
            $node->setAttribute('stage_id', (string) $discussion->stage_id);
            $node->setAttribute('closed', $discussion->closed ? 'true' : 'false');
            $node->setAttribute('sequence', (string) $discussion->seq);
            $participants = DB::table('query_participants')->where('query_id', $discussion->query_id)
                ->orderBy('user_id')->get()->all();
            $this->appendChildren(
                $document,
                $node,
                'discussion-participant=>full-journal-workflow-xml',
                $participants
            );
            $notes = DB::table('notes')->where('assoc_type', Application::ASSOC_TYPE_QUERY)
                ->where('assoc_id', $discussion->query_id)->orderBy('date_created')->orderBy('note_id')->get()->all();
            $this->appendChildren($document, $node, 'discussion-note=>full-journal-workflow-xml', $notes);
            $root->appendChild($node);
        }
        return $document;
    }

    private function appendChildren(DOMDocument $document, DOMElement $parent, string $group, array $data): void
    {
        $filter = PKPImportExportFilter::getFilter($group, $this->getDeployment());
        $children = $filter->execute($data);
        foreach ($children->documentElement->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $parent->appendChild($document->importNode($child, true));
            }
        }
    }
}
