<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use Illuminate\Support\Facades\DB;

class NativeXmlIssueMetricFilter extends NativeXmlMetricFilter
{
    public function getPluralElementName()
    {
        return 'issue_metrics';
    }

    public function getSingularElementName()
    {
        return 'issue_metric';
    }

    public function handleElement($node)
    {
        $key = [
            'load_id' => $this->required($node, 'load_id'),
            'context_id' => (int) $this->getDeployment()->getContext()->getId(),
            'issue_id' => $this->getDeployment()->requireReference('issue', $this->required($node, 'issue_ref')),
            'issue_galley_id' => $this->optionalReference($node, 'issue_galley', 'issue_galley_ref'),
            'date' => $this->date($node),
        ];
        DB::table('metrics_issue')->updateOrInsert($key, [
            'metric' => $this->nonNegativeInteger($node, 'metric'),
        ]);
        return (object) $key;
    }
}
