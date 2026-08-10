<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\plugins\importexport\fullJournalTransfer\ArchiveManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ArchiveManagerTest extends TestCase
{
    private const RELEASE = '3.4.0.10';

    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } elseif (file_exists($path) || is_link($path)) {
                unlink($path);
            }
        }
    }

    public function testItValidatesAndExtractsTheCompletePackageBeforeCallingTheImporter(): void
    {
        $archive = $this->createValidArchive();
        $stagingPath = null;

        $result = (new ArchiveManager())->withExtractedPackage(
            $archive,
            self::RELEASE,
            function (string $path) use (&$stagingPath): string {
                $stagingPath = $path;
                $this->assertFileExists($path . '/manifest.xml');
                $this->assertFileExists($path . '/journal.xml');

                return (string) file_get_contents($path . '/journal.xml');
            }
        );

        $this->assertSame('<journal/>', $result);
        $this->assertNotNull($stagingPath);
        $this->assertDirectoryDoesNotExist($stagingPath);
    }

    public function testItRejectsAChecksumMismatchBeforeCallingTheImporter(): void
    {
        $archive = $this->createValidArchive(str_repeat('0', 64));
        $called = false;

        try {
            (new ArchiveManager())->withExtractedPackage(
                $archive,
                self::RELEASE,
                function () use (&$called): void {
                    $called = true;
                }
            );
            $this->fail('A package with an invalid checksum was accepted');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('checksum', $exception->getMessage());
        }

        $this->assertFalse($called);
    }

    public function testItRejectsAbsolutePathsBeforeCallingTheImporter(): void
    {
        $this->assertUnsafeArchiveRejected('absolute', 'relative');
    }

    public function testItRejectsParentTraversalBeforeCallingTheImporter(): void
    {
        $this->assertUnsafeArchiveRejected('traversal', 'relative');
    }

    public function testItRejectsSymbolicLinksBeforeCallingTheImporter(): void
    {
        $this->assertUnsafeArchiveRejected('symlink', 'link');
    }

    public function testItRejectsDuplicateEntriesBeforeCallingTheImporter(): void
    {
        $this->assertUnsafeArchiveRejected('duplicate', 'duplicate');
    }

    public function testItCleansTheStagingDirectoryWhenTheImporterFails(): void
    {
        $archive = $this->createValidArchive();
        $stagingPath = null;

        try {
            (new ArchiveManager())->withExtractedPackage(
                $archive,
                self::RELEASE,
                function (string $path) use (&$stagingPath): void {
                    $stagingPath = $path;
                    throw new RuntimeException('Import failed');
                }
            );
            $this->fail('The importer failure was not propagated');
        } catch (RuntimeException $exception) {
            $this->assertSame('Import failed', $exception->getMessage());
        }

        $this->assertNotNull($stagingPath);
        $this->assertDirectoryDoesNotExist($stagingPath);
    }

    private function createValidArchive(?string $checksum = null): string
    {
        $source = $this->createTemporaryDirectory();
        $journal = '<journal/>';
        file_put_contents($source . '/journal.xml', $journal);
        file_put_contents($source . '/manifest.xml', $this->manifest($checksum ?? hash('sha256', $journal)));

        return $this->createTar($source, ['manifest.xml', 'journal.xml']);
    }

    private function createUnsafeArchive(string $kind): string
    {
        $source = $this->createTemporaryDirectory();
        $journal = '<journal/>';
        file_put_contents($source . '/journal.xml', $journal);
        file_put_contents($source . '/manifest.xml', $this->manifest(hash('sha256', $journal)));

        if ($kind === 'symlink') {
            symlink('journal.xml', $source . '/linked.xml');
            return $this->createTar($source, ['manifest.xml', 'journal.xml', 'linked.xml']);
        }

        $archive = $this->newTemporaryPath('.tar.gz');
        if ($kind === 'absolute') {
            $arguments = ['--transform=s|^journal.xml$|/journal.xml|', 'manifest.xml', 'journal.xml'];
        } elseif ($kind === 'traversal') {
            $arguments = ['--transform=s|^journal.xml$|../journal.xml|', 'manifest.xml', 'journal.xml'];
        } else {
            $arguments = ['manifest.xml', 'journal.xml', 'journal.xml'];
        }
        $this->runTar($archive, $source, $arguments);

        return $archive;
    }

    private function createTar(string $source, array $entries): string
    {
        $archive = $this->newTemporaryPath('.tar.gz');
        $this->runTar($archive, $source, $entries);

        return $archive;
    }

    private function runTar(string $archive, string $source, array $arguments): void
    {
        $command = array_merge(['/bin/tar', '-czf', $archive, '-C', $source], $arguments);
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to create test archive');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stdout . "\n" . $stderr));
        }
    }

    private function manifest(string $checksum): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<full_journal_package application="ojs" application_version="' . self::RELEASE . '" '
            . 'format_version="1.0" created_at="2026-08-10T16:00:00-04:00">'
            . '<capabilities><capability name="journal"/></capabilities>'
            . '<files><file path="journal.xml" size="10" checksum="' . $checksum . '"/></files>'
            . '</full_journal_package>';
    }

    private function assertUnsafeArchiveRejected(string $kind, string $message): void
    {
        $archive = $this->createUnsafeArchive($kind);
        $called = false;

        try {
            (new ArchiveManager())->withExtractedPackage(
                $archive,
                self::RELEASE,
                function () use (&$called): void {
                    $called = true;
                }
            );
            $this->fail('An unsafe package was accepted');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertFalse($called);
    }

    private function createTemporaryDirectory(): string
    {
        $path = $this->newTemporaryPath('');
        mkdir($path, 0700);

        return $path;
    }

    private function newTemporaryPath(string $suffix): string
    {
        $path = sys_get_temp_dir() . '/full-journal-test-' . bin2hex(random_bytes(8)) . $suffix;
        $this->temporaryPaths[] = $path;

        return $path;
    }

    private function removeDirectory(string $path): void
    {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            if (is_dir($child) && !is_link($child)) {
                $this->removeDirectory($child);
            } else {
                unlink($child);
            }
        }
        rmdir($path);
    }
}
