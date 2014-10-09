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
		$this->parent = $parent;
		$parent->headerMessage(LocalizationUtility::translate('migrateDamTtnewsCommand', 'dam_falmigration'));
		if ($this->isTableAvailable('tx_dam_mm_ref')) {
			$articlesWithImagesResult = $this->getRecordsWithDamConnections('tt_news', 'tx_damnews_dam_images');
			$this->migrateRecords($articlesWithImagesResult, 'image');

			$articlesWithImagesResult = $this->getRecordsWithDamConnections('tt_news', 'tx_damnews_dam_media');
			$this->migrateRecords($articlesWithImagesResult, 'media');
		} else {
			$parent->errorMessage('Table tx_dam_mm_ref not found. So there is nothing to migrate.');
		}

		return $this->getResultMessage();
	}

	/**
	 * Migrate the resultset
	 *
	 * @param $result
	 * @param $type
	 */
	protected function migrateRecords($result, $type) {
		$counter = 0;
		$total = $this->database->sql_num_rows($result);
		$this->parent->infoMessage('Found ' . $total . ' articles with a dam ' . $type);

		while ($article = $this->database->sql_fetch_assoc($result)) {
			$identifier = str_replace('fileadmin', '', $this->getFileIdentifier($article));
			$counter++;
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
					$this->parent->errorMessage('Unable to create directory: ' . PATH_site . 'fileadmin/', str_replace('fileadmin/', '', $article['file_path']));
					continue;
				}
				$this->parent->infoMessage('Creating empty missing file: ' . PATH_site . $this->getFileIdentifier($article));

				try {
					\TYPO3\CMS\Core\Utility\GeneralUtility::writeFile(PATH_site . $this->getFileIdentifier($article), '');
				} catch (\Exception $e) {
					$this->parent->errorMessage('Unable to create file: ' . PATH_site . $this->getFileIdentifier($article), '');
					continue;
				}
				$fileObject = $this->storageObject->getFile($identifier);
			}
			if ($fileObject instanceof \TYPO3\CMS\Core\Resource\File) {
				if ($fileObject->isMissing()) {
					$this->parent->warningMessage('FAL did not find any file resource for DAM record. DAM uid: ' . $article['uid'] . ': "' . $identifier . '"');
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
					'pid' => $article['item_pid'],
					'l10n_diffsource' => (string)$article['l18n_diffsource']
				);

				if (!$this->doesFileReferenceExist($article)) {
					$this->database->exec_INSERTquery(
						'sys_file_reference',
						$insertData
					);
					$this->amountOfMigratedRecords++;
					$this->parent->message(number_format(100 * ($counter / $total), 1) . '% of ' . $total . ' id: ' . $article['item_uid'] . ': dam uid: ' . $article['dam_uid'] . ' to fal uid: ' . $article['uid_local'] . ' ' . $identifier);
				} else {
					$this->parent->message(number_format(100 * ($counter / $total), 1) . '% of ' . $total . ' Reference already exists.');
				}
			}

			$this->updateReferenceCounters($article);
		}
		$this->database->sql_free_result($result);
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
			'uid = ' . $article['item_uid'],
			array(
				'tx_falttnews_fal_images' => $article['tx_damnews_dam_images'],
				'tx_falttnews_fal_media' => $article['tx_damnews_dam_media']
			)
		);
	}

}
