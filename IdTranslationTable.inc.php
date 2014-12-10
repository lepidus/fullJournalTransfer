<?php

class IdTranslationTable {

	var $translationTable;
	var $supportedObjectClassesNames;

	function IdTranslationTable($supportedObjectClasses) {
		$this->supportedObjectClassesNames = $supportedObjectClasses;

		$this->translationTable = array();
		foreach ($supportedObjectClasses as $supportedObjectClassName => $supportedObjectClassId) {
			$this->translationTable[$supportedObjectClassId] = array();
		}
	}

	function register($objectClass, $oldId, $newId) {
		if (!isset($this->translationTable[$objectClass])) {
			die("Fatal error: unsupported object class");
		} else {
			$objectClassTable =& $this->translationTable[$objectClass]; 
			$objectClassTable[$oldId] = $newId;
		}
	}

	function resolve($objectClass, $oldId) {
		if (!array_key_exists($objectClass, $this->translationTable)) {
			die("Fatal error: unsupported object class");
		}
		if ($oldId === null) {
			return null;
		}
		
		$objectClassTable =& $this->translationTable[$objectClass];
		if (!array_key_exists($oldId, $objectClassTable)) {
			if ($oldId === 0) {
				return 0;
			}
			$objectName = array_search($objectClass, $translationTable);

			throw new Exception(__('plugins.importexport.fullJournalTransfer.error.unkownObject', array("objectName" => $objectName, "id" => $oldId)));
		}

		return $objectClassTable[$oldId];
	}
}

?>