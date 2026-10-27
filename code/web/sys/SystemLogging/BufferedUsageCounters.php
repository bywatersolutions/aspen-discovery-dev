<?php

/**
 * Adds up usage counters in shared memory and writes them to the database every few seconds.
 *
 * Every request used to update aspen_usage, usage_by_user_agent and slow_page directly. Every request on a site shares
 * today's aspen_usage row, nearly every request shares the one 'Mozilla' row in usage_by_user_agent, and a handful of
 * slow_page rows get most of the rest, so under load those updates queue up behind each other's row locks. With APCu the counts are kept in shared memory, and the first request after each
 * FLUSH_SECONDS writes them out with one statement per counter. Counts not yet written are lost if PHP restarts.
 * Without APCu callers write to the database directly as before.
 */
class BufferedUsageCounters {
	const FLUSH_SECONDS = 10;

	//Tables that have a unique key on their key columns, so a count can be added with one statement
	private static array $tablesWithUniqueKey = [
		'aspen_usage',
		'usage_by_user_agent',
	];

	//Each command line process has its own APCu memory that is gone when it exits, so cron writes directly
	public static function isAvailable() : bool {
		return PHP_SAPI != 'cli' && function_exists('apcu_enabled') && apcu_enabled();
	}

	/**
	 * @param string $table
	 * @param array $keyValues The column values that identify the row, for example year, month and instance
	 * @param string $counterColumn
	 * @param int $amount
	 */
	public static function add(string $table, array $keyValues, string $counterColumn, int $amount = 1) : void {
		$counterKey = self::getPrefix() . json_encode([$table, $keyValues, $counterColumn]);
		//No time to live: APCu counts it from when the entry was created, so a busy counter would expire with counts not
		//yet written. There is one entry per row being counted, and the year, month and day in the keys bound how many pile up.
		apcu_add($counterKey, 0);
		apcu_inc($counterKey, $amount);

		//Only the request that creates the lock writes the counts out, then nobody can until it expires
		if (apcu_add(self::getPrefix() . 'flush_lock', 1, self::FLUSH_SECONDS)) {
			self::flush();
		}
	}

	public static function flush() : void {
		$prefix = self::getPrefix();
		foreach (new APCUIterator('/^' . preg_quote($prefix, '/') . '\[/', APC_ITER_KEY | APC_ITER_VALUE) as $counterKey => $entry) {
			$amount = (int)$entry['value'];
			//The compare and swap makes sure counts added while this runs are kept for the next flush
			if ($amount <= 0 || !apcu_cas($counterKey, $amount, 0)) {
				continue;
			}
			[$table, $keyValues, $counterColumn] = json_decode(substr($counterKey, strlen($prefix)), true);
			self::write($table, $keyValues, $counterColumn, $amount);
		}
	}

	private static function write(string $table, array $keyValues, string $counterColumn, int $amount) : void {
		/** @var PDO $aspen_db */
		global $aspen_db;
		try {
			$columns = implode(', ', array_keys($keyValues));
			$values = implode(', ', array_map(fn($value) => $aspen_db->quote($value), $keyValues));
			if (in_array($table, self::$tablesWithUniqueKey)) {
				$aspen_db->exec("INSERT INTO $table ($columns, $counterColumn) VALUES ($values, $amount) ON DUPLICATE KEY UPDATE $counterColumn = COALESCE($counterColumn, 0) + $amount");
			} else {
				$conditions = implode(' AND ', array_map(fn($column, $value) => "$column = " . $aspen_db->quote($value), array_keys($keyValues), $keyValues));
				//Counter columns can be NULL, which would swallow the addition. LIMIT 1 so rows duplicated by earlier races are not all counted.
				if ($aspen_db->exec("UPDATE $table SET $counterColumn = COALESCE($counterColumn, 0) + $amount WHERE $conditions LIMIT 1") === 0) {
					$aspen_db->exec("INSERT INTO $table ($columns, $counterColumn) VALUES ($values, $amount)");
				}
			}
		} catch (PDOException $e) {
			global $logger;
			$logger->log("Could not write usage counter for $table: " . $e->getMessage(), Logger::LOG_ERROR);
		}
	}

	private static function getPrefix() : string {
		global $serverName;
		return 'aspen_' . $serverName . '_usage_counter';
	}
}
