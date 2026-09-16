<?php

/**
 * Copyright (c) 2014-2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\transfer;

use InvalidArgumentException;

class ImportedResourceJournal
{
    private array $files = [];
    private array $directories = [];

    public function recordFile(string $path): void
    {
        $this->validateAbsolutePath($path, __('plugins.importexport.fullJournal.entity.file'));
        $this->files[] = $path;
    }

    public function recordDirectory(string $path): void
    {
        $this->validateAbsolutePath($path, __('plugins.importexport.fullJournal.entity.directory'));
        $this->directories[] = $path;
    }

    public function reset(): void
    {
        $this->files = [];
        $this->directories = [];
    }

    public function compensate(callable $removeDirectory): array
    {
        $errors = [];
        foreach (array_reverse($this->files) as $path) {
            if ((is_file($path) || is_link($path)) && !unlink($path)) {
                $errors[] = __('plugins.importexport.fullJournal.error.failedCompensateImportedFile');
            }
        }
        foreach (array_reverse($this->directories) as $path) {
            if (is_dir($path) && !$removeDirectory($path)) {
                $errors[] = __('plugins.importexport.fullJournal.error.failedCompensateImportedDirectory');
            }
        }
        $this->reset();
        return $errors;
    }

    private function validateAbsolutePath(string $path, string $resource): void
    {
        if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.resourceAbsolutePathRequired',
                [
                    'resource' => $resource,
                ]
            ));
        }
    }
}
