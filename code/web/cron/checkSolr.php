<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../sys/SolrUtils.php';
require_once __DIR__ . '/../sys/Utils/SystemUtils.php';

if (SystemUtils::getSupervisionBackend() == 'systemd') {
	echo("Solr is supervised by systemd (aspen-solr@$serverName.service), nothing to do\r\n");
	die();
}

SolrUtils::startSolr();

global $aspen_db;
$aspen_db = null;
$configArray = null;

die();

/////// END OF PROCESS ///////

function execInBackground($cmd) {
	/** @noinspection PhpStrFunctionsInspection */
	if (substr(php_uname(), 0, 7) == "Windows") {
		pclose(popen("start /B " . $cmd, "r"));
	} else {
		exec($cmd . " > /dev/null &");
	}
}