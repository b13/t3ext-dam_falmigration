<?php
namespace TYPO3\CMS\DamFalmigration\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Benjamin Mack <benni@typo3.org>
 *  (c) 2013 Stefan Froemken <froemken@gmail.com>
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
 *  A copy is found in the textfile GPL.txt and important notices to the
 * license from the author is found in LICENSE.txt distributed with these
 * scripts.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use B13\DamFalmigration\Controller\DamMigrationCommandController;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * @author Benjamin Mack <benni@typo3.org>
 */
abstract class AbstractService {

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager;

	/**
	 * @var \B13\DamFalmigration\Controller\DamMigrationCommandController
	 *    $controller Used to log output to console
	 */
	protected $controller;

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $database;

	/**
	 * @var array
	 */
	protected $fieldMapping = array();

	/**
	 * @var \TYPO3\CMS\Core\Resource\FileRepository
	 * @inject
	 */
	protected $fileRepository;

	/**
	 * @var integer
	 */
	private $recordLimit = 999999;

	/**
	 * @var string
	 */
	protected $storageBasePath;

	/**
	 * @var \TYPO3\CMS\Core\Resource\ResourceStorage
	 */
	protected $storageObject;

	/**
	 * @var integer the storage uid for fileadmin
	 */
	protected $storageUid = 1;

	/**
	 * @var integer amount of migrated records
	 */
	protected $amountOfMigratedRecords = 0;

	/**
	 * initializes this object
	 *
	 * @param \B13\DamFalmigration\Controller\DamMigrationCommandController $controller
	 */
	public function __construct(DamMigrationCommandController $controller = NULL) {
		$this->setController($controller);
	}

	/**
	 * initializes this object
	 *
	 * @return void
	 */
	public function initializeObject() {
		$this->database = $GLOBALS['TYPO3_DB'];
		$fileFactory = ResourceFactory::getInstance();
		$this->storageObject = $fileFactory->getStorageObject($this->storageUid);
		$storageConfiguration = $this->storageObject->getConfiguration();
		$this->storageBasePath = $storageConfiguration['basePath'];
	}

	/**
	 * check if given table exists in current database
	 * we can't check TCA or for installed extensions because dam and
	 * dam_ttcontent are not available for TYPO3 6.2
	 *
	 * @param $table
	 *
	 * @return bool
	 */
	protected function isTableAvailable($table) {
		$tables = $this->database->admin_get_tables();

		return array_key_exists($table, $tables);
	}

	/**
	 * check if given field exists in a table
	 *
	 * @param string $field
	 * @param string $table
	 *
	 * @return bool
	 */
	protected function isFieldAvailable($field, $table) {
		$tables = $this->database->admin_get_fields($table);

		return array_key_exists($field, $tables);
	}

	/**
	 * Fetches all $tablename records with DAM connections
	 * Returns the item uid and pid as item_uid and item_pid
	 *
	 * @param string $tableName
	 * @param string $ident
	 *
	 * @return \mysqli_result
	 */
	protected function getRecordsWithDamConnections($tableName, $ident) {
		return $this->database->exec_SELECTquery(
			'i.uid as item_uid,
			 i.pid as item_pid,
			 r.uid_local,
			 r.uid_foreign,
			 r.tablenames,
			 r.sorting,
			 r.sorting_foreign,
			 r.ident,
			 d.uid as dam_uid,
			 d.file_name,
			 d.file_path,
			 d.l18n_diffsource',
			'tx_dam_mm_ref as r
				INNER JOIN ' . $tableName . ' as i ON r.uid_foreign = i.uid
				INNER JOIN tx_dam as d ON d.uid = r.uid_local',
			'r.tablenames = "' . $tableName . '"
			 AND r.ident = "' . $ident . '"
			 AND d.file_path LIKE "' . $this->storageBasePath . '%"
			 AND d.deleted = 0
			 AND i.deleted = 0'
		);
	}

	/**
	 * Migrate dam references to fal references
	 *
	 * @param \mysqli_result $result
	 * @param string $table
	 * @param string $type
	 * @param array $fieldnameMapping Re-map fieldnames e.g.
	 *    tx_damnews_dam_images => tx_falttnews_fal_images
	 *
	 * @return void
	 */
	protected function migrateDamReferencesToFalReferences($result, $table, $type, $fieldnameMapping = array()) {
		$counter = 0;
		$total = $this->database->sql_num_rows($result);
		$this->controller->infoMessage('Found ' . $total . ' ' . $table . ' records with a dam ' . $type);
		while ($record = $this->database->sql_fetch_assoc($result)) {
			$identifier = $this->getFullFileName($record);

			try {
				$fileObject = $this->storageObject->getFile($identifier);
			} catch (\Exception $e) {
				// If file is not found
				// getFile will throw an invalidArgumentException if the file
				// does not exist. Create an empty file to avoid this. This is
				// usefull in a development environment that has the production
				// database but not all the physical files.
				try {
					GeneralUtility::mkdir_deep(PATH_site . $this->storageBasePath . dirname($identifier));
				} catch (\Exception $e) {
					$this->controller->errorMessage('Unable to create directory: ' . PATH_site . $this->storageBasePath . $identifier);
					continue;
				}

				$config = $this->controller->getConfiguration();
				if (isset($config['createMissingFiles']) && (int)$config['createMissingFiles']) {
					$this->controller->infoMessage('Creating empty missing file: ' . PATH_site . $this->storageBasePath . $identifier);
					try {
						GeneralUtility::writeFile(PATH_site . $this->storageBasePath . $identifier, '');
					} catch (\Exception $e) {
						$this->controller->errorMessage('Unable to create file: ' . PATH_site . $this->storageBasePath . $identifier);
						continue;
					}
				} else {
					$this->controller->errorMessage('File not found: ' . PATH_site . $this->storageBasePath . $identifier);
					continue;
				}
				$fileObject = $this->storageObject->getFile($identifier);
			}
			if ($fileObject instanceof \TYPO3\CMS\Core\Resource\File) {
				if ($fileObject->isMissing()) {
					$this->controller->warningMessage('FAL did not find any file resource for DAM record. DAM uid: ' . $record['uid'] . ': "' . $identifier . '"');
					continue;
				}

				$record['uid_local'] = $fileObject->getUid();
				foreach ($fieldnameMapping as $old => $new) {
					if ($record['ident'] === $old) {
						$record['ident'] = $new;
					}
				}

				$progress = number_format(100 * ($counter++ / $total), 1) . '% of ' . $total;
				if (!$this->doesFileReferenceExist($record)) {

					$insertData = array(
						'tstamp' => time(),
						'crdate' => time(),
						'cruser_id' => $GLOBALS['BE_USER']->user['uid'],
						'uid_local' => $record['uid_local'],
						'uid_foreign' => (int)$record['uid_foreign'],
						'sorting' => (int)$record['sorting'],
						'sorting_foreign' => (int)$record['sorting_foreign'],
						'tablenames' => (string)$record['tablenames'],
						'fieldname' => (string)$record['ident'],
						'table_local' => 'sys_file',
						'pid' => $record['item_pid'],
						'l10n_diffsource' => (string)$record['l18n_diffsource']
					);
					$this->database->exec_INSERTquery(
						'sys_file_reference',
						$insertData
					);
					$this->amountOfMigratedRecords++;
					$this->controller->message($progress . ' Migrating relation for ' . (string)$record['tablenames'] . ' uid: ' . $record['item_uid'] . ' dam uid: ' . $record['dam_uid'] . ' to fal uid: ' . $record['uid_local']);
				} else {
					$this->controller->message($progress . ' Reference already exists for uid: ' . (int)$record['item_uid']);
				}
			}
		}
		$this->database->sql_free_result($result);
	}

	/**
	 * check if a sys_file_reference already exists
	 *
	 * @param array $fileReference
	 *
	 * @return boolean
	 */
	protected function doesFileReferenceExist(array $fileReference) {
		return (bool)$this->database->exec_SELECTcountRows(
			'uid',
			'sys_file_reference',
			'uid_local = ' . $fileReference['uid_local'] .
			' AND uid_foreign = ' . $fileReference['uid_foreign'] .
			' AND tablenames = "' . $fileReference['tablenames'] . '"' .
			' AND fieldname = "' . $fileReference['ident'] . '"' .
			' AND table_local = "sys_file"' .
			' AND deleted = 0'
		);
	}

	/**
	 * create file identifier from dam record
	 *
	 * @param array $damRecord
	 *
	 * @return string
	 */
	protected function getFileIdentifier(array $damRecord) {
		return $damRecord['file_path'] . $damRecord['file_name'];
	}

	/**
	 * remove storage name from fileIdentifier
	 *
	 * @param $damRecord
	 *
	 * @return mixed
	 */
	protected function getFullFileName($damRecord) {
		return str_replace($this->storageBasePath, '', $this->getFileIdentifier($damRecord));
	}

	/**
	 * add flashmessage if migration was successful or not.
	 *
	 * @param null $status
	 * @param null $message Additional message body to pass along with a status
	 *
	 * @return FlashMessage
	 */
	protected function getResultMessage($status = NULL, $message = NULL) {
		$headline = LocalizationUtility::translate('nothingToSeeHere', 'dam_falmigration');
		if ($message === NULL) {
			$message = LocalizationUtility::translate('moveAlong', 'dam_falmigration');
		}
		if ($this->amountOfMigratedRecords > 0) {
			$headline = LocalizationUtility::translate('migrationSuccessful', 'dam_falmigration');
			$message = LocalizationUtility::translate('migratedFiles', 'dam_falmigration', array(0 => $this->amountOfMigratedRecords));
		} elseif ($this->amountOfMigratedRecords === 0 && $status !== NULL) {
			$headline = LocalizationUtility::translate('migrationNotNecessary', 'dam_falmigration');
			$message = LocalizationUtility::translate('allFilesMigrated', 'dam_falmigration');
		} elseif ($status !== NULL) {
			$headline = LocalizationUtility::translate('migrationStatusHeadline.' . $status, 'dam_falmigration');
			if ($message === NULL) {
				$message = LocalizationUtility::translate('migrationStatusMessage' . $status, 'dam_falmigration');
			}
		}

		return GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $message, $headline);
	}

	/**
	 * @return int
	 */
	public function getRecordLimit() {
		return $this->recordLimit;
	}

	/**
	 * @param int $recordLimit
	 *
	 * @return $this to allow for chaining
	 */
	public function setRecordLimit($recordLimit) {
		$this->recordLimit = $recordLimit;

		return $this;
	}

	/**
	 * Sets the uid of the File storage record
	 *
	 * @return int
	 */
	public function getStorageUid() {
		return $this->storageUid;
	}

	/**
	 * Gets the uid of the File storage record
	 *
	 * @param int $storageUid
	 *
	 * @return $this to allow for chaining
	 */
	public function setStorageUid($storageUid) {
		$this->storageUid = $storageUid;

		return $this;
	}

	/**
	 * @param DamMigrationCommandController $controller
	 *
	 * @return $this to allow for chaining
	 */
	public function setController($controller) {
		$this->controller = $controller;

		return $this;
	}

	/**
	 * Update reference counters for given table and fieldmapping
	 *
	 * @param string $table
	 *
	 * @return void
	 */
	protected function updateReferenceCounters($table) {
		$set = array();
		$this->controller->successMessage(LocalizationUtility::translate('updateReferenceCounters', 'dam_falmigration'));
		foreach ($this->fieldMapping as $old => $new) {
			if ($this->isFieldAvailable($new, $table)) {
				$set[] = $new . ' = ' . $old;
			}
		}
		if (count($set)) {
			$this->database->sql_query(
				'UPDATE ' . $table . ' SET ' . implode(',', $set)
			);
		}
	}
}