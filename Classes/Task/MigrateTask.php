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
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Scheduler Task to Migrate Records
 * Finds all DAM records that have not been migrated yet
 * and adds a DB field "_migrateddamuid" to each FAL record
 * to connect the DAM and FAL DB records
 *
 * currently it only works for files within the fileadmin
 * FILES DON'T GET MOVED somewhere else
 *
 * @author Benjamin Mack <benni@typo3.org>
 */
class MigrateTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask {

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 */
	protected $objectManager;

	/**
	 * @var \TYPO3\CMS\Core\Resource\FileRepository
	 */
	protected $fileRepository;

	/**
	 * @var \TYPO3\CMS\Core\Resource\ResourceStorage
	 */
	protected $storageObject;

	/**
	 * @var integer the storage uid for fileadmin
	 */
	protected $storageUid = 1;

	/**
	 * @var integer amount of migrated files
	 */
	protected $amountOfMigratedFiles = 0;

	/**
	 * initializes this object
	 */
	protected function init() {
		$this->objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->fileRepository = $this->objectManager->get('TYPO3\\CMS\\Core\\Resource\\FileRepository');
		$fileFactory = ResourceFactory::getInstance();
		$this->storageObject = $fileFactory->getStorageObject($this->storageUid);
	}

	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 *
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
	 * @throws \Exception
	 * @return boolean
	 */
	public function execute() {
		$this->init();

		if (ExtensionManagementUtility::isLoaded('dam')) {
			$rows = $this->getNotMigratedDamRecords();
			foreach ($rows as $damRecord) {
				if ($this->isValidDirectory($damRecord)) {
					try {
						$fileObject = $this->storageObject->getFile($this->getFullFileName($damRecord));
						if ($fileObject instanceof \TYPO3\CMS\Core\Resource\File) {
							$this->migrateFileFromDamToFal($damRecord, $fileObject);
							$this->amountOfMigratedFiles++;
						}
					} catch(\Exception $e) {
						// If file is not found
						continue;
					}
				}
			}
			// mark task as successful executed
			return TRUE;
		} else {
			throw new \Exception('Extension tx_dam is not installed. So there is nothing to migrate.');
		}
	}

	/**
	 * checks if file identifier is in a valid directory
	 * For now we check only for fileadmin directory
	 *
	 * @param array $damRecord
	 * @return bool
	 */
	protected function isValidDirectory(array $damRecord) {
		return GeneralUtility::isFirstPartOfStr($this->getFileIdentifier($damRecord), 'fileadmin/');
	}

	/**
	 * get comma separated list of already migrated dam records
	 * This method checked this with help of col: _migrateddamuid
	 *
	 * @return string
	 */
	protected function getUidListOfAlreadyMigratedRecords() {
		list($migratedRecords) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'GROUP_CONCAT( uid ) AS uidList',
			'sys_file',
			'_migrateddamuid > 0 AND deleted = 0'
		);
		if (!empty($migratedRecords['uidList'])) {
			return $migratedRecords;
		} else return '';
	}

	/**
	 * this method generates an additional where clause to find all dam records
	 * which were not already migrated
	 *
	 * @return string
	 */
	protected function getAdditionalWhereClauseForNotMigratedDamRecords() {
		$uidList = $this->getUidListOfAlreadyMigratedRecords();
		if ($uidList) {
			$additionalWhereClause = 'AND uid NOT IN (' . $uidList . ')';
		} else $additionalWhereClause = '';
		return $additionalWhereClause;
	}

	/**
	 * get all dam records which have not been migrated yet
	 *
	 * @return array
	 */
	protected function getNotMigratedDamRecords() {
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_dam',
			'deleted = 0 ' . $this->getAdditionalWhereClauseForNotMigratedDamRecords()
		);

		return $rows;
	}

	/**
	 * create file identifier from dam record
	 *
	 * @param array $damRecord
	 * @return string
	 */
	protected function getFileIdentifier(array $damRecord) {
		return $damRecord['file_path'] . $damRecord['file_name'];
	}

	/**
	 * remove storage name from fileIdentifier
	 *
	 * @param $damRecord
	 * @return mixed
	 */
	protected function getFullFileName($damRecord) {
		// maybe substr is faster but as long as fileadmin directory is configurable in installtool I think str_replace is better
		return str_replace('fileadmin/', '', $this->getFileIdentifier($damRecord));
	}

	/**
	 * add flashmessage if migration was successful or not.
	 *
	 * @return void
	 */
	protected function addResultMessage() {
		if ($this->amountOfMigratedFiles > 0) {
			$headline = LocalizationUtility::translate('migrationSuccessful', 'dam_falmigration');
			$message = LocalizationUtility::translate('migratedFiles', 'dam_falmigration', array(0 => $this->amountOfMigratedFiles));
		} else {
			$headline = LocalizationUtility::translate('migrationNotNecassary', 'dam_falmigration');;
			$message = LocalizationUtility::translate('allFilesMigrated', 'dam_falmigration');
		}

		$messageObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $message, $headline);
		// addMessage is a magic method realized by __call()
		FlashMessageQueue::addMessage($messageObject);
	}

	/**
	 * migrate file from dam record to fal system
	 *
	 * @param array $damRecord
	 * @param \TYPO3\CMS\Core\Resource\File $fileObject
	 * @return void
	 */
	protected function migrateFileFromDamToFal(array $damRecord, \TYPO3\CMS\Core\Resource\File $fileObject) {
		// add the migrated uid of the DAM record to the FAL record
		$updateData = array(
			'_migrateddamuid' => $damRecord['uid']
		);

		// also add meta data to the FAL record
		if (ExtensionManagementUtility::isLoaded('filemetadata')) {
			// see script of CK in Installtool for migration sys_file -> sys_file_metadata
			$updateData['keywords'] = $damRecord['keywords'];
			$updateData['description'] = $damRecord['description'];
			$updateData['location_country'] = $damRecord['loc_country'];
		}

		$fileObject->updateProperties($updateData);

		/**
		 * FAL has a list of allowed updateable fields:
		 * 'uid', 'pid', 'missing', 'type', 'storage', 'identifier', 'extension', 'mime_type', 'name', 'sha1', 'size', 'creation_date', 'modification_date'
		 * That's why _migrateddamuid, keywords, description and location_country will never be filled
		 */
		\TYPO3\CMS\Core\Resource\Index\FileIndexRepository::getInstance()->update($fileObject);
	}

}
