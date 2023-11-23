<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
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
        $command = array_shift($args);
        $xmlFile = array_shift($args);
        $journalPath = array_shift($args);

        AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

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
                $this->importJournal(file_get_contents($xmlFile), null, null);
                return;
            case 'export':
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
                    file_put_contents($xmlFile, $this->exportJournal($journal, null));
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

        return $filter->execute($importXml);
    }

    public function exportJournal($journal, $user, &$filter = null)
    {
        $xml = '';

        if (!$filter) {
            $filter = $this->getJournalImportExportFilter($journal, $user, false);
        }

        libxml_use_internal_errors(true);
        $journalXml = $filter->execute($journal, true);
        $xml = $journalXml->saveXml();
        $errors = array_filter(libxml_get_errors(), function ($a) {
            return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
        });
        if (!empty($errors)) {
            $this->displayXMLValidationErrors($errors, $xml);
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

    public function usage($scriptName)
    {
        echo __('plugins.importexport.fullJournal.cliUsage', array(
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        )) . "\n";
    }
}
