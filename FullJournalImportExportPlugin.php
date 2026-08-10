<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer;

use APP\plugins\importexport\native\NativeImportExportPlugin;

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

    public function getAppSpecificDeployment($context, $user)
    {
        return new FullJournalImportExportDeployment($context, $user);
    }
}
