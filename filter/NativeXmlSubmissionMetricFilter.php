<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use Illuminate\Support\Facades\DB;

class NativeXmlSubmissionMetricFilter extends NativeXmlMetricFilter
{
    public function getPluralElementName()
    {
        return 'submission_metrics';
    }

    public function getSingularElementName()
    {
        return 'submission_metric';
    }

    public function handleElement($node)
    {
        $key = [
            'load_id' => $this->required($node, 'load_id'),
            'context_id' => (int) $this->getDeployment()->getContext()->getId(),
            'submission_id' => $this->getDeployment()->requireReference(
                'submission',
                $this->required($node, 'submission_ref')
            ),
            'representation_id' => $this->optionalReference($node, 'article_galley', 'representation_ref'),
            'submission_file_id' => $this->optionalReference($node, 'submission_file', 'submission_file_ref'),
            'file_type' => $this->optionalInteger($node, 'file_type'),
            'assoc_type' => $this->nonNegativeInteger($node, 'assoc_type'),
            'date' => $this->date($node),
        ];
        DB::table('metrics_submission')->updateOrInsert($key, [
            'metric' => $this->nonNegativeInteger($node, 'metric'),
        ]);
        return (object) $key;
    }
}
