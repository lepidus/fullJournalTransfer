<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\persistence;

use APP\decision\Decision;
use APP\facades\Repo;
use RuntimeException;

class HistoricalDecisionPersistenceAdapter
{
    public function insert(array $data): Decision
    {
        $decision = Repo::decision()->newDataObject($data);
        $id = Repo::decision()->dao->insert($decision);
        $persisted = Repo::decision()->get($id, (int) $data['submissionId']);
        if (!$persisted) {
            throw new RuntimeException('Historical decision was not persisted');
        }
        return $persisted;
    }
}
