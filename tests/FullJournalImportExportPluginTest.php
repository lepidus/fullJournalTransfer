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
        $xmlPath = __DIR__ . '/samples/' . $xmlFile;
        $archivePath = __DIR__ . '/samples/' . $journalPath . '.tar.gz';

        $plugin->archiveFiles($xmlPath, $archivePath, $journalPath);
        $this->assertTrue(file_exists($archivePath));

        exec(Config::getVar('cli', 'tar') . ' -ztf ' . $archivePath, $archiveContent);
        $this->assertTrue(in_array($xmlFile, $archiveContent));

        unlink($archivePath);
    }
}
