<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

if (TYPO3_MODE == 'BE') {
	$TYPO3_CONF_VARS['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\DamFalmigration\\Task\\MigrateTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => 'DAM-FAL Migration: Migrate DAM Records to FAL Records',
		'description'      => 'Migrates all available DAM records from fileadmin/ to the local storage of the FAL records.',
	);

	$TYPO3_CONF_VARS['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\DamFalmigration\\Task\\MigrateRelationsTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => 'DAM-FAL Migration: Migrate DAM Relations to FAL Relations',
		'description'      => 'Migrates all available DAM relations from tt_content textpic/image/uploads to FAL relations.',
	);

	$TYPO3_CONF_VARS['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\DamFalmigration\\Task\\MigrateDamSelectionsTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => 'DAM-FAL Migration: Migrate DAM Selections',
		'description'      => 'Migrates all available DAM Selections in sys_file_collections (only folder based selections for now).',
	);
	$TYPO3_CONF_VARS['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\DamFalmigration\\Task\\MigrateDamCategoriesTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => 'DAM-FAL Migration: Migrate DAM Categories',
		'description'      => 'Migrates all available DAM Categories in sys_category (only default language categories for now).',
		'additionalFields' => 'TYPO3\\CMS\\DamFalmigration\\Task\\MigrationDamCategoriesAdditionalFieldProvider',
	);

	$TYPO3_CONF_VARS['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\DamFalmigration\\Task\\MigrateDamCategoryRelationsTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => 'DAM-FAL Migration: Migrate DAM Category Relations',
		'description'      => 'Migrates all Relations between DAM Categories and DAM files to FAL Files and Category.',
	);

	$TYPO3_CONF_VARS['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\DamFalmigration\\Task\\MigrateMediaTagTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => 'DAM-FAL Migration: Migrate media-Tags in tt_content',
		'description'      => 'Migrates all tt_content records which contains a media-tag and converts them to a FAL-Link-Tag.',
	);

	$TYPO3_CONF_VARS['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\DamFalmigration\\Task\\MigrateTtContentImagecaptionTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => 'DAM-FAL Migration: Migrate imagecaption in tt_content',
		'description'      => 'Migrates "imagecaption" in tt_content records to "description" in corresponding sys_file_reference records. If the number of lines in a tt_content record\'s imagecaption is greater than the number of associated FAL records it is very likely that tt_content.imagecaption is not used as it is generally supposed to be. These tt_content\'s imagecaption will neither be migrated nor will they be deleted but must be migrated manually! Have a look at the database or (if used) the devlog.',
	);

	$TYPO3_CONF_VARS['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\DamFalmigration\\Task\\MigrateTtContentImageLinkTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => 'DAM-FAL Migration: Migrate image_link in tt_content',
		'description'      => 'Migrates "image_link" in tt_content records to "link" in corresponding sys_file_reference records. If the number of lines in a tt_content record\'s image_link is greater than the number of associated FAL records it is very likely that tt_content.image_link is not used as it is generally supposed to be. These tt_content\'s image_link will neither be migrated nor will they be deleted but must be migrated manually! Have a look at the database or (if used) the devlog.',
	);

	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'B13\\DamFalmigration\\Controller\\DamMigrationCommandController';
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['extTablesInclusion-PostProcessing'][] = 'EXT:dam_falmigration/Classes/Hooks/TcaCategory.php:Tx_DamFalmigration_Hooks_TcaCategory';
