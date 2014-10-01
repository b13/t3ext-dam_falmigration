<?php
namespace TYPO3\CMS\DamFalmigration\Service;

/**
 *  Copyright notice
 *
 *  (c) 2012 Benjamin Mack <benni@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is free
 *  software; you can redistribute it and/or modify it under the terms of the
 *  GNU General Public License as published by the Free Software Foundation;
 *  either version 2 of the License, or (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 *  or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 *  more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use B13\DamFalmigration\Controller\DamMigrationCommandController;

/**
 * Service to Migrate Records
 * Finds all DAM records that have not been migrated yet
 * and adds a DB field "_migrateddamuid" to each FAL record
 * to connect the DAM and FAL DB records
 *
 * currently it only works for files within the fileadmin
 * FILES DON'T GET MOVED somewhere else
 *
 * @author Benjamin Mack <benni@typo3.org>
 */
class MigrateService extends AbstractService {

	/**
	 * how to map cols for meta data
	 * These cols are always available since TYPO3 6.2
	 *
	 * @var array
	 */
	protected $metaColMapping = array(
		'title' => 'title',
		'hpixels' => 'width',
		'vpixels' => 'height',
		'description' => 'description',
		'alt_text' => 'alternative',
		'categories' => 'categories',
	);

	/**
	 * how to map cols for meta data
	 * These additional cols are available only if ext:filemetadata is installed
	 *
	 * @var array
	 */
	protected $additionalMetaColMapping = array(
		'creator' => 'creator',
		'keywords' => 'keywords',
		'caption' => 'caption',
		'language' => 'language',
		'pages' => 'pages',
		'publisher' => 'publisher',
		'loc_country' => 'location_country',
		'loc_city' => 'location_city',
	);

	/**
	 * saves the amount of files which could not be found in storage
	 *
	 * @var integer
	 */
	protected $amountOfFilesNotFound = 0;

	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 *
	 * @param DamMigrationCommandController $parent Used to log output to
	 *    console
	 *
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
	 * @throws \Exception
	 * @return FlashMessage
	 */
	public function execute($parent) {
		if ($this->isTableAvailable('tx_dam')) {

			$rows = $this->getNotMigratedDamRecords();

			$parent->infoMessage('Found ' . count($rows) . ' DAM records with no connected sys_file entry');

			foreach ($rows as $damRecord) {
				if ($this->isValidDirectory($damRecord)) {
					try {
						$fullFileName = $this->getFullFileName($damRecord);
						$fileObject = $this->storageObject->getFile($fullFileName);
						if ($fileObject instanceof \TYPO3\CMS\Core\Resource\File) {
							if ($fileObject->isMissing()) {
								$this->warningMessage('FAL did not find any file resource for DAM record. DAM uid: ' . $damRecord['uid'] . ': "' . $fullFileName . '"');
								continue;
							}
							$this->migrateFileFromDamToFal($damRecord, $fileObject);
							$this->amountOfMigratedRecords++;
						}
					} catch (\Exception $e) {
						// If file is not found
						$this->amountOfFilesNotFound++;
						continue;
					}
				}
			}

			$parent->message(
				'Not migrated dam records at start of task: ' . count($rows) . '. Migrated files after task: ' . $this->amountOfMigratedRecords . '. Files not found: ' . $this->amountOfFilesNotFound . '.'
			);
		} else {
			$parent->errorMessage('Extension tx_dam is not installed. So there is nothing to migrate.');
		}

		return $this->getResultMessage();
	}

	/**
	 * checks if file identifier is in a valid directory
	 * For now we check only for fileadmin directory
	 *
	 * @param array $damRecord
	 *
	 * @return bool
	 */
	protected function isValidDirectory(array $damRecord) {
		return GeneralUtility::isFirstPartOfStr($this->getFileIdentifier($damRecord), 'fileadmin/');
	}

	/**
	 * get a count of all dam records which have been migrated
	 *
	 * @return array
	 */
	protected function countMigratedDamRecords() {
		if ($this->database === NULL) {
			$this->init();
		}
		$row = $this->database->exec_SELECTgetSingleRow(
			'COUNT(*) as recordCount',
			'tx_dam LEFT JOIN sys_file ON (tx_dam.uid = sys_file._migrateddamuid)',
			'NOT sys_file.uid IS NULL AND tx_dam.deleted = 0'
		);
		if ($row === NULL) {
			// SQL error appears
			return 0;
		} else {
			return $row['recordCount'];
		}
	}

	/**
	 * get all dam records which have not been migrated yet
	 *
	 * @return array
	 */
	protected function getNotMigratedDamRecords() {
		$rows = $this->database->exec_SELECTgetRows(
			'tx_dam.*, (SELECT COUNT(*) FROM tx_dam_mm_cat WHERE tx_dam_mm_cat.uid_local = tx_dam.uid) as categories',
			'tx_dam LEFT JOIN sys_file ON (tx_dam.uid = sys_file._migrateddamuid)',
			'sys_file.uid IS NULL AND tx_dam.deleted = 0'
		);
		if ($rows === NULL) {
			// SQL error appears
			return array();
		} else {
			return $rows;
		}
	}

	/**
	 * remove storage name from fileIdentifier
	 *
	 * @param $damRecord
	 *
	 * @return mixed
	 */
	protected function getFullFileName($damRecord) {
		return str_replace('fileadmin', '', $this->getFileIdentifier($damRecord));
	}

	/**
	 * migrate file from dam record to fal system
	 *
	 * @param array $damRecord
	 * @param \TYPO3\CMS\Core\Resource\File $fileObject
	 *
	 * @throws \Exception
	 * @return void
	 */
	protected function migrateFileFromDamToFal(array $damRecord, \TYPO3\CMS\Core\Resource\File $fileObject) {
		// in getProperties() we don't have the required UID of metadata record
		// if no metadata record is available it will automatically created within FAL
		$metadataRecord = $fileObject->_getMetaData();

		if (is_array($metadataRecord)) {
			// update existing record
			$this->database->exec_UPDATEquery(
				'sys_file_metadata',
				'uid = ' . $metadataRecord['uid'],
				$this->createArrayForUpdateInsertSysFileRecord($damRecord)
			);

			// add the migrated uid of the DAM record to the FAL record
			$this->database->exec_UPDATEquery(
				'sys_file',
				'uid = ' . $fileObject->getUid(),
				array('_migrateddamuid' => $damRecord['uid'])
			);
		}
	}

	/**
	 * create an array for insert or updating the sys_file record
	 *
	 * @param array $damRecord
	 *
	 * @return array
	 */
	protected function createArrayForUpdateInsertSysFileRecord(array $damRecord) {
		$updateData = array(
			'tstamp' => time(),
		);

		// add always available cols for filemetadata
		foreach ($this->metaColMapping as $damColName => $metaColName) {
			$updateData[$metaColName] = $damRecord[$damColName];
		}

		// add additional cols if ext:for filemetadata is installed
		if (ExtensionManagementUtility::isLoaded('filemetadata')) {
			foreach ($this->additionalMetaColMapping as $damColName => $metaColName) {
				$updateData[$metaColName] = $damRecord[$damColName];
			}
		}

		return $updateData;
	}
}
