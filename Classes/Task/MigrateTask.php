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
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
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
class MigrateTask extends AbstractTask {

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
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 *
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
	 * @throws \Exception
	 * @return boolean
	 */
	public function execute() {
		$this->init();

		if ($this->isTableAvailable('tx_dam')) {
			$rows = $this->getNotMigratedDamRecords();
			foreach ($rows as $damRecord) {
				if ($this->isValidDirectory($damRecord)) {
					try {
						$fileObject = $this->storageObject->getFile($this->getFullFileName($damRecord));
						if ($fileObject instanceof \TYPO3\CMS\Core\Resource\File) {
							$this->migrateFileFromDamToFal($damRecord, $fileObject);
							$this->amountOfMigratedRecords++;
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
		list($migratedRecords) = $this->database->exec_SELECTgetRows(
			'GROUP_CONCAT( _migrateddamuid ) AS uidList',
			'sys_file',
			'_migrateddamuid > 0'
		);
		if (!empty($migratedRecords['uidList'])) {
			return $migratedRecords['uidList'];
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
		$rows = $this->database->exec_SELECTgetRows(
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
		return str_replace('fileadmin', '', $this->getFileIdentifier($damRecord));
	}

	/**
	 * migrate file from dam record to fal system
	 *
	 * @param array $damRecord
	 * @param \TYPO3\CMS\Core\Resource\File $fileObject
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
