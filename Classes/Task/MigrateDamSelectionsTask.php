<?php
namespace TYPO3\CMS\DamFalmigration\Task;
/***************************************************************
 *  Copyright notice
 *  (c) 2013 Frans Saris <franssaris@gmail.com>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

class MigrateDamSelectionsTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask {

	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 *
	 * @return boolean
	 */
	public function execute() {

		$migratedCollections = 0;

		// STEP 1
		// fetch all processed sys_file_collections
		$migratedRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, _migrateddamselectionuid AS damuid',
			'sys_file_collection',
			'_migrateddamselectionuid>0 AND deleted=0',
			'',	// group by
			'', // order by
			'', // limit
			'damuid'
		);

		// exclude the already migrated DAM selections
		if (count($migratedRecords)) {
			$migratedUids = array_keys($migratedRecords);
			$additionalWhereClause = ' AND uid NOT IN (' . implode(',', $migratedUids) . ')';
		} else {
			$additionalWhereClause = '';
		}


		// STEP 2
		// fetch all DAM selection objects that are not processed yet
		$damSelections = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_dam_selection',
			'type=0 AND deleted=0' . $additionalWhereClause,
			'', // group by
			'', // order by
			'' // limit
		);

		// STEP 3
		// search for txdamFolder and create new folder based sys_file_collection
		foreach ($damSelections as $selection) {
			$selection['definition'] = unserialize($selection['definition']);
			$damFolder = FALSE;
			foreach ($selection['definition'] as $selectionElements) {
				if (array_key_exists('txdamFolder', $selectionElements)) {
					$damFolder = key($selectionElements['txdamFolder']);
					break;
				}
			}

			if ($damFolder !== FALSE) {
				$damFolder = substr($damFolder, strpos($damFolder, '/fileadmin')+10);

				$sysFileCollection = array(
					'pid' => $selection['pid'],
					'title' => $selection['title'],
					'storage' => 1,
					'description' => $selection['description'],
					'type' => 'folder',
					'folder' => $damFolder,
					'_migrateddamselectionuid' => $selection['uid']
				);
				$GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_file_collection', $sysFileCollection);

				$migratedCollections++;
			}
		}

		// STEP 4

		// print a message
		if ($migratedCollections > 0) {
			$headline = 'Migration successful';
			$message = 'Migrated ' . $migratedCollections . ' selections.';
		} else {
			$headline = 'Migration not necessary';
			$message = 'All selections have been migrated.';
		}

		$messageObject = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $message, $headline);
		\TYPO3\CMS\Core\Messaging\FlashMessageQueue::addMessage($messageObject);


		// it was always a success
		return TRUE;
	}
}