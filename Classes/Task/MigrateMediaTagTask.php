<?php
namespace TYPO3\CMS\DamFalmigration\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Stefan Froemken <froemken@gmail.com>
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
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Scheduler Task to Migrate <media>-Tags
 *
 * @author Stefan Froemken <froemken@gmail.com>
 */
class MigrateMediaTagTask extends AbstractTask {

	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 *
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
	 * @throws \Exception
	 * @return boolean
	 */
	public function execute() {
		$this->init();

		$ttContentRecords = $this->getTtContentRecordsWithMediaTag();
		foreach ($ttContentRecords as $ttContentRecord) {
			$results = preg_match_all('/<media ([0-9]{1,}) (.*)>(.*)<\/media>/', $ttContentRecord['bodytext'], $matches);
			if ($results) {
				foreach ($matches[0] as $key => $mediaTag) {
					$linkTag = '<link file:' . $this->getUidOfSysFileRecord($matches[1][$key]) . ' ' . $matches[2][$key] . '>' . $matches[3][$key] . '</link>';
					$ttContentRecord['bodytext'] = str_replace($mediaTag, $linkTag, $ttContentRecord['bodytext']);
				}
				$this->database->exec_UPDATEquery(
					'tt_content',
					'uid = ' . $ttContentRecord['uid'],
					array('bodytext' => $ttContentRecord['bodytext'])
				);
			}
		}

		// mark task as successful executed
		return TRUE;
	}

	/**
	 * Returns all tt_content records containing a <media>-Tag in col bodytext
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function getTtContentRecordsWithMediaTag() {
		$rows = $this->database->exec_SELECTgetRows(
			'uid, bodytext',
			'tt_content',
			'bodytext REGEXP ".*<media [0-9]{1,}.*</media>.*"	AND deleted = 0',
			'', '', ''
		);
		if ($rows === NULL) {
			throw new \Exception('Error in Query', 1383924636);
		} else {
			return $rows;
		}
	}

	/**
	 * after migration of DAM-Records we can find sys_file-UID with help of DAM-UID
	 *
	 * @param integer $damUid
	 * @return integer
	 */
	protected function getUidOfSysFileRecord($damUid) {
		$record = $this->database->exec_SELECTgetSingleRow(
			'uid',
			'sys_file',
			'_migrateddamuid = ' . (integer) $damUid,
			'', '', ''
		);
		return $record['uid'];
	}

}