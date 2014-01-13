<?php

namespace B13\DamFalmigration\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Benjamin Mack <typo3@b13.de>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Command Controller to execute DAM to FAL Migration scripts
 *
 * @package B13\DamFalmigration
 * @subpackage Controller
 */
class DamMigrationCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController {

	/**
	 * migrates DAM metadata to FAL metadata
	 * searches for all sys_file records that don't have any titles yet
	 * with a connection to a _dammigration record
	 */
	public function migrateDamMetadataCommand() {
		$recordsToMigrate = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'DISTINCT m.uid AS metadata_uid, f._migrateddamuid AS damuid, d.title, d.description, d.alt_text',
			'sys_file f, sys_file_metadata m, tx_dam d',
			'm.file=f.uid AND f._migrateddamuid=d.uid AND f._migrateddamuid > 0 AND m.title IS NULL'
		);

		$this->outputLine('Found ' . count($recordsToMigrate) . ' sys_file_metadata records that have no title but associated with a DAM record that has a title');
		
		$migratedRecords = 0;
		foreach ($recordsToMigrate as $rec) {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'sys_file_metadata',
				'uid=' . intval($rec['metadata_uid']),
				array(
					'title' => $rec['title'],
					'description' => $rec['description'],
					'alternative' => $rec['alt_text']
				)
			);
			$migratedRecords++;
		}

		$this->outputLine('Migrated title, description and alt_text for ' . $migratedRecords . ' records');
	}


	/**
	 * migrates the <media DAM_UID target title>Linktext</media>
	 * to <link file:29643 - download>My link to a file</link>
	 *
	 * @param \string $table the table to look for
	 * @param \string $field the DB field to look for
	 */
	public function migrateMediaTagsInRteCommand($table = 'tt_content', $field = 'bodytext') {
		$recordsToMigrate = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, ' . $field,
			$table,
			'deleted=0 AND ' . $field . ' LIKE "%<media%"'
		);

		$this->outputLine('Found ' . count($recordsToMigrate) . ' ' . $table . ' records that have a "<media>" tag in the field ' . $field);
		foreach ($recordsToMigrate as $rec) {
			$originalContent = $rec[$field];
			$finalContent = $originalContent;
			$results = array();
			preg_match_all('/<media ([0-9]+)([^>]*)>(.*?)<\/media>/', $originalContent, $results, PREG_SET_ORDER);
			if (count($results)) {
				foreach ($results as $result) {
					$searchString = $result[0];
					$damUid = $result[1];
					// see EXT:dam/mediatag/class.tx_dam_rtetransform_mediatag.php
					list($linkTarget, $linkClass, $linkTitle) = explode(' ', trim($result[2]), 3);
					$linkText = $result[3];
					$this->outputLine('Replacing "' . $result[0] . '" with DAM UID ' . $damUid . ' (target ' . $linkTarget . '; class ' . $linkClass . '; title "' . $linkTitle . '") and linktext "' . $linkText . '"');
					// fetch the DAM uid from sys_file
					// and replace the full tag with a valid href="file:FALUID"
					// <link file:29643 - download>My link to a file</link>
					$falRecord = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('uid', 'sys_file', '_migrateddamuid=' . intval($damUid));
					if (is_array($falRecord)) {
						$replaceString = '<link file:' . $falRecord['uid'] . ' ' . $result[2] . '>' . $linkText . '</link>';
						$finalContent = str_replace($searchString, $replaceString, $finalContent);
					}
				}
				// update the record
				if ($finalContent !== $originalContent) {
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
						$table,
						'uid=' . $rec['uid'],
						array($field => $finalContent)
					);
					$this->outputLine('Updated ' . $table . ':' . $rec['uid'] . ' with: ' . $finalContent);
				}
			} else {
				$this->outputLine('Nothing found: ' . $originalContent);
			}
		}
		$this->outputLine('DONE');
	}


	/**
	 * migrate all DAM categories to sys_file_collection records,
	 * while also migrating the references if they don't exist yet
	 * as a pre-requisite, there needs to be sys_file records that 
	 * have been migrated from DAM
	 *
	 * @param \string $migrateReferences whether just the categories should be migrated or the references as well
	 */
	public function migrateDamCategoriesToFalCollectionsCommand($migrateReferences = TRUE) {

		$fileCollectionStoragePid = 44;

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
				$damCategories[$relation['categoryuid']]['files'][] = $falRecords[$relation['damuid']]['uid'];
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
					$this->outputLine('Category ' . $categoryInfo['title'] . ' was not added since it has no valid FAL record attached to it');
					continue;
				}

				// check if there is a file collection with that category information
				$existingFileCollection = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
					'uid, _migrateddamcatuid',
					'sys_file_collection',
					'_migrateddamcatuid=' . intval($damCategoryUid)
				);

				if (is_array($existingFileCollection)) {
					$damCategories[$damCategoryUid]['falcollectionuid'] = $existingFileCollection['uid'];
					$this->outputLine('DAM category ' . $damCategoryUid . ' has the existing FAL collection ' . $existingFileCollection['uid']);

				} else {

					$GLOBALS['TYPO3_DB']->exec_INSERTquery(
						'sys_file_collection',
						array(
							'pid'   => $fileCollectionStoragePid,
							'title' => $categoryInfo['title'],
							'_migrateddamcatuid' => $damCategoryUid
						)
					);
					$damCategories[$damCategoryUid]['falcollectionuid'] = $GLOBALS['TYPO3_DB']->sql_insert_id();
					$this->outputLine('New FAL collection added (uid ' . $damCategories[$damCategoryUid]['falcollectionuid'] . ') from DAM category ' . $damCategoryUid);
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
									$this->outputLine('Added FAL file ' . $falUid . ' to FAL collection ' . $falCollectionUid);
								} else {
									$this->outputLine('FAL file relation of file ' . $falUid . ' to FAL collection ' . $falCollectionUid . ' already exists. Nothing modified.');
								}
							}
						}
					} else {
						$this->outputLine('Notice: Collection / DAM Category "' . $categoryInfo['title'] . '" (DAM Category ID ' . $damCategoryUid . '/FAL Collection ID ' . $falCollectionUid . ') has no files attached to it');
					}
				}
			}

			$this->outputLine('Migration done.');
		} else {
			$this->outputLine('No categories found, nothing migrated.');
		}
	}


	/**
	 * migrate all damfrontend_pi1 plugins to tt_content.uploads with file_collection
	 * usually used in conjunction with / after migrateDamCategoriesToFalCollectionsCommand()
	 */
	public function migrateDamFrontendPluginsCommand() {

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
			$plugin['pi_flexform'] = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($plugin['pi_flexform']);
			$plugin['pi_flexform'] = $plugin['pi_flexform']['data'];
			$plugin['damfrontend_staticCatSelection'] = $plugin['pi_flexform']['sSelection']['lDEF']['useStaticCatSelection']['vDEF'];
			$plugin['damfrontend_usedCategories'] = $plugin['pi_flexform']['sSelection']['lDEF']['catMounts']['vDEF'];
		}

		$this->outputLine('Found ' . count($damFrontendPlugins) . ' plugins of dam_frontend_pi1');
		

		// replace the plugins with the new ones
		foreach ($damFrontendPlugins as $plugin) {

			$usedDamCategories = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $plugin['damfrontend_usedCategories'], TRUE);
			$fileCollections = array();

			foreach ($usedDamCategories as $damCategoryUid) {
				if (isset($migratedFileCollections[$damCategoryUid])) {
					$fileCollections[] = $migratedFileCollections[$damCategoryUid]['uid'];
				}
			}

			$this->outputLine('Categories for plugin ' . $plugin['uid'] . ': ' . implode(',', $fileCollections) . ' (originally: ' . $plugin['damfrontend_usedCategories'] . ')');

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
				$this->outputLine('Plugin ' . $plugin['uid'] . ' not migrated because there are no file collections');
			}
		}
	}


	/**
	 * checks if there are multiple entries in sys_file_reference that contain
	 * the same uid_local and uid_foreign with sys_file_collection references
	 * and removes the duplicates
	 * NOTE: this command is usually *NOT* necessary, but only if something went wrong
	 */
	public function cleanupDuplicateFalCollectionReferencesCommand() {
		$references = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, uid_local, uid_foreign, COUNT(uid) AS amountOfRows',
			'sys_file_reference',
			'tablenames="sys_file_collection" AND fieldname="files" AND deleted=0',
			'uid_foreign, uid_local',	// ROLLUP
			'uid_foreign, uid_local'
		);
		$this->outputLine('Found ' . count($references) . ' references to sys_file_collection');
		$affectedRecords=0;
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
		$this->outputLine('Cleaned up ' . $affectedRows . ' duplicates of references');
	}

	/**
	 * updates the reference index
	 */
	public function updateReferenceIndexCommand() {
				// update the reference index
			$refIndexObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\ReferenceIndex');
//			list($headerContent, $bodyContent, $errorCount) = $refIndexObj->updateIndex('check', FALSE);
			list($headerContent, $bodyContent, $errorCount) = $refIndexObj->updateIndex('update', FALSE);
	}
}
