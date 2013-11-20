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
 * Scheduler Task to Migrate Records
 * Finds all DAM records that have not been migrated yet
 * and adds a DB field "_migrateddamuid" to each FAL record
 * to connect the DAM and FAL DB records
 *
 * currently it only works for files within the fileadmin
 * FILES DON'T GET MOVED somewhere else
 *
 * @author      Benjamin Mack <benni@typo3.org>
 *
 */
class MigrateTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask {

		// the storage object for the fileadmin
	protected $storageUid = 1;

	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 *
	 * @return boolean
	 */
	public function execute() {

			// check for all FAL records that are there, that have been migrated already
			// seen by the "_migrateddamuid" flag
		$migratedRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, _migrateddamuid AS damuid',
			'sys_file',
			'_migrateddamuid>0 AND deleted=0',
			'',	// group by
			'', // order by
			'', // limit
			'damuid'
		);

			// exclude the already migrated DAM records
		if (count($migratedRecords)) {
			$migratedUids = array_keys($migratedRecords);
			$additionalWhereClause = ' AND uid NOT IN (' . implode(',', $migratedUids) . ')';
		} else {
			$additionalWhereClause = '';
		}

		$fileFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();

			// create the storage object
		$storageObject = $fileFactory->getStorageObject($this->storageUid);

			// DB-query to update all info
		/** @var $fileRepository t3lib_file_Repository_FileRepository */
		$fileRepository = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\FileRepository');

		$migratedFiles = 0;
		$newFalRecords = array();

		// get all DAM records that have not been migrated yet
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_dam',
			'deleted=0 ' . $additionalWhereClause
		);
		while ($damRecord = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

			$damUid = $damRecord['uid'];
			$fileIdentifier = $damRecord['file_path'] . $damRecord['file_name'];

			// right now we only support files in fileadmin/
			if (\TYPO3\CMS\Core\Utility\GeneralUtility::isFirstPartOfStr($fileIdentifier, 'fileadmin/') === TRUE) {
				// strip away the "fileadmin/" prefix
				$fullFileName = substr($fileIdentifier, 10);

				// check if the DAM record is already indexed for FAL (based on the filename)
				try {
					$fileObject = $storageObject->getFile($fullFileName);
				} catch(\TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException $e) {
					// file not found jump to next file
					continue;
				} catch(\Exception $e) {
					var_dump($e);
				}

				if ($fileObject instanceof \TYPO3\CMS\Core\Resource\File) {
					// add the migrated uid of the DAM record to the FAL record
					$updateData = array(
						'_migrateddamuid' => $damUid
					);

					// also add meta data to the FAL record
					if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('media')) {
						$updateData['keywords']    = $damRecord['keywords'];
						$updateData['description'] = $damRecord['description'];
						$updateData['location_country'] = $damRecord['location_country'];
					}

					$fileObject->updateProperties($updateData);
					$fileRepository->update($fileObject);

					#$uid = $fileObject->getUid();
					$migratedFiles++;


				} else {
					// no file object
					// what to do?
				}
			}
		}

			// print a message
		if ($migratedFiles > 0) {
			$headline = 'Migration successful';
			$message = 'Migrated ' . $migratedFiles . ' files.';
		} else {
			$headline = 'Migration not necessary';
			$message = 'All files have been migrated.';
		}

		$messageObject = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $message, $headline);
		\TYPO3\CMS\Core\Messaging\FlashMessageQueue::addMessage($messageObject);

			// it was always a success
		return TRUE;
	}

}