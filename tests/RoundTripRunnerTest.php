<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class RoundTripRunnerTest extends TestCase
{
    private array $temporaryPaths = [];

    public function testItVerifiesTheCommittedFixture(): void
    {
        $process = $this->runFixture(
            __DIR__ . '/round-trip/fixture-ojs-3.4.0.10-v1.tar.gz',
            __DIR__ . '/round-trip/expected-ojs-3.4.0.10-v1.json'
        );

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame("fixture=valid version=v1\n", $process->getOutput());
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testItVerifiesTheVersionedFixtureWithoutRestoringIt(): void
    {
        [$fixture, $expected] = $this->createFixture([
            'source.sql',
            'clean-target.sql',
            'source-files/',
            'source-public/',
        ]);

        $process = $this->runFixture($fixture, $expected);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame("fixture=valid version=v1\n", $process->getOutput());
    }

    public function testItRejectsAFixtureWithoutTheCleanTargetSnapshot(): void
    {
        [$fixture, $expected] = $this->createFixture([
            'source.sql',
            'source-files/',
            'source-public/',
        ]);

        $process = $this->runFixture($fixture, $expected);

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('clean-target.sql', $process->getErrorOutput());
    }

    public function testItPrintsTheExplicitRestorationPlanWithoutChangingTheEnvironment(): void
    {
        [$fixture, $expected] = $this->createFixture([
            'source.sql',
            'clean-target.sql',
            'source-files/',
            'source-public/',
        ]);
        $application = $this->createTemporaryDirectory();
        $files = $this->createTemporaryDirectory();
        $public = $this->createTemporaryDirectory();
        file_put_contents($application . '/config.inc.php', "[database]\nname = round_trip_test\n");

        $process = new Process([
            PHP_BINARY,
            __DIR__ . '/round-trip/run',
            '--fixture',
            $fixture,
            '--expected',
            $expected,
            '--app-root',
            $application,
            '--database',
            'round_trip_test',
            '--files-dir',
            $files,
            '--public-dir',
            $public,
        ]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertStringContainsString("mode=plan\n", $process->getOutput());
        $this->assertStringContainsString("database=round_trip_test\n", $process->getOutput());
        $this->assertFileExists($files);
        $this->assertFileExists($public);
    }

    public function testItRestoresAndRunsTheCliOnlyAfterExplicitApply(): void
    {
        [$fixture, $expected] = $this->createFixture([
            'source.sql',
            'clean-target.sql',
            'source-files/',
            'source-public/',
        ]);
        $application = $this->createTemporaryDirectory();
        $files = $this->createTemporaryDirectory();
        $public = $this->createTemporaryDirectory();
        mkdir($files . '/temp');
        file_put_contents($files . '/temp/keep.txt', 'keep');
        mkdir($public . '/site');
        file_put_contents($public . '/site/keep.txt', 'keep');
        $log = $this->temporaryPath('.log');
        $importMarker = $this->temporaryPath('.imported');
        unlink($importMarker);
        file_put_contents($application . '/config.inc.php', implode("\n", [
            '[database]',
            'host = 127.0.0.1',
            'username = test',
            'password = test',
            'name = round_trip_test',
            '',
        ]));
        mkdir($application . '/tools');
        file_put_contents($application . '/tools/importExport.php', <<<'PHP'
<?php
$arguments = array_slice($argv, 1);
file_put_contents(getenv('ROUND_TRIP_LOG'), implode(' ', array_slice($arguments, 0, 2)) . "\n", FILE_APPEND);
if ($arguments[1] === 'export') {
    if (!is_file(getenv('ROUND_TRIP_FILES') . '/journals/1/article.txt')) {
        fwrite(STDERR, "source file was not restored\n");
        exit(1);
    }
    file_put_contents($arguments[2], 'package');
}
if ($arguments[1] === 'import') {
    if (file_exists(getenv('ROUND_TRIP_IMPORT_MARKER'))) {
        fwrite(STDERR, "journal already exists\n");
        exit(1);
    }
    file_put_contents(getenv('ROUND_TRIP_IMPORT_MARKER'), 'imported');
}
PHP);
        $mysql = $this->temporaryPath('.php');
        file_put_contents($mysql, <<<'PHP'
#!/usr/bin/env php
<?php
file_put_contents(getenv('ROUND_TRIP_LOG'), trim(stream_get_contents(STDIN)) . "\n", FILE_APPEND);
PHP);
        chmod($mysql, 0700);
        $inventory = $this->temporaryPath('.php');
        file_put_contents($inventory, <<<'PHP'
#!/usr/bin/env php
<?php
echo json_encode(['journal' => ['path' => 'reference-journal']]), "\n";
PHP);
        chmod($inventory, 0700);

        $process = new Process([
            PHP_BINARY,
            __DIR__ . '/round-trip/run',
            '--fixture',
            $fixture,
            '--expected',
            $expected,
            '--app-root',
            $application,
            '--database',
            'round_trip_test',
            '--files-dir',
            $files,
            '--public-dir',
            $public,
            '--mysql-command',
            $mysql,
            '--inventory-command',
            $inventory,
            '--apply',
        ], null, [
            'ROUND_TRIP_LOG' => $log,
            'ROUND_TRIP_FILES' => $files,
            'ROUND_TRIP_IMPORT_MARKER' => $importMarker,
        ]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame("round_trip=completed\n", $process->getOutput());
        $this->assertSame(implode("\n", [
            'SOURCE DATABASE',
            'FullJournalImportExportPlugin export',
            'CLEAN TARGET',
            'FullJournalImportExportPlugin import',
            'FullJournalImportExportPlugin import',
            '',
        ]), file_get_contents($log));
        $this->assertDirectoryExists($files);
        $this->assertFileExists($files . '/temp/keep.txt');
        $this->assertFileDoesNotExist($files . '/journals/1/article.txt');
        $this->assertDirectoryExists($public);
        $this->assertFileExists($public . '/site/keep.txt');
        $this->assertFileDoesNotExist($public . '/journals/1/cover.png');
    }

    private function runFixture(string $fixture, string $expected): Process
    {
        $process = new Process([
            PHP_BINARY,
            __DIR__ . '/round-trip/run',
            '--verify-only',
            '--fixture',
            $fixture,
            '--expected',
            $expected,
        ]);
        $process->run();

        return $process;
    }

    private function createFixture(array $entries): array
    {
        $directory = $this->createTemporaryDirectory();
        file_put_contents($directory . '/source.sql', 'SOURCE DATABASE');
        file_put_contents($directory . '/clean-target.sql', 'CLEAN TARGET');
        mkdir($directory . '/source-files');
        mkdir($directory . '/source-public');
        mkdir($directory . '/source-files/journals/1', 0700, true);
        mkdir($directory . '/source-public/journals/1', 0700, true);
        file_put_contents($directory . '/source-files/journals/1/article.txt', 'article');
        file_put_contents($directory . '/source-public/journals/1/cover.png', 'cover');

        $fixture = $this->temporaryPath('.tar.gz');
        $process = new Process(array_merge(['/bin/tar', '-czf', $fixture, '-C', $directory], $entries));
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $checksum = $fixture . '.sha256';
        file_put_contents($checksum, hash_file('sha256', $fixture) . '  ' . basename($fixture) . "\n");
        $this->temporaryPaths[] = $checksum;

        $expected = $this->temporaryPath('.json');
        file_put_contents($expected, json_encode([
            'fixtureVersion' => 'v1',
            'application' => 'ojs',
            'applicationVersion' => '3.4.0.10',
            'journalPath' => 'reference-journal',
            'importUsername' => 'admin',
            'inventory' => [
                'journal' => [
                    'path' => 'reference-journal',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [$fixture, $expected];
    }

    private function createTemporaryDirectory(): string
    {
        $path = $this->temporaryPath('');
        unlink($path);
        mkdir($path, 0700);

        return $path;
    }

    private function temporaryPath(string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'full-journal-round-trip-');
        $this->assertNotFalse($path);
        if ($suffix !== '') {
            $target = $path . $suffix;
            rename($path, $target);
            $path = $target;
        }
        $this->temporaryPaths[] = $path;

        return $path;
    }

    private function removeDirectory(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $child = $path . '/' . $item;
            if (is_dir($child)) {
                $this->removeDirectory($child);
            } else {
                unlink($child);
            }
        }
        rmdir($path);
    }
}
