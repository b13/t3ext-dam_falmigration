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
class MigrateDamCategoryRelationsTask extends AbstractTask {

	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 *
	 * @throws \Exception
	 * @return boolean
	 */
	public function execute() {
		$this->init();

		if ($this->isTableAvailable('tx_dam_mm_ref')) {
			$categoryRelations = $this->getCategoryRelationsWhereSysCategoryExists();
			foreach ($categoryRelations as $categoryRelation) {
				$insertData = array(
					'uid_local' => $categoryRelation['sys_file_uid'],
					'uid_foreign' => $categoryRelation['sys_category_uid'],
					'sorting' => $categoryRelation['sorting'],
					'sorting_foreign' => $categoryRelation['sorting_foreign'],
					'tablenames' => 'sys_file',
					'fieldname' => 'categories'
				);

				if (!$this->checkIfSysCategoryRelationExists($categoryRelation)) {
					$this->database->exec_INSERTquery(
						'sys_category_record_mm',
						$insertData
					);
					$this->amountOfMigratedRecords++;
				}
			}
			$this->addResultMessage();
			return TRUE;
		} else {
			throw new \Exception('Table tx_dam_mm_ref not found. So there is nothing to migrate.');
		}
	}

	/**
	 * After a migration of tx_dam_cat -> sys_category the col _migrateddamcatuid is filled with dam category uid
	 * Now we can search in dam category relations for dam categories which have already been migrated to sys_category
	 *
	 * @throws \Exception
	 * @return array
	 */
	protected function getCategoryRelationsWhereSysCategoryExists() {
		$rows = $this->database->exec_SELECTgetRows(
			'MM.*, SF.uid as sys_file_uid, SC.uid as sys_category_uid',
			'tx_dam_mm_cat MM, sys_file SF, sys_category SC',
			'SC._migrateddamcatuid = MM.uid_foreign AND SF._migrateddamuid = MM.uid_local'
		);
		if ($rows === NULL) {
			throw new \Exception('SQL-Error in getCategoryRelationsWhereSysCategoryExists()', 1382968725);
		} elseif (count($rows) === 0) {
			throw new \Exception('There are no migrated dam categories in sys_category. Please start to migrate DAM Cat -> sys_category first. Or, maybe there are no dam categories to migrate', 1382968775);
		} else return $rows;
	}

	/**
	 * check if a sys_category_record_mm already exists
	 *
	 * @param array $categoryRelation
	 * @return boolean
	 */
	protected function checkIfSysCategoryRelationExists(array $categoryRelation) {
		$amountOfExistingRecords = $this->database->exec_SELECTcountRows(
			'*',
			'sys_category_record_mm',
			'uid_local = ' . $categoryRelation['sys_file_uid'] .
			' AND uid_foreign = ' . $categoryRelation['sys_category_uid'] .
			' AND tablenames = "sys_file"'
		);
		if ($amountOfExistingRecords) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

}