<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlMetricsFilter extends NativeImportFilter
{
    public function getPluralElementName()
    {
        return 'metrics_collection';
    }

    public function getSingularElementName()
    {
        return 'metrics';
    }

    public function handleElement($root)
    {
        return DB::transaction(function () use ($root) {
            $this->provisionInstitutions($root);
            foreach ([
                'context_metrics' => 'full-journal-metrics-xml=>context-metric',
                'submission_metrics' => 'full-journal-metrics-xml=>submission-metric',
                'issue_metrics' => 'full-journal-metrics-xml=>issue-metric',
                'geo_metrics' => 'full-journal-metrics-xml=>geo-metric',
                'counter_metrics' => 'full-journal-metrics-xml=>counter-metric',
                'institution_metrics' => 'full-journal-metrics-xml=>institution-metric',
            ] as $element => $group) {
                $filter = PKPImportExportFilter::getFilter($group, $this->getDeployment());
                $document = $this->documentFor($this->requiredChild($root, $element));
                $filter->execute($document);
            }
            return $this->getDeployment()->getContext();
        });
    }

    private function provisionInstitutions(DOMElement $root): void
    {
        $contextId = (int) $this->getDeployment()->getContext()->getId();
        $rors = [];
        foreach ($root->getElementsByTagNameNS($this->getDeployment()->getNamespace(), 'institution_metric') as $node) {
            $ror = $this->normalizeRor($node->getAttribute('institution_ror'));
            if ($ror !== null) {
                $rors[$ror] = true;
            }
        }
        foreach (array_keys($rors) as $ror) {
            $matches = 0;
            $institutions = DB::table('institutions')
                ->where('context_id', $contextId)
                ->whereNull('deleted_at')
                ->get();
            foreach ($institutions as $row) {
                if ($this->normalizeRor((string) $row->ror) === $ror) {
                    $matches++;
                }
            }
            if ($matches === 0) {
                DB::table('institutions')->insert([
                    'context_id' => $contextId,
                    'ror' => $ror,
                    'deleted_at' => null,
                ]);
            }
        }
    }

    private function normalizeRor(string $ror): ?string
    {
        $ror = strtolower(rtrim(trim($ror), '/'));
        return preg_match('#^https://ror\.org/0[^ilou]{6}\d{2}$#i', $ror) === 1 ? $ror : null;
    }

    private function requiredChild(DOMElement $parent, string $name): DOMElement
    {
        $matches = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $matches[] = $child;
            }
        }
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('Expected exactly one metrics element: ' . $name);
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
