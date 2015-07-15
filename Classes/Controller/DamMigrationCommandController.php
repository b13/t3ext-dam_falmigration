<?php
namespace B13\DamFalmigration\Controller;

/**
 * Copyright notice
 *
 * (c) 2013 Benjamin Mack <typo3@b13.de>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is free
 * software; you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 */
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\DamFalmigration\Service;

/**
 * Command Controller to execute DAM to FAL Migration scripts
 *
 * @package B13\DamFalmigration
 * @subpackage Controller
 */
class DamMigrationCommandController extends AbstractCommandController {

	/**
	 * Migrates all DAM records to FAL.
	 * A database field "_migrateddamuid" connects each FAL record to the original DAM record.
	 *
	 * @param int $storageUid The UID of the storage (default: 1 Do not modify if you are unsure.)
	 * @param int $recordLimit The amount of records to process in a single run. You can set this value if you have memory constraints.
	 *
	 * @return void
	 */
	public function migrateDamRecordsCommand($storageUid = 1, $recordLimit = 999999) {
		/** @var Service\MigrateService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateService', $this);
		$service->setStorageUid((int)$storageUid);
		$service->setRecordLimit((int)$recordLimit);
		// Service needs re-initialization after setting properties
		$service->initializeObject();
		$this->outputMessage($service->execute());
	}

	/**
	 * Migrates DAM metadata to FAL metadata.
	 * Searches for all migrated sys_file records that do not have any titles yet.
	 *
	 * @return void
	 */
	public function migrateDamMetadataCommand() {
		/** @var Service\MigrateMetadataService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateMetadataService', $this);
		$this->outputMessage($service->execute());
	}

	/**
	 * Migrate RTE media tags
	 * Migrates the ``<media DAM_UID target title>Linktext</media>`` to ``<link file:29643 - download>Linktext</link>``
	 *
	 * @param string $table The table to work on. Default: `tt_content`.
	 * @param string $field The field to work on. Default: `bodytext`.
	 *
	 * @return void
	 */
	public function migrateMediaTagsInRteCommand($table = 'tt_content', $field = 'bodytext') {
		/** @var Service\MigrateRteMediaTagService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateRteMediaTagService', $this);
		$this->outputMessage($service->execute($table, $field));
	}

	/**
	 * Migrate DAM categories to FAL categories
	 *
	 * @param integer $initialParentUid The id of a sys_category record to use as the root category.
	 * @param integer $storagePid Page id to store created categories on.
	 *
	 * @return void
	 */
	public function migrateDamCategoriesCommand($initialParentUid = 0, $storagePid = 1) {
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
	 * while also migrating the references if they do not exist yet
	 * as a pre-requisite, there needs to be sys_file records that
	 * have been migrated from DAM
	 *
	 * @param integer $fileCollectionStoragePid The page id on which to store the collections
	 * @param bool $migrateReferences Besides migrating the collections, the references are migrated as well. Default: TRUE
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
	 * Migrate Relations to DAM Categories
	 *
	 * It is highly recommended to update the reference index afterwards.
	 *
	 * @param int $recordLimit The amount of records to process in a single run. You can set this value if you have memory constraints.
	 *
	 * @return void
	 */
	public function migrateCategoryRelationsCommand($recordLimit = 999999) {
		/** @var Service\MigrateCategoryRelationsService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateCategoryRelationsService', $this);
		$service->setRecordLimit((int)$recordLimit);
		// Service needs re-initialization after setting properties
		$service->initializeObject();
		$this->outputMessage($service->execute());
	}

	/**
	 * Migrate dam frontend plugins
	 *
	 * Migrate all damfrontend_pi1 plugins to tt_content.uploads with file_collection. Usually used in conjunction with or after migrateDamCategoriesToFalCollectionsCommand().
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
		
		// find all dam_frontend_pi2 plugins
		$damFrontendPlugins = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'tx_damdownloadlist_records, list_type, CType, uid, pid, deleted',
			'tt_content',
			'list_type="dam_frontend_pi2" AND CType="list" AND deleted=0 AND tx_damdownloadlist_records!=""'
		);

		$this->infoMessage('Found ' . count($damFrontendPlugins) . ' plugins of dam_frontend_pi2');

		foreach ($damFrontendPlugins as $plugin) {

			if (!empty($plugin['tx_damdownloadlist_records'])) {

				$hasReferencesAlready = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
					'COUNT(uid) AS count',
					'sys_file_reference',
					'uid_foreign = ' . $plugin['uid']
				);
				if ($hasReferencesAlready['count'] > 0) {
					$this->warningMessage('Plugin ' . $plugin['uid'] . ' has already been migrated because', TRUE);
				}
				else {
					$newFalRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
						'uid, _migrateddamuid',
						'sys_file',
						'_migrateddamuid IN (' . $plugin['tx_damdownloadlist_records'] . ')'
					);

					// update ctype for dam_frontend_pi2 plugin
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
						'tt_content',
						'uid=' . intval($plugin['uid']),
						array(
							'CType' => 'uploads',
						)
					);

					// add the file references for the uploads plugin
					$sort = 0;
					foreach ($newFalRecords as $falRecord) {
						$this->message('Adding file reference for plugin:uid ' . $plugin['uid'] . ' <-> sys_file:uid ' . $falRecord['uid']);
						$GLOBALS['TYPO3_DB']->exec_INSERTquery(
							'sys_file_reference',
							array(
								'pid' => $plugin['pid'],
								'tstamp' => time(),
								'crdate' => time(),
								'cruser_id' => 0,
								'sorting' => $sort,
								'l10n_diffsource' => 'a:1:{s:6:"hidden";N;}',
								'uid_local' => $falRecord['uid'],
								'uid_foreign' => $plugin['uid'],
								'tablenames' => 'tt_content',
								'fieldname' => 'media',
								'sorting_foreign' => $sort++,
								'table_local' => 'sys_file'
							)
						);
					}
				}
			}
		}
	}


	/**
	 * Cleanup duplicate FAL collection references
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
	 * Updates the reference index
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
	 * Migrate relations to DAM records
	 * Migrate relations to dam records that dam_ttcontent and dam_uploads introduced.
	 *
	 * The way image captions, title and alt attributes apply varies wildly across
	 * TYPO3 installations, mainly depending on whether you used the static
	 * include that came with dam_ttcontent and how you configured it. Please
	 * read the documentation for more information. To support all
	 * configurations, you can specify a chain for each image caption, title and
	 * alt text which defines the priority of each field. Each chain consists
	 * of one or more of the following options, separated by commas, earliest
	 * non-empty field takes precedence over later ones:
	 *
	 * contentTitle     title line from content element
	 * contentAlt       alt text line from content element
	 * contentCaption   caption text line from content element
	 * metaTitle        title from DAM meta data
	 * metaAlt          alt text from DAM meta data
	 * metaCaption      caption text from DAM meta data
	 * metaDescription  description from DAM meta data
	 * empty            ends chain with an empty string if nothing applied
	 *                  (overriding FAL metadata with no output)
	 * default          ends chain without overriding FAL metadata if nothing
	 *                  applied (using central FAL metadata without copy)
	 *
	 * meta options cause DAM/FAL meta data to be copied to the content element,
	 * so they override values entered later on central FAL record. Thus they
	 * freeze input to the state at the time of migration, so later edits of
	 * central metadata won't have any effect on migrated content element
	 * references. Omit meta options and instead add default to the end of your
	 * chain if you want FAL edits to have an effect on migrated content.
	 *
	 * It is highly recommended to update the ref index afterwards.
	 *
	 * @param string $tablename The tablename to migrate relations for
	 * @param string $imageCaption Chain of fields to determine image captions. (Default: metaDescription,default)
	 * @param string $imageTitle Chain of fields to determine image title. (Default: contentCaption,metaTitle,empty)
	 * @param string $imageAlt Chain of fields to determine image alt texts. (Default: metaAlt,empty)
	 * @param string $uploadsLayout The layout ID to set on migrated CType uploads ("file links") content elements. 1 shows file type icons (like dam_filelinks did), 2 shows a thumbnail preview instead, 0 shows nothing but link & caption. Set to 'null' if no action should be taken. Default: 1
	 *
	 * @return void
	 */
	public function migrateRelationsCommand($tablename = '', $imageCaption = 'metaDescription,default', $imageTitle = 'contentCaption,metaTitle,empty', $imageAlt = 'metaAlt,empty', $uploadsLayout = '1') {
		$tablename = preg_replace('/[^a-zA-Z0-9_-]/', '', $tablename);

		/** @var Service\MigrateRelationsService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateRelationsService', $this);
		$service->setTablename($tablename);
		$service->setChainImageCaption($imageCaption);
		$service->setChainImageTitle($imageTitle);
		$service->setChainImageAlt($imageAlt);
		$service->setUploadsLayout($uploadsLayout);
		$this->outputMessage($service->execute());
	}

	/**
	 * Migrate DAM selections
	 * Migrates all available DAM Selections in sys_file_collections (only folder based selections for now).
	 *
	 * It is highly recommended to update the ref index afterwards.
	 *
	 * @return void
	 */
	public function migrateSelectionsCommand() {
		/** @var Service\MigrateSelectionsService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateSelectionsService', $this);
		$this->outputMessage($service->execute());
	}

	/**
	 * Migrates tt_news records enriched with DAM fields to FAL.
	 *
	 * It is highly recommended to update the ref index afterwards.
	 *
	 * @param int $storageUid The UID of the storage (default: 1 Do not modify if you are unsure.)
	 *
	 * @return void
	 */
	public function migrateDamTtnewsCommand($storageUid = 1) {
		/** @var Service\MigrateDamTtnewsService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateDamTtnewsService', $this);
		$service->setStorageUid((int)$storageUid);
		// Service needs re-initialization after setting properties
		$service->initializeObject();
		$this->outputMessage($service->execute());
	}

	/**
	 * Upgrade the storage index.
	 *
	 * @param int $storageUid The UID of the storage (default: 1 Do not modify if you are unsure.)
	 * @param int $recordLimit The amount of records to process in a single run. You can set this value if you have memory constraints.
	 *
	 * @return void
	 */
	public function upgradeStorageIndexCommand($storageUid = 1, $recordLimit = 999999) {
		/** @var Service\MigrateService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\UpgradeStorageIndexService', $this);
		$service->setStorageUid((int)$storageUid);
		$service->setRecordLimit((int)$recordLimit);
		// Service needs re-initialization after setting properties
		$service->initializeObject();
		$this->outputMessage($service->execute());
	}

	/**
	 * Migrate category mounts
	 *
	 * Migrate category mounts to category_pems in either be_groups or be_users.
	 *
	 * @param string $table The table to work on. Either be_groups or be_users.
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
	 * Migrate media:xxx style file references in link fields to file:xxx.
	 * If optional table & field name is omitted, migration will be performed on
	 * tt_content.header_link and tt_content.image_link. Should be run before
	 * migrateRelations as it transfers image_link contents to FAL as-is.
	 * 
	 * @param string $table The table to work on. Default: `tt_content`.
	 * @param string $field The field to work on. Default if table name is omitted: `header_link` and `image_link`.
	 */
	public function migrateLinksCommand($table = '', $field = '') {
		/** @var Service\MigrateLinksService $service */
		$service = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateLinksService', $this);
		$service->setTablename($table);
		$service->setFieldname($field);
		$this->outputMessage($service->execute());
	}
}
