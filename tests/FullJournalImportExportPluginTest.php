<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportPlugin');

class FullJournalImportExportPluginTest extends PKPTestCase
{
    public function testArchiveFiles()
    {
        $plugin = new FullJournalImportExportPlugin();
        $journalPath = 'publicknowledge';
        $xmlFile = 'journal.xml';
        $dest = __DIR__ . '/samples';
        $xmlPath = __DIR__ . '/samples/' . $xmlFile;

        $archiveFile = $plugin->archiveFiles($xmlPath, $dest, $journalPath);
        $this->assertTrue(file_exists($archiveFile));

        exec(Config::getVar('cli', 'tar') . ' -ztf ' . $archiveFile, $archiveContent);
        $this->assertTrue(in_array($xmlFile, $archiveContent));

        unlink($archiveFile);
    }
}
