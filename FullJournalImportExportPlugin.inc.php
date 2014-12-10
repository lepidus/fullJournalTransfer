<?php

/**
 * Copyright (c) 2014 Instituto Brasileiro de Informação em Ciência e Tecnologia
 * Author: Giovani Pieri <giovani@lepidus.com.br>
 *
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 */


import('classes.plugins.ImportExportPlugin');

class FullJournalImportExportPlugin extends ImportExportPlugin {

	function FullJournalImportExportPlugin() {
		parent::ImportExportPlugin();
	}

	function register($category, $path) {
		$success = parent::register($category, $path);
		$this->addLocaleData();
		return $success;
	}

	function getName() {
		return 'FullJournalImportExportPlugin';
	}

	function getDisplayName() {
		return __('plugins.importexport.fullJournal.displayName');
	}

	function getDescription() {
		return __('plugins.importexport.fullJournal.description');
	}

	function display(&$args, $request) {
		parent::display($args, $request);

		$templateMgr =& TemplateManager::getManager();
		$journal =& $request->getJournal();

		switch (array_shift($args)) {
			case 'export':
				@set_time_limit(0);
				$errors = array();
				$success = $this->handleExport($journal, $errors);
				if ($success === false) {
					return $errors;
				}
				break;

			case 'import':
				AppLocale::requireComponents(LOCALE_COMPONENT_OJS_EDITOR, LOCALE_COMPONENT_OJS_AUTHOR);
				import('classes.file.TemporaryFileManager');

				$temporaryFileManager = new TemporaryFileManager();
				if (($existingFileId = $request->getUserVar('temporaryFileId'))) {
					// The user has just entered more context. Fetch an existing file.
					$temporaryFile = $temporaryFileManager->getFile($existingFileId, $user->getId());
				} else {
					$user = Request::getUser();
					$temporaryFile = $temporaryFileManager->handleUpload('importFile', $user->getId());
				}
				if (!$temporaryFile) {
					$templateMgr->assign('error', 'plugins.importexport.fullJournal.error.uploadFailed');
					return $templateMgr->display($this->getTemplatePath() . 'importError.tpl');
				}

				@set_time_limit(0);
				$errors = array();
				if ($this->handleImport($temporaryFile->getFilePath(), $errors)) {
					return $templateMgr->display($this->getTemplatePath() . 'importSuccess.tpl');
				} else {
					$templateMgr->assign_by_ref('errors', $errors);
					return $templateMgr->display($this->getTemplatePath() . 'importError.tpl');
				}
				break;
			default:
				$this->setBreadcrumbs();
				$templateMgr->display($this->getTemplatePath() . 'index.tpl');
		}
	}

	function executeCLI($scriptName, &$args) {
		$command = array_shift($args);
		$tarFile = array_shift($args);
		AppLocale::requireComponents(LOCALE_COMPONENT_OJS_DEFAULT,
			LOCALE_COMPONENT_APPLICATION_COMMON,
			LOCALE_COMPONENT_OJS_MANAGER,
			LOCALE_COMPONENT_OJS_AUTHOR,
			LOCALE_COMPONENT_PKP_USER);

		$journalDao =& DAORegistry::getDAO('JournalDAO');

		if ($tarFile[0] !== '/') {
			$tarFile = PWD . '/' . $tarFile;
		}

		switch ($command) {
			case 'import':
				import('classes.file.TemporaryFileManager');
				if (!file_exists($tarFile)) {
					echo __('plugins.importexport.fullJournal.import.error') . "\n";
					echo __('plugins.importexport.fullJournal.cliError.fileNotFound', array('tarFile' => $tarFile)) . "\n";
					return false;
				}

				@set_time_limit(0);
				$errors = array();
				if ($this->handleImport($tarFile, $errors)) {
					echo __('plugins.importexport.fullJournal.import.success.description') . "\n";
					return true;
				} else {
					echo __('plugins.importexport.fullJournal.cliError') . "\n";
					foreach ($errors as $error) {
						echo $error;
					}
					return false;
				}
				break;
			case 'export':
				$journalPath = array_shift($args);
				$journal =& $journalDao->getJournalByPath($journalPath);
				if (!$journal) {
					if ($journalPath != '') {
						echo __('plugins.importexport.fullJournal.cliError') . "\n";
						echo __('plugins.importexport.fullJournal.error.unknownJournal', array('journalPath' => $journalPath)) . "\n\n";
					}
					$this->usage($scriptName);
					return;
				}

				if ($tarFile != '') {
					$errors = array();
					$this->handleExport($journal, $errors, $tarFile);
					if (!empty($errors)) {
						echo __('plugins.importexport.fullJournal.cliError') . "\n";
						echo __('plugins.importexport.fullJournal.export.error.couldNotWrite', array('fileName' => $tarFile)) . "\n\n";
					}
					return;
				}
				break;
			default:
				$this->usage($scriptName);
				break;
		}
	}

	function usage($scriptName) {
		echo __('plugins.importexport.fullJournal.cliUsage', array(
			'scriptName' => $scriptName,
			'pluginName' => $this->getName()
		)) . "\n";

	}

	function handleExport(&$journal, &$errors, $outputFile=null) {
		$this->import('XMLAssembler');
		import('classes.file.PublicFileManager');
		import('classes.file.JournalFileManager');

		$isTarOk = $this->_checkForTar();
		if (is_array($isTarOk)) {
			$errors = $isTarOk;
			return false;
		}
		$tmpPath = $this->_getTmpPath();
		if (is_array($tmpPath)) {
			$errors = $tmpPath;
			return false;
		}

		$xmlAbsolutePath = $this->_generateXml($journal, $tmpPath);
		if ($xmlAbsolutePath === false) {
			return false;
		}
		$publicFileManager = new PublicFileManager();
		$journalFileManager = new JournalFileManager($journal);
		$journalPublicPath = $publicFileManager->getJournalFilesPath($journal->getId());
		$sitePublicPath = $publicFileManager->getSiteFilesPath();
		$journalFilesPath = $journalFileManager->filesDir;

		$exportFiles = array();
		$exportFiles[$xmlAbsolutePath] = "journal.xml";
		$exportFiles[$journalPublicPath] = "public";
		$exportFiles[$journalFilesPath] = "files";
		$exportFiles[$sitePublicPath] = "sitePublic";

		// Package the files up as a single tar before going on.
		$finalExportFileName = $tmpPath . $journal->getPath() . ".tar.gz";
		$this->tarFiles($tmpPath, $finalExportFileName, $exportFiles);

		if (is_null($outputFile)) {
			header('Content-Type: application/x-gtar');
			header('Cache-Control: private');
			header('Content-Disposition: attachment; filename="' . $journal->getPath() . '.tar.gz"');
			readfile($finalExportFileName);
		} else {
			$outputFileExtension = '.tar.gz';
			if (substr($outputFile, -strlen($outputFileExtension)) != $outputFileExtension) {
				$outputFile .= $outputFileExtension;
			}
			$outputDir = dirname($outputFile);
			if (empty($outputDir)) $outputDir = getcwd();
			if (!is_writable($outputDir)) {
				$this->_removeTemporaryFiles(array_keys($xmlAbsolutePath));
				$errors[] = array('plugins.importexport.common.export.error.outputFileNotWritable', $outputFile);
				return $errors;
			}
			$fileManager = new FileManager();
			$fileManager->copyFile($finalExportFileName, $outputFile);
		}

		$this->_removeTemporaryFiles(array($xmlAbsolutePath, $finalExportFileName));
		return true;
	}

	function _generateXml(&$journal, $exportPath) {
		$assembler = new XMLAssembler($exportPath, $journal);
		$xmlAbsolutePath = $assembler->exportJournal();
		return $xmlAbsolutePath;
	}

	function handleImport($tarFile, &$errors) {
		$this->import('XMLDisassembler');
		$this->import('IdTranslationTable');
		$this->import('FullJournalImportExportLogger');

		$errors = $this->_checkForTar();
		if (is_array($errors)) {
			return false;
		}
		$tmpPath = $this->_getTmpPath();
		if (is_array($tmpPath)) {
			$errors = $tmpPath;
			return false;
		}

		$this->_untarFile($tmpPath, $tarFile);

		$xmlFileName = $tmpPath . 'journal.xml';
		$journalFolderPath = $tmpPath . 'files';
		$publicFolderPath = $tmpPath . 'public';
		$siteFolderPath = $tmpPath . 'sitePublic';
		if (!file_exists($xmlFileName) || !file_exists($journalFolderPath) ||
				!file_exists($publicFolderPath) || !file_exists($siteFolderPath)) {
			unlink($tarFile);
			$errors[] = array(__('plugins.importexport.fullJournal.import.error.invalidPackage'));
			return $errors;
		}

		try {
			$disassembler = new XMLDisassembler($xmlFileName, $publicFolderPath, $siteFolderPath, $journalFolderPath);
			$disassembler->startImporting();
			$this->_removeTemporaryFiles(array($xmlFileName, $journalFolderPath,
											$publicFolderPath, $siteFolderPath));
		} catch (Exception $e) {
			$this->_removeTemporaryFiles(array($xmlFileName, $journalFolderPath,
											$publicFolderPath, $siteFolderPath));
			$errors = array($e->getMessage());
			return false;
		}

		return true;
	}


	function _checkForTar() {
		$tarBinary = Config::getVar('cli', 'tar');
		if (empty($tarBinary) || !is_executable($tarBinary)) {
			$result = array(__('manager.plugins.tarCommandNotFound'));
		} else {
			$result = true;
		}
		$this->_checkedForTar = true;
		return $result;
	}

	function _getTmpPath() {
		$tmpPath = Config::getVar('files', 'files_dir') . '/fullJournalImportExport';
		if (!file_exists($tmpPath)) {
			$fileManager = new FileManager();
			$fileManager->mkdir($tmpPath);
		}
		if (!is_writable($tmpPath)) {
			$errors = array(__('plugins.importexport.common.export.error.outputFileNotWritable', $tmpPath));
			return $errors;
		}
		return realpath($tmpPath) . '/';
	}

	function _removeTemporaryFiles($tempfiles) {
		foreach ($tempfiles as $tempfile) {
			if (is_dir($tempfile)) {
				$files = array_diff(scandir($tempfile), array('..', '.'));
				foreach ($files as &$file) {
					$file = $tempfile . DIRECTORY_SEPARATOR . $file;
				}
				$this->_removeTemporaryFiles($files);
				rmdir($tempfile);
			} else if (is_file($tempfile)) {
				unlink($tempfile);
			}
		}
	}

	function tarFiles($targetPath, $targetFile, $sourceFiles) {
		assert($this->_checkedForTar);

		$tarCommand = Config::getVar('cli', 'tar') . ' -czf ' . escapeshellarg($targetFile);

		// Transform original path into relative path.
		foreach($sourceFiles as $originFile=>$nameInTar) {
			$originFile = rtrim($originFile, '/');
			$nameInTar = rtrim($nameInTar, '/');
			$tarCommand .= ' --transform=' . escapeshellarg("s,^$originFile,$nameInTar,");
		}

		$tarCommand .= ' -P'; // Allow absolute paths. The transform will render them relative.
		$tarCommand .= " --hard-dereference"; // Dereference hard-links
		$tarCommand .= ' --owner 0 --group 0 --'; // Do not reveal our webserver user by forcing root as owner.

		foreach($sourceFiles as $originFile=>$nameInTar) {
			$originFile = rtrim($originFile, '/');
			$nameInTar = rtrim($nameInTar, '/');
			$tarCommand .= ' ' . escapeshellarg($originFile);
		}

		exec($tarCommand);
	}

	function _untarFile($targetPath, $targetFile) {
		assert($this->_checkedForTar);
		$tarCommand = Config::getVar('cli', 'tar') . ' -xzf ' . escapeshellarg($targetFile);
		$tarCommand .= ' -C ' . escapeshellarg($targetPath);
		exec($tarCommand);
	}

}

?>
