<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\package;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

class PackageManifest
{
    public const APPLICATION = 'ojs';
    public const FORMAT_VERSION = '1.1';

    private string $application;
    private string $applicationVersion;
    private string $formatVersion;

    private array $capabilities;

    private array $files;

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
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.manifestMalformedXml'));
        }

        $root = $document->documentElement;
        if ($root->tagName !== 'full_journal_package') {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.manifestInvalidRoot'));
        }

        self::requireAttribute($root, 'created_at');
        $application = self::requireAttribute($root, 'application');
        $applicationVersion = self::requireAttribute($root, 'application_version');
        $formatVersion = self::requireAttribute($root, 'format_version');

        if ($application !== self::APPLICATION) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.manifestInvalidApplication'));
        }
        $sourceVersionLine = self::getVersionLine($applicationVersion);
        $targetVersionLine = self::getVersionLine($expectedApplicationVersion);
        if ($sourceVersionLine !== $targetVersionLine) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.manifestVersionLineMismatch'));
        }
        if ($formatVersion !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.manifestFormatVersionNotSupported'));
        }

        $xpath = new DOMXPath($document);
        $journalFiles = $xpath->query('/full_journal_package/files/file[@path="journal.xml"]');
        if ($journalFiles === false || $journalFiles->length !== 1) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.manifestSingleJournalFileRequired'));
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
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.manifestDeclaresDuplicatePath',
                    [
                        'path' => $path,
                    ]
                ));
            }
            $size = self::requireAttribute($file, 'size');
            $checksum = self::requireAttribute($file, 'checksum');
            if (!ctype_digit($size) || !preg_match('/\A[a-f0-9]{64}\z/', $checksum)) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.manifestMetadataInvalid',
                    [
                        'path' => $path,
                    ]
                ));
            }
            if ($path[0] === '/' || str_contains('/' . $path . '/', '/../')) {
                throw new InvalidArgumentException(__(
                    'plugins.importexport.fullJournal.error.manifestRelativePathRequired',
                    [
                        'path' => $path,
                    ]
                ));
            }
            $files[$path] = ['size' => (int) $size, 'checksum' => $checksum];
        }

        return new self($application, $applicationVersion, $formatVersion, $capabilities, $files);
    }

    public function validatePackageEntries(array $entries): void
    {
        $counts = array_count_values($entries);
        if (($counts['manifest.xml'] ?? 0) !== 1 || ($counts['journal.xml'] ?? 0) !== 1) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.packageRequiredFilesMismatch'));
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

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    private static function requireAttribute(DOMElement $element, string $name): string
    {
        $value = $element->getAttribute($name);
        if ($value === '') {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.manifestAttributeRequiredElementLine',
                [
                    'name' => $name,
                    'localName' => $element->localName,
                    'line' => $element->getLineNo(),
                ]
            ));
        }
        return $value;
    }

    private static function getVersionLine(string $version): string
    {
        if (!preg_match('/\A(\d+\.\d+\.\d+)\.\d+\z/', $version, $matches)) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.completeApplicationVersionRequired'));
        }

        return $matches[1];
    }
}
