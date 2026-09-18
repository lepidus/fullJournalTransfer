<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('classes.statistics.MetricsDAO');

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;

class FullJournalMetricsDAO extends MetricsDAO
{
    public const IMPORT_BATCH_SIZE = 500;

    private $transactionalConnection;
    private $transactionalMetrics;

    /**
     * Import a batch, resolving each destination association only once in this batch.
     * Invalid records are reported individually, as in the native metrics importer.
     */
    public function insertRecords(array $records, callable $onError)
    {
        $associations = [];
        $rows = [];
        foreach ($records as $record) {
            try {
                $rows[] = $this->prepareImportRecord($record, $associations);
            } catch (Exception $e) {
                $onError($e);
            }
        }
        if (!$rows) {
            return;
        }
        if ($this->supportsBatchTransactions()) {
            $this->insertBatch($rows, $onError);
        } else {
            // Legacy MyISAM tables cannot roll back a partially written multi-row INSERT.
            // Keep the association cache benefit without risking duplicate retries.
            foreach ($rows as $row) {
                $this->insertBatch([$row], $onError, false);
            }
        }
    }

    protected function supportsBatchTransactions()
    {
        $connection = Capsule::connection();
        if ($connection !== $this->transactionalConnection) {
            $driver = $connection->getDriverName();
            $this->transactionalMetrics = in_array($driver, ['pgsql', 'sqlite'], true);
            if ($driver === 'mysql') {
                $table = $connection->selectOne(
                    'SELECT ENGINE AS engine FROM information_schema.tables'
                    . ' WHERE table_schema = DATABASE() AND table_name = ?',
                    ['metrics']
                );
                $this->transactionalMetrics = $table && strtoupper($table->engine) === 'INNODB';
            }
            $this->transactionalConnection = $connection;
        }
        return $this->transactionalMetrics;
    }

    /**
     * Keep normalization aligned with PKPMetricsDAO::insertRecord() in OJS 3.3.
     * The core does not expose preparation separately from its single-row INSERT.
     * Nullable columns are explicit so all rows share the same INSERT shape.
     */
    private function prepareImportRecord(array $record, array &$associations)
    {
        $row = [];
        foreach (['load_id', 'assoc_type', 'assoc_id', 'metric_type'] as $dimension) {
            if (!isset($record[$dimension])) {
                throw new Exception('Cannot load record: missing dimension "' . $dimension . '".');
            }
            $row[$dimension] = $record[$dimension];
        }
        $row['assoc_type'] = (int) $row['assoc_type'];
        $row['assoc_id'] = (int) $row['assoc_id'];
        $key = $row['assoc_type'] . ':' . $row['assoc_id'];
        if (!isset($associations[$key])) {
            $associations[$key] = $this->foreignKeyLookup($row['assoc_type'], $row['assoc_id']);
        }
        [$row['context_id'], $row['pkp_section_id'], $row['assoc_object_type'],
            $row['assoc_object_id'], $row['submission_id'], $row['representation_id']] = $associations[$key];

        $row['file_type'] = !empty($record['file_type']) ? (int) $record['file_type'] : null;
        $row['day'] = null;
        if (isset($record['day'])) {
            if (!PKPString::regexp_match('/[0-9]{8}/', $record['day'])) {
                throw new Exception('Cannot load record: invalid date.');
            }
            $row['day'] = $record['day'];
            $row['month'] = substr($record['day'], 0, 6);
            if (isset($record['month']) && $row['month'] != $record['month']) {
                throw new Exception('Cannot load record: invalid month.');
            }
        } elseif (isset($record['month'])) {
            if (!PKPString::regexp_match('/[0-9]{6}/', $record['month'])) {
                throw new Exception('Cannot load record: invalid month.');
            }
            $row['month'] = $record['month'];
        } else {
            throw new Exception('Cannot load record: Missing time dimension.');
        }
        foreach (['country_id', 'region', 'city'] as $dimension) {
            $row[$dimension] = isset($record[$dimension]) ? (string) $record[$dimension] : null;
        }
        if (!isset($record['metric'])) {
            throw new Exception('Cannot load record: metric is missing.');
        }
        if (!is_numeric($record['metric'])) {
            throw new Exception('Cannot load record: invalid metric.');
        }
        $row['metric'] = (int) $record['metric'];
        return $row;
    }

    private function insertBatch(array $rows, callable $onError, $transactional = true)
    {
        if (!$rows) {
            return;
        }
        $fields = implode(', ', array_keys($rows[0]));
        $placeholders = '(' . implode(', ', array_fill(0, count($rows[0]), '?')) . ')';
        $values = implode(', ', array_fill(0, count($rows), $placeholders));
        $params = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
        }
        $connection = Capsule::connection();
        $transactionLevel = $connection->transactionLevel();
        try {
            // A failed statement must be rolled back before retrying smaller batches,
            // including when the caller already has a transaction (PostgreSQL).
            $insert = function () use ($fields, $values, $params) {
                $this->update("INSERT INTO metrics ($fields) VALUES $values", $params);
            };
            if ($transactional) {
                $connection->transaction($insert);
            } else {
                $insert();
            }
        } catch (QueryException $e) {
            // Only data/constraint errors are record-level failures. Never retry a
            // connection loss, deadlock or failed commit whose outcome is uncertain.
            $stateClass = substr((string) $e->getCode(), 0, 2);
            if (
                !in_array($stateClass, ['22', '23'], true)
                || $connection->transactionLevel() !== $transactionLevel
            ) {
                throw $e;
            }
            if (count($rows) === 1) {
                $onError($e);
                return;
            }
            $middle = intdiv(count($rows), 2);
            $this->insertBatch(array_slice($rows, 0, $middle), $onError);
            $this->insertBatch(array_slice($rows, $middle), $onError);
        }
    }

    public function getByContextId($contextId)
    {
        $result = $this->retrieve(
            'SELECT assoc_type, assoc_id, day, country_id, region, city, file_type, load_id, metric, metric_type
            FROM metrics WHERE context_id = ?',
            [$contextId]
        );

        $returner = [];
        foreach ($result as $row) {
            $returner[] = (array) $row;
        }
        return $returner;
    }
}
