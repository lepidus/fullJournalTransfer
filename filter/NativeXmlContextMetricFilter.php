<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use Illuminate\Support\Facades\DB;

class NativeXmlContextMetricFilter extends NativeXmlMetricFilter
{
    public function getPluralElementName()
    {
        return 'context_metrics';
    }

    public function getSingularElementName()
    {
        return 'context_metric';
    }

    public function handleElement($node)
    {
        $key = [
            'load_id' => $this->required($node, 'load_id'),
            'context_id' => (int) $this->getDeployment()->getContext()->getId(),
            'date' => $this->date($node),
        ];
        DB::table('metrics_context')->updateOrInsert($key, [
            'metric' => $this->nonNegativeInteger($node, 'metric'),
        ]);
        return (object) array_merge($key, ['metric' => $this->nonNegativeInteger($node, 'metric')]);
    }

}
