<?php
namespace TYPO3\CMS\DamFalmigration\Service;

/**
 *  Copyright notice
 *
 *  (c) 2015 glutrot GmbH <dneuge@glutrot.de>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Providing migration for media:xxx style file references in link fields such
 * as tt_content.header_link or tt_content.image_link.
 */
class MigrateLinksService extends AbstractService {
	/**
	 * table to migrate
	 * @var string
	 */
	protected $tablename = '';

	/**
	 * fields in table to migrate
	 * @var array
	 */
	protected $fieldnames = array();

	/**
	 * mappings from DAM UID to FAL UID
	 * @var array
	 */
	private $mappingCache = array();

	public function execute() {
		// check that we either got both table and field names or both have
		// been omitted to use default parameters
		$hasTablename = ($this->tablename !== '');
		$hasFieldname = (count($this->fieldnames) > 0);

		if (!$hasTablename && !$hasFieldname) {
			// default if both have been omitted:
			// tt_content.header_link and tt_content.image_link
			$this->tablename = 'tt_content';
			$this->fieldnames = array('header_link', 'image_link');

			$this->controller->headerMessage(LocalizationUtility::translate('migrateLinksCommand', 'dam_falmigration'));
		} else if ($hasTablename && $hasFieldname) {
			// user supplied both table and field name
			$this->controller->headerMessage(LocalizationUtility::translate('migrateLinksCommandForField', 'dam_falmigration', array($this->tablename, $this->fieldnames[0])));
		} else {
			// either parameter is missing, so we cannot proceed
			// NOTE: workaround for error message issue #70
			$this->amountOfMigratedRecords = -1;
			return $this->getResultMessage('migrateLinksCommandMissingParameter', LocalizationUtility::translate('migrationStatusMessage.migrateLinksCommandMissingParameter', 'dam_falmigration'));
		}

		// find all rows to migrate
		$migratableRows = array();
		foreach ($this->fieldnames as $fieldname) {
			$res = $this->database->exec_SELECTquery(
				'uid, `' . $fieldname . '`', // select fields
				$this->tablename, // from table
				'`' . $fieldname . '` LIKE \'%media:%\'', // where clause
				'', // group by
				'', // order by
				(int)$this->getRecordLimit() // limit
			);

			// stop on database error (e.g. table/column not found)
			$error = $this->database->sql_error();
			if ($error) {
				$this->controller->errorMessage($error);
				exit;
			}

			// remember row for migration
			while ($row = $this->database->sql_fetch_assoc($res)) {
				$migratableRows[] = array(
					'field' => $fieldname,
					'content' => $row[$fieldname],
					'uid' => $row['uid']
				);
			}

			$this->database->sql_free_result($res);
		}

		// quit if no migration is required
		$total = count($migratableRows);
		if ($total === 0) {
			return $this->getResultMessage('migrationNotNecessary', '');
		}

		// perform migration
		$counter = 0;
		$countSuccessful = 0;
		$countFailed = 0;
		foreach ($migratableRows as $row) {
			$counter++;
			$content = $row['content'];

			// it makes more sense to display the migration message before
			// processing to have the UID ready if debugging is needed
			$this->controller->message(number_format(100 * ($counter / $total), 1) . '% of ' . $total .
					' id: ' . $row['uid'].
					' table: ' . $this->tablename.
					' field: ' . $row['field']);

			$m = array();
			if (!preg_match_all('/^\s*media:(\d+)( .*|)$/mi', $content, $m, PREG_SET_ORDER)) {
				$this->controller->errorMessage('unexpected error in '.__FILE__.', line '.__LINE__.': database found media: link but we did not?! skipping');
				$countFailed++; // not exact but will trigger error result message
				continue;
			} else {
				foreach ($m as $singleMatch) {
					$mediaUID = (int)$singleMatch[1];
					$falUID = $this->findMigratedFileUIDForDAMRecord($mediaUID);

					if ($falUID < 0) {
						// no migrated record found, print error message
						$this->controller->errorMessage(LocalizationUtility::translate('migrateLinksCommandMissingFile', 'dam_falmigration', array($mediaUID)));
						$countFailed++;
					} else {
						// record found, replace all occurrences in content
						$content = preg_replace('/^(\s*)media:' . $mediaUID . '( .*|)$/mi', '$1file:' . $falUID . '$3', $content);
						$countSuccessful++;
					}
				}
			}

			// save changed record to DB
			if ($row['content'] !== $content) {
				$res = $this->database->exec_UPDATEquery(
					$this->tablename,
					'uid = ' . $this->database->fullQuoteStr((int)$row['uid'], 'tt_content', false),
					array(
						$row['field'] => $content
					)
				);

				if (!$res) {
					$this->controller->errorMessage($this->database->sql_error());
					$countFailed++; // not exact but will trigger error result message
				}
			}
		}

		// print result message
		$headline = LocalizationUtility::translate('migrateLinksCommandDoneHeadline', 'dam_falmigration');;
		$message = '';
		if ($countFailed === 0) {
			$message = LocalizationUtility::translate('migrateLinksCommandComplete', 'dam_falmigration', array($countSuccessful));
		} else {
			$message = LocalizationUtility::translate('migrateLinksCommandPartial', 'dam_falmigration', array($countSuccessful, $countFailed));
		}
		return GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $message, $headline);
	}

	/**
	 * Searches sys_file for given DAM record.
	 * @param int $mediaUID UID of former DAM record
	 * @return int UID of FAL record
	 */
	protected function findMigratedFileUIDForDAMRecord($mediaUID) {
		// use cached result if any
		$cacheKey = '' . $mediaUID;
		if (array_key_exists($cacheKey, $this->mappingCache)) {
			return $this->mappingCache[$cacheKey];
		}

		// query database
		$res = $this->database->exec_SELECTgetSingleRow(
			'uid',
			'sys_file',
			'`_migrateddamuid` = ' . $this->database->fullQuoteStr((int)$mediaUID, 'sys_file', false)
		);

		// use negative value if not found
		$falUID = -1;
		if (($res !== NULL) && is_array($res) && (count($res) > 0)) {
			$falUID = (int)$res['uid'];
		}

		// save to cache
		$this->mappingCache[$cacheKey] = $falUID;

		return $falUID;
	}

	public function setTablename($tablename) {
		$this->tablename = preg_replace('/[^a-z0-9_]/i', '', $tablename);

		return $this;
	}

	public function setFieldname($fieldname) {
		if ($fieldname === '') {
			$this->fieldnames = array();
		} else {
			$this->fieldnames = array(
				preg_replace('/[^a-z0-9_]/i', '', $fieldname)
			);
		}

		return $this;
	}
}
