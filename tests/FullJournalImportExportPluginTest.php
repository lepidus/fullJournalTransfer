<?php

use Symfony\Component\Process\Process;

import('lib.pkp.tests.PKPTestCase');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportPlugin');

class FullJournalImportExportPluginTest extends PKPTestCase
{
    public function testArchiveFiles()
    {
        $plugin = new FullJournalImportExportPlugin();

        $samplesDir = __DIR__ . '/samples';
        $xmlFile = 'journal.xml';
        $xmlPath = $samplesDir . '/' . $xmlFile;
        $journalFilesDir = $samplesDir . '/journals/5';
        $archivePath = $samplesDir . '/publicknowledge.tar.gz';

        $plugin->archiveFiles($archivePath, $xmlPath, $journalFilesDir);
        $this->assertTrue(file_exists($archivePath));

        $process = new Process([Config::getVar('cli', 'tar'), '-ztf', $archivePath]);
        $archiveContent = explode("\n", trim($process->mustRun()->getOutput()));
        $this->assertTrue(in_array($xmlFile, $archiveContent));
        $this->assertTrue(in_array('journals/5/', $archiveContent));

        unlink($archivePath);
    }
}
