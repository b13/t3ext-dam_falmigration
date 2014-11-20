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
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Service to Upgrade the storage index
 *
 * @author Michiel Roos <michiel@maxserv.nl>
 */
class UpgradeStorageIndexService extends AbstractService {

	/**
	 * Main function, returns a FlashMessge
	 *
	 * @throws \Exception
	 *
	 * @return \TYPO3\CMS\Core\Messaging\FlashMessage
	 */
	public function execute() {
		$this->controller->headerMessage(LocalizationUtility::translate('upgradeStorageIndexCommand', 'dam_falmigration'));

		if ((int)$this->storageUid > 0) {
			$storage = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getStorageObject($this->storageUid);
			$storage->setEvaluatePermissions(FALSE);
			$indexer = $this->getIndexer($storage);
			try {
				$indexer->processChangesInStorages();
				$message = $this->getResultMessage('storageUpdated');
			} catch (\Exception $e) {
				$message = $this->getResultMessage('storageUpdateFailure', $e->getCode() . ' ' . $e->getMessage());
			}
			$storage->setEvaluatePermissions(TRUE);
			return $message;
		}
	}

	/**
	 * Gets the indexer
	 *
	 * @param \TYPO3\CMS\Core\Resource\ResourceStorage $storage
	 *
	 * @return \TYPO3\CMS\Core\Resource\Index\Indexer
	 */
	protected function getIndexer(\TYPO3\CMS\Core\Resource\ResourceStorage $storage) {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\Index\\Indexer', $storage);
	}
}
