<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('plugins.importexport.native.NativeImportExportDeployment');

class FullJournalImportExportDeployment extends NativeImportExportDeployment
{
    public function __construct($context, $user = null)
    {
        parent::__construct($context, $user);
    }

    public function getSchemaFilename()
    {
        return 'fullJournal.xsd';
    }
}
