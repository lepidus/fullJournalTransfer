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
        $xmlFile = array_shift($args);

        AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION);

        if ($xmlFile && $this->isRelativePath($xmlFile)) {
            $xmlFile = PWD . '/' . $xmlFile;
        }
        $outputDir = dirname($xmlFile);
        if (!is_writable($outputDir) || (file_exists($xmlFile) && !is_writable($xmlFile))) {
            echo __('plugins.importexport.common.cliError') . "\n";
            echo __('plugins.importexport.common.export.error.outputFileNotWritable', ['param' => $xmlFile]) . "\n\n";
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

                $deployment = $this->importJournal(file_get_contents($xmlFile), null, null);

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
                } else {
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
                if ($xmlFile != '') {
                    file_put_contents($xmlFile, $this->exportJournal($journal, null, $opts));
                    $this->archiveFiles($xmlFile, $dest, $journalPath);
                    return;
                }
                break;
        }
        $this->usage($scriptName);
    }

    public function importJournal($importXml, $journal, $user, &$filter = null)
    {
        if (!$filter) {
            $filter = $this->getJournalImportExportFilter($journal, $user);
        }

        $content = $filter->execute($importXml);

        $journal = $filter->getDeployment()->getContext();

        return $filter->getDeployment();
    }

    public function exportJournal($journal, $user, $opts)
    {
        $xml = '';
        $filter = $this->getJournalImportExportFilter($journal, $user, false);
        $filter->setOpts($opts);

        libxml_use_internal_errors(true);
        $journalXml = $filter->execute($journal);
        $errors = array_filter(libxml_get_errors(), function ($a) {
            return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
        });
        if (!empty($errors)) {
            $this->displayXMLValidationErrors($errors, $xml);
        }
        $xml = $journalXml->saveXml();

        if ($xml) {
            echo __('plugins.importexport.fullJournal.exportCompleted') . "\n";
        }

        return $xml;
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

    public function archiveFiles($xmlPath, $dest, $journalPath)
    {
        $xmlDir = dirname($xmlPath);
        $xmlFile = basename($xmlPath);
        $archivePath = $dest . DIRECTORY_SEPARATOR . $journalPath . '.tar.gz';

        import('lib.pkp.classes.file.FileArchive');
        if (FileArchive::tarFunctional()) {
            exec(
                Config::getVar('cli', 'tar') . ' -c -z ' .
                    '-f ' . escapeshellarg($archivePath) . ' ' .
                    '-C ' . escapeshellarg($xmlDir) . ' ' .
                    escapeshellarg($xmlFile)
            );
        } else {
            throw new Exception('No archive tool is available!');
        }

        return $archivePath;
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
