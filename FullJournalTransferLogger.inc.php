<?php

/**
 * Copyright (c) 2014 Instituto Brasileiro de Informação em Ciência e Tecnologia 
 * Author: Giovani Pieri <giovani@lepidus.com.br>
 *
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 */

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