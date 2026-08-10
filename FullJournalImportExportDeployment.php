<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\plugins\importexport\native\NativeImportExportDeployment;

class FullJournalImportExportDeployment extends NativeImportExportDeployment
{
    private array $createdFiles = [];

    public function importPackage(
        string $archivePath,
        string $applicationVersion,
        string $rootFilter,
        ?ArchiveManager $archiveManager = null
    ): bool {
        $archiveManager = $archiveManager ?? new ArchiveManager();

        return $archiveManager->withExtractedPackage(
            $archivePath,
            $applicationVersion,
            function (string $stagingPath) use ($rootFilter): bool {
                $journalXml = file_get_contents($stagingPath . DIRECTORY_SEPARATOR . 'journal.xml');
                if ($journalXml === false) {
                    throw new \RuntimeException('The journal XML could not be read');
                }

                $this->setImportPath($stagingPath);
                try {
                    $this->import($rootFilter, $journalXml);
                    return !$this->isProcessFailed();
                } finally {
                    $this->setImportPath('');
                }
            }
        );
    }

    public function import($rootFilter, $importXml)
    {
        $this->createdFiles = [];
        $this->runNativeImport($rootFilter, $importXml);

        if ($this->isProcessFailed()) {
            $this->compensateCreatedFiles();
        } else {
            $this->createdFiles = [];
        }
    }

    public function recordCreatedFile(string $path): void
    {
        if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR) {
            throw new \InvalidArgumentException('A created file journal entry must use an absolute path');
        }
        $this->createdFiles[] = $path;
    }

    protected function runNativeImport($rootFilter, $importXml): void
    {
        parent::import($rootFilter, $importXml);
    }

    private function compensateCreatedFiles(): void
    {
        foreach (array_reverse($this->createdFiles) as $path) {
            if ((is_file($path) || is_link($path)) && !unlink($path)) {
                $this->addError(\PKP\core\PKPApplication::ASSOC_TYPE_NONE, 0, 'Failed to compensate an imported file');
            }
        }
        $this->createdFiles = [];
    }
}
