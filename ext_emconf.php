<?php

########################################################################
# Extension Manager/Repository config file for ext "dam_falmigration".
#
# Auto generated 15-11-2009 15:50
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Migrate DAM Records and references to FAL references',
	'description' => 'Takes the DAM records and references (for tt_content right now) and migrates them into the local fileadmin/ FAL storage.',
	'category' => 'misc',
	'author' => 'Benjamin Mack, Michiel Roos and a lot others',
	'author_email' => 'benni@typo3.org',
	'shy' => '',
	'dependencies' => 'cms',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => '',
	'version' => '1.0.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.2.0-6.2.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);
