<?php

class BotChecker {

	static ?bool $isBot = null;

	//Matched case-insensitively anywhere in the full user agent. 'Bot' alone covers GPTBot, ClaudeBot, Amazonbot,
	//Applebot, PerplexityBot, CCBot, Googlebot, bingbot and most other crawlers.
	private static array $botStrings = [
		'Bot',
		'Crawl',
		'Slurp',
		'Spider',
		'Spyder',
		'SearchHelper',
		'Claude-Web',
		'anthropic-ai',
		'cohere-ai',
		'ChatGPT-User',
		'Perplexity-User',
		'meta-externalagent',
		'meta-externalfetcher',
		'facebookexternalhit',
		'omgili',
		'ia_archiver',
		'HeadlessChrome',
		'PhantomJS',
		'Scrapy',
		'python-requests',
		'python-urllib',
		'Go-http-client',
		'libwww-perl',
		'Wget',
		'curl/',
		//Calls from the Aspen Discovery user agent are generally monitoring calls or calls for Aspen to update itself
		'Aspen Discovery',
	];

	//The user_agent table keeps only the text before the first '/', so every browser and most crawlers share these rows
	private static array $sharedBrowserPrefixes = [
		'mozilla',
		'opera',
	];

	/**
	 * Determines if the current request appears to be from a bot
	 */
	public static function isRequestFromBot() : bool {
		if (BotChecker::$isBot === null) {
			BotChecker::$isBot = BotChecker::checkUserAgent();
		}
		return BotChecker::$isBot;
	}

	private static function checkUserAgent() : bool {
		global $timer;
		global $memCache;
		global $configArray;
		global $userAgent;
		if (empty($_SERVER['HTTP_USER_AGENT'])) {
			//No user agent passed, assume it is a bot
			return true;
		}
		$fullUserAgent = $_SERVER['HTTP_USER_AGENT'];

		//An administrator can mark a distinctive user agent (curl, a library's own script) as a bot or not in the user_agent table.
		//The shared browser rows say nothing about one request, so those are classified on the full user agent instead.
		if ($userAgent instanceof UserAgent && $userAgent->isBot !== null && !in_array(strtolower($userAgent->userAgent), BotChecker::$sharedBrowserPrefixes)) {
			return (bool)$userAgent->isBot;
		}

		//Hash the user agent so the key fits the database cache, full user agents are often longer than its 200 character limit
		$cacheKey = 'bot_by_user_agent_' . md5($fullUserAgent);
		//The answer for a user agent only changes if bots.txt does, so shared memory is enough when there is some
		if ($memCache->isSharedMemoryEnabled()) {
			return $memCache->getFromSharedMemory($cacheKey, Memcache::MAX_SHARED_MEMORY_SECONDS, fn() => BotChecker::isBotUserAgent($fullUserAgent) ? 'TRUE' : 'FALSE') === 'TRUE';
		}
		$isBot = $memCache->get($cacheKey);
		if ($isBot !== false) {
			return $isBot === 'TRUE';
		}

		$isBot = BotChecker::isBotUserAgent($fullUserAgent);
		$memCache->set($cacheKey, ($isBot ? 'TRUE' : 'FALSE'), $configArray['Caching']['bot_by_user_agent']);
		$timer->logTime("Checking isRequestFromBot");
		return $isBot;
	}

	public static function isBotUserAgent(string $fullUserAgent) : bool {
		foreach (BotChecker::$botStrings as $botString) {
			if (stripos($fullUserAgent, $botString) !== false) {
				return true;
			}
		}

		global $serverName;
		if (file_exists('../../sites/' . $serverName . '/conf/bots.txt')) {
			$fileHandle = fopen('../../sites/' . $serverName . '/conf/bots.txt', 'r');
		} elseif (file_exists('../../sites/default/conf/bots.txt')) {
			$fileHandle = fopen('../../sites/default/conf/bots.txt', 'r');
		} else {
			global $logger;
			$logger->log("Did not find bots.txt file, cannot detect bots", Logger::LOG_ERROR);
			return false;
		}

		$isBot = false;
		while (($curAgent = fgets($fileHandle, 4096)) !== false) {
			//Remove line separators
			$curAgent = str_replace("\r", '', $curAgent);
			$curAgent = str_replace("\n", '', $curAgent);
			if (strcasecmp($fullUserAgent, $curAgent) == 0) {
				$isBot = true;
				break;
			}
		}
		fclose($fileHandle);
		return $isBot;
	}
}
