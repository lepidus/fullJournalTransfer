<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\core\Application;
use APP\facades\Repo;
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
            $deployment = $this->getAppSpecificDeployment($journal, null);
            $exporter = new FullJournalPackageExporter(
                (string) Config::getVar('files', 'files_dir'),
                Application::get()->getCurrentVersion()->getVersionString()
            );
            $exporter->export($deployment, $archivePath);
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
