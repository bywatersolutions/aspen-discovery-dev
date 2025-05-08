<?php
/**
 * Load Initial Reading History for users who haven't had their reading history loaded yet.
 *
 * This is run as a cron job to prevent AJAX timeouts. The logic has been
 * transferred from the getReadingHistory() method in CatalogConnection.php.
 *
 * If the process is terminated in the command-line at a point when the CurlWrapper is running,
 * the command-line will return an error, but it is inconsequential.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../bootstrap_aspen.php';
require_once ROOT_DIR . '/sys/ReadingHistoryEntry.php';

global $configArray;
global $serverName;
global $aspen_db;
global $logger;

set_time_limit(0);

$staleIntervalMinutes = 30; // Configurable: Interval after which an import is considered stale.

// Look for users who need their initial reading history loaded and are not currently being processed.
$selectIdSql = "
	SELECT id FROM user
	WHERE initialReadingHistoryLoaded = 0
		AND forceReadingHistoryLoad = 1
		AND trackReadingHistory = 1
		AND (readingHistoryImportStartedAt IS NULL
			OR readingHistoryImportStartedAt < UTC_TIMESTAMP() - INTERVAL :staleInterval MINUTE)
	ORDER BY id
";

try {
	$stmt = $aspen_db->prepare($selectIdSql);
	$stmt->bindValue(':staleInterval', $staleIntervalMinutes, PDO::PARAM_INT);
	$stmt->execute();
	$usersToProcess = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
	$logger->log("Error fetching users for reading history import: " . $e->getMessage() . ".", Logger::LOG_ERROR);
	exit(1);
}

$loadedCount = 0;
$errorCount = 0;

$logger->log("Starting initial reading history load. Found ". count($usersToProcess) ." potential users to process.", Logger::LOG_DEBUG);

foreach ($usersToProcess as $userId) {

	// Attempt to atomically claim the user.
	$claimSql = "
		UPDATE user
		SET readingHistoryImportStartedAt = UTC_TIMESTAMP()
		WHERE id = :user_id
		AND initialReadingHistoryLoaded = 0 -- Re-check conditions atomically.
		AND forceReadingHistoryLoad = 1
		AND (readingHistoryImportStartedAt IS NULL
		OR readingHistoryImportStartedAt < UTC_TIMESTAMP() - INTERVAL :staleInterval MINUTE)
	";

	try {
		$claimStmt = $aspen_db->prepare($claimSql);
		$claimStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
		$claimStmt->bindValue(':staleInterval', $staleIntervalMinutes, PDO::PARAM_INT);
		$claimStmt->execute();

		if ($claimStmt->rowCount() === 0) {
			$logger->log("User $userId already claimed by another process or state changed. Skipping.", Logger::LOG_ERROR);
			continue;
		}
	} catch (Exception $e) {
		$logger->log("Error claiming user $userId: " . $e->getMessage() . ".", Logger::LOG_ERROR);
		$errorCount++;
		continue;
	}

	// Successfully claimed, now load the full User object for processing.
	$user = new User();
	$user->id = $userId;
	if (!$user->find(true)) {
		$logger->log("Failed to load claimed user object for $userId. Skipping.", Logger::LOG_ERROR);
		// Note: The timestamp remains set, will be retried later if needed.
		$errorCount++;
		continue;
	}

	$logger->log("Processing initial reading history for user: $user->displayName ($userId).", Logger::LOG_DEBUG);

	try {
		$catalog = $user->getCatalogDriver();

		if ($catalog) {
			if ($catalog->driver->hasNativeReadingHistory()) {
				$result = $catalog->driver->getReadingHistory($user, -1, -1, "checkedOut");
				if ($result['numTitles'] > 0) {
					$logger->log("Found {$result['numTitles']} titles to load for $user->displayName ($user->id).", Logger::LOG_DEBUG);

					foreach ($result['titles'] as $title) {
						$userReadingHistoryEntry = new ReadingHistoryEntry();
						$userReadingHistoryEntry->userId = $user->id;
						$userReadingHistoryEntry->groupedWorkPermanentId = $title['permanentId'];
						$userReadingHistoryEntry->source = $catalog->accountProfile->recordSource;
						$userReadingHistoryEntry->sourceId = $title['recordId'];
						$userReadingHistoryEntry->title = substr($title['title'], 0, 150);
						$userReadingHistoryEntry->author = substr($title['author'], 0, 75);
						$userReadingHistoryEntry->format = $title['format'];
						$userReadingHistoryEntry->checkOutDate = $title['checkout'];

						if (!empty($title['checkin'])) {
							$userReadingHistoryEntry->checkInDate = $title['checkin'];
						} else {
							$userReadingHistoryEntry->checkInDate = null;
						}

						if (empty($title['isIll'])) {
							$userReadingHistoryEntry->isIll = 0;
						} else {
							$userReadingHistoryEntry->isIll = 1;
						}

						$userReadingHistoryEntry->deleted = 0;
						$userReadingHistoryEntry->insert();
					}

				}

				// Mark that the initial reading history has been loaded and clear the timestamp.
				$updateSql = "
					UPDATE user
					SET initialReadingHistoryLoaded = 1,
					forceReadingHistoryLoad = 0,	-- initialReadingHistoryLoaded determines if it should be imported; this just determines when, so reset it.
					readingHistoryImportStartedAt = NULL
					WHERE id = :user_id
				";
				$updateStmt = $aspen_db->prepare($updateSql);
				$updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
				$updateStmt->execute();

				$loadedCount++;
				$logger->log("Successfully loaded initial reading history for $user->displayName ($user->id).", Logger::LOG_DEBUG);
			} else {
				// Mark the attempted load even if the ILS doesn't support it and clear timestamp.
				$updateSql = "
					UPDATE user
					SET initialReadingHistoryLoaded = 1,
					forceReadingHistoryLoad = 0,	-- initialReadingHistoryLoaded determines if it should be imported; this just determines when, so reset it.
					readingHistoryImportStartedAt = NULL
					WHERE id = :user_id
				";
				$updateStmt = $aspen_db->prepare($updateSql);
				$updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
				$updateStmt->execute();

				$loadedCount++;
				$logger->log("Marked user $user->id as having reading history loaded, ILS does not support native reading history.", Logger::LOG_DEBUG);
			}
		} else {
			$logger->log("Could not get catalog driver for $user->displayName ($user->id).", Logger::LOG_ERROR);
			$errorCount++;
		}
	} catch (Exception $e) {
		$logger->log("Error loading reading history for $user->displayName ($user->id): " . $e->getMessage() . ".", Logger::LOG_ERROR);
		$errorCount++;
	}

	$logger->log("Processed $loadedCount users so far, with $errorCount errors.", Logger::LOG_DEBUG);
}

$logger->log("Finished initial reading history load process. Processed $loadedCount users with $errorCount errors.", Logger::LOG_DEBUG);