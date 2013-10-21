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
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Scheduler Task to Migrate DAM relations to FAL relations
 * right now this is dam_ttcontent, dam_uploads
 *
 * @author      Benjamin Mack <benni@typo3.org>
 */
class MigrateRelationsTask extends \TYPO3\CMS\DamFalmigration\Task\AbstractTask {

	/**
	 * @var \TYPO3\CMS\Core\Database\ReferenceIndex
	 */
	protected $referenceIndex;

	/**
	 * initializes this object
	 */
	protected function init() {
		parent::init();
		$this->referenceIndex = $this->objectManager->get('TYPO3\\CMS\\Core\\Database\\ReferenceIndex');
	}

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
			$damRelations = $this->getDamReferencesWhereSysFileExists();
			foreach ($damRelations as $damRelation) {
				$insertData = array(
					'pid' => $this->getPidOfForeignRecord($damRelation),
					'tstamp' => time(),
					'crdate' => time(),
					'cruser_id' => $GLOBALS['BE_USER']->user['uid'],
					'sorting' => $damRelation['sorting'],
					'uid_local' => $damRelation['sys_file_uid'],
					'uid_foreign' => $damRelation['uid_foreign'],
					'tablenames' => $damRelation['tablenames'],
					'fieldname' => $this->getColForFieldName($damRelation),
					'table_local' => 'sys_file',
					'title' => $damRelation['title'],
					'description' => $damRelation['description'],
					'alternative' => $damRelation['alternative'],
				);

				if (!$this->checkIfSysFileRelationExists($damRelation)) {
					$this->database->exec_INSERTquery(
						'sys_file_reference',
						$insertData
					);
					$this->updateReferenceIndex($this->database->sql_insert_id());
					$this->amountOfMigratedFiles++;
				}
			}
			$this->addResultMessage();
			return TRUE;
		} else {
			throw new \Exception('Extension tx_dam and dam_ttcontent is not installed. So there is nothing to migrate.');
		}
	}

	/**
	 * get pid of foreign record
	 * this is needed by sys_file_reference records
	 *
	 * @param array $damRelation
	 * @return mixed
	 */
	protected function getPidOfForeignRecord(array $damRelation) {
		$record = BackendUtility::getRecord(
			$damRelation['tablenames'],
			$damRelation['uid_foreign'],
			'pid', '', FALSE
		);
		return $record['pid'];
	}

	/**
	 * After a migration of tx_dam -> sys_file the col _migrateddamuid is filled with dam uid
	 * Now we can search in dam relations for dam records which have already been migrated to sys_file
	 *
	 * @throws \Exception
	 * @return array|NULL
	 */
	protected function getDamReferencesWhereSysFileExists() {
		$rows = $this->database->exec_SELECTgetRows(
			'MM.*, SF.uid as sys_file_uid, MD.title, MD.description, MD.alternative',
			'tx_dam_mm_ref MM, sys_file SF, sys_file_metadata MD',
			'MD.file = SF.uid' .
			' AND SF._migrateddamuid = MM.uid_local'
		);
		if ($rows === NULL) {
			throw new \Exception('SQL-Error in getDamReferencesWhereSysFileExists()', 1382353670);
		} elseif (count($rows) === 0) {
			throw new \Exception('There are no migrated dam records in sys_file. Please start to migrate DAM -> sys_file first. Or, maybe there are no dam records to migrate', 1382355647);
		} else return $rows;
	}

	/**
	 * check if a sys_file_reference already exists
	 *
	 * @param array $damRelation
	 * @return boolean
	 */
	protected function checkIfSysFileRelationExists(array $damRelation) {
		$amountOfExistingRecords = $this->database->exec_SELECTcountRows(
			'*',
			'sys_file_reference',
			'uid_local = ' . $damRelation['sys_file_uid'] .
			' AND uid_foreign = ' . $damRelation['uid_foreign'] .
			' AND tablenames = ' . $this->database->fullQuoteStr($damRelation['tablenames'], 'sys_file_reference') .
			' AND deleted = 0'
		);
		if ($amountOfExistingRecords) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * col for fieldname was saved in col "ident"
	 * But: If dam_ttcontent is installed fieldName is "image"
	 *
	 * @param array $damRelation
	 * @return string
	 */
	protected function getColForFieldName(array $damRelation) {
		if ($damRelation['tablenames'] == 'tt_content' && $damRelation['ident'] == 'tx_damttcontent_files') {
			$fieldName = 'image';
		} else {
			$fieldName = $damRelation['ident'];
		}
		return $fieldName;
	}

	/**
	 * update reference index
	 *
	 * @param integer $uid
	 * @return void
	 */
	protected function updateReferenceIndex($uid) {
		$this->referenceIndex->updateRefIndexTable('sys_file_reference', $uid);
	}

}