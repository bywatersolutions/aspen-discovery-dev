<?php
// run-pending-db-updates.php -- bring a site's database schema current.
//
// usage: php run-pending-db-updates.php <sitename>
//
// Run by aspen-create-site and aspen-migrate-from-git; these are the same
// updates Database Maintenance in the admin UI runs. createSite.php seeds
// the database from aspen.sql, which can lag the version updates the code
// expects, and a migrated site's database is at whatever version the old
// install last ran.

if (empty($_SERVER['argv'][1])) {
	echo "usage: php run-pending-db-updates.php <sitename>\n";
	exit(1);
}

require_once '/usr/local/aspen-discovery/code/web/bootstrap.php';
require_once ROOT_DIR . '/services/API/SystemAPI.php';

$systemAPI = new SystemAPI();
try {
	$result = $systemAPI->runPendingDatabaseUpdates();
	echo strip_tags(str_replace('<br/>', "\n", $result['message'] ?? '')) . "\n";
	exit(empty($result['success']) ? 1 : 0);
} catch (Throwable $e) {
	// The updates run before the admin search index refresh, which can
	// fail from the command line; judge success by whether any updates
	// are still pending.
	$pending = $systemAPI->getPendingDatabaseUpdates();
	if (count($pending) == 0) {
		echo "Database updates applied (post-update step failed: " . $e->getMessage() . ")\n";
		exit(0);
	}
	echo "Database updates failed, " . count($pending) . " still pending: " . $e->getMessage() . "\n";
	exit(1);
}
