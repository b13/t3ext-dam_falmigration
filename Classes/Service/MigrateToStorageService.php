<?php
namespace TYPO3\CMS\DamFalmigration\Service;

/**
 *  Copyright notice
 *
 *  (c) 2015 Nicole Cordes <cordes@cps-it.de>
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

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Resource;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Service to move record files which are not belonging to a storage yet.
 * The files are stored in the _migrated folder of the configured storage.
 *
 * @author Nicole Cordes <cordes@cps-it.de>
 */
class MigrateToStorageService extends AbstractService {

	/**
	 * @var Resource\Index\FileIndexRepository
	 */
	protected $fileIndexRepository = NULL;

	/**
	 * @var int
	 */
	protected $amountOfMigratedRecords = 0;

	/**
	 * @var int
	 */
	protected $amountOfFilesNotFound = 0;

	/**
	 * @var string
	 */
	protected $targetFolderBasePath = '_migrated/dam/';

	/**
	 * @return FlashMessage
	 */
	public function execute() {
		$this->controller->headerMessage(LocalizationUtility::translate('moveDamRecordsToStorageCommand', 'dam_falmigration', array($this->storageObject->getName())));
		if (!$this->isTableAvailable('tx_dam')) {
			return $this->getResultMessage('damTableNotFound');
		}

		$this->fileIndexRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\Index\\FileIndexRepository');
		$result = $this->execSelectNotMigratedDamRecordsQuery();

		$counter = 0;
		$total = $this->database->sql_num_rows($result);
		$this->controller->infoMessage('Found ' . $total . ' DAM records without a connection to a sys_file storage');
		$relativeTargetFolderBasePath = $this->storageBasePath . $this->targetFolderBasePath;

		while ($damRecord = $this->database->sql_fetch_assoc($result)) {
			$counter++;
			try {
				$relativeSourceFilePath = GeneralUtility::fixWindowsFilePath($this->getFullFileName($damRecord));
				$absoluteSourceFilePath = PATH_site . $relativeSourceFilePath;

				if (!file_exists($absoluteSourceFilePath)) {
					throw new \RuntimeException('No file found for DAM record. DAM uid: ' . $damRecord['uid'] . ': "' . $relativeSourceFilePath . '"', 1441110613);
				}

				list($_, $directory) = explode('/', dirname($relativeSourceFilePath), 2);
				$relativeTargetFolder = $relativeTargetFolderBasePath . rtrim($directory, '/') . '/';
				$absoluteTargetFolder = PATH_site . $relativeTargetFolder;
				if (!is_dir($absoluteTargetFolder)) {
					GeneralUtility::mkdir_deep($absoluteTargetFolder);
				}

				$basename = basename($relativeSourceFilePath);
				$absoluteTargetFilePath = $absoluteTargetFolder . $basename;
				if (!file_exists($absoluteTargetFilePath)) {
					GeneralUtility::upload_copy_move($absoluteSourceFilePath, $absoluteTargetFilePath);
				} elseif (filesize($absoluteSourceFilePath) !== filesize($absoluteTargetFilePath)) {
					throw new \RuntimeException('File already exists. DAM uid: ' . $damRecord['uid'] . ': "' . $relativeSourceFilePath . '"', 1441112138);
				}

				$fileIdentifier = substr($relativeTargetFolder, strlen($this->storageBasePath)) . $basename;
				$fileObject = $this->storageObject->getFile($fileIdentifier);
				$this->fileIndexRepository->add($fileObject);
				$this->updateDamFilePath($damRecord['uid'], $relativeTargetFolder);
				$this->amountOfMigratedRecords++;
			} catch (\Exception $e) {
				$this->setDamFileMissingByUid($damRecord['uid']);
				$this->controller->warningMessage($e->getMessage());
				$this->amountOfFilesNotFound++;
				continue;
			}
		}
		$this->database->sql_free_result($result);

		$this->controller->message(
			'Not migrated dam records at start of task: ' . $total . '. Migrated files after task: ' . $this->amountOfMigratedRecords . '. Files not found: ' . $this->amountOfFilesNotFound . '.'
		);

		return $this->getResultMessage();
	}

	/**
	 * @return \mysqli_result
	 */
	protected function execSelectNotMigratedDamRecordsQuery() {
		/** @var $storageRepository Resource\StorageRepository */
		$storageRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\StorageRepository');
		$storages = $storageRepository->findAll();
		$constraint = array();
		/** @var Resource\ResourceStorage $storage */
		foreach ($storages as $storage) {
			$configuration = $storage->getConfiguration();
			$constraint[] = 'tx_dam.file_path NOT LIKE "' . $this->database->escapeStrForLike($configuration['basePath'], 'sys_file_storage') . '%"';
		}
		unset($storage);

		return $this->database->exec_SELECTquery(
			'tx_dam.*',
			'tx_dam',
			'tx_dam.deleted=0 AND tx_dam._missingfile=0 AND ' . implode(' AND ', $constraint),
			'',
			'',
			$this->getRecordLimit()
		);
	}

	/**
	 * Mark a dam file as missing
	 *
	 * @param int $uid
	 */
	protected function setDamFileMissingByUid($uid) {
		$this->database->exec_UPDATEquery(
			'tx_dam',
			'uid = ' . (int)$uid,
			array(
				'_missingfile' => 1
			)
		);
	}

	/**
	 * @param int $uid
	 * @param string $filePath
	 */
	protected function updateDamFilePath($uid, $filePath) {
		$this->database->exec_UPDATEquery(
			'tx_dam',
			'uid=' . (int)$uid,
			array(
				'tstamp' => time(),
				'file_path' => $filePath,
			)
		);
	}

}
