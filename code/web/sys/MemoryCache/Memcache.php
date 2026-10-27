<?php

require_once ROOT_DIR . '/sys/MemoryCache/CachedValue.php';

class Memcache {
	private bool $enableDbCache = true;
	private array $vars = [];
	private bool $enableSharedMemory;
	private string $sharedMemoryPrefix;

	//Shared memory entries are rebuilt at least this often, so changes made where this server's shared memory cannot be
	//cleared (cron, the indexer, or another web server for the same site) still show up
	const MAX_SHARED_MEMORY_SECONDS = 300;

	public function __construct() {
		//APCu is shared by every PHP worker on the server, so a value one request loads saves the database query for the rest
		$this->enableSharedMemory = function_exists('apcu_enabled') && apcu_enabled();
		//One server can run several Aspen sites with their own databases, so keep each site's entries apart
		global $serverName;
		$this->sharedMemoryPrefix = 'aspen_' . ($serverName ?? 'default') . '_';
	}

	public function __destruct() {
		//Clear old expired memory cache entries.  This can be done VERY sporadically.
		$random = rand(0, 1000);
		if ($random == 1) {
			try {
				$cachedValue = new CachedValue();
				$cachedValue->whereAdd();
				$cachedValue->whereAdd("expirationTime <= " . time());
				$cachedValue->delete(true);
			} catch (Exception) {
				global $logger;
				$logger->log("Could not clear cache of old values", Logger::LOG_DEBUG);
			}
		}
	}

	public function get($name) {
		if (!array_key_exists($name, $this->vars)) {
			if ($this->enableDbCache && !isset($_REQUEST['reload'])) {
				try {
					$cachedValue = new CachedValue();
					$cachedValue->cacheKey = $name;
					if ($cachedValue->find(true)) {
						if ($cachedValue->expirationTime != 0 && $cachedValue->expirationTime < time()) {
							$this->vars[$name] = false;
						} else {
							$unSerializedValue = unserialize($cachedValue->value);
							$this->vars[$name] = $unSerializedValue;
						}
					} else {
						$this->vars[$name] = false;
					}
				} catch (Exception) {
					//Table has not been created ignore
					$this->vars[$name] = false;
				}
			} else {
				$this->vars[$name] = false;
			}
		}
		return $this->vars[$name];
	}

	public function set($name, $value, $timeout) : bool {
		$this->vars[$name] = $value;
		if ($this->enableDbCache) {
			$valueToCache = serialize($value);
			if (strlen($valueToCache) <= 16384 && strlen($name) < 200) {
				try {
					$cachedValue = new CachedValue();
					$cachedValue->cacheKey = $name;
					$isNew = true;
					if ($cachedValue->find(true)) {
						$isNew = false;
					}
					$cachedValue->value = $valueToCache;
					if ($timeout == 0) {
						$cachedValue->expirationTime = 0;
					} else {
						$cachedValue->expirationTime = time() + $timeout;
					}

					if ($isNew) {
						/** @noinspection PhpUnusedLocalVariableInspection */
						$result = $cachedValue->insert();
					} else {
						/** @noinspection PhpUnusedLocalVariableInspection */
						$result = $cachedValue->update();
					}
				} catch (Exception) {
					//Table has not been created ignore
					global $logger;
					$logger->log("error caching data", Logger::LOG_DEBUG);
				}
			} else {
				if (IPAddress::showDebuggingInformation()) {
					global $logger;
					$logger->log("data for $name was too large to be cached, fields was " . strlen($valueToCache) . " characters long", Logger::LOG_WARNING);
				}
				return false;
			}
		}
		return true;
	}

	static ?CachedValue $cachedValueCleaner = null;

	public function delete($name) : void {
		unset($this->vars[$name]);
		if ($this->enableDbCache) {
			try {
				if (Memcache::$cachedValueCleaner == null) {
					Memcache::$cachedValueCleaner = new CachedValue();
				}

				Memcache::$cachedValueCleaner->whereAdd();
				Memcache::$cachedValueCleaner->cacheKey = $name;
				Memcache::$cachedValueCleaner->delete(true);
			} catch (Exception) {
				//Table has not been created ignore
			}
		}
	}

	public function deleteStartingWith($name) : void {
		foreach ($this->vars as $key => $value) {
			if (str_starts_with($key, $name)) {
				unset($this->vars[$key]);
			}
		}
		if ($this->enableDbCache) {
			try {
				if (Memcache::$cachedValueCleaner == null) {
					Memcache::$cachedValueCleaner = new CachedValue();
				}

				Memcache::$cachedValueCleaner->whereAdd();
				Memcache::$cachedValueCleaner->whereAdd("cacheKey like '$name%'");
				Memcache::$cachedValueCleaner->delete(true);
			} catch (Exception) {
				//Table has not been created ignore
			}
		}
	}

	/**
	 * Get a value kept only in shared memory (never the database), building it with $loader on a miss.
	 * Without APCu the value lives for the current request only, which is how Aspen behaved before.
	 * A loader result of false is not shared, so something that does not exist yet is looked up again.
	 * Shared memory belongs to one web server, so clearing an entry does not reach other servers for the same site.
	 * Only use this for data where being up to $timeout seconds out of date on those is acceptable.
	 *
	 * @param int $timeout Seconds to keep the value, capped at MAX_SHARED_MEMORY_SECONDS
	 */
	public function getFromSharedMemory(string $name, int $timeout, callable $loader) : mixed {
		if (array_key_exists($name, $this->vars)) {
			return $this->vars[$name];
		}
		if (!$this->fetchFromSharedMemory($name, $value)) {
			$value = $loader();
			if ($value !== false) {
				$this->storeInSharedMemory($name, $value, $timeout);
			}
		}
		$this->vars[$name] = $value;
		return $value;
	}

	//Entries from getFromSharedMemory() are never written to the database, so clearing them should not cost a query
	public function deleteFromSharedMemory(string $name) : void {
		unset($this->vars[$name]);
		if ($this->enableSharedMemory) {
			apcu_delete($this->sharedMemoryPrefix . $name);
		}
	}

	public function isSharedMemoryEnabled() : bool {
		return $this->enableSharedMemory;
	}

	private function fetchFromSharedMemory(string $name, &$value) : bool {
		if (!$this->enableSharedMemory) {
			return false;
		}
		$found = false;
		$value = apcu_fetch($this->sharedMemoryPrefix . $name, $found);
		return $found;
	}

	private function storeInSharedMemory(string $name, $value, int $timeout) : bool {
		if (!$this->enableSharedMemory) {
			return false;
		}
		if ($timeout <= 0 || $timeout > self::MAX_SHARED_MEMORY_SECONDS) {
			$timeout = self::MAX_SHARED_MEMORY_SECONDS;
		}
		try {
			return apcu_store($this->sharedMemoryPrefix . $name, $value, $timeout);
		} catch (Throwable) {
			//Objects that hold a database handle cannot be serialized, those just are not shared
			return false;
		}
	}
}