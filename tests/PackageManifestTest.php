<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\plugins\importexport\fullJournalTransfer\PackageManifest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PackageManifestTest extends TestCase
{
    private const RELEASE = '3.4.0.10';

    public function testItAcceptsACompatibleVersionedManifest(): void
    {
        $manifest = PackageManifest::fromXml($this->validManifest(), self::RELEASE);

        $this->assertSame('ojs', $manifest->getApplication());
        $this->assertSame(self::RELEASE, $manifest->getApplicationVersion());
        $this->assertSame('1.1', $manifest->getFormatVersion());
        $this->assertSame(['journal', 'users', 'workflow', 'metrics'], $manifest->getCapabilities());
        $this->assertSame(
            [
                'submissions' => 2,
                'review_rounds' => 3,
                'metrics_submission' => 5,
            ],
            $manifest->getIntegrityCounts()
        );
        $this->assertSame(
            [
                'journal.xml' => [
                    'size' => 128,
                    'checksum' => str_repeat('a', 64),
                ],
            ],
            $manifest->getFiles()
        );
    }

    public function testItAcceptsDifferentMaintenanceReleasesFromTheSameOjsLine(): void
    {
        $sourceRelease = '3.4.0.9';
        $manifest = PackageManifest::fromXml(
            str_replace(self::RELEASE, $sourceRelease, $this->validManifest()),
            self::RELEASE
        );

        $this->assertSame($sourceRelease, $manifest->getApplicationVersion());
    }

    public function testVersionedFixtureMatchesTheJournalPayload(): void
    {
        $directory = __DIR__ . '/samples/full-journal-1.1';
        $manifest = PackageManifest::fromXml(
            (string) file_get_contents($directory . '/manifest.xml'),
            self::RELEASE
        );
        $journal = $directory . '/journal.xml';

        $this->assertSame([], $manifest->getIntegrityCounts());
        $this->assertSame(filesize($journal), $manifest->getFiles()['journal.xml']['size']);
        $this->assertSame(hash_file('sha256', $journal), $manifest->getFiles()['journal.xml']['checksum']);
    }

    /**
     * @dataProvider incompatibleManifestProvider
     */
    public function testItRejectsIncompatibleManifestMetadata(string $xml, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        PackageManifest::fromXml($xml, self::RELEASE);
    }

    public function incompatibleManifestProvider(): array
    {
        return [
            'root' => [
                str_replace('full_journal_package', 'package', $this->validManifest()),
                'root element',
            ],
            'application' => [
                str_replace('application="ojs"', 'application="omp"', $this->validManifest()),
                'application',
            ],
            'version line' => [
                str_replace(self::RELEASE, '3.4.1.0', $this->validManifest()),
                'application version line',
            ],
            'incomplete release' => [
                str_replace(self::RELEASE, '3.4.0', $this->validManifest()),
                'complete application version',
            ],
            'format' => [
                str_replace('format_version="1.1"', 'format_version="2.0"', $this->validManifest()),
                'format version',
            ],
            'previous format' => [
                str_replace('format_version="1.1"', 'format_version="1.0"', $this->validManifest()),
                'format version',
            ],
            'journal cardinality' => [
                str_replace('</files>', $this->journalFile() . '</files>', $this->validManifest()),
                'exactly one journal.xml',
            ],
            'invalid checksum' => [
                str_replace(str_repeat('a', 64), 'not-a-sha256', $this->validManifest()),
                'metadata for journal.xml',
            ],
            'parent path' => [
                str_replace(
                    '</files>',
                    '<file path="../files/private.pdf" size="1" checksum="' . str_repeat('b', 64) . '"/></files>',
                    $this->validManifest()
                ),
                'must be relative',
            ],
            'malformed xml' => [
                '<full_journal_package>',
                'well-formed XML',
            ],
        ];
    }

    public function testItRejectsInvalidPackageRootCardinality(): void
    {
        $manifest = PackageManifest::fromXml($this->validManifest(), self::RELEASE);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly one manifest.xml and one journal.xml');

        $manifest->validatePackageEntries(['manifest.xml', 'journal.xml', 'journal.xml']);
    }

    /**
     * @dataProvider invalidIntegrityCountProvider
     */
    public function testItRejectsInvalidIntegrityCounts(string $integrity, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        PackageManifest::fromXml(
            str_replace($this->integrity(), $integrity, $this->validManifest()),
            self::RELEASE
        );
    }

    public function invalidIntegrityCountProvider(): array
    {
        return [
            'duplicate entity' => [
                '<integrity><entity name="submissions" count="2"/>'
                    . '<entity name="submissions" count="3"/></integrity>',
                'duplicate entity submissions',
            ],
            'unknown entity' => [
                '<integrity><entity name="unknown" count="2"/></integrity>',
                'unknown entity unknown',
            ],
            'invalid count' => [
                '<integrity><entity name="submissions" count="-1"/></integrity>',
                'count for submissions is invalid',
            ],
            'multiple integrity elements' => [
                '<integrity><entity name="submissions" count="2"/></integrity><integrity/>',
                'at most one integrity element',
            ],
        ];
    }

    private function validManifest(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<full_journal_package application="ojs" application_version="' . self::RELEASE . '" '
            . 'format_version="1.1" created_at="2026-08-10T15:00:00-04:00">'
            . '<capabilities><capability name="journal"/><capability name="users"/>'
            . '<capability name="workflow"/><capability name="metrics"/></capabilities>'
            . $this->integrity()
            . '<files>' . $this->journalFile() . '</files>'
            . '</full_journal_package>';
    }

    private function integrity(): string
    {
        return '<integrity><entity name="submissions" count="2"/>'
            . '<entity name="review_rounds" count="3"/>'
            . '<entity name="metrics_submission" count="5"/></integrity>';
    }

    private function journalFile(): string
    {
        return '<file path="journal.xml" size="128" checksum="' . str_repeat('a', 64) . '"/>';
    }
}
