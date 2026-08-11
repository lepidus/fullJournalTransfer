<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use PKP\db\DAORegistry;

class DefaultContextDataCleaner
{
    public function cleanReferenceData(Journal $context): void
    {
        Repo::section()->deleteByContextId((int) $context->getId());
        DAORegistry::getDAO('GenreDAO')->deleteByContextId($context->getId());
        DAORegistry::getDAO('ReviewFormDAO')->deleteByAssoc(Application::ASSOC_TYPE_JOURNAL, $context->getId());
    }
}
