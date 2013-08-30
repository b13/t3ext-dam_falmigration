<?php
namespace TYPO3\CMS\DamFalmigration\Task;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Alexander Boehm <boehm@punkt.de>
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
 * Scheduler Task to Migrate Categories
 * Finds all DAM categories and adds a DB field "_migrateddamcatuid"
 * to each category record
 *
 * currently it does not take care of the sys_language_uid, so all categories
 * get default language uid.
 *
 * @author      Alexander Boehm <boehm@punkt.de>
 *
 */
class MigrateDamCategoryRelationsTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask {

	// the storage pid for the categories
	protected $storageUid = 1;


	/**
	 * Defined parent uid if the migrated categories should not be under
	 * category root
	 *
	 * @var int parent uid
	 */
	public $initialParentUid = 0;


	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 *
	 * @return boolean
	 */
	public function execute() {

		/*
		 * Vorgehen:
		 *
		 * ------------------ Hole alle FAL mit _migrateddam attribut
		 * ------------------ Hole alle Einträge aus dam_mm der migrierten Daten (sortiert nach local_uid)
		 * ------------------ Hole Kategorien (uid, dam_uid)
		 * - Schreibe in sys_category_record_mm neue Einträge (+Mitzählen)
		 * - Update FAL-Eintrag Spalte categories < = Count
		 *
		 */


		//******** STEP 1 *********//
		// get all FAL records that are there, that have been migrated already
		// seen by the "_migrateddamuid" flag
		$migratedRecords = $this->getAllMigratedDamRecords();


		//******** STEP 2 *********//
		// get all entries from dam_cat_mm with given local_uid
		$damUids = array_keys($migratedRecords);
		$additionalWhere = implode(',',$damUids);
		$damMMEntries = $this->getAllDamMMCatEntries($additionalWhere);


		//******** STEP 3 *********//
		// get all FAL records that are there, that have been migrated already
		// seen by the "_migrateddamuid" flag
		$migratedCategories = $this->getAllMigratedDamCategories();


		//******** STEP 4 *********//
		// add new file to cat relations
		foreach($damMMEntries as $mmEntry) {
			$newFileUid = $migratedRecords[$mmEntry['uid_local']]['uid'];
			$newCatUid = $migratedCategories[$mmEntry['uid_foreign']]['uid'];

			//Insert new Relation
			$insertResult = $this->createNewCategoryToFileRelation($newFileUid, $newCatUid);

			if($insertResult) {
				// increase items in cat
				$this->increaseItemsFieldOfCat($newCatUid);
				// increase categories in file
				$this->increaseCategoriesFieldOfFile($newFileUid);
			} else {
				// do nothing at the moment
			}
		}


		//******** STEP 4 - Finished, do output *********//
		// print a message
		/*if ($migratedCategories > 0) {
			$headline = 'Migration successful';
			$message = 'Migrated ' . $migratedCategories . ' categories.';
		} else {
			$headline = 'Migration not necessary';
			$message = 'All categories have been migrated.';
		}
*/
//		$messageObject = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $message, $headline);
//		\TYPO3\CMS\Core\Messaging\FlashMessageQueue::addMessage($messageObject);


			// it was always a success
		return TRUE;
	}


	/**
	 * Gets all available (not deleted) migrated DAM categories.
	 * Returns array with all categories.
	 *
	 *
	 * @return mixed
	 */
	protected function getAllMigratedDamCategories() {

		$select = 'uid, _migrateddamcatuid AS damcatuid';
		$from = 'sys_category';
		$where = '_migrateddamcatuid>0 AND deleted=0';
		$index = 'damcatuid';

		$migratedCategories = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($select,$from,$where,'','','',$index);

		return $migratedCategories;
	}


	/**
	 * Gets all available (not deleted) migrated DAM records.
	 * Returns array with all records.
	 *
	 *
	 * @return mixed
	 */
	protected function getAllMigratedDamRecords() {

		$select = 'uid, _migrateddamuid AS damuid';
		$from = 'sys_file';
		$where = '_migrateddamuid>0 AND deleted=0';
		$index = 'damuid';

		$migratedRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($select,$from,$where,'','','',$index);

		return $migratedRecords;
	}


	/**
	 * Gets all dam_mm_cat entries.
	 * Returns array with all records.
	 *
	 *
	 * @param $damUids  A list of dam uids, seperated with ','
	 * @return mixed
	 */
	protected function getAllDamMMCatEntries($damUids) {

		$select = 'uid_local, uid_foreign';
		$from = 'tx_dam_mm_cat';
		$where = 'uid_local IN(' . $damUids . ')';
		$orderBy = 'uid_local';

		$damMMEntries = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($select,$from,$where,'',$orderBy);

		return $damMMEntries;
	}


	/**
	 * Adds new relation between category and file in table sys_category_record_mm.
	 * Requires the uid of the FAL file and the uid of the Category
	 *
	 * @param $record
	 * @param $newParentUid
	 * @return mixed
	 */
	protected function createNewCategoryToFileRelation($newFileUid, $newCatUid) {

		if($newCatUid <= 0 || $newFileUid <= 0){
			return FALSE;
		}

		$mmRelation = array(
			'uid_local' => $newCatUid,
			'uid_foreign' => $newFileUid,
			'tablenames' => 'sys_file'
		);

		$result = $GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_category_record_mm', $mmRelation);

		if($result) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	protected function increaseItemsFieldOfCat($catUid) {

		$select = 'items';
		$from = 'sys_category';
		$where = 'deleted=0 AND uid=' . $catUid;

		$currentItemsValue = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($select,$from,$where);

		$updateValues = array(
			'items' => $currentItemsValue[0]['items'] +1
		);
		$where = 'uid = ' . $catUid;

		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_category', $where, $updateValues);
	}

	protected function increaseCategoriesFieldOfFile($fileUid) {

		$select = 'categories';
		$from = 'sys_file';
		$where = 'deleted=0 AND uid=' . $fileUid;

		$currentCategoriesValue = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($select,$from,$where);


		$updateValues = array(
			'categories' => $currentCategoriesValue[0]['categories'] +1
		);
		$where = 'uid = ' . $fileUid;

		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_file', $where, $updateValues);
	}
}