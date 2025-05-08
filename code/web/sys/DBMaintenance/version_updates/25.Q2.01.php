<?php

function getUpdates25_Q2_01(): array {
	$curTime = time();
	return [
		/*'name' => [
			 'title' => '',
			 'description' => '',
			 'continueOnError' => false,
			 'sql' => [
				 ''
			 ]
		 ], //name*/

		//katherine - Grove

		//kirstien - Grove

		//kodi - Grove

		//Yanjun Li - ByWater

		// Leo Stoyanov - BWS
		'add_placard_image_max_height_to_themes' => [
			'title' => 'Add Placard Image Max Height to Themes',
			'description' => 'Adds a placardImageMaxHeight column to the themes table to control placard image height.',
			'sql' => [
				"ALTER TABLE themes ADD COLUMN IF NOT EXISTS `placardImageMaxHeight` INT DEFAULT 0",
			]
		], //add_placard_image_max_height_to_themes
		'reading_history_columns_and_index' => [
			'title' => 'Add Force Reading History Load Flag, Reading History Import Start Datetime, & Index',
			'description' => 'Add a flag to force immediate loading of reading history for users, a reading history import start datetime, and an index of initial reading history loaded and the previous two new columns.',
			'continueOnError' => false,
			'sql' => [
				"ALTER TABLE user ADD COLUMN IF NOT EXISTS forceReadingHistoryLoad TINYINT(1) DEFAULT 0",
				"ALTER TABLE user ADD COLUMN IF NOT EXISTS readingHistoryImportStartedAt DATETIME DEFAULT NULL",
				"DROP INDEX IF EXISTS idx_reading_history_import_status ON user",
				"CREATE INDEX idx_reading_history_import_status ON user (initialReadingHistoryLoaded, forceReadingHistoryLoad, readingHistoryImportStartedAt)"
			]
		], //reading_history_columns_and_index

		//alexander - PTFS-Europe
		'add_weight_to_campaign_milestones' => [
			'title' => 'Add Weight To Campaign Milestones',
			'description' => 'Add a weight column to campaign milestones to allow ordering',
			'sql' => [
				"ALTER TABLE ce_campaign_milestones ADD COLUMN weight int(11) NOT NULL DEFAULT 0",
			],
		], //DIS-606

		//chloe - Open Fifth
		'permanentUrl_allows_longer_strings' => [
			'title' => 'PermanentUrl Allows For Longer Strings',
			'description' => 'Allow for longer permanent URLs so that Open Archive records can be indexed without clashing with the length constraint',
			'continueOnError' => false,
			'sql' => [
				"ALTER TABLE open_archives_record MODIFY COLUMN permanentUrl VARCHAR(2048) NOT NULL",
			]
		], // permanentUrl_allows_longer_strings

		//James Staub - Nashville Public Library

		//Lucas Montoya - Theke Solutions

		//other

	];
}
