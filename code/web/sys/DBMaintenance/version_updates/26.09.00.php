<?php
/** @noinspection SqlDialectInspection */

/** @noinspection PhpUnused */
function getUpdates26_09_00(): array {
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
		'insert_aspen_pwa_self_check_permission' => [
			'title' => 'Add Aspen Progressive Web Application(PWA) self check permission',
			'description' => 'Add permisions for administering Aspen Progressive Web Application(PWA) self check settings.',
			'sql' => [
				"INSERT IGNORE into `permissions` (name, sectionName, requiredModule, weight, description) VALUES ('Administer Aspen PWA Self-Check Settings', 'Aspen Progressive Web Application(PWA)', 'Aspen Progressive Web Application(PWA)', 6, 'Controls if the user can administer Self Check Settings for the progressive web Application');",
			],
		],
		//galen

		//chloe
	
		//pedro

		//mark j

		//lucas

		//tomas

		// stephen

		//jacob - OpenFifth


	];
}
