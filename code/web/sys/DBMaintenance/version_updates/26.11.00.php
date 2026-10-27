<?php
/** @noinspection SqlDialectInspection */

/** @noinspection PhpUnused */
function getUpdates26_11_00(): array {
	$now = time();

	return [
		/*'name' => [
			 'title' => '',
			 'description' => '',
			 'continueOnError' => false,
			 'sql' => [
				 ''
			 ]
		 ], //name*/

		//mark n

		//kirstien

		//kodi

		//yanjun

		//imani

		//galen

		//chloe

		//pedro

		//mark j

		//lucas

		//tomas

		// stephen

		//jacob - OpenFifth

		//kyle - ByWater
		'user_agent_bot_flags_for_full_user_agent_checks' => [
			'title' => 'Update bot flags for full user agent checks',
			'description' => 'Flag scraping tools and AI crawlers as bots and clear the flag on the rows every browser shares, which no longer decide whether a request is from a bot.',
			'continueOnError' => true,
			'sql' => [
				"UPDATE user_agent SET isBot = 1 WHERE userAgent IN ('curl', 'Wget', 'python-requests', 'Python-urllib', 'Go-http-client', 'libwww-perl', 'Scrapy', 'facebookexternalhit', 'meta-externalagent', 'meta-externalfetcher', 'ChatGPT-User', 'Perplexity-User', 'Claude-Web', 'anthropic-ai', 'cohere-ai', 'omgili', 'ia_archiver')",
				"UPDATE user_agent SET isBot = 0 WHERE userAgent IN ('Mozilla', 'Opera')",
			],
		], //user_agent_bot_flags_for_full_user_agent_checks

	];
}