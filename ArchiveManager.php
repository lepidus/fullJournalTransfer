<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

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

    /**
     * @template T
     *
     * @param callable(string, PackageManifest): T $importer
     *
     * @return T
     */
    public function withExtractedPackage(string $archivePath, string $applicationVersion, callable $importer)
    {
        $archive = $this->openAndValidateArchive($archivePath);
        [$manifest, $entries] = $this->validateContents($archive, $archivePath, $applicationVersion);
        $stagingPath = $this->createStagingDirectory();

        try {
            if (!$archive->extractTo($stagingPath, $entries, false)) {
                throw new RuntimeException('The package could not be extracted');
            }
            $this->validateExtractedFiles($stagingPath, $entries);

            return $importer($stagingPath, $manifest);
        } finally {
            $this->removeDirectory($stagingPath);
        }
    }

    private function openAndValidateArchive(string $archivePath): PharData
    {
        if (!is_file($archivePath) || is_link($archivePath)) {
            throw new InvalidArgumentException('The package archive must be a regular file');
        }
        $size = filesize($archivePath);
        if ($size === false || $size <= 0 || $size > self::MAX_ARCHIVE_SIZE) {
            throw new InvalidArgumentException('The package archive size is invalid');
        }

        try {
            return new PharData($archivePath);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The package archive is invalid', 0, $exception);
        }
    }

    /**
     * @return array{PackageManifest, list<string>}
     */
    private function validateContents(PharData $archive, string $archivePath, string $applicationVersion): array
    {
        $listedEntries = $this->listArchiveEntries($archivePath);
        if (count($listedEntries) > self::MAX_ENTRIES) {
            throw new InvalidArgumentException('The package contains too many entries');
        }
        if (count($listedEntries) !== count(array_unique($listedEntries))) {
            throw new InvalidArgumentException('The package contains a duplicate entry');
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
                throw new InvalidArgumentException(sprintf('The package entry %s must not be a link', $path));
            }
            $entries[] = $path;
            $totalSize += $file->getSize();
            if ($totalSize > self::MAX_EXTRACTED_SIZE) {
                throw new InvalidArgumentException('The package extracted size exceeds the limit');
            }
        }

        sort($entries);
        $listedFiles = array_values(array_filter($listedEntries, fn (string $path): bool => substr($path, -1) !== '/'));
        sort($listedFiles);
        if ($entries !== $listedFiles) {
            throw new InvalidArgumentException('The package entry list is inconsistent');
        }

        if (!in_array('manifest.xml', $entries, true)) {
            throw new InvalidArgumentException('The package must contain manifest.xml');
        }
        $manifestXml = file_get_contents($prefix . 'manifest.xml');
        if ($manifestXml === false) {
            throw new InvalidArgumentException('The package manifest could not be read');
        }
        $manifest = PackageManifest::fromXml($manifestXml, $applicationVersion);
        $manifest->validatePackageEntries($entries);

        $expectedEntries = array_merge(['manifest.xml'], array_keys($manifest->getFiles()));
        sort($expectedEntries);
        if ($entries !== $expectedEntries) {
            throw new InvalidArgumentException('The package entries must match the manifest');
        }
        foreach ($manifest->getFiles() as $path => $metadata) {
            $entryPath = $prefix . $path;
            $actualSize = filesize($entryPath);
            $actualChecksum = hash_file('sha256', $entryPath);
            if ($actualSize !== $metadata['size'] || $actualChecksum !== $metadata['checksum']) {
                throw new InvalidArgumentException(sprintf('The package checksum or size for %s is invalid', $path));
            }
        }

        return [$manifest, $entries];
    }

    /** @return list<string> */
    private function listArchiveEntries(string $archivePath): array
    {
        $stdout = $this->runTar(['/bin/tar', '-tzf', $archivePath]);
        $verboseOutput = $this->runTar(['/bin/tar', '-tvzf', $archivePath]);

        $entries = preg_split('/\r?\n/', rtrim($stdout, "\r\n"));
        $verboseEntries = preg_split('/\r?\n/', rtrim($verboseOutput, "\r\n"));
        if ($entries === false || $entries === ['']) {
            throw new InvalidArgumentException('The package archive is empty');
        }
        if ($verboseEntries === false || count($entries) !== count($verboseEntries)) {
            throw new InvalidArgumentException('The package entry list is inconsistent');
        }
        if (count($entries) !== count(array_unique($entries))) {
            throw new InvalidArgumentException('The package contains a duplicate entry');
        }
        foreach ($verboseEntries as $index => $verboseEntry) {
            $type = $verboseEntry[0] ?? '';
            $isDirectory = substr($entries[$index], -1) === '/';
            if (($isDirectory && $type !== 'd') || (!$isDirectory && $type !== '-')) {
                throw new InvalidArgumentException(sprintf('The package entry %s must not be a link or special file', $entries[$index]));
            }
        }
        foreach ($entries as $path) {
            $this->validateEntryPath(rtrim($path, '/'));
        }

        return $entries;
    }

    /** @param list<string> $command */
    private function runTar(array $command): string
    {
        $process = new Process($command);
        $process->run();
        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();
        if (!$process->isSuccessful()) {
            throw new InvalidArgumentException('The package archive could not be listed: ' . trim($stderr));
        }
        if (trim($stderr) !== '') {
            throw new InvalidArgumentException('The package path must be relative; tar reported: ' . trim($stderr));
        }

        return $stdout;
    }

    private function validateEntryPath(string $path): void
    {
        if ($path === '' || $path[0] === '/' || str_contains($path, '\\') || str_contains($path, "\0")) {
            throw new InvalidArgumentException(sprintf('The package path %s must be relative', $path));
        }
        foreach (explode('/', $path) as $component) {
            if ($component === '' || $component === '.' || $component === '..') {
                throw new InvalidArgumentException(sprintf('The package path %s must be relative', $path));
            }
        }
    }

    private function createStagingDirectory(): string
    {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'full-journal-' . bin2hex(random_bytes(16));
        if (!mkdir($path, 0700)) {
            throw new RuntimeException('The package staging directory could not be created');
        }

        return $path;
    }

    /** @param list<string> $entries */
    private function validateExtractedFiles(string $stagingPath, array $entries): void
    {
        $stagingRealPath = realpath($stagingPath);
        if ($stagingRealPath === false) {
            throw new RuntimeException('The package staging directory is unavailable');
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
                throw new InvalidArgumentException(sprintf('The extracted package entry %s is unsafe', $path));
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
