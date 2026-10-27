<?php

/**
 * Sends cover images that are already cached on disk before Aspen starts up.
 *
 * A page of search results asks for 20 or more covers at once and each used to run the whole bootstrap, a dozen
 * queries and a database update just to send a file. When BookCoverProcessor sends a cached cover it leaves a marker
 * in shared memory naming the file it sent for that exact request. The next identical request is sent straight from
 * that marker without touching the database.
 *
 * Markers are retired when anything could change which file a request should get: saving the cover's bookcover_info
 * row bumps a generation number for that record, and saving the system variables or reloading covers in bulk bumps one
 * for the whole site. Changes made outside the web server (the ILS exports write bookcover_info directly) cannot reach
 * shared memory, so markers also expire after MARKER_SECONDS. Without APCu nothing is sent early.
 *
 * This file must not depend on anything else in Aspen since it runs before bootstrap.
 */
class CachedCoverFile {
	const MARKER_SECONDS = 600;
	const CACHE_SECONDS = 60 * 60 * 24 * 14;

	//Markers for one site have to be retired from any of its hosts, so this needs the site name the web server sets
	public static function isSharedMemoryAvailable() : bool {
		return !empty($_SERVER['aspen_server']) && function_exists('apcu_enabled') && apcu_enabled();
	}

	//test_servername makes a request act as another library, whose default covers differ, so those requests neither use
	//nor leave markers
	private static function isActingAsAnotherLibrary() : bool {
		return isset($_REQUEST['test_servername']) || isset($_COOKIE['test_servername']);
	}

	/**
	 * Send the cached cover for this request if a current marker exists for it.
	 *
	 * @return bool true if the cover was sent
	 */
	public static function sendIfCached() : bool {
		if (!self::isSharedMemoryAvailable() || self::isActingAsAnotherLibrary() || isset($_GET['reload']) || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'])) {
			return false;
		}
		$marker = apcu_fetch(self::getMarkerKey());
		if (!is_array($marker)) {
			return false;
		}
		if ((int)apcu_fetch($marker['recordGenerationKey']) !== $marker['recordGeneration'] || (int)apcu_fetch($marker['siteGenerationKey']) !== $marker['siteGeneration']) {
			return false;
		}
		if (!is_file($marker['file'])) {
			return false;
		}

		header('Content-type: image/png');
		header("Last-Modified: {$marker['lastModified']}");
		header("ETag: {$marker['etag']}");
		//Same rules as BookCoverProcessor::addModificationHeaders()
		$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
		$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : false;
		$notModified = ($ifModifiedSince || $ifNoneMatch) && (!$ifNoneMatch || $ifNoneMatch == $marker['etag']) && (!$ifModifiedSince || $ifModifiedSince == $marker['lastModified']);
		if ($notModified) {
			header('HTTP/1.0 304 Not Modified');
		} else {
			self::sendCachingHeaders();
			header('Content-Length: ' . filesize($marker['file']));
			readfile($marker['file']);
		}

		//Bootstrap adds these to aspen_usage on the next request that opens the database, see recordPendingCoverViews()
		$coverViewsKey = self::getCoverViewsKey();
		apcu_add($coverViewsKey, 0);
		apcu_inc($coverViewsKey);
		return true;
	}

	public static function sendCachingHeaders() : void {
		header('Cache-Control: public, max-age=' . self::CACHE_SECONDS);
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + self::CACHE_SECONDS) . ' GMT');
	}

	/**
	 * Read the generation numbers for a cover before its bookcover_info row is loaded, so a change saved while the
	 * request is running retires the marker this request leaves.
	 */
	public static function getGenerations(string $recordType, string $recordId) : array {
		if (!self::isSharedMemoryAvailable()) {
			return [];
		}
		$recordGenerationKey = self::getRecordGenerationKey($recordType, $recordId);
		$siteGenerationKey = self::getSiteGenerationKey();
		return [
			'recordGenerationKey' => $recordGenerationKey,
			'recordGeneration' => (int)apcu_fetch($recordGenerationKey),
			'siteGenerationKey' => $siteGenerationKey,
			'siteGeneration' => (int)apcu_fetch($siteGenerationKey),
		];
	}

	/**
	 * Called once BookCoverProcessor has sent a cached cover, so the same request can be sent early next time.
	 */
	public static function rememberSentFile(string $file, string $lastModified, string $etag, array $generations) : void {
		if (empty($generations) || !self::isSharedMemoryAvailable() || self::isActingAsAnotherLibrary()) {
			return;
		}
		apcu_store(self::getMarkerKey(), $generations + [
			'file' => $file,
			'lastModified' => $lastModified,
			'etag' => $etag,
		], self::MARKER_SECONDS);
	}

	public static function retireRecord(?string $recordType, ?string $recordId) : void {
		if ($recordType !== null && $recordId !== null && self::isSharedMemoryAvailable()) {
			self::incrementGeneration(self::getRecordGenerationKey($recordType, $recordId));
		}
	}

	public static function retireSite() : void {
		if (self::isSharedMemoryAvailable()) {
			self::incrementGeneration(self::getSiteGenerationKey());
		}
	}

	/**
	 * Add the cover views sent early since the last time this ran to aspen_usage.
	 */
	public static function recordPendingCoverViews(AspenUsage $aspenUsage) : void {
		if (!self::isSharedMemoryAvailable()) {
			return;
		}
		$coverViewsKey = self::getCoverViewsKey();
		$pendingCoverViews = apcu_fetch($coverViewsKey);
		//The compare and swap makes sure two requests flushing at the same time cannot count the same views twice
		if ($pendingCoverViews > 0 && apcu_cas($coverViewsKey, $pendingCoverViews, 0)) {
			$aspenUsage->addCoverViews($pendingCoverViews);
		}
	}

	private static function incrementGeneration(string $key) : void {
		apcu_add($key, 0);
		apcu_inc($key);
	}

	//The host is part of the key because default covers are drawn for each library, and the whole query string is so a
	//request only gets the file that was sent for exactly the same request
	private static function getMarkerKey() : string {
		return 'aspen_cover_marker_' . md5(($_SERVER['SERVER_NAME'] ?? '') . '?' . ($_SERVER['QUERY_STRING'] ?? ''));
	}

	private static function getRecordGenerationKey(string $recordType, string $recordId) : string {
		return self::getSiteGenerationKey() . '_' . md5($recordType . ':' . $recordId);
	}

	//The web server's environment names the site, the same way readConfig() finds it, so this works before bootstrap
	private static function getSiteGenerationKey() : string {
		return 'aspen_cover_generation_' . ($_SERVER['aspen_server'] ?? '');
	}

	private static function getCoverViewsKey() : string {
		return 'aspen_cover_views_' . ($_SERVER['SERVER_NAME'] ?? '');
	}
}
