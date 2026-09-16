<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\package;

use InvalidArgumentException;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Process\Process;
use Throwable;

class ArchiveManager
{
    private const MAX_ARCHIVE_SIZE = 2147483648;
    private const MAX_EXTRACTED_SIZE = 10737418240;
    private const MAX_ENTRIES = 100000;

    public function withExtractedPackage(
        string $archivePath,
        callable $importer,
        ?callable $progress = null
    ) {
        if ($progress) {
            $progress(__('plugins.importexport.fullJournal.progress.validatingJournalPackage'));
        }
        $archive = $this->openAndValidateArchive($archivePath);
        $entries = $this->validateContents($archive, $archivePath);
        if ($progress) {
            $progress(__('plugins.importexport.fullJournal.progress.extractingJournalPackage'));
        }
        $stagingPath = $this->createStagingDirectory();

        try {
            if (!$archive->extractTo($stagingPath, $entries, false)) {
                throw new RuntimeException(__('plugins.importexport.fullJournal.error.packageExtractionFailed'));
            }
            $this->validateExtractedFiles($stagingPath, $entries);

            return $importer($stagingPath);
        } finally {
            $this->removeDirectory($stagingPath);
        }
    }

    private function openAndValidateArchive(string $archivePath): PharData
    {
        if (!is_file($archivePath) || is_link($archivePath)) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageRegularFileRequired'));
        }
        $size = filesize($archivePath);
        if ($size === false || $size <= 0 || $size > self::MAX_ARCHIVE_SIZE) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageArchiveSizeInvalid'));
        }

        try {
            return new PharData($archivePath);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageArchiveInvalid'), 0, $exception);
        }
    }

    private function validateContents(PharData $archive, string $archivePath): array
    {
        $listedEntries = $this->listArchiveEntries($archivePath);
        if (count($listedEntries) > self::MAX_ENTRIES) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageContainsTooManyEntries'));
        }
        if (count($listedEntries) !== count(array_unique($listedEntries))) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageContainsDuplicateEntry'));
        }

        $entries = [];
        $totalSize = 0;
        $prefix = 'phar://' . $archivePath . '/';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($prefix, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }
            $path = substr($file->getPathname(), strlen($prefix));
            $this->validateEntryPath($path);
            if ($file->isLink() || !$file->isFile()) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.packageEntryLinkNotAllowed',
                    [
                        'path' => $path,
                    ]
                ));
            }
            $entries[] = $path;
            $totalSize += $file->getSize();
            if ($totalSize > self::MAX_EXTRACTED_SIZE) {
                throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageExtractedSizeExceedsLimit'));
            }
        }

        sort($entries);
        $listedFiles = array_values(array_filter($listedEntries, fn (string $path): bool => substr($path, -1) !== '/'));
        sort($listedFiles);
        if ($entries !== $listedFiles) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageEntryListInconsistent'));
        }

        if (!in_array('journal.xml', $entries, true)) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageJournalXmlMissing'));
        }

        return $entries;
    }

    private function listArchiveEntries(string $archivePath): array
    {
        $stdout = $this->runTar(['/bin/tar', '-tzf', $archivePath]);
        $verboseOutput = $this->runTar(['/bin/tar', '-tvzf', $archivePath]);

        $entries = preg_split('/\r?\n/', rtrim($stdout, "\r\n"));
        $verboseEntries = preg_split('/\r?\n/', rtrim($verboseOutput, "\r\n"));
        if ($entries === false || $entries === ['']) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageArchiveEmpty'));
        }
        if ($verboseEntries === false || count($entries) !== count($verboseEntries)) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageEntryListInconsistent'));
        }
        if (count($entries) !== count(array_unique($entries))) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageContainsDuplicateEntry'));
        }
        foreach ($verboseEntries as $index => $verboseEntry) {
            $type = $verboseEntry[0] ?? '';
            $isDirectory = substr($entries[$index], -1) === '/';
            if (($isDirectory && $type !== 'd') || (!$isDirectory && $type !== '-')) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.packageLinkOrSpecialFileNotAllowed',
                    [
                        'path' => $entries[$index],
                    ]
                ));
            }
        }
        foreach ($entries as $path) {
            $this->validateEntryPath(rtrim($path, '/'));
        }

        return $entries;
    }

    private function runTar(array $command): string
    {
        $process = new Process($command);
        $process->run();
        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();
        if (!$process->isSuccessful()) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.packageListingFailed',
                [
                    'details' => trim($stderr),
                ]
            ));
        }
        if (trim($stderr) !== '') {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.packageAbsolutePathReportedByTar',
                [
                    'details' => trim($stderr),
                ]
            ));
        }

        return $stdout;
    }

    private function validateEntryPath(string $path): void
    {
        if ($path === '' || $path[0] === '/' || str_contains($path, '\\') || str_contains($path, "\0")) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.packageRelativePathRequired',
                [
                    'path' => $path,
                ]
            ));
        }
        foreach (explode('/', $path) as $component) {
            if ($component === '' || $component === '.' || $component === '..') {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.packageRelativePathRequired',
                    [
                        'path' => $path,
                    ]
                ));
            }
        }
    }

    private function createStagingDirectory(): string
    {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'full-journal-' . bin2hex(random_bytes(16));
        if (!mkdir($path, 0700)) {
            throw new RuntimeException(__('plugins.importexport.fullJournal.error.packageStagingDirectoryCreationFailed'));
        }

        return $path;
    }

    private function validateExtractedFiles(string $stagingPath, array $entries): void
    {
        $stagingRealPath = realpath($stagingPath);
        if ($stagingRealPath === false) {
            throw new RuntimeException(__('plugins.importexport.fullJournal.error.packageStagingDirectoryUnavailable'));
        }
        foreach ($entries as $path) {
            $file = $stagingPath . DIRECTORY_SEPARATOR . $path;
            $realPath = realpath($file);
            if (
                $realPath === false
                || !str_starts_with($realPath, $stagingRealPath . DIRECTORY_SEPARATOR)
                || !is_file($realPath)
                || is_link($file)
            ) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.extractedPackageEntryUnsafe',
                    [
                        'path' => $path,
                    ]
                ));
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($child) && !is_link($child)) {
                $this->removeDirectory($child);
            } else {
                unlink($child);
            }
        }
        rmdir($path);
    }
}
