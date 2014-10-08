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
use B13\DamFalmigration\Controller\DamMigrationCommandController;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Migrates DAM metadata to FAL metadata. Searches for all migrated sys_file
 * records that don't have any titles yet.
 *
 * @author Benjamin Mack <benni@typo3.org>
 */
class MigrateMetadataService extends AbstractService {

	/**
	 * how to map cols for meta data
	 * These cols are always available since TYPO3 6.2
	 *
	 * @var array
	 */
	protected $columnMapping = array(
		'alt_text' => 'alternative',
		'categories' => 'categories',
		'description' => 'description',
		'hpixels' => 'width',
		'title' => 'title',
		'vpixels' => 'height',
	);

	/**
	 * how to map cols for meta data
	 * These additional cols are available only if ext:filemetadata is installed
	 *
	 * @var array
	 */
	protected $fileMetadataColumnMapping = array(
		'caption' => 'caption',
		'color_space' => 'color_space',
		'creator' => 'creator',
		'date_cr' => 'content_creation_date',
		'date_mod' => 'content_modification_date',
		'fe_group' => 'fe_groups',
		'file_dl_name' => 'download_name',
		'height_unit' => 'unit',
		'hidden' => 'visible',
		'instrcuctions' => 'note',
		'keywords' => 'keywords',
		'language' => 'language',
		'loc_city' => 'location_city',
		'loc_country' => 'location_country',
		'pages' => 'pages',
		'publisher' => 'publisher',
	);

	/**
	 * how to map cols for meta data
	 * These additional cols are available only if ext:media is installed
	 *
	 * @var array
	 */
	protected $mediaColumnMapping = array(
		'caption' => 'caption',
		'color_space' => 'color_space',
		'creator' => 'creator',
		'date_cr' => 'creation_date',
		'date_mod' => 'modification_date',
		'fe_group' => 'fe_groups',
		'file_dl_name' => 'download_name',
		'height_unit' => 'unit',
		'hidden' => 'visible',
		'instrcuctions' => 'note',
		'keywords' => 'keywords',
		'language' => 'language',
		'loc_city' => 'location_city',
		'loc_country' => 'location_country',
		'pages' => 'pages',
		'publisher' => 'publisher',
	);

	/**
	 * If the filemetadata extension is installed, this value will be true
	 *
	 * @var boolean
	 */
	protected $isInstalledFileMetadata = FALSE;

	/**
	 * If the media extension is installed, this value will be true
	 *
	 * @var boolean
	 */
	protected $isInstalledMedia = FALSE;

	/**
	 * Main method
	 *
	 * @param DamMigrationCommandController $parent Used to log output to
	 *    console
	 *
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
	 * @throws \Exception
	 * @return FlashMessage
	 */
	public function execute($parent) {
		$parent->headerMessage(LocalizationUtility::translate('migrateDamMetadataCommand', 'dam_falmigration'));
		if ($this->isTableAvailable('tx_dam')) {

			$rows = $this->getSysFileRecords();

			$parent->infoMessage('Found ' . count($rows) . ' migrated sys_file records without a title');

			$this->isInstalledFileMetadata = ExtensionManagementUtility::isLoaded('filemetadata');
			$this->isInstalledMedia = ExtensionManagementUtility::isLoaded('media');

			foreach ($rows as $record) {
				$this->database->exec_UPDATEquery(
					'sys_file_metadata',
					'uid = ' . $record['metadata_uid'],
					$this->createArrayForUpdateSysFileMetadataRecord($record)
				);

				$this->database->exec_UPDATEquery(
					'sys_file',
					'uid = ' . $record['file_uid'],
					$this->createArrayForUpdateSysFileRecord($record)
				);
				$parent->message('Updating metadata of record: ' . $record['file_uid'] . ' ' . $record['file_uid']);
				$this->amountOfMigratedRecords++;
			}
		} else {
			$parent->errorMessage('Extension tx_dam is not installed. So there is nothing to migrate.');
		}

		return $this->getResultMessage();
	}

	/**
	 * Get all migrated sys_file records without a title
	 *
	 * @return array
	 */
	protected function getSysFileRecords() {
		$rows = $this->database->exec_SELECTgetRows(
			'DISTINCT m.uid AS metadata_uid, f.uid as file_uid, f._migrateddamuid AS dam_uid, d.*',
			'sys_file f, sys_file_metadata m, tx_dam d',
			'm.file=f.uid AND f._migrateddamuid=d.uid AND f._migrateddamuid > 0 AND m.title IS NULL'
		);
		if ($rows === NULL) {
			// SQL error appears
			return array();
		} else {
			return $rows;
		}
	}

	/**
	 * create an array for updating the sys_file_metadata record
	 *
	 * @param array $damRecord
	 *
	 * @return array
	 */
	protected function createArrayForUpdateSysFileMetadataRecord(array $damRecord) {
		$updateData = array();

		// add always available columns for filemetadata
		foreach ($this->columnMapping as $damColName => $metaColName) {
			$updateData[$metaColName] = $damRecord[$damColName];
		}

		// add additional columns if ext:filemetadata is installed
		if ($this->isInstalledFileMetadata) {
			foreach ($this->fileMetadataColumnMapping as $damColName => $metaColName) {
				$updateData[$metaColName] = $damRecord[$damColName];
			}
		}

		return $updateData;
	}

	/**
	 * create an array for updating the sys_file record
	 *
	 * @param array $damRecord
	 *
	 * @return array
	 */
	protected function createArrayForUpdateSysFileRecord(array $damRecord) {
		$updateData = array(
			'tstamp' => time(),
		);

		// add always available columns for filemetadata
		foreach ($this->columnMapping as $damColName => $metaColName) {
			$updateData[$metaColName] = $damRecord[$damColName];
		}

		// add additional columns if ext:media is installed
		if ($this->isInstalledMedia) {
			foreach ($this->mediaColumnMapping as $damColName => $metaColName) {
				$updateData[$metaColName] = $damRecord[$damColName];
			}
		}

		return $updateData;
	}
}
