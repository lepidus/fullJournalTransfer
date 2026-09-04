<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\metrics;

use APP\plugins\importexport\fullJournalTransfer\transfer\MetricsImporter;
use PKP\plugins\importexport\native\filter\NativeImportFilter;

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
        (new MetricsImporter($this->getDeployment()))->import($root);
        return $this->getDeployment()->getContext();
    }
}
