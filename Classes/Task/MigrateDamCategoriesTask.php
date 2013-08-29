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
 * Scheduler Task to Migrate Records
 * Finds all DAM records that have not been migrated yet
 * and adds a DB field "_migrateddamuid" to each FAL record
 * to connect the DAM and FAL DB records
 *
 * currently it only works for files within the fileadmin
 * FILES DON'T GET MOVED somewhere else
 *
 * @author      Benjamin Mack <benni@typo3.org>
 *
 */
class MigrateDamCategoriesTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask {

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

		// STEP 1 - Get all categories
		$damCategories = $this->getAllDamCategories();

		// $parrentUidMap[oldUid] = 'newUid';
		$parentUidMap = array();
		$parentUidMap[0] = $this->initialParentUid;

		// STEP 2 - resort categorie array!!!
		$damCategories = $this->sortingCategories($damCategories, 0);
		//var_dump($damCategories);die();


		// STEP 3 - Build categorie tree
		foreach($damCategories as $category) {

			$newParentUid = $parentUidMap[$category['parent_id']];

			$newUid = $this->createNewCategory($category, $newParentUid);

			$parentUidMap[$category['uid']] = $newUid;
		}


			// it was always a success
		return TRUE;
	}


	/**
	 * Gets all available (not deleted) DAM categories.
	 * Returns array with all categories.
	 *
	 *
	 * @return mixed
	 */
	protected function getAllDamCategories() {

		$select = 'uid, parent_id, title, description';
		$from = 'tx_dam_cat';
		$where = 'deleted = 0';
		$orderBy = 'parent_id';

		$damCategories = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($select,$from,$where,'',$orderBy);

		return $damCategories;
	}


	/**
	 * Adds new categorie in table sys_category. Requires the record array and the
	 * new parent_id for it has changed from DAM to FAL migration.
	 *
	 * @param $record
	 * @param $newParentUid
	 * @return mixed
	 */
	protected function createNewCategory($record, $newParentUid) {
		$sysCategory = array(
			'pid' => $this->storageUid,
			'title' => $record['title'],
			'description' => $record['description'],
			'parent' => $newParentUid
		);

		$GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_category', $sysCategory);

		$newUid = $GLOBALS['TYPO3_DB']->sql_insert_id();

		return $newUid;
	}


	/**
	 * Resorts the categorie array for we have the parent categories BEFORE the subcategories!
	 * Runs recursively down the cat-tree.
	 *
	 * @param $damCategories
	 * @param $parentUid
	 * @return array
	 */
	protected function sortingCategories($damCategories, $parentUid) {

		// New array for sorting dam records
		$sortedDamCategories = array();
		// Remember the uids for finding sub-categories
		$rememberUids = array();

		// Find all categories for the given parent_uid
		foreach($damCategories as $key =>$category) {
			if($category['parent_id'] == $parentUid) {
				$sortedDamCategories[] = $category;
				$rememberUids[] = $category['uid'];

				// The current entry isn't needed anymore, so remove it from the array.
				unset($damCategories[$key]);
			}
		}

		// Search for sub-categories recursivliy
		foreach($rememberUids as $nextLevelUid) {
			$subCategories = $this->sortingCategories($damCategories,$nextLevelUid);
			if(count($subCategories) > 0) {
				foreach($subCategories as $newCategory) {
					$sortedDamCategories[] = $newCategory;
				}
			}
		}

		return $sortedDamCategories;
	}

}