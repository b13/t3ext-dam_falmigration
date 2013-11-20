<?php
namespace TYPO3\CMS\DamFalmigration\Task;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Benjamin Mack <benni@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * Scheduler Task to Migrate DAM relations to FAL relations
 * right now this is dam_ttcontent, dam_uploads
 *
 * @author      Benjamin Mack <benni@typo3.org>
 *
 */
class MigrateRelationsTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask {

	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 * 
	 * @return boolean
	 */
	public function execute() {

			// check for all FAL records that are there, that have been migrated already
		$falRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, _migrateddamuid AS damuid',
			'sys_file',
			'_migrateddamuid>0 AND deleted=0',
			'',	// group by
			'', // order by
			'', // limit
			'damuid'
		);

			// get all DAM relations
		$damRelations = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid_local AS damuid, uid_foreign AS referenceuid, tablenames, ident',
			'tx_dam_mm_ref',
			'1=1'
		);

		$migratedFiles = 0;
		foreach ($damRelations as $damRelationData) {
			$falUid = $falRecords[$damRelationData['damuid']]['uid'];
			$falUid = intval($falUid);
			$recordUid = intval($damRelationData['referenceuid']);

			$fieldName = $damRelationData['ident'];
			
				// only if we have an indexed FAL record
			if ($falUid > 0) {
				
			
					// migrate from dam_ttcontent to native FAL images
				if ($damRelationData['tablenames'] == 'tt_content' && $fieldName == 'tx_damttcontent_files') {
					$fieldName = 'image';
				}
			
				$insertData = array(
					'uid_local' => $falUid,
					'uid_foreign' => $recordUid,
					'tablenames' => $damRelationData['tablenames'],
					'fieldname' => $fieldName,
					'table_local' => 'sys_file',
				);
			
					// check if the relation already exists
					// if so, we don't do anything
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'uid',
					'sys_file_reference',
					'uid_local=' . $falUid . ' AND uid_foreign=' . $recordUid . ' AND tablenames=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($damRelationData['tablenames'], 'sys_file_reference')
					. ' AND deleted=0'
				);

					// now put them into the sys_file_reference table
				if ($res && $GLOBALS['TYPO3_DB']->sql_num_rows($res) === 0) {
					/** @var $GLOBALS['TYPO3_DB'] t3lib_DB */
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_file_reference', $insertData);
					$migratedFiles++;
				}
			}
		}

		
		if ($migratedFiles > 0) {
				// update the reference index
			$refIndexObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\ReferenceIndex');
//			list($headerContent, $bodyContent, $errorCount) = $refIndexObj->updateIndex('check', FALSE);
			list($headerContent, $bodyContent, $errorCount) = $refIndexObj->updateIndex('update', FALSE);

			$messageObject = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
				'Migrated ' . $migratedFiles . ' relations. <br />' . nl2br($bodyContent),
				'Migration successful'
			);
			\TYPO3\CMS\Core\Messaging\FlashMessageQueue::addMessage($messageObject);
		}

			// it was always a success
		return TRUE;
	}

}