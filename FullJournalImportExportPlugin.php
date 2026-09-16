<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\importexport\fullJournalTransfer\package\FullJournalPackageExporter;
use APP\plugins\importexport\native\NativeImportExportPlugin;
use InvalidArgumentException;
use PKP\config\Config;
use RuntimeException;

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
        try {
            return $this->executeCLICommand($scriptName, $args);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->exitWithCLIError($exception->getMessage());
            return false;
        }
    }

    private function executeCLICommand($scriptName, &$args)
    {
        $command = array_shift($args);
        if ($command === 'usage' && $args === []) {
            $this->usage($scriptName);
            return true;
        }
        $archivePath = array_shift($args);
        $identifier = array_shift($args);
        if (!in_array($command, ['export', 'import'], true) || !$archivePath || !$identifier || $args !== []) {
            $this->usage($scriptName);
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.invalidFullJournalCommandArguments'));
        }
        $archivePath = $this->absolutePath($archivePath);

        if ($command === 'export') {
            $journal = Application::get()->getContextDAO()->getByPath($identifier);
            if (!$journal) {
                throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.journalNotFound'));
            }
            $deployment = $this->getAppSpecificDeployment($journal, null);
            $exporter = new FullJournalPackageExporter(
                (string) Config::getVar('files', 'files_dir')
            );
            $exporter->export($deployment, $archivePath, function (string $message): void {
                $this->writeCLIOutput($message);
            });
            $this->writeCLIOutput(__('plugins.importexport.fullJournal.progress.journalExportCompleted'));
            return true;
        }

        $user = Repo::user()->getByUsername($identifier, true);
        if (!$user) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.importUserNotFound'));
        }
        $deployment = $this->getAppSpecificDeployment(Application::get()->getContextDAO()->newDataObject(), $user);
        $progress = function (string $message): void {
            $this->writeCLIOutput($message);
        };
        if (!$deployment->importPackage($archivePath, 'full-journal-xml=>journal', null, $progress)) {
            $message = __('plugins.importexport.fullJournal.error.journalImportFailed');
            $problems = $this->formatCLIProblems($deployment->getWarningsAndErrors());
            if ($problems !== '') {
                $message .= "\n\n" . $problems;
            }
            throw new RuntimeException($message);
        }
        $problems = $deployment->getWarningsAndErrors();
        $warnings = $this->formatCLIProblems(['warnings' => $problems['warnings'] ?? []]);
        if ($warnings !== '') {
            $this->writeCLIOutput($warnings);
        }
        $this->writeCLIOutput(__('plugins.importexport.fullJournal.progress.journalImportCompleted'));
        return true;
    }

    protected function writeCLIOutput(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    protected function exitWithCLIError(string $message): void
    {
        fwrite(STDERR, __('plugins.importexport.fullJournal.error.cli', ['message' => $message]) . PHP_EOL);
        exit(1);
    }

    private function formatCLIProblems(array $problems): string
    {
        $sections = [];
        $problemTypes = [
            'warnings' => __('plugins.importexport.common.warningsEncountered'),
            'errors' => __('plugins.importexport.common.errorsOccured'),
        ];

        foreach ($problemTypes as $problemType => $title) {
            if (empty($problems[$problemType])) {
                continue;
            }

            $lines = [$title];
            $typeIndex = 0;
            foreach ($problems[$problemType] as $objectTypeName => $objectTypeGroups) {
                foreach ($objectTypeGroups as $objectTypeItems) {
                    if ($objectTypeItems === []) {
                        continue;
                    }

                    $lines[] = ++$typeIndex . '.' . $objectTypeName;
                    foreach ($objectTypeItems as $itemMessages) {
                        foreach ($itemMessages as $message) {
                            $lines[] = '- ' . $message;
                        }
                    }
                }
            }
            $sections[] = implode("\n", $lines);
        }

        return implode("\n", $sections);
    }

    public function getAppSpecificDeployment($context, $user)
    {
        return new FullJournalImportExportDeployment($context, $user);
    }

    private function absolutePath(string $path): string
    {
        if ($path !== '' && $path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }
        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            throw new RuntimeException(__('plugins.importexport.fullJournal.error.currentWorkingDirectoryUnavailable'));
        }
        return $workingDirectory . DIRECTORY_SEPARATOR . $path;
    }
}
