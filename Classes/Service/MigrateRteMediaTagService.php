<?php
namespace TYPO3\CMS\DamFalmigration\Service;

/**
 *  Copyright notice
 *
 *  (c) 2012 Benjamin Mack <benni@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is free
 *  software; you can redistribute it and/or modify it under the terms of the
 *  GNU General Public License as published by the Free Software Foundation;
 *  either version 2 of the License, or (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 *  or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 *  more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

use TYPO3\CMS\Core\Database\PreparedStatement;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Migrate DAM Media Tags in RTE to <link /> elements
 * migrates the <media DAM_UID target title>Linktext</media>
 * to <link file:29643 - download>My link to a file</link> *
 *
 * @author Benjamin Mack <benni@typo3.org>
 * @author Michiel Roos <michiel@maxserv.nl>
 * @author Stefan Froemken <froemken@gmail.com>
 */
class MigrateRteMediaTagService extends AbstractService {

	/**
	 * main function, returns a FlashMessge
	 *
	 * @param string $table The table to search for RTE fields
	 * @param string $field The fieldname to search
	 *
	 * @throws \Exception
	 *
	 * @return FlashMessage
	 */
	public function execute($table, $field) {
		$this->controller->headerMessage(LocalizationUtility::translate('migrateMediaTagsInRteCommand', 'dam_falmigration'));
		$table = preg_replace('/[^a-zA-Z0-9_-]/', '', $table);
		$field = preg_replace('/[^a-zA-Z0-9_-]/', '', $field);

		$records = $this->getRecords($table, $field);

		$this->controller->infoMessage('Found ' . count($records) . ' ' . $table . ' records that have a "<media>" tag in the field ' . $field);

		/** @var PreparedStatement $getSysFileUidStatement */
		$getSysFileUidStatement = $this->database->prepare_SELECTquery(
			'uid',
			'sys_file',
			'_migrateddamuid = :migrateddamuid'
		);
		foreach ($records as $rec) {
			$originalContent = $rec[$field];
			$finalContent = $originalContent;
			$results = array();
			preg_match_all('/(?:<media ([0-9]+)([^>]*)>(.*?)<\/media>|&lt;media ([0-9]+)(.*?)&gt;(.*?)&lt;\/media&gt;)/', $originalContent, $results, PREG_SET_ORDER);
			if (count($results)) {
				foreach ($results as $result) {
					$searchString = $result[0];
					$damUid = 0;
					$linkClass = '';
					$linkTarget = '';
					$linkText = '';
					$linkTitle = '';
					// Match for <media
					if ((int)$result[1] > 0) {
						$damUid = $result[1];
						// see EXT:dam/mediatag/class.tx_dam_rtetransform_mediatag.php
						list($linkTarget, $linkClass, $linkTitle) = explode(' ', trim($result[2]), 3);
						$linkText = $result[3];
					}
					// Match for &lt;media
					if ((int)$result[4] > 0) {
						$damUid = $result[4];
						// see EXT:dam/mediatag/class.tx_dam_rtetransform_mediatag.php
						list($linkTarget, $linkClass, $linkTitle) = explode(' ', trim($result[5]), 3);
						$linkText = $result[6];
					}
					$this->controller->message('Replacing "' . $result[0] . '" with DAM UID ' . $damUid . ' (target ' . $linkTarget . '; class ' . $linkClass . '; title "' . $linkTitle . '") and linktext "' . $linkText . '"');
					/**
					 * after migration of DAM-Records we can find sys_file-UID with help of
					 * DAM-UID fetch the DAM uid from sys_file and replace the full tag with a
					 * valid href="file:FALUID"
					 * <link file:29643 - download>My link to a file</link>
					 */
					$getSysFileUidStatement->execute(array(':migrateddamuid' => (int)$damUid));
					$falRecord = $getSysFileUidStatement->fetch();
					if (is_array($falRecord)) {
						$replaceString = '<link file:' . $falRecord['uid'] . ' ' . $linkTarget . ' ' . $linkClass . ' ' . $linkTitle . ' ' . '>' . $linkText . '</link>';
						$finalContent = str_replace($searchString, $replaceString, $finalContent);
					} else {
						$this->controller->warningMessage('No FAL record found for dam uid: ' . $damUid);
					}
				}
				// update the record
				if ($finalContent !== $originalContent) {
					$this->database->exec_UPDATEquery(
						$table,
						'uid=' . $rec['uid'],
						array($field => $finalContent)
					);
					$this->controller->infoMessage('Updated ' . $table . ':' . $rec['uid'] . ' with: ' . $finalContent);
					$this->amountOfMigratedRecords++;
				}
			} else {
				$this->controller->warningMessage('Nothing found: ' . $originalContent);
			}
		}

		//$getSysFileUidStatement->free();

		return $this->getResultMessage();
	}

	/**
	 * @param string $table
	 * @param string $field
	 *
	 * @throws \Exception
	 * @return mixed
	 */
	private function getRecords($table, $field) {
		$rows = $this->database->exec_SELECTgetRows(
			'uid, ' . $field,
			$table,
			'deleted=0 AND (' . $field . ' LIKE "%<media%" OR ' . $field . ' LIKE "%&lt;media%")'
		);
		if ($rows === NULL) {
			throw new \Exception('Error in Query', 1383924636);
		} else {
			$result = $rows;
		}

		return $result;
	}

}
