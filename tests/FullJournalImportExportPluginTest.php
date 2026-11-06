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

    public function testImportStopsBeforeFilteringWhenAFileReferenceLeavesThePackage()
    {
        $plugin = new FullJournalImportExportPlugin();
        $sourceDir = sys_get_temp_dir() . '/fjt-unsafe-source-' . bin2hex(random_bytes(8));
        mkdir($sourceDir, 0700);
        file_put_contents(
            $sourceDir . '/journal.xml',
            '<journal xmlns="http://pkp.sfu.ca"><file><href src="/etc/passwd"/></file></journal>'
        );
        $archivePath = sys_get_temp_dir() . '/fjt-unsafe-' . bin2hex(random_bytes(8)) . '.tar.gz';
        $process = new Process([Config::getVar('cli', 'tar'), '-czf', $archivePath, '-C', $sourceDir, 'journal.xml']);
        $process->mustRun();
        $filter = $this->getMockBuilder(stdClass::class)->addMethods(['execute', 'getDeployment'])->getMock();
        $filter->expects($this->never())->method('execute');

        try {
            ob_start();
            $imported = $plugin->importJournal($archivePath, null, $filter);
            ob_end_clean();
        } finally {
            unlink($archivePath);
            unlink($sourceDir . '/journal.xml');
            rmdir($sourceDir);
        }

        $this->assertFalse($imported);
        $this->assertDirectoryDoesNotExist(sys_get_temp_dir() . '/' . basename($archivePath, '.tar.gz'));
    }
}
