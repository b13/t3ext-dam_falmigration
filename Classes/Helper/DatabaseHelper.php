<?php

namespace B13\DamFalmigration\Helper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Benjamin Mack <benni@typo3.org>
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
 * this is a helper class to call certain recurring DB 
 *
 * @author      Benjamin Mack <benni@typo3.org>
 */
class DatabaseHelper {

	/**
	 * simple wrapper
	 *
	 * @return \B13\DamFalmigration\Helper\DatabaseHelper
	 */
	public static function getInstance() {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('B13\\DamFalmigration\\Helper\\DatabaseHelper');
	}

	/**
	 * fetch all FAL records that are there, that have been migrated 
	 * already from an existing DAM record
	 * 
	 * @return array with all DAM records with "uid" (= FAL uid) and "damuid" (= DAM uid)
	 */
	public function getAllMigratedFalRecords() {
		return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, _migrateddamuid AS damuid',
			'sys_file',
			'_migrateddamuid>0',
			'',	// group by
			'', // order by
			'', // limit
			'damuid'
		);
	}


	/**
	 * fetch all DAM categories
	 */
	public function getAllDamCategories() {
		$categoryRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, title, parent_id',
			'tx_dam_cat',
			'deleted=0',
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
				if ($parentRecord['title']) {
					$title = $parentRecord['title'] . ' - ' . $title;
				}
			}
			$usedCategories[$rec['uid']] = array(
				'damcategoryuid' => $rec['uid'],
				'title' => trim($title),
				'files' => array()
			);
		}

		return $usedCategories;

	}

	/**
	 * Gets all available (not deleted) DAM categories.
	 * Returns array with all categories.
	 *
	 * @return array
	 */
	public function getAllNotYetMigratedDamCategoriesWithItemCount() {
		// this query can also count all related categories (sys_category.items)
		$damCategories = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, parent_id, tstamp, sorting, crdate, cruser_id, hidden, title, description, (SELECT COUNT(*) FROM tx_dam_mm_cat WHERE tx_dam_mm_cat.uid_foreign = tx_dam_cat.uid) as items',
			'tx_dam_cat',
			'deleted = 0',
			'', 'parent_id', ''
		);

		// fetch all already imported sys_categories
		$importedSysCategories = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'_migrateddamcatuid',
			'sys_category',
			'deleted = 0 AND _migrateddamcatuid > 0',
			'',
			'',
			'',
			'_migrateddamcatuid'
		);

		// remove already imported categories from DAM categories to be imported
		foreach ($damCategories as $key => $damCategory) {
			//\TYPO3\CMS\Core\Utility\DebugUtility::debug($damCategory);
			if (array_key_exists($damCategory['uid'], $importedSysCategories)) {
				unset($damCategories[$key]);
			}
		}

		return $damCategories;
	}

	/**
	 * adds a relation to sys_file_reference if it does not exist yet
	 *
	 * @param integer $fileUid mapped to sys_file.uid
	 * @param integer $foreignUid mapped to e.g. sys_file_collection.uid
	 * @param string $tablename the foreign table name e.g. "sys_file_collection"
	 * @param string $fieldname the foreign table field name
	 * @return boolean TRUE if the record was added, false if it has already existed
	 */
	public function addToFileReferenceIfNotExists($fileUid, $foreignUid, $tablename, $fieldname) {
		// check if the reference already exists
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
			'uid',
			'sys_file_reference',
			'uid_local=' . $fileUid . ' AND uid_foreign=' . $foreignUid
			. ' AND tablenames=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($tablename, 'sys_file_reference')
			. ' AND fieldname=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($fieldname, 'sys_file_reference')
		);

		if (!is_array($res)) {
			// now put the record into the sys_file_reference table
			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'sys_file_reference',
				array(
					'uid_local'   => $fileUid,
					'uid_foreign' => $foreignUid,
					'tablenames'  => $tablename,
					'fieldname'   => $fieldname,
					'table_local' => 'sys_file',
				)
			);
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * check if given table exists in current database
	 * we can't check TCA or for installed extensions because dam and dam_ttcontent are not available for TYPO3 6.2
	 *
	 * @param $table
	 * @return bool
	 */
	public function isTableAvailable($table) {
		$tables = $GLOBALS['TYPO3_DB']->admin_get_tables();
		return array_key_exists($table, $tables);
	}

	/**
	 * Check if the wanted parent uid is available.
	 *
	 * @return boolean
	 */
	public function checkInitialParentAvailable() {
		$amountOfResults = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
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

	/**
	 * Adds new categorie in table sys_category. Requires the record array and the
	 * new parent_id for it has changed from DAM to FAL migration.
	 *
	 * @param $record
	 * @param $newParentUid
	 * @param $storagePid
	 * @return integer
	 */
	public function createNewCategory($record, $newParentUid, $storagePid) {
		$sysCategory = array(
			'pid' => $storagePid,
			'parent' => $newParentUid,
			'tstamp' => $record['tstamp'],
			'sorting' => $record['sorting'],
			'crdate' => $record['crdate'],
			'cruser_id' => $record['cruser_id'],
			'hidden' => $record['hidden'],
			'title' => $record['title'],
			'description' => (string)$record['description'],
			'items' => $record['items'],
			'_migrateddamcatuid' => $record['uid']
		);

		$GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_category', $sysCategory);

		$newUid = $GLOBALS['TYPO3_DB']->sql_insert_id();

		return $newUid;
	}

}
