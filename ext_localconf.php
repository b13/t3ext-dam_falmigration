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

	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'B13\\DamFalmigration\\Controller\\DamMigrationCommandController';
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['extTablesInclusion-PostProcessing'][] = 'EXT:dam_falmigration/Classes/Hooks/TcaCategory.php:Tx_DamFalmigration_Hooks_TcaCategory';
