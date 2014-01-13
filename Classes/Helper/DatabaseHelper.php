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



}
