<?php
namespace TYPO3\CMS\DamFalmigration\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 in2code.de
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

/**
 * Scheduler Task to migrate "imagecaption" in tt_content records to "description"
 * in corresponding sys_file_reference records.
 *
 * tt_content record's "imagecaption" is deleted after being sucessfully migrated
 * to it's corresponding sys_file_reference record's "description".
 *
 * ---------------------------------------------------------------------------------
 *
 * Important note:
 *
 * If the number of lines in a tt_content record's description is greater than the
 * number of associated FAL records it is very likely that tt_content.imagecaption
 * is not used as it is generally supposed to be. Most probably tt_content's
 * imagecaption is not used as a one-line-per-image field but as a
 * all-lines-for-one-image field.
 *
 * These tt_content's description will neither be migrated nor will they be deleted
 * but must be migrated manually!
 *
 * Have a look at the database or (if used) the devlog.
 *
 * ---------------------------------------------------------------------------------
 */
class MigrateTtContentImagecaptionTask extends AbstractTask {

	/**
	 * Main method which is called by EXT:scheduler.
	 *
	 * Needs to return TRUE or FALSE in order to tell EXT:scheduler whether the task
	 * went through smoothly.
	 *
	 * @return bool
	 */
	public function execute() {
		$this->init();
		$migrationQueue = array();

		$res = $this->database->exec_SELECTquery(
			'tt_content.uid AS tt_content_uid, sys_file_reference.uid AS sys_file_reference_uid, tt_content.imagecaption AS imagecaption',
			'tt_content INNER JOIN sys_file_reference ON tt_content.uid = sys_file_reference.uid_foreign',
			'tt_content.imagecaption != "" AND sys_file_reference.fieldname = "image" AND sys_file_reference.tablenames = "tt_content"'
		);

		while (($row = $this->database->sql_fetch_assoc($res))) {
			// remove trailing linebreaks (and spaces) in imagecaption
			$row['imagecaption'] = preg_replace('/(\r\n|\r|\n| )+$/', '', $row['imagecaption']);
			$imagecaptionLines = preg_split("/\r\n|\r|\n/", $row['imagecaption']);
			$numberOfImagecaptionLines = count($imagecaptionLines);

			if (
				$numberOfImagecaptionLines > $this->getNumberOfFalRecordsOfTtContentRecord($row['tt_content_uid'])
			) {
				// ...it is very likely that tt_content.imagecaption is not used as it
				// is generally supposed to be used. Most probably tt_content.imagecaption
				// is not used as a one-line-per-image field but as a all-lines-for-one-image
				// field. This records must be migrated manually.
				GeneralUtility::devLog(
					'tt_content record ' . $row['tt_content_uid'] . ": The number of lines in tt_content.imagecaption exceeds the record's number of FAL records! Therefore tt_content.imagecaption was neither migrated nor deleted for this tt_content record.",
					'dam_falmigration',
					2
				);
			} else {
				if (!array_key_exists($row['tt_content_uid'], $migrationQueue)) {
					$migrationQueue[$row['tt_content_uid']] = $imagecaptionLines;
				}
			}
		}

		$this->migrate($migrationQueue);

		// mark task as successfully executed
		return TRUE;
	}

	/**
	 * @param int $ttContentUid
	 * @return int
	 */
	protected function getNumberOfFalRecordsOfTtContentRecord($ttContentUid) {
		$result = $this->database->exec_SELECTcountRows(
			'*',
			'sys_file_reference',
			'tablenames="tt_content" AND fieldname="image" AND uid_foreign=' . $ttContentUid
		);

		return $result;
	}

	/**
	 * Example $migrationQueue array:
	 *
	 * $migrationQueue = array(
	 *     23 => array(
	 *         'Lorem ipsum',
	 *         'Dolor sit amet',
	 *     ),
	 *     45 => array(
	 *         'Aramistum loctus quam',
	 *         'Amicatur esequetam erratum est',
	 *         'Macadamia repectum vestubulatem',
	 *     ),
	 * );
	 *
	 * @param array $migrationQueue The keys are the tt_content record's UIDs and values are arrays holding the tt_content records' imagecaption lines
	 * @return void
	 */
	protected function migrate($migrationQueue) {
		foreach ($migrationQueue as $ttContentUid => $imagecaptionLines) {
			$associatedFalRecordsSorted = $this->database->exec_SELECTgetRows(
				'uid',
				'sys_file_reference',
				'fieldname = "image" AND tablenames = "tt_content" AND uid_foreign=' . $ttContentUid,
				'',
				'sorting_foreign ASC'
			);

			foreach ($associatedFalRecordsSorted as $i => $falRecord) {
				$this->database->exec_UPDATEquery(
					'sys_file_reference',
					'uid=' . $falRecord['uid'],
					array(
						'description' => $imagecaptionLines[$i],
					)
				);
			}

			$this->deleteMigratedData($ttContentUid);
		}
	}

	/**
	 * @param int $ttContentUid
	 * @return void
	 */
	protected function deleteMigratedData($ttContentUid) {
		$this->database->exec_UPDATEquery(
			'tt_content',
			'uid=' . $ttContentUid,
			array(
				'imagecaption' => ''
			)
		);
	}

}
