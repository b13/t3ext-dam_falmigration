<?php
/*
 * Register necessary class names with autoloader
 */
$extensionPath = t3lib_extMgm::extPath('dam_falmigration');
return array(
	'tx_damfalmigration_task_migratetask' => $extensionPath . 'Classes/Task/MigrateTask.php',
	'tx_damfalmigration_task_migraterelationstask' => $extensionPath . 'Classes/Task/MigrateRelationsTask.php',
	'tx_damfalmigration_task_migratedamfrontendtask' => $extensionPath . 'Classes/Task/MigrateDamFrontendTask.php',
);
