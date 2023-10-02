<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');

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

        AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION);

        switch ($command) {
            case 'import':
                break;
            case 'export':
                break;
            default:
                $this->usage($scriptName);
                break;
        }
    }

    public function usage($scriptName)
    {
        echo __('plugins.importexport.fullJournal.cliUsage', array(
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        )) . "\n";
    }
}
