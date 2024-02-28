<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportDeployment');

class FullJournalImportExportPlugin extends ImportExportPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
        return $success;
    }

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

    public function display($args, $request)
    {
        parent::display($args, $request);

        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        switch (array_shift($args)) {
            case 'export':
                break;
            case 'import':
                break;
            default:
                $dispatcher = $request->getDispatcher();
                $dispatcher->handle404();
        }
    }

    public function executeCLI($scriptName, &$args)
    {
        $args[] = '--no-embed';
        $opts = $this->parseOpts($args, ['no-embed', 'use-file-urls']);
        $command = array_shift($args);
        $archivePath = array_shift($args);

        AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION);

        if ($archivePath && $this->isRelativePath($archivePath)) {
            $archivePath = PWD . '/' . $archivePath;
        }
        $outputDir = dirname($archivePath);
        if (!is_writable($outputDir) || (file_exists($archivePath) && !is_writable($archivePath))) {
            echo __('plugins.importexport.common.cliError') . "\n";
            echo __('plugins.importexport.common.export.error.outputFileNotWritable', ['param' => $archivePath]) . "\n\n";
            $this->usage($scriptName);
            return;
        }

        switch ($command) {
            case 'import':
                $userName = array_shift($args);
                $userDAO = DAORegistry::getDAO('UserDAO');
                $user = $userDAO->getByUsername($userName);

                if (!$user) {
                    if ($userName != '') {
                        echo __('plugins.importexport.common.cliError') . "\n";
                        echo __('plugins.importexport.native.error.unknownUser', array('userName' => $userName)) . "\n\n";
                    }
                    $this->usage($scriptName);
                    return;
                }

                $request = Application::get()->getRequest();
                if (!$request->getUser()) {
                    Registry::set('user', $user);
                }

                $filter = $this->getJournalImportExportFilter(null, $user);
                $imported = $this->importJournal($archivePath, $user, $filter, $opts);
                $deployment = $filter->getDeployment();

                $validationErrors = array_filter(libxml_get_errors(), function ($a) {
                    return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
                });

                $errorTypes = array(
                    ASSOC_TYPE_ISSUE => 'issue.issue',
                    ASSOC_TYPE_SUBMISSION => 'submission.submission',
                    ASSOC_TYPE_SECTION => 'section.section',
                    ASSOC_TYPE_JOURNAL => 'journal.journal',
                );
                foreach ($errorTypes as $assocType => $localeKey) {
                    $foundWarnings = $deployment->getProcessedObjectsWarnings($assocType);
                    if (!empty($foundWarnings)) {
                        echo __('plugins.importexport.common.warningsEncountered') . "\n";
                        $i = 0;
                        foreach ($foundWarnings as $foundWarningMessages) {
                            if (count($foundWarningMessages) > 0) {
                                echo ++$i . '.' . __($localeKey) . "\n";
                                foreach ($foundWarningMessages as $foundWarningMessage) {
                                    echo '- ' . $foundWarningMessage . "\n";
                                }
                            }
                        }
                    }
                }

                $foundErrors = false;
                foreach ($errorTypes as $assocType => $localeKey) {
                    $currentErrors = $deployment->getProcessedObjectsErrors($assocType);
                    if (!empty($currentErrors)) {
                        echo __('plugins.importexport.common.errorsOccured') . "\n";
                        $i = 0;
                        foreach ($currentErrors as $currentErrorMessages) {
                            if (count($currentErrorMessages) > 0) {
                                echo ++$i . '.' . __($localeKey) . "\n";
                                foreach ($currentErrorMessages as $currentErrorMessage) {
                                    echo '- ' . $currentErrorMessage . "\n";
                                }
                            }
                        }
                        $foundErrors = true;
                    }
                }

                if ($foundErrors || !empty($validationErrors)) {
                    foreach (array_keys($errorTypes) as $assocType) {
                        $deployment->removeImportedObjects($assocType);
                    }
                    echo __('plugins.importexport.common.validationErrors') . "\n";
                    $i = 0;
                    foreach ($validationErrors as $validationError) {
                        echo ++$i . '. Line: ' . $validationError->line . ' Column: ' . $validationError->column . ' > ' . $validationError->message . "\n";
                    }
                }

                if ($imported) {
                    echo __('plugins.importexport.fullJournal.importCompleted') . "\n";
                }

                return;
            case 'export':
                $journalPath = array_shift($args);
                $journalDao = DAORegistry::getDAO('JournalDAO');

                $journal = $journalDao->getByPath($journalPath);
                if (!$journal) {
                    if ($journalPath != '') {
                        echo __('plugins.importexport.common.cliError') . "\n";
                        echo __('plugins.importexport.common.error.unknownJournal', ['journalPath' => $journalPath]) . "\n\n";
                    }
                    $this->usage($scriptName);
                    return;
                }
                if ($archivePath != '') {
                    if ($this->exportJournal($journal, $archivePath, $opts)) {
                        echo __('plugins.importexport.fullJournal.exportCompleted') . "\n";
                    }
                    return;
                }
                break;
        }
        $this->usage($scriptName);
    }

    public function importJournal($archivePath, $user, $filter, $opts = [])
    {
        $extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($archivePath, '.tar.gz');

        if (!mkdir($extractDir)) {
            echo "Could not create directory "  . $extractDir . "\n";
            return false;
        }

        exec(
            Config::getVar('cli', 'tar') . ' -xzf ' .
            escapeshellarg($archivePath) .
            ' -C ' . escapeshellarg($extractDir)
        );

        $xmlFile = null;
        foreach (scandir($extractDir) as $item) {
            if (strtolower(substr($item, -4)) == '.xml') {
                $xmlFile = $extractDir . DIRECTORY_SEPARATOR . $item;
            }
        }

        $xml = file_get_contents($xmlFile);

        $filter->getDeployment()->setImportPath($extractDir);
        $content = $filter->execute($xml);

        import('lib.pkp.classes.file.FileManager');
        $fileManager = new FileManager();
        $fileManager->rmtree($extractDir);

        return true;
    }

    public function exportJournal($journal, $archivePath, $opts)
    {
        $journalPath = $journal->getPath();
        $xmlPath = '/tmp/' . $journalPath . '.xml';

        $filter = $this->getJournalImportExportFilter($journal, null, false);
        $filter->setOpts($opts);

        libxml_use_internal_errors(true);
        $journalXml = $filter->execute($journal);

        $errors = array_filter(libxml_get_errors(), function ($error) {
            return $error->level == LIBXML_ERR_ERROR || $error->level == LIBXML_ERR_FATAL;
        });

        if (!empty($errors)) {
            $this->displayXMLValidationErrors($errors, $xml);
            return false;
        }

        $xml = $journalXml->saveXml();

        if (empty($xml)) {
            return false;
        }

        if (!file_put_contents($xmlPath, $xml)) {
            return false;
        }

        import('lib.pkp.classes.file.ContextFileManager');
        $contextFileManager = new ContextFileManager($journal->getId());
        $journalFilesDir = $contextFileManager->getBasePath();
        $this->archiveFiles($archivePath, $xmlPath, $journalFilesDir);
        unlink($xmlPath);

        if (!file_exists($archivePath)) {
            return false;
        }

        return true;
    }

    public function getJournalImportExportFilter($context, $user, $isImport = true)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');

        if ($isImport) {
            $journalFilters = $filterDao->getObjectsByGroup('native-xml=>journal');
        } else {
            $journalFilters = $filterDao->getObjectsByGroup('journal=>native-xml');
        }

        assert(count($journalFilters) == 1);
        $filter = array_shift($journalFilters);
        $filter->setDeployment(new FullJournalImportExportDeployment($context, $user));

        return $filter;
    }

    public function archiveFiles($archivePath, $xmlPath, $journalFilesDir)
    {
        import('lib.pkp.classes.file.FileArchive');
        $tarCommand = Config::getVar('cli', 'tar');

        if (FileArchive::tarFunctional() && $tarCommand) {
            $xmlDir = dirname($xmlPath);

            $command = "$tarCommand -czf " .
                escapeshellarg($archivePath) . " " .
                "-C " . escapeshellarg($xmlDir) . " " .
                escapeshellarg(basename($xmlPath));

            if (is_dir($journalFilesDir)) {
                $journalParentDir = dirname($journalFilesDir, 2);
                $journalDir = basename(dirname($journalFilesDir)) . DIRECTORY_SEPARATOR . basename($journalFilesDir);

                $command .= " -C " .
                    escapeshellarg($journalParentDir) . " " .
                    escapeshellarg($journalDir);
            }

            exec($command);
        } else {
            throw new Exception('No archive tool is available!');
        }
    }

    public function parseOpts(&$args, $optCodes)
    {
        $newArgs = [];
        $opts = [];
        $sticky = null;
        foreach ($args as $arg) {
            if ($sticky) {
                $opts[$sticky] = $arg;
                $sticky = null;
                continue;
            }
            if (substr($arg, 0, 2) != '--') {
                $newArgs[] = $arg;
                continue;
            }
            $opt = substr($arg, 2);
            if (in_array($opt, $optCodes)) {
                $opts[$opt] = true;
                continue;
            }
            if (in_array($opt . ":", $optCodes)) {
                $sticky = $opt;
                continue;
            }
        }
        $args = $newArgs;
        return $opts;
    }

    public function usage($scriptName)
    {
        echo __('plugins.importexport.fullJournal.cliUsage', array(
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        )) . "\n";
    }
}
