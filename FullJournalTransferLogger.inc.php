<?php

abstract class FullJournalTransferLogger {
	abstract function log($string, $data=array());
}

class FullJournalTransferStdOutLogger extends FullJournalTransferLogger {
	function log($string, $data=array()) {
		printf($string, $data);
	}
}

class NullFullJournalTransferLogger extends FullJournalTransferLogger {
	function log($string, $data=array()) {
	}
}

?>