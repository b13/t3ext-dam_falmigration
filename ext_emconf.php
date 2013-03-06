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
	'author' => 'Benjamin Mack',
	'author_email' => 'benni@typo3.org',
	'shy' => '',
	'dependencies' => 'cms',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'alpha',
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
			'cms' => '',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:5:{s:9:"ChangeLog";s:4:"64e1";s:10:"README.txt";s:4:"ee2d";s:12:"ext_icon.gif";s:4:"1bdc";s:19:"doc/wizard_form.dat";s:4:"b6d7";s:20:"doc/wizard_form.html";s:4:"7bb0";}',
);

?>
