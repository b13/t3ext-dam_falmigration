<?php
namespace B13\DamFalmigration\Controller;

/**
 *  Copyright notice
 *
 *  (c) 2013 Benjamin Mack <typo3@b13.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is free
 *  software; you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\DamFalmigration\Service;

// I can haz color / use unicode?
if (DIRECTORY_SEPARATOR !== '\\') {
	define('USE_COLOR', function_exists('posix_isatty') && posix_isatty(STDOUT));
	define('UNICODE', TRUE);
} else {
	define('USE_COLOR', getenv('ANSICON') !== FALSE);
	define('UNICODE', FALSE);
}

// Get terminal width
if (@exec('tput cols')) {
	define('TERMINAL_WIDTH', exec('tput cols'));
} else {
	define('TERMINAL_WIDTH', 79);
}

/**
 * Command Controller to execute DAM to FAL Migration scripts
 *
 * @package B13\DamFalmigration
 * @subpackage Controller
 */
class DamMigrationCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController {

	/**
	 * Migrates all DAM records to FAL. A DB field "_migrateddamuid" connects each FAL record to the original DAM record.
	 *
	 * @param int|string $storageUid the UID of the storage (usually 1, don't
	 *    modify if you are unsure)
	 *
	 * @return void
	 */
	public function migrateDamRecordsCommand($storageUid = 1) {
		$this->headerMessage(LocalizationUtility::translate('connectDamRecordsWithSysFileCommand', 'dam_falmigration'));
		/** @var Service\MigrateService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateService');
		$service->setStorageUid((int)$storageUid);
		$this->outputMessage($service->execute($this));
	}

	/**
	 * Migrates DAM metadata to FAL metadata. Searches for all migrated sys_file records that don't have any titles yet.
	 *
	 * @return void
	 */
	public function migrateDamMetadataCommand() {
		$this->headerMessage(LocalizationUtility::translate('migrateDamMetadataCommand', 'dam_falmigration'));
		/** @var Service\MigrateMetadataService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateMetadataService');
		$this->outputMessage($service->execute($this));
	}

	/**
	 * Migrates the <media DAM_UID target title>Linktext</media> to <link file:29643 - download>Linktext</link>
	 *
	 * @param \string $table the table to look for
	 * @param \string $field the DB field to look for
	 *
	 * @return void
	 */
	public function migrateMediaTagsInRteCommand($table = 'tt_content', $field = 'bodytext') {
		$this->headerMessage(LocalizationUtility::translate('migrateMediaTagsInRteCommand', 'dam_falmigration'));
		/** @var Service\MigrateRteMediaTagService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateRteMediaTagService');
		$this->outputMessage($service->execute($this, $table, $field));
	}

	/**
	 * Migrate DAM categories to FAL categories
	 *
	 * @param integer $initialParentUid Initial parent UID
	 * @param integer $storagePid Store on PID
	 *
	 * @return void
	 */
	public function migrateDamCategoriesCommand($initialParentUid = 0, $storagePid) {
		$this->headerMessage(LocalizationUtility::translate('migrateDamCategoriesCommand', 'dam_falmigration'));
		$databaseHelper = \B13\DamFalmigration\Helper\DatabaseHelper::getInstance();
		if ($databaseHelper->isTableAvailable('tx_dam_cat')) {
			// if a parent uid is given but not available, set initial uid to 0
			if ($initialParentUid > 0 && !$databaseHelper->checkInitialParentAvailable()) {
				$initialParentUid = 0;
			}

			// $parrentUidMap[oldUid] = 'newUid';
			$parentUidMap = array();
			$parentUidMap[0] = $initialParentUid;

			//******** STEP 1 - Get all categories *********//
			$damCategories = $databaseHelper->getAllNotYetMigratedDamCategoriesWithItemCount();

			//******** STEP 2 - re-sort category array *********//
			$damCategories = \TYPO3\CMS\DamFalmigration\Utility\GeneralUtility::sortCategories($damCategories, 0);

			//******** STEP 3 - Build category tree *********//
			$amountOfMigratedRecords = 0;
			foreach ($damCategories as $category) {

				$newParentUid = $parentUidMap[ $category['parent_id'] ];

				// create the new category in table sys_category
				$newUid = $databaseHelper->createNewCategory($category, $newParentUid, $storagePid);

				$this->message(LocalizationUtility::translate('creatingCategory', 'dam_falmigration', array($category['title'])));

				$parentUidMap[ $category['uid'] ] = $newUid;
				$amountOfMigratedRecords++;
			}

			//******** STEP 3 - Migrate DAM category mountpoints to sys_category permissions *********//
			$this->migrateDamCategoryMountsToSysCategoryPerms('be_users');
			$this->migrateDamCategoryMountsToSysCategoryPerms('be_groups');

			if ($amountOfMigratedRecords > 0) {
				$this->successMessage(LocalizationUtility::translate('migrationSuccessful', 'dam_falmigration'));
				$this->successMessage(LocalizationUtility::translate('migratedCategories', 'dam_falmigration', array(0 => $amountOfMigratedRecords)));
			} else {
				$this->infoMessage(LocalizationUtility::translate('migrationNotNecessary', 'dam_falmigration'));
				$this->infoMessage(LocalizationUtility::translate('allCategoriesMigrated', 'dam_falmigration'));
			}
		} else {
			$this->warningMessage('Table tx_dam_cat is not available. So there is nothing to migrate.');
		}
	}

	/**
	 * migrate all DAM categories to sys_file_collection records,
	 * while also migrating the references if they don't exist yet
	 * as a pre-requisite, there needs to be sys_file records that
	 * have been migrated from DAM
	 *
	 * @param integer $fileCollectionStoragePid The page id on which to store
	 *    the collections
	 * @param bool|string $migrateReferences whether just the categories should
	 *    be migrated or the references as well
	 *
	 * @return void
	 */
	public function migrateDamCategoriesToFalCollectionsCommand($fileCollectionStoragePid = 0, $migrateReferences = TRUE) {
		$this->headerMessage(LocalizationUtility::translate('migrateDamCategoriesToFalCollectionsCommand', 'dam_falmigration'));

		$databaseHelper = \B13\DamFalmigration\Helper\DatabaseHelper::getInstance();

		// get all categories that are in use
		$damCategories = $databaseHelper->getAllDamCategories();

		if (count($damCategories) > 0) {

			// fetch all FAL records that are there, that have been migrated already
			$falRecords = $databaseHelper->getAllMigratedFalRecords();

			// first, find all DAM records that are attached to the DAM categories
			$mmRelations = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'uid_local AS damuid, uid_foreign AS categoryuid',
				'tx_dam_mm_cat',
				'uid_foreign IN (' . implode(',', array_keys($damCategories)) . ')'
			);

			foreach ($mmRelations as $relation) {
				$damCategories[ $relation['categoryuid'] ]['files'][] = $falRecords[ $relation['damuid'] ]['uid'];
			}

			// create FAL collections out of the categories
			// get all DAM relations

			// add the categories as "sys_file_collection"
			foreach ($damCategories as $damCategoryUid => $categoryInfo) {

				if (!is_numeric($damCategoryUid) || empty($damCategoryUid)) {
					continue;
				}

				// don't migrate categories with no files
				if (count($categoryInfo['files']) == 0) {
					$this->warningMessage('Category ' . $categoryInfo['title'] . ' was not added since it has no valid FAL record attached to it');
					continue;
				}

				// check if there is a file collection with that category information
				$existingFileCollection = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
					'uid, _migrateddamcatuid',
					'sys_file_collection',
					'_migrateddamcatuid=' . intval($damCategoryUid)
				);

				if (is_array($existingFileCollection)) {
					$damCategories[ $damCategoryUid ]['falcollectionuid'] = $existingFileCollection['uid'];
					$this->infoMessage('DAM category ' . $damCategoryUid . ' has the existing FAL collection ' . $existingFileCollection['uid']);
				} else {

					$GLOBALS['TYPO3_DB']->exec_INSERTquery(
						'sys_file_collection',
						array(
							'pid' => (int)$fileCollectionStoragePid,
							'title' => $categoryInfo['title'],
							'_migrateddamcatuid' => $damCategoryUid
						)
					);
					$damCategories[ $damCategoryUid ]['falcollectionuid'] = $GLOBALS['TYPO3_DB']->sql_insert_id();
					$this->infoMessage('New FAL collection added (uid ' . $damCategories[ $damCategoryUid ]['falcollectionuid'] . ') from DAM category ' . $damCategoryUid);
				}
			}


			// add the FAL records as IRRE relations (sys_file_reference), if the reference does not exist yet
			if ($migrateReferences) {
				foreach ($damCategories as $damCategoryUid => $categoryInfo) {

					if (!is_numeric($damCategoryUid) || empty($damCategoryUid)) {
						continue;
					}

					$falCollectionUid = intval($categoryInfo['falcollectionuid']);

					if (count($categoryInfo['files']) > 0) {
						foreach ($categoryInfo['files'] as $falUid) {
							$falUid = intval($falUid);
							if ($falUid > 0) {
								$r = $databaseHelper->addToFileReferenceIfNotExists($falUid, $falCollectionUid, 'sys_file_collection', 'files');
								if ($r) {
									$this->message('Added FAL file ' . $falUid . ' to FAL collection ' . $falCollectionUid);
								} else {
									$this->message('FAL file relation of file ' . $falUid . ' to FAL collection ' . $falCollectionUid . ' already exists. Nothing modified.');
								}
							}
						}
					} else {
						$this->infoMessage('Notice: Collection / DAM Category "' . $categoryInfo['title'] . '" (DAM Category ID ' . $damCategoryUid . '/FAL Collection ID ' . $falCollectionUid . ') has no files attached to it');
					}
				}
			}

			$this->successMessage('Migration done.');
		} else {
			$this->successMessage('No categories found, nothing migrated.');
		}
	}

	/**
	 * Migrate DAM Category Relations
	 *
	 * it is highly recommended to update the ref index afterwards
	 *
	 * @return void
	 */
	public function migrateCategoryRelationsCommand() {
		$this->headerMessage(LocalizationUtility::translate('migrateCategoryRelationsCommand', 'dam_falmigration'));
		/** @var Service\MigrateCategoryRelationsService $migrateRelationsService */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateCategoryRelationsService');
		$this->outputMessage($service->execute($this));
	}

	/**
	 * migrate all damfrontend_pi1 plugins to tt_content.uploads with file_collection usually used in conjunction with / after migrateDamCategoriesToFalCollectionsCommand()
	 *
	 * @return void
	 */
	public function migrateDamFrontendPluginsCommand() {
		$this->headerMessage(LocalizationUtility::translate('migrateDamFrontendPluginsCommand', 'dam_falmigration'));

		// get all FAL collections that have been migrated so far
		$migratedFileCollections = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, _migrateddamcatuid AS damcatuid',
			'sys_file_collection',
			'_migrateddamcatuid > 0',
			'',
			'',
			'',
			'damcatuid'
		);

		// find all dam_frontend plugins
		$damFrontendPlugins = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'pi_flexform, list_type, CType, uid, pid, deleted',
			'tt_content',
			'list_type="dam_frontend_pi1" AND CType="list" AND deleted=0 AND pi_flexform!=""'
		//'((list_type="dam_frontend_pi1" AND CType="list") OR CType="uploads") AND deleted=0 AND pi_flexform!=""'
		);

		foreach ($damFrontendPlugins as &$plugin) {
			$plugin['pi_flexform'] = GeneralUtility::xml2array($plugin['pi_flexform']);
			$plugin['pi_flexform'] = $plugin['pi_flexform']['data'];
			$plugin['damfrontend_staticCatSelection'] = $plugin['pi_flexform']['sSelection']['lDEF']['useStaticCatSelection']['vDEF'];
			$plugin['damfrontend_usedCategories'] = $plugin['pi_flexform']['sSelection']['lDEF']['catMounts']['vDEF'];
		}

		$this->infoMessage('Found ' . count($damFrontendPlugins) . ' plugins of dam_frontend_pi1');


		// replace the plugins with the new ones
		foreach ($damFrontendPlugins as $plugin) {

			$usedDamCategories = GeneralUtility::trimExplode(',', $plugin['damfrontend_usedCategories'], TRUE);
			$fileCollections = array();

			foreach ($usedDamCategories as $damCategoryUid) {
				if (isset($migratedFileCollections[ $damCategoryUid ])) {
					$fileCollections[] = $migratedFileCollections[ $damCategoryUid ]['uid'];
				}
			}

			$this->message('Categories for plugin ' . $plugin['uid'] . ': ' . implode(',', $fileCollections) . ' (originally: ' . $plugin['damfrontend_usedCategories'] . ')');

			if (count($fileCollections) > 0) {
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
					'tt_content',
					'uid=' . intval($plugin['uid']),
					array(
						'CType' => 'uploads',
						'file_collections' => implode(',', $fileCollections),
					)
				);
			} else {
				$this->warningMessage('Plugin ' . $plugin['uid'] . ' not migrated because there are no file collections', TRUE);
			}
		}
	}


	/**
	 * Checks if there are multiple entries in sys_file_reference that contain the same uid_local and uid_foreign with sys_file_collection references and removes the duplicates
	 * NOTE: this command is usually *NOT* necessary, but only if something
	 * went wrong
	 *
	 * @return void
	 */
	public function cleanupDuplicateFalCollectionReferencesCommand() {
		$this->headerMessage(LocalizationUtility::translate('cleanupDuplicateFalCollectionReferencesCommand', 'dam_falmigration'));
		$references = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, uid_local, uid_foreign, COUNT(uid) AS amountOfRows',
			'sys_file_reference',
			'tablenames="sys_file_collection" AND fieldname="files" AND deleted=0',
			'uid_foreign, uid_local', // ROLLUP
			'uid_foreign, uid_local'
		);
		$this->infoMessage('Found ' . count($references) . ' references to sys_file_collection');
		$affectedRecords = 0;
		foreach ($references as $ref) {
			// this reference has duplicates
			if ($ref['amountOfRows'] > 1) {
				$GLOBALS['TYPO3_DB']->exec_DELETEquery(
					'sys_file_reference',
					'uid != ' . $ref['uid'] . ' AND tablenames="sys_file_collection" AND fieldname="files" AND deleted=0 AND uid_local=' . $ref['uid_local'] . ' AND uid_foreign=' . $ref['uid_foreign']
				);
				$affectedRecords++;
			}
		}
		$this->successMessage('Cleaned up ' . $affectedRecords . ' duplicates of references');
	}

	/**
	 * updates the reference index
	 *
	 * @return void
	 */
	public function updateReferenceIndexCommand() {
		$this->headerMessage(LocalizationUtility::translate('updateReferenceIndexCommand', 'dam_falmigration'));
		// update the reference index
		$refIndexObj = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\ReferenceIndex');
//			list($headerContent, $bodyContent, $errorCount) = $refIndexObj->updateIndex('check', FALSE);
		list($headerContent, $bodyContent, $errorCount) = $refIndexObj->updateIndex('update', FALSE);
	}

	/**
	 * migrate relations to dam records that dam_ttcontent and dam_uploads introduced
	 *
	 * it is highly recommended to update the ref index afterwards
	 *
	 * @return void
	 */
	public function migrateRelationsCommand() {
		$this->headerMessage(LocalizationUtility::translate('migrateRelationsCommand', 'dam_falmigration'));
		/** @var Service\MigrateRelationsService $migrateRelationsService */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateRelationsService');
		$this->outputMessage($service->execute($this));
	}

	/**
	 * Migrates all available DAM Selections in sys_file_collections (only folder based selections for now).
	 *
	 * it is highly recommended to update the ref index afterwards
	 *
	 * @return void
	 */
	public function migrateSelectionsCommand() {
		$this->headerMessage(LocalizationUtility::translate('migrateSelectionsCommand', 'dam_falmigration'));
		/** @var Service\MigrateSelectionsService $migrateSelectionsService */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateSelectionsService');
		$this->outputMessage($service->execute($this));
	}

	/**
	 * Migrates tt_news records enriched with DAM fields to FAL.
	 *
	 * It is highly recommended to update the ref index afterwards
	 *
	 * @return void
	 */
	public function migrateDamTtnewsCommand() {
		$this->headerMessage(LocalizationUtility::translate('migrateDamTtnewsCommand', 'dam_falmigration'));
		/** @var Service\MigrateDamTtnewsService $migrateSelectionsService */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateDamTtnewsService');
		$this->outputMessage($service->execute($this));
	}

	/**
	 * Migrate tt_news_categorymounts to category_pems in either be_groups or be_users
	 *
	 * @param string $table either be_groups or be_users
	 *
	 * @return void
	 */
	public function migrateDamCategoryMountsToSysCategoryPerms($table) {
		$this->headerMessage(LocalizationUtility::translate('migrateDamCategoryMountsToSysCategoryPerms', 'dam_falmigration', array($table)));
		/** @var \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler */
		$dataHandler = GeneralUtility::makeInstance('TYPO3\CMS\Core\DataHandling\DataHandler');

		/* assign imported categories to be_groups or be_users */
		$whereClause = 'tx_dam_mountpoints != \'\'' . BackendUtility::deleteClause($table);
		$beGroupsOrUsersWithTxDamMountpoints = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $table, $whereClause);
		$data = array();

		foreach ($beGroupsOrUsersWithTxDamMountpoints as $beGroupOrUser) {
			$txDamMountpoints = GeneralUtility::trimExplode(',', $beGroupOrUser['tx_dam_mountpoints']);
			$sysCategoryPermissions = array();
			foreach ($txDamMountpoints as $txDamMountpoint) {
				if (GeneralUtility::isFirstPartOfStr($txDamMountpoint, 'txdamCat:')) {
					// we only migrate DAM category mounts
					$damCategoryMountpoint = GeneralUtility::trimExplode(':', $txDamMountpoint);
					$whereClause = '_migrateddamcatuid = ' . $damCategoryMountpoint[1];
					$sysCategory = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('uid', 'sys_category', $whereClause);
					if (!empty($sysCategory)) {
						$sysCategoryPermissions[] = $sysCategory['uid'];
					}
				}

			}
			if (count($sysCategoryPermissions)) {
				$data[ $table ][ $beGroupOrUser['uid'] ] = array(
					'category_perms' => implode(',', $sysCategoryPermissions) . ',' . $beGroupOrUser['category_perms']
				);
			}
		}
		$dataHandler->start($data, array());
		$dataHandler->admin = TRUE;
		$dataHandler->process_datamap();
	}

	/**
	 * Output FlashMessage
	 *
	 * @param FlashMessage $message
	 *
	 * @return void
	 */
	public function outputMessage($message = NULL) {
		if ($message->getTitle()) {
			$this->outputLine($message->getTitle());
		}
		if ($message->getMessage()) {
			$this->outputLine($message->getMessage());
		}
		if ($message->getSeverity() !== FlashMessage::OK) {
			$this->sendAndExit(1);
		}
	}

	/**
	 * Normal message
	 *
	 * @param $message
	 * @param boolean $flushOutput
	 *
	 * @return void
	 */
	public function message($message = NULL, $flushOutput = TRUE) {
		$this->outputLine($message);
		if ($flushOutput) {
			$this->response->send();
		}
	}

	/**
	 * Informational message
	 *
	 * @param string $message
	 * @param boolean $showIcon
	 * @param boolean $flushOutput
	 *
	 * @return void
	 */
	public function infoMessage($message = NULL, $showIcon = FALSE, $flushOutput = TRUE) {
		$icon = '';
		if ($showIcon && UNICODE) {
			$icon = '★ ';
		}
		if (USE_COLOR) {
			$this->outputLine("\033[0;36m" . $icon . $message . "\033[0m");
		} else {
			$this->outputLine($icon . $message);
		}
		if ($flushOutput) {
			$this->response->send();
		}
	}

	/**
	 * Error message
	 *
	 * @param string $message
	 * @param boolean $showIcon
	 * @param boolean $flushOutput
	 *
	 * @return void
	 */
	public function errorMessage($message = NULL, $showIcon = FALSE, $flushOutput = TRUE) {
		$icon = '';
		if ($showIcon && UNICODE) {
			$icon = '✖ ';
		}
		if (USE_COLOR) {
			$this->outputLine("\033[31m" . $icon . $message . "\033[0m");
		} else {
			$this->outputLine($icon . $message);
		}
		if ($flushOutput) {
			$this->response->send();
		}
	}

	/**
	 * Warning message
	 *
	 * @param string $message
	 * @param boolean $showIcon
	 * @param boolean $flushOutput
	 *
	 * @return void
	 */
	public function warningMessage($message = NULL, $showIcon = FALSE, $flushOutput = TRUE) {
		$icon = '';
		if ($showIcon) {
			$icon = '! ';
		}
		if (USE_COLOR) {
			$this->outputLine("\033[0;33m" . $icon . $message . "\033[0m");
		} else {
			$this->outputLine($icon . $message);
		}
		if ($flushOutput) {
			$this->response->send();
		}
	}

	/**
	 * Success message
	 *
	 * @param string $message
	 * @param boolean $showIcon
	 * @param boolean $flushOutput
	 *
	 * @return void
	 */
	public function successMessage($message = NULL, $showIcon = FALSE, $flushOutput = TRUE) {
		$icon = '';
		if ($showIcon && UNICODE) {
			$icon = '✔ ';
		}
		if (USE_COLOR) {
			$this->outputLine("\033[0;32m" . $icon . $message . "\033[0m");
		} else {
			$this->outputLine($icon . $message);
		}
		if ($flushOutput) {
			$this->response->send();
		}
	}

	/**
	 * Show a header message
	 *
	 * @param $message
	 * @param string $style
	 *
	 * @return void
	 */
	public function headerMessage($message, $style = 'info') {
		// Crop the message
		$message = substr($message, 0, TERMINAL_WIDTH - 3);
		if (UNICODE) {
			$linePaddingLength = mb_strlen('─') * (TERMINAL_WIDTH - 2);
			$message =
				'┌' . str_pad('', $linePaddingLength, '─') . '┐' . LF .
				'│ ' . str_pad($message, TERMINAL_WIDTH - 3) . '│' . LF .
				'└' . str_pad('', $linePaddingLength, '─') . '┘';
		} else {
			$message =
				str_pad('', TERMINAL_WIDTH, '-') . LF .
				'+ ' . str_pad($message, TERMINAL_WIDTH - 3) . '+' . LF .
				str_pad('', TERMINAL_WIDTH, '-');
		}
		switch ($style) {
			case 'error':
				$this->errorMessage($message, FALSE);
				break;
			case 'info':
				$this->infoMessage($message, FALSE);
				break;
			case 'success':
				$this->successMessage($message, FALSE);
				break;
			case 'warning':
				$this->warningMessage($message, FALSE);
				break;
			default:
				$this->message($message);
		}
	}
}
