<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\plugins\importexport\fullJournalTransfer\filter\ReferenceDataXmlSupport;
use DOMElement;

class PackageReferenceValidator
{
    public function validateReferenceData(DOMElement $node): void
    {
        (new ReferenceDataXmlSupport())->validate($node);
    }
}
