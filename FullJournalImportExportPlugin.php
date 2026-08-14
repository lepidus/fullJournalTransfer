<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\importexport\native\NativeImportExportPlugin;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use PKP\config\Config;
use RuntimeException;
use Symfony\Component\Process\Process;

class FullJournalImportExportPlugin extends NativeImportExportPlugin
{
    public function getName()
    {
        return 'FullJournalImportExportPlugin';
    }

    public function getDisplayName()
    {
        return __('plugins.importexport.fullJournal.displayName');
    }

    public function getDescription()
    {
        return __('plugins.importexport.fullJournal.description');
    }

    public function getPluginSettingsPrefix()
    {
        return 'fullJournalTransfer';
    }

    public function usage($scriptName)
    {
        echo __('plugins.importexport.fullJournal.cliUsage', [
            'scriptName' => $scriptName,
            'pluginName' => $this->getName(),
        ]) . "\n";
    }

    public function executeCLI($scriptName, &$args)
    {
        $command = array_shift($args);
        $archivePath = array_shift($args);
        $identifier = array_shift($args);
        if (!in_array($command, ['export', 'import'], true) || !$archivePath || !$identifier || $args !== []) {
            $this->usage($scriptName);
            throw new InvalidArgumentException('Invalid full journal command arguments');
        }
        $archivePath = $this->absolutePath($archivePath);

        if ($command === 'export') {
            $journal = Application::get()->getContextDAO()->getByPath($identifier);
            if (!$journal) {
                throw new InvalidArgumentException('The journal path does not exist');
            }
            $this->exportPackage($journal, $archivePath);
            fwrite(STDOUT, "Journal export completed\n");
            return true;
        }

        $user = Repo::user()->getByUsername($identifier, true);
        if (!$user) {
            throw new InvalidArgumentException('The import user does not exist');
        }
        $deployment = $this->getAppSpecificDeployment(Application::get()->getContextDAO()->newDataObject(), $user);
        $version = Application::get()->getCurrentVersion()->getVersionString();
        if (!$deployment->importPackage($archivePath, $version, 'full-journal-xml=>journal')) {
            $problems = json_encode($deployment->getWarningsAndErrors(), JSON_UNESCAPED_SLASHES);
            throw new RuntimeException(
                'The journal package could not be imported: ' . (is_string($problems) ? $problems : 'unknown error')
            );
        }
        fwrite(STDOUT, "Journal import completed\n");
        return true;
    }

    public function getAppSpecificDeployment($context, $user)
    {
        return new FullJournalImportExportDeployment($context, $user);
    }

    private function exportPackage($journal, string $archivePath): void
    {
        $outputDirectory = realpath(dirname($archivePath));
        if ($outputDirectory === false || !is_writable($outputDirectory)) {
            throw new InvalidArgumentException('The export directory is not writable');
        }
        if (file_exists($archivePath) || is_link($archivePath)) {
            throw new InvalidArgumentException('The export archive path is invalid');
        }
        $archivePath = $outputDirectory . DIRECTORY_SEPARATOR . basename($archivePath);
        $stagingPath = $this->createStagingDirectory();
        $journalPath = $stagingPath . DIRECTORY_SEPARATOR . 'journal.xml';
        $manifestPath = $stagingPath . DIRECTORY_SEPARATOR . 'manifest.xml';
        $completed = false;

        try {
            $deployment = $this->getAppSpecificDeployment($journal, null);
            $document = $deployment->exportContextData();
            $xml = $document->saveXML();
            if (!is_string($xml) || file_put_contents($journalPath, $xml) === false) {
                throw new RuntimeException('The journal XML could not be written');
            }
            $packageFiles = array_merge(['journal.xml'], $this->stageReferencedFiles($document, $stagingPath));
            if (file_put_contents($manifestPath, $this->createManifest($stagingPath, $packageFiles)) === false) {
                throw new RuntimeException('The package manifest could not be written');
            }
            $arguments = [
                '/bin/tar',
                '-czf',
                $archivePath,
                '-C',
                $stagingPath,
                'manifest.xml',
            ];
            array_push($arguments, ...$packageFiles);
            $process = new Process($arguments);
            $process->run();
            if (!$process->isSuccessful()) {
                $error = trim($process->getErrorOutput());
                throw new RuntimeException('The journal archive could not be created: ' . $error);
            }
            $completed = true;
        } finally {
            $this->removeStagingDirectory($stagingPath);
            if (!$completed && is_file($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    private function createManifest(string $stagingPath, array $packageFiles): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElement('full_journal_package');
        $root->setAttribute('application', PackageManifest::APPLICATION);
        $root->setAttribute('application_version', Application::get()->getCurrentVersion()->getVersionString());
        $root->setAttribute('format_version', PackageManifest::FORMAT_VERSION);
        $root->setAttribute('created_at', date(DATE_ATOM));
        $document->appendChild($root);
        $capabilities = $document->createElement('capabilities');
        foreach (['journal', 'users', 'workflow', 'metrics'] as $name) {
            $capability = $document->createElement('capability');
            $capability->setAttribute('name', $name);
            $capabilities->appendChild($capability);
        }
        $root->appendChild($capabilities);
        $files = $document->createElement('files');
        sort($packageFiles);
        foreach ($packageFiles as $path) {
            $absolutePath = $stagingPath . DIRECTORY_SEPARATOR . $path;
            $size = filesize($absolutePath);
            $checksum = hash_file('sha256', $absolutePath);
            if (!is_int($size) || !is_string($checksum)) {
                throw new RuntimeException('The package file metadata could not be calculated');
            }
            $file = $document->createElement('file');
            $file->setAttribute('path', $path);
            $file->setAttribute('size', (string) $size);
            $file->setAttribute('checksum', $checksum);
            $files->appendChild($file);
        }
        $root->appendChild($files);
        $xml = $document->saveXML();
        if (!is_string($xml)) {
            throw new RuntimeException('The package manifest could not be serialized');
        }
        return $xml;
    }

    private function stageReferencedFiles(DOMDocument $document, string $stagingPath): array
    {
        $filesDirectory = realpath((string) Config::getVar('files', 'files_dir'));
        if ($filesDirectory === false || is_link($filesDirectory)) {
            throw new RuntimeException('The application files directory is invalid');
        }
        $paths = [];
        $nodes = (new DOMXPath($document))->query('//*[local-name()="href"]');
        foreach ($nodes ?: [] as $node) {
            $path = $node->getAttribute('src');
            $this->validatePackagePath($path);
            if (isset($paths[$path])) {
                continue;
            }
            $source = realpath($filesDirectory . DIRECTORY_SEPARATOR . $path);
            if ($source === false || !is_file($source) || is_link($source)
                || !str_starts_with($source, $filesDirectory . DIRECTORY_SEPARATOR)
            ) {
                throw new RuntimeException('A referenced journal file is unavailable');
            }
            $destination = $stagingPath . DIRECTORY_SEPARATOR . $path;
            $directory = dirname($destination);
            if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
                throw new RuntimeException('A package file directory could not be created');
            }
            if (!copy($source, $destination)) {
                throw new RuntimeException('A referenced journal file could not be staged');
            }
            $paths[$path] = true;
        }
        $paths = array_keys($paths);
        sort($paths);
        return $paths;
    }

    private function validatePackagePath(string $path): void
    {
        if ($path === '' || $path[0] === '/' || str_contains($path, '\\') || str_contains($path, "\0")) {
            throw new InvalidArgumentException('A referenced journal file path is invalid');
        }
        foreach (explode('/', $path) as $component) {
            if ($component === '' || $component === '.' || $component === '..') {
                throw new InvalidArgumentException('A referenced journal file path is invalid');
            }
        }
    }

    private function removeStagingDirectory(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }

    private function createStagingDirectory(): string
    {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'full-journal-export-' . bin2hex(random_bytes(16));
        if (!mkdir($path, 0700)) {
            throw new RuntimeException('The export staging directory could not be created');
        }
        return $path;
    }

    private function absolutePath(string $path): string
    {
        if ($path !== '' && $path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }
        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            throw new RuntimeException('The current working directory is unavailable');
        }
        return $workingDirectory . DIRECTORY_SEPARATOR . $path;
    }
}
