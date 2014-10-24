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
	 * @throws \Exception
	 *
	 * @return \TYPO3\CMS\Core\Messaging\FlashMessage
	 */
	public function execute() {
		$this->controller->headerMessage(LocalizationUtility::translate('migrateDamTtnewsCommand', 'dam_falmigration'));
		if (!$this->isTableAvailable('tx_dam_mm_ref')) {
			return $this->getResultMessage('referenceTableNotFound');
		}

		$articlesWithImagesResult = $this->getRecordsWithDamConnections('tt_news', 'tx_damnews_dam_images');
		$this->migrateDamReferencesToFalReferences($articlesWithImagesResult, 'tt_news', 'image', array('tx_damnews_dam_images' => 'tx_falttnews_fal_images'));

		$articlesWithImagesResult = $this->getRecordsWithDamConnections('tt_news', 'tx_damnews_dam_media');
		$this->migrateDamReferencesToFalReferences($articlesWithImagesResult, 'tt_news', 'media', array('tx_damnews_dam_media' => 'tx_falttnews_fal_media'));

		return $this->getResultMessage();
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
