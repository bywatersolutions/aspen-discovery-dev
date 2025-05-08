<?php

if (count($_SERVER['argv']) > 1) {
	$serverName = $_SERVER['argv'][1];
	// Check to see if the update already exists properly.
	$fhnd = fopen('/usr/local/aspen-discovery/sites/' . $serverName . '/conf/crontab_settings.txt', 'r');
	if ($fhnd) {
		$lines = [];
		$insertLoadInitialHistory = true;
		$loadInitialHistoryInserted = false;
		while (($line = fgets($fhnd)) !== false) {
			// Detect if the cron job is already present.
			if (str_contains($line, 'loadInitialReadingHistory.php')) {
				$insertLoadInitialHistory = false;
			}
			// Insert before Debian end-of-file marker.
			if ($insertLoadInitialHistory && str_contains($line, 'Debian needs a blank line at the end of cron')) {
				if (!empty($lines) && trim(end($lines)) !== '') {
					$lines[] = "\n";
				}
				$lines[] = "##########################################\n";
				$lines[] = "# Load Initial Reading History for Users #\n";
				$lines[] = "##########################################\n";
				$lines[] = "*/5 * * * * root php /usr/local/aspen-discovery/code/web/cron/loadInitialReadingHistory.php $serverName\n";
				$lines[] = "\n";
				$loadInitialHistoryInserted = true;
			}
			$lines[] = $line;
		}
		fclose($fhnd);

		// Fallback: If marker was not found, add at the end.
		if ($insertLoadInitialHistory && !$loadInitialHistoryInserted) {
			if (!empty($lines) && trim(end($lines)) !== '') {
				$lines[] = "\n";
			}
			$lines[] = "##########################################\n";
			$lines[] = "# Load Initial Reading History for Users #\n";
			$lines[] = "##########################################\n";
			$lines[] = "*/5 * * * * root php /usr/local/aspen-discovery/code/web/cron/loadInitialReadingHistory.php $serverName\n";
			$lines[] = "\n";
			$loadInitialHistoryInserted = true;
		}

		// Write only if the new cron job was inserted.
		if ($loadInitialHistoryInserted) {
			$newContent = implode('', $lines);
			file_put_contents('/usr/local/aspen-discovery/sites/' . $serverName . '/conf/crontab_settings.txt', $newContent);
		}
	} else {
		echo '- Could not find cron settings file.' . PHP_EOL;
	}
} else {
	echo 'Must provide servername as first argument.'. PHP_EOL;
	exit();
}