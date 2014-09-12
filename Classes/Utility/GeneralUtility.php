<?php
namespace TYPO3\CMS\DamFalmigration\Utility;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

class GeneralUtility {

	/**
	 * Re-sorts the category array for we have the parent categories BEFORE the subcategories!
	 * Walks recursively through the category tree
	 *
	 * @param $damCategories
	 * @param $parentUid
	 * @return array
	 */
	static public function sortCategories($damCategories, $parentUid) {
		// New array for sorting dam records
		$sortedDamCategories = array();
		// Remember the uids for finding sub-categories
		$rememberUids = array();

		// Find all categories for the given parent_uid
		foreach($damCategories as $key =>$category) {
			if($category['parent_id'] == $parentUid) {
				$sortedDamCategories[] = $category;
				$rememberUids[] = $category['uid'];

				// The current entry isn't needed anymore, so remove it from the array.
				unset($damCategories[$key]);
			}
		}

		// Search for sub-categories recursivliy
		foreach($rememberUids as $nextLevelUid) {
			$subCategories = self::sortCategories($damCategories,$nextLevelUid);
			if(count($subCategories) > 0) {
				foreach($subCategories as $newCategory) {
					$sortedDamCategories[] = $newCategory;
				}
			}
		}

		return $sortedDamCategories;
	}
}