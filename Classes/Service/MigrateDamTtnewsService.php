<?php
namespace TYPO3\CMS\DamFalmigration\Service;

/**
 *  Copyright notice
 *
 *  ⓒ 2014 Michiel Roos <michiel@maxserv.nl>
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
use B13\DamFalmigration\Controller\DamMigrationCommandController;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Service to Migrate tt_news records enhanced with dam_ttnews
 *
 * @author Michiel Roos <michiel@maxserv.nl>
 */
class MigrateDamTtnewsService extends AbstractService {

	/**
	 * Main function, returns a FlashMessge
	 *
	 * @param DamMigrationCommandController $parent Used to log output to
	 *    console
	 *
	 * @throws \Exception
	 *
	 * @return FlashMessage
	 */
	public function execute($parent) {
		$parent->headerMessage(LocalizationUtility::translate('migrateDamTtnewsCommand', 'dam_falmigration'));
		if ($this->isTableAvailable('tx_dam_mm_ref')) {
			$articles = $this->getNewsWithDamConnections();
			$parent->infoMessage('Found ' . count($articles) . ' articles');
			foreach ($articles as $article) {
				$identifier = str_replace('fileadmin', '', $this->getFileIdentifier($article));

				try {
					$fileObject = $this->storageObject->getFile($identifier);
				} catch (\Exception $e) {
					// If file is not found
					// getFile will throw an invalidArgumentException if the file
					// does not exist. Create an empty file to avoid this. This is
					// usefull in a development environment that has the production
					// database but not all the physical files.
					try {
						\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep(PATH_site . 'fileadmin/', str_replace('fileadmin/', '', $article['file_path']));
					} catch (\Exception $e) {
						$parent->errorMessage('Unable to create directory: ' . PATH_site . 'fileadmin/', str_replace('fileadmin/', '', $article['file_path']));
						continue;
					}
					$parent->infoMessage('Creating empty missing file: ' . PATH_site . $this->getFileIdentifier($article));

					try {
						\TYPO3\CMS\Core\Utility\GeneralUtility::writeFile(PATH_site . $this->getFileIdentifier($article), '');
					} catch (\Exception $e) {
						$parent->errorMessage('Unable to create file: ' . PATH_site . $this->getFileIdentifier($article), '');
						continue;
					}
					$fileObject = $this->storageObject->getFile($identifier);
				}
				if ($fileObject instanceof \TYPO3\CMS\Core\Resource\File) {
					if ($fileObject->isMissing()) {
						$parent->warningMessage('FAL did not find any file resource for DAM record. DAM uid: ' . $article['uid'] . ': "' . $identifier . '"');
						continue;
					}

					$article['uid_local'] = $fileObject->getUid();
					if ($article['ident'] === 'tx_damnews_dam_images') {
						$article['ident'] = 'tx_falttnews_fal_images';
					}
					if ($article['ident'] === 'tx_damnews_dam_media') {
						$article['ident'] = 'tx_falttnews_fal_media';
					}
					$insertData = array(
						'uid_local' => $article['uid_local'],
						'uid_foreign' => (int)$article['uid_foreign'],
						'sorting' => (int)$article['sorting'],
						'sorting_foreign' => (int)$article['sorting_foreign'],
						'tablenames' => (string)$article['tablenames'],
						'fieldname' => (string)$article['ident'],
						'table_local' => 'sys_file',
						'pid' => $article['news_pid'],
						'l10n_diffsource' => (string)$article['l18n_diffsource']
					);

					if (!$this->doesFileReferenceExist($article)) {
						$this->database->exec_INSERTquery(
							'sys_file_reference',
							$insertData
						);
						$this->amountOfMigratedRecords++;
						$parent->message('Migrating relation for article: ' . $article['news_uid'] . ' dam uid: ' . $article['dam_uid'] . ' to fal uid: ' . $article['uid_local']);
					} else {
						$parent->message('Reference already exists.');
					}
				}

				$this->updateReferenceCounters($article);
			}
		} else {
			$parent->errorMessage('Table tx_dam_mm_ref not found. So there is nothing to migrate.');
		}

		return $this->getResultMessage();
	}

	/**
	 * Fetches all news records with DAM connections
	 *
	 * @throws \Exception
	 * @return array
	 */
	protected function getNewsWithDamConnections() {
		$rows = $this->database->exec_SELECTgetRows(
			'n.uid as news_uid,
			 n.pid as news_pid,
			 n.tx_damnews_dam_images,
			 n.tx_damnews_dam_media,
			 r.uid_local,
			 r.uid_foreign,
			 r.tablenames,
			 r.sorting,
			 r.ident,
			 d.uid as dam_uid,
			 d.file_name,
			 d.file_path,
			 d.l18n_diffsource',
			'tx_dam_mm_ref as r
				INNER JOIN tt_news as n ON r.uid_foreign = n.uid
				INNER JOIN tx_dam as d ON d.uid = r.uid_local',
			'(NOT n.tx_damnews_dam_images = 0 OR NOT n.tx_damnews_dam_media = 0)
			 AND r.tablenames = "tt_news"
			 AND d.deleted = 0
			 AND n.deleted = 0'
		);
		if ($rows === NULL) {
			throw new \Exception('SQL-Error in getNewsWithDamConnections()', 1382968725);
		}

		return $rows;
	}

	/**
	 * check if a sys_file_reference already exists
	 *
	 * @param array $fileReference
	 *
	 * @return boolean
	 */
	protected function doesFileReferenceExist(array $fileReference) {
		return (bool)$this->database->exec_SELECTcountRows(
			'uid',
			'sys_file_reference',
			'uid_local = ' . $fileReference['uid_local'] .
			' AND uid_foreign = ' . $fileReference['uid_foreign'] .
			' AND tablenames = "' . $fileReference['tablenames'] . '"' .
			' AND fieldname = "' . $fileReference['ident'] . '"' .
			' AND table_local = "sys_file"'
		);
	}

	/**
	 * Update reference counters of tt_news record
	 *
	 * @param array $article
	 *
	 * @return void
	 */
	protected function updateReferenceCounters(array $article) {
		$this->database->exec_UPDATEquery(
			'tt_news',
			'uid = ' . $article['news_uid'],
			array (
				'tx_falttnews_fal_images' => $article['tx_damnews_dam_images'],
				'tx_falttnews_fal_media' => $article['tx_damnews_dam_media']
			)
		);
	}

}
