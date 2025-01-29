<?php

function getUpdates25_02_00(): array {
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

		//mark - Grove
		'ptype_account_profile' => [
			'title' => 'Patron Type - Add Account Profile',
			'description' => 'Add Information about which account profile a patron type belongs to',
			'continueOnError' => true,
			'sql' => [
				'ALTER TABLE ptype ADD COLUMN accountProfileId INT',
				"UPDATE ptype set accountProfileId = (SELECT MIN(id) from account_profiles where ils <> 'na' and name <> 'admin')",
				"ALTER TABLE ptype DROP INDEX ptype",
				"ALTER TABLE ptype ADD UNIQUE INDEX ptype_profile(ptype, accountProfileId)",
			]
		], //ptype_account_profile
		'manage_local_administrators_permission' => [
			'title' => 'Manage Local Administrators Permission',
			'description' => 'Add new permission to manage local administrators',
			'continueOnError' => true,
			'sql' => [
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('System Administration', 'Manage Local Administrators', '', 12, 'Allows an administrator to add, edit, and delete local administrators.')",
				"INSERT INTO role_permissions(roleId, permissionId) VALUES ((SELECT roleId from roles where name='userAdmin'), (SELECT id from permissions where name='Manage Local Administrators'))",
			],
		], //manage_local_administrators_permission
		'account_profile_admin_updates' => [
			'title' => 'Admin Account Profile Updates',
			'description' => 'Clean up default admin account profile',
			'continueOnError' => true,
			'sql' => [
				"UPDATE account_profiles set vendorOpacUrl = '', patronApiUrl = '', ils = 'na', driver = '', recordSource = '' where name = 'admin'",
			],
		], //account_profile_admin_updates
		'two_factor_authentication' => [
			'title' => 'Two Factor Authentication Updates',
			'description' => 'Remove unused settings and add new link to account profile for two factor authentication',
			'continueOnError' => true,
			'sql' => [
				'ALTER TABLE two_factor_auth_settings DROP COLUMN authMethods',
				'ALTER TABLE two_factor_auth_settings ADD COLUMN accountProfileId INT',
				"UPDATE two_factor_auth_settings set accountProfileId = (SELECT MIN(id) from account_profiles where ils <> 'na' and name <> 'admin')"
			]
		], //two_factor_authentication
		'indexing_profile_remove_unused_properties' => [
			'title' => 'Indexing Profile remove unused properties',
			'description' => 'Remove unused properties from indexing profiles',
			'sql' => [
				'ALTER TABLE indexing_profiles DROP column groupingClass',
				'ALTER TABLE indexing_profiles DROP column recordDriver',
			]
		], //indexing_profile_remove_unused_properties
		'sideload_remove_unused_properties' => [
			'title' => 'SideLoad remove unused properties',
			'description' => 'Remove unused properties from sideloads',
			'sql' => [
				'ALTER TABLE sideloads DROP column groupingClass',
				'ALTER TABLE sideloads DROP column recordDriver',
			]
		], //sideload_remove_unused_properties
		// Leo Stoyanov - BWS
		'aggregate_usage_by_user_agent' => [
			'title' => 'Aggregate usage_by_user_agent & add unique key',
			'description' => 'Combine multiple rows per (userAgentId, year, month, instance) into one row before adding unique key',
			'continueOnError' => true,
			'sql' => [
				// 1) Create a temporary table with the same structure.
				"CREATE TABLE usage_by_user_agent_temp LIKE usage_by_user_agent",

				// 2) Insert aggregated data into temp table.
				"INSERT INTO usage_by_user_agent_temp (userAgentId, year, month, instance, numRequests, numBlockedRequests)
				 SELECT userAgentId, year, month, instance,
						SUM(numRequests) AS totalRequests,
						SUM(numBlockedRequests) AS totalBlocked
				 FROM usage_by_user_agent
				 GROUP BY userAgentId, year, month, instance",

				// 3) Clear out original table.
				"TRUNCATE TABLE usage_by_user_agent",

				// 4) Re-insert aggregated rows into original table.
				"INSERT INTO usage_by_user_agent
				 SELECT * FROM usage_by_user_agent_temp",

				// 5) Drop the temp table.
				"DROP TABLE usage_by_user_agent_temp",

				// 6) Finally, drop the old index and add the unique index.
				"ALTER TABLE usage_by_user_agent
				 DROP INDEX userAgentId,
				 ADD UNIQUE KEY userAgentId (userAgentId, year, month, instance)"
			],
		], //aggregate_usage_by_user_agent
		'branded_app_api_keys' => [
			'title' => 'Branded App API Keys',
			'description' => 'Add API keys to branded app settings',
			'sql' => [
				"ALTER TABLE aspen_lida_branded_settings ADD COLUMN apiKey1 varchar(256) DEFAULT NULL",
				"ALTER TABLE aspen_lida_branded_settings ADD COLUMN apiKey2 varchar(256) DEFAULT NULL",
				"ALTER TABLE aspen_lida_branded_settings ADD COLUMN apiKey3 varchar(256) DEFAULT NULL",
				"ALTER TABLE aspen_lida_branded_settings ADD COLUMN apiKey4 varchar(256) DEFAULT NULL",
				"ALTER TABLE aspen_lida_branded_settings ADD COLUMN apiKey5 varchar(256) DEFAULT NULL",
			]
		], //branded_app_api_keys

		//katherine
		'native_events_permissions' => [
			'title' => 'Native Events Permissions',
			'description' => 'Add new permissions for native events',
			'continueOnError' => true,
			'sql' => [
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'Administer Field Sets', 'Events', 30, 'Allows the user to administer field sets for native events.')",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'Administer Event Types', 'Events', 40, 'Allows the user to administer native event types.')",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'Administer Events for All Locations', 'Events', 50, 'Allows the user to administer native events for all locations.')",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'Administer Events for Home Library Locations', 'Events', 51, 'Allows the user to administer native events for home library locations.')",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'Administer Events for Home Location', 'Events', 52, 'Allows the user to administer native events for home location.')",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'View Private Events for All Locations', 'Events', 60, 'Allows the user to view private native events for all locations.')",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'View Private Events for Home Library Locations', 'Events', 61, 'Allows the user to view private native events for home library locations.')",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'View Private Events for Home Location', 'Events', 62, 'Allows the user to view private native events for home location.')",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'View Event Reports for All Libraries', 'Events', 70, 'Allows the user to view event reports for all libraries.')",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'View Event Reports for Home Library', 'Events', 71, 'Allows the user to view event reports for their home library.')",
				"INSERT INTO permissions (sectionName, name, requiredModule, weight, description) VALUES ('Events', 'Print Calendars with Header Images', 'Events', 80, 'Allows the user to print calendars with header images.')",
				"INSERT INTO role_permissions(roleId, permissionId) VALUES ((SELECT roleId from roles where name='opacAdmin'), (SELECT id from permissions where name='Administer Field Sets'))",
				"INSERT INTO role_permissions(roleId, permissionId) VALUES ((SELECT roleId from roles where name='opacAdmin'), (SELECT id from permissions where name='Administer Event Types'))",
				"INSERT INTO role_permissions(roleId, permissionId) VALUES ((SELECT roleId from roles where name='opacAdmin'), (SELECT id from permissions where name='Administer Events for All Locations'))",
				"INSERT INTO role_permissions(roleId, permissionId) VALUES ((SELECT roleId from roles where name='opacAdmin'), (SELECT id from permissions where name='View Private Events for All Locations'))",
				"INSERT INTO role_permissions(roleId, permissionId) VALUES ((SELECT roleId from roles where name='opacAdmin'), (SELECT id from permissions where name='View Event Reports for All Libraries'))",
				"INSERT INTO role_permissions(roleId, permissionId) VALUES ((SELECT roleId from roles where name='opacAdmin'), (SELECT id from permissions where name='Print Calendars with Header Images'))",
			],
		], //native_events_permissions
		'native_event_tables' => [
			'title' => 'Native Event Tables',
			'description' => 'Add new tables for native events',
			'continueOnError' => true,
			'sql' => [
				"CREATE TABLE event_field (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					name VARCHAR(50) NOT NULL UNIQUE,
					description VARCHAR(100) NOT NULL,
					type TINYINT NOT NULL DEFAULT 0,
					allowableValues VARCHAR(150),
					defaultValue VARCHAR(150),
					facetName INT NOT NULL DEFAULT 0
				) ENGINE INNODB CHARACTER SET utf8 COLLATE utf8_general_ci",
				"CREATE TABLE event_field_set (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					name VARCHAR(50) NOT NULL UNIQUE
				) ENGINE INNODB CHARACTER SET utf8 COLLATE utf8_general_ci",
				"CREATE TABLE event_field_set_field (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					eventFieldId INT NOT NULL,
					eventFieldSetId INT NOT NULL
				) ENGINE INNODB CHARACTER SET utf8 COLLATE utf8_general_ci",
				"CREATE TABLE event_type (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					eventFieldSetId INT NOT NULL,
					title VARCHAR(50) NOT NULL UNIQUE,
					titleCustomizable TINYINT(1) NOT NULL DEFAULT 1,
					description VARCHAR(100) NOT NULL,
					descriptionCustomizable TINYINT(1) NOT NULL DEFAULT 1,
					cover VARCHAR(100) DEFAULT NULL,
					coverCustomizable TINYINT(1) NOT NULL DEFAULT 1,
					eventLength FLOAT NOT NULL DEFAULT 1,
					lengthCustomizable TINYINT(1) NOT NULL DEFAULT 1,
					archived TINYINT(1) NOT NULL DEFAULT 0
				) ENGINE INNODB CHARACTER SET utf8 COLLATE utf8_general_ci",
				"CREATE TABLE event_type_library (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					eventTypeId INT NOT NULL,
					libraryId INT NOT NULL
				) ENGINE INNODB CHARACTER SET utf8 COLLATE utf8_general_ci",
				"CREATE TABLE event_type_location (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					eventTypeId INT NOT NULL,
					locationId INT NOT NULL
				) ENGINE INNODB CHARACTER SET utf8 COLLATE utf8_general_ci",
				"CREATE TABLE event (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					eventTypeId INT NOT NULL,
					locationId INT NOT NULL,
					sublocationId INT,
					title VARCHAR(50),
					description VARCHAR(500),
					cover VARCHAR(100) DEFAULT NULL,
					private TINYINT(1) NOT NULL DEFAULT 1,
					startDate DATE NOT NULL DEFAULT (CURRENT_DATE),
					startTime TIME NOT NULL DEFAULT (CURRENT_TIME),
					eventLength INT NOT NULL DEFAULT 60,
					recurrenceOption TINYINT,
					recurrenceFrequency TINYINT,
					recurrenceInterval INT NOT NULL DEFAULT 1,
					weekDays VARCHAR(25),
					monthlyOption TINYINT,
					monthDay TINYINT,
					monthDate TINYINT,
					monthOffset TINYINT,
					endOption TINYINT,
					recurrenceEnd DATE,
					recurrenceCount INT
				) ENGINE INNODB CHARACTER SET utf8 COLLATE utf8_general_ci",
				"CREATE TABLE event_event_field (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					eventId INT NOT NULL,
					eventFieldId INT NOT NULL,
					value VARCHAR(150) NOT NULL
				) ENGINE INNODB CHARACTER SET utf8 COLLATE utf8_general_ci",
				"CREATE TABLE event_instance (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					eventId INT NOT NULL,
					date DATE NOT NULL,
					time TIME NOT NULL,
					length INT NOT NULL,
					status TINYINT(1) NOT NULL DEFAULT 1,
					note VARCHAR(150)
				) ENGINE INNODB CHARACTER SET utf8 COLLATE utf8_general_ci",
			]
		], //native_events_tables

		//kirstien - Grove

		//kodi

		//alexander - PTFS-Europe

		//chloe - PTFS-Europe

		//James Staub - Nashville Public Library

		//Lucas Montoya - Theke Solutions

		//other

	];
}