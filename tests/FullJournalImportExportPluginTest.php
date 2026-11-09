<?php

use Symfony\Component\Process\Process;

import('lib.pkp.tests.PKPTestCase');
import('plugins.importexport.fullJournalTransfer.FullJournalImportExportPlugin');
import('plugins.importexport.fullJournalTransfer.classes.ExtractedPackageDirectory');

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

    public function testImportRejectsPackageLinksWithoutDeletingTheirTargets()
    {
        $plugin = new FullJournalImportExportPlugin();
        $root = sys_get_temp_dir() . '/fjt-link-' . bin2hex(random_bytes(8));
        $sourceDir = $root . '/source';
        $outsideDir = $root . '/outside';
        mkdir($sourceDir, 0700, true);
        mkdir($outsideDir, 0700);
        file_put_contents($outsideDir . '/sentinel.txt', 'outside file');
        // The unsafe href makes the import stop in the reference check, which used to clean up with rmtree().
        file_put_contents(
            $sourceDir . '/journal.xml',
            '<journal xmlns="http://pkp.sfu.ca"><file><href src="/etc/passwd"/></file></journal>'
        );
        symlink($outsideDir, $sourceDir . '/evil');
        $archivePath = $root . '/fjt-link-package-' . bin2hex(random_bytes(8)) . '.tar.gz';
        $process = new Process([Config::getVar('cli', 'tar'), '-czf', $archivePath, '-C', $sourceDir, 'journal.xml', 'evil']);
        $process->mustRun();
        $filter = $this->getMockBuilder(stdClass::class)->addMethods(['execute', 'getDeployment'])->getMock();
        $filter->expects($this->never())->method('execute');

        try {
            ob_start();
            $imported = $plugin->importJournal($archivePath, null, $filter);
            ob_end_clean();
            $this->assertFalse($imported);
            $this->assertSame('outside file', file_get_contents($outsideDir . '/sentinel.txt'));
            $this->assertDirectoryDoesNotExist(sys_get_temp_dir() . '/' . basename($archivePath, '.tar.gz'));
        } finally {
            (new ExtractedPackageDirectory())->remove($root);
        }
    }
}
