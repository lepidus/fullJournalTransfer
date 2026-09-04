<?php

/**
 * Copyright (c) 2014-2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\transfer;

use APP\plugins\importexport\fullJournalTransfer\transfer\ImportedResourceJournal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ImportedResourceJournalTest extends TestCase
{
    public function testItCompensatesFilesBeforeDirectories(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'full-journal-resource-' . bin2hex(random_bytes(8));
        $file = $directory . DIRECTORY_SEPARATOR . 'file.txt';
        mkdir($directory, 0700);
        file_put_contents($file, 'content');
        $journal = new ImportedResourceJournal();
        $journal->recordDirectory($directory);
        $journal->recordFile($file);

        $errors = $journal->compensate(function (string $path): bool {
            return rmdir($path);
        });

        $this->assertSame([], $errors);
        $this->assertFileDoesNotExist($file);
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function testItRejectsRelativePaths(): void
    {
        $journal = new ImportedResourceJournal();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A created file journal entry must use an absolute path');

        $journal->recordFile('relative/file.txt');
    }
}
