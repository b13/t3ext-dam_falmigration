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
 * @author Alexander Boehm <boehm@punkt.de>
 */
class MigrateDamCategoriesTask extends AbstractTask {

	/**
	 * @var integer Where to store new created sys_category records
	 */
	public $storeOnPid = 1;

	/**
	 * @var integer Defines a sys_category UID where to store the new category tree in.
	 */
	public $initialParentUid = 0;

	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 *
	 * @throws \Exception
	 * @return boolean
	 */
	public function execute() {
		$this->init();

		if ($this->isTableAvailable('tx_dam_cat')) {
			// if a parent uid is given but not available, set initial uid to 0
			if($this->initialParentUid > 0 && !$this->checkInitialParentAvailable()) {
				$this->initialParentUid = 0;
			}

			// $parrentUidMap[oldUid] = 'newUid';
			$parentUidMap = array();
			$parentUidMap[0] = $this->initialParentUid;

			//******** STEP 1 - Get all categories *********//
			$damCategories = $this->getAllDamCategories();

			//******** STEP 2 - resort categorie array *********//
			$damCategories = $this->sortingCategories($damCategories, 0);

			//******** STEP 3 - Build categorie tree *********//
			foreach($damCategories as $category) {

				$newParentUid = $parentUidMap[$category['parent_id']];

				//here the new category gets createt in table sys_category
				$newUid = $this->createNewCategory($category, $newParentUid);

				$parentUidMap[$category['uid']] = $newUid;
				$this->amountOfMigratedRecords++;
			}

			$this->addResultMessage();
			return TRUE;
		} else {
			throw new \Exception('Table tx_dam_cat is not avai´able. So there is nothing to migrate.');
		}
	}

	/**
	 * Gets all available (not deleted) DAM categories.
	 * Returns array with all categories.
	 *
	 * @return array
	 */
	protected function getAllDamCategories() {
		// this query can also count all related categories (sys_category.items)
		$damCategories = $this->database->exec_SELECTgetRows(
			'uid, parent_id, tstamp, sorting, crdate, cruser_id, hidden, title, description, (SELECT COUNT(*) FROM tx_dam_mm_cat WHERE tx_dam_mm_cat.uid_foreign = tx_dam_cat.uid) as items',
			'tx_dam_cat',
			'deleted = 0',
			'', 'parent_id', ''
		);
		return $damCategories;
	}

	/**
	 * Adds new categorie in table sys_category. Requires the record array and the
	 * new parent_id for it has changed from DAM to FAL migration.
	 *
	 * @param $record
	 * @param $newParentUid
	 * @return integer
	 */
	protected function createNewCategory($record, $newParentUid) {
		$sysCategory = array(
			'pid' => $this->storeOnPid,
			'parent' => $newParentUid,
			'tstamp' => $record['tstamp'],
			'sorting' => $record['sorting'],
			'crdate' => $record['crdate'],
			'cruser_id' => $record['cruser_id'],
			'hidden' => $record['hidden'],
			'title' => $record['title'],
			'description' => $record['description'],
			'items' => $record['items'],
			'_migrateddamcatuid' => $record['uid']
		);

		$this->database->exec_INSERTquery('sys_category', $sysCategory);

		$newUid = $this->database->sql_insert_id();

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


	/**
	 * Check if the wanted parent uid is available.
	 *
	 * @return boolean
	 */
	protected function checkInitialParentAvailable() {
		$amountOfResults = $this->database->exec_SELECTcountRows(
			'*',
			'sys_category',
			'deleted = 0'
		);

		if ($amountOfResults) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

}