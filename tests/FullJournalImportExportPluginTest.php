<?php

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
        $archivePath = tempnam(sys_get_temp_dir(), 'full-journal-transfer-');
        $this->assertNotFalse($archivePath);
        unlink($archivePath);
        $archivePath .= '.tar.gz';

        try {
            $plugin->archiveFiles($archivePath, $xmlPath, $journalFilesDir);
            $this->assertFileExists($archivePath);

            exec(Config::getVar('cli', 'tar') . ' -ztf ' . escapeshellarg($archivePath), $archiveContent);

            $this->assertContains($xmlFile, $archiveContent);
            $this->assertContains('journals/5/', $archiveContent);
            $this->assertContains('journals/5/articles/13/dummy.pdf', $archiveContent);
            $this->assertContains('journals/5/issues/7/dummy.pdf', $archiveContent);
            $this->assertNotContains($xmlPath, $archiveContent);
            $this->assertNotContains($journalFilesDir, $archiveContent);
        } finally {
            if (file_exists($archivePath)) {
                unlink($archivePath);
            }
        }
    }
}
