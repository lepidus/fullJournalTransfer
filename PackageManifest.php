<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

class PackageManifest
{
    public const APPLICATION = 'ojs';
    public const FORMAT_VERSION = '1.0';

    private string $application;
    private string $applicationVersion;
    private string $formatVersion;

    /** @var list<string> */
    private array $capabilities;

    /** @var array<string, array{size: int, checksum: string}> */
    private array $files;

    /**
     * @param list<string> $capabilities
     * @param array<string, array{size: int, checksum: string}> $files
     */
    private function __construct(
        string $application,
        string $applicationVersion,
        string $formatVersion,
        array $capabilities,
        array $files
    ) {
        $this->application = $application;
        $this->applicationVersion = $applicationVersion;
        $this->formatVersion = $formatVersion;
        $this->capabilities = $capabilities;
        $this->files = $files;
    }

    public static function fromXml(string $xml, string $expectedApplicationVersion): self
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || !$document->documentElement instanceof DOMElement) {
            throw new InvalidArgumentException('The manifest must be a well-formed XML document');
        }

        $root = $document->documentElement;
        if ($root->tagName !== 'full_journal_package') {
            throw new InvalidArgumentException('The manifest root element must be full_journal_package');
        }

        self::requireAttribute($root, 'created_at');
        $application = self::requireAttribute($root, 'application');
        $applicationVersion = self::requireAttribute($root, 'application_version');
        $formatVersion = self::requireAttribute($root, 'format_version');

        if ($application !== self::APPLICATION) {
            throw new InvalidArgumentException('The manifest application must be ojs');
        }
        if ($applicationVersion !== $expectedApplicationVersion) {
            throw new InvalidArgumentException('The manifest application version must match the target release');
        }
        if ($formatVersion !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException('The manifest format version is not supported');
        }

        $xpath = new DOMXPath($document);
        $journalFiles = $xpath->query('/full_journal_package/files/file[@path="journal.xml"]');
        if ($journalFiles === false || $journalFiles->length !== 1) {
            throw new InvalidArgumentException('The manifest must declare exactly one journal.xml file');
        }

        $capabilities = [];
        foreach ($xpath->query('/full_journal_package/capabilities/capability') ?: [] as $capability) {
            if ($capability instanceof DOMElement) {
                $capabilities[] = self::requireAttribute($capability, 'name');
            }
        }

        $files = [];
        foreach ($xpath->query('/full_journal_package/files/file') ?: [] as $file) {
            if (!$file instanceof DOMElement) {
                continue;
            }
            $path = self::requireAttribute($file, 'path');
            if (isset($files[$path])) {
                throw new InvalidArgumentException(sprintf('The manifest declares duplicate path %s', $path));
            }
            $size = self::requireAttribute($file, 'size');
            $checksum = self::requireAttribute($file, 'checksum');
            if (!ctype_digit($size) || !preg_match('/\A[a-f0-9]{64}\z/', $checksum)) {
                throw new InvalidArgumentException(sprintf('The manifest metadata for %s is invalid', $path));
            }
            if ($path[0] === '/' || str_contains('/' . $path . '/', '/../')) {
                throw new InvalidArgumentException(sprintf('The manifest path %s must be relative', $path));
            }
            $files[$path] = ['size' => (int) $size, 'checksum' => $checksum];
        }

        return new self($application, $applicationVersion, $formatVersion, $capabilities, $files);
    }

    /** @param list<string> $entries */
    public function validatePackageEntries(array $entries): void
    {
        $counts = array_count_values($entries);
        if (($counts['manifest.xml'] ?? 0) !== 1 || ($counts['journal.xml'] ?? 0) !== 1) {
            throw new InvalidArgumentException('The package must contain exactly one manifest.xml and one journal.xml');
        }
    }

    public function getApplication(): string
    {
        return $this->application;
    }

    public function getApplicationVersion(): string
    {
        return $this->applicationVersion;
    }

    public function getFormatVersion(): string
    {
        return $this->formatVersion;
    }

    /** @return list<string> */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /** @return array<string, array{size: int, checksum: string}> */
    public function getFiles(): array
    {
        return $this->files;
    }

    private static function requireAttribute(DOMElement $element, string $name): string
    {
        $value = $element->getAttribute($name);
        if ($value === '') {
            throw new InvalidArgumentException(sprintf('The %s attribute is required', $name));
        }
        return $value;
    }
}
