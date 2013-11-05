<?php
namespace TYPO3\CMS\DamFalmigration\Controller;

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

/**
 * Show information about the current status of the file migration
 *
 * @author Benjamin Mack <benni@typo3.org>
 */
class FileController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	/**
	 * shows an overview of the current status of the File migration
	 *
	 */
	public function overviewAction() {
	
		$indexedFilesInMainStorage = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
			'count(*) AS total',
			'sys_file',
			'storage = 1'
		);
		$indexedFilesInMainStorage = $indexedFilesInMainStorage['total'];
		$this->view->assign('indexedFilesInMainStorage', $indexedFilesInMainStorage);


		$migratedFileFields = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['INSTALL']['wizardDone']['TYPO3\CMS\Install\Updates\TceformsUpdateWizard'], TRUE);
		$this->view->assign('migratedFileFields', $migratedFileFields);


		$isDAMloaded = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('dam');
		$this->view->assign('isDAMloaded', $isDAMloaded);
	}

}