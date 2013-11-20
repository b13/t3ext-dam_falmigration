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
 * Scheduler Task to Migrate DAM frontend plugins
 * should be called *after* the DAM-FAL migration has been executed
 * because then every "sys_file" (= FAL) record has a relation to a DAM
 * record via "_migrateddamuid"
 * 
 * 1. find all DAM frontend plugins, and ask for their Plugin settings 
 *    to see what categories are needed
 * 2. add all DAM categories as FAL collections and add their FAL files 
 *    to the collections
 * 3. replace the DAM frontend plugins by their tt_content uploads selection
 *
 * @author      Benjamin Mack <benni@typo3.org>
 */
class MigrateDamFrontendTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask {

	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 * 
	 * @return boolean
	 */
	public function execute() {

			// check for all FAL records that are there, that have been migrated already
		$falRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, _migrateddamuid AS damuid',
			'sys_file',
			'_migrateddamuid>0 AND deleted=0',
			'',	// group by
			'', // order by
			'', // limit
			'damuid'
		);

			// STEP 1
			// get all categories that are in use
		$categories = $this->findAllCategoriesUsedInFrontend();

			// find all DAM records that are attached to them
		$mmRelations = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid_local AS damuid, uid_foreign AS categoryuid',
			'tx_dam_mm_cat',
			'uid_foreign IN (' . implode(',', array_keys($categories)) . ')'
		);
		
		foreach ($mmRelations as $relation) {
			$categories[$relation['categoryuid']]['files'][] = $falRecords[$relation['damuid']]['uid'];
		}

			// STEP 2
			// create FAL collections out of the categories, and attach the FAL records to them
			// get all DAM relations

			// add the categories as "sys_file_collection"
		foreach ($categories as $damCategoryUid => $categoryInfo) {
			$sysFileCollection = array(
				'pid' => 48,
				'title' => $categoryInfo['title'],
				'_migrateddamcatuid' => $damCategoryUid
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_file_collection', $sysFileCollection);
			$categories[$damCategoryUid]['falcollectionuid'] = $GLOBALS['TYPO3_DB']->sql_insert_id();
		}


			// add the FAL records as IRRE relations (sys_file_reference)
		foreach ($categories as $damCategoryUid => $categoryInfo) {
			$falCollectionUid = intval($categoryInfo['falcollectionuid']);
			$recordUid = intval($damRelationData['referenceuid']);

			foreach ($categoryInfo['files'] as $falUid) {
				$falUid = intval($falUid);
				if ($falUid > 0) {				
					$insertData = array(
						'uid_local'   => $falUid,
						'uid_foreign' => $falCollectionUid,
						'tablenames'  => 'sys_file_collection',
						'fieldname'   => 'files',
						'table_local' => 'sys_file',
					);
				
						// now put them into the sys_file_reference table
					/** @var $GLOBALS['TYPO3_DB'] t3lib_DB */
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_file_reference', $insertData);
					$migratedFiles++;
				}
				
			} 
		}
		
			// STEP 3
			// replace the dam_frontend plugins with the new ones
		$damFrontendPlugins = $this->findAllDamFrontendPlugins();
		foreach ($damFrontendPlugins as $plugin) {

			$usedDamCategories = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode($plugin['damfrontend_usedCategories']);
			$fileCollections = array();

			foreach ($usedDamCategories as $damCategoryUid) {
				$fileCollections[] = $categories[$damCategoryUid]['falcollectionuid'];
			}

			$updateData = array(
				'CType' => 'uploads',
				'file_collections' => implode(',', $fileCollections),
			);

			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'tt_content',
				'uid=' . intval($plugin['uid']),
				$updateData
			);
		}


			// STEP 4
			// update the refindex
		if ($migratedFiles > 0) {
				// update the reference index
			$refIndexObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\ReferenceIndex');
//			list($headerContent, $bodyContent, $errorCount) = $refIndexObj->updateIndex('check', FALSE);
			list($headerContent, $bodyContent, $errorCount) = $refIndexObj->updateIndex('update', FALSE);
		}


			// it was always a success
		return TRUE;
	}


	/**
	 * fetches a list of all categories that are used in DAM frontend
	 * 
	 */
	protected function findAllCategoriesUsedInFrontend() {
		$damFrontendPlugins = $this->findAllDamFrontendPlugins();

		$allCategories = '';
		foreach ($damFrontendPlugins as $plugin) {
			$allCategories .= ',' . $plugin['damfrontend_usedCategories'];
		}
		$allCategories = \TYPO3\CMS\Core\Utility\GeneralUtility::uniqueList($allCategories);
		$categoryUids = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $allCategories, TRUE);

			// check for all FAL records that are there, that have been migrated already
		$categoryRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, title, parent_id',
			'tx_dam_cat',
			'uid IN (' . implode(',', $categoryUids) . ')',
			'',
			'',
			'',
			'uid'
		);
		
		// create a non-hierarchical list of all categories, and find 
		$usedCategories = array();
		foreach ($categoryRecords as $rec) {
			$parentId = $rec['parent_id'];
			$title = $rec['title'];
			while ($parentId > 0) {
				$parentRecord = \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord('tx_dam_cat', $parentId);
				$parentId = $parentRecord['parent_id'];
				$title = $parentRecord['title'] . ' - ' . $title;
			}
			$usedCategories[$rec['uid']] = array(
				'damcategoryuid' => $rec['uid'],
				'title' => $title,
				'files' => array()
			);
		}


		return $usedCategories;


/*
		$categoryTitles = array();
		foreach ($damFrontendPlugins as $contentRecord) {
			$categoryTitles[] = \TYPO3\CMS\Backend\Utility\BackendUtility::getRecordPath($contentRecord['pid'], '', 40);
		}
		asort($categoryTitles);
		
*/

		$message = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', '<pre>' . implode(CRLF, $categoryTitles) . '</pre>', 'Used Categories (' . count($categoryTitles) . ')');
		\TYPO3\CMS\Core\Messaging\FlashMessageQueue::addMessage($message);
	}


	/** 
	 * find all DAM frontend plugins in use
	 */
	protected function findAllDamFrontendPlugins() {
			// get all DAM relations
		$damFrontendPlugins = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'pi_flexform, list_type, CType, uid, pid, deleted, hidden',
			'tt_content',
			'list_type="dam_frontend_pi1" AND CType="list" AND deleted=0 AND hidden=0 AND sys_language_uid != 1'
		);

		foreach ($damFrontendPlugins as &$plugin) {
		
/*
			$pageUid = $plugin['pid'];
			
				// get the rootline and sort out old pages
			$rootLine = \TYPO3\CMS\Backend\Utility\BackendUtility::BEgetRootLine($pageUid);
			$rootRecord = array_pop($rootLine);
			if ($rootRecord['uid'] == 12964) {
				continue;
			}
*/
			$plugin['pi_flexform'] = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($plugin['pi_flexform']);
			$plugin['pi_flexform'] = $plugin['pi_flexform']['data'];
			$plugin['damfrontend_staticCatSelection'] = $plugin['pi_flexform']['sSelection']['lDEF']['useStaticCatSelection']['vDEF'];
			$plugin['damfrontend_usedCategories'] = $plugin['pi_flexform']['sSelection']['lDEF']['catMounts']['vDEF'];
		}
		return $damFrontendPlugins;
	}
}