<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\plugins\importexport\native\NativeImportExportDeployment;

class FullJournalImportExportDeployment extends NativeImportExportDeployment
{
    public function getSubmissionNodeName()
    {
        return 'extended_article';
    }

    public function getSubmissionsNodeName()
    {
        return 'extended_articles';
    }
}
