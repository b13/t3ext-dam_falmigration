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

	$TYPO3_CONF_VARS['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\DamFalmigration\\Task\\MigrateDamFrontendTask'] = array(
		'extension'        => $_EXTKEY,
		'title'            => 'DAM-FAL Migration: Migrate DAM_Frontend Plugins',
		'description'      => 'Migrates all available DAM frontend plugins, and replaces them with tt_content uploads elements.',
	);

}

