<?php
namespace TYPO3\CMS\DamFalmigration\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2013 Alexander Boehm <boehm@punkt.de>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;

/**
 * Additional BE fields for Migration Dam Categories task.
 * Adds field to enter the new starting parent uid for the categories.
 *
 * @author Alexander Boehm <boehm@punkt.de>
 */
class MigrationDamCategoriesAdditionalFieldProvider implements AdditionalFieldProviderInterface {

	/**
	 * @param array $taskInfo Reference to the array containing the info used in the add/edit form
	 * @param object $task When editing, reference to the current task object. Null when adding.
	 * @param \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject Reference to the calling object (Scheduler's BE module)
	 * @return array Array containing all the information pertaining to the additional fields
	 */
	public function getAdditionalFields(array &$taskInfo, $task, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject) {
		$this->setValueForTaskInfo($taskInfo, $parentObject, 'scheduler_migrationDamCategories_initialParentUid', 0, $task->initialParentUid);
		$this->setValueForTaskInfo($taskInfo, $parentObject, 'scheduler_migrationDamCategories_storeOnPid', 0, $task->storeOnPid);

		$additionalFields = array();
		$id = 'scheduler_migrationDamCategories_initialParentUid';
		$additionalFields[$id] = array('code' => $this->createInputField($taskInfo, $id),	'label' => 'Initial parent UID');
		$id = 'scheduler_migrationDamCategories_storeOnPid';
		$additionalFields[$id] = array('code' => $this->createInputField($taskInfo, $id),	'label' => 'Store on PID');

		return $additionalFields;
	}

	/**
	 * set the value for given field in task
	 *
	 * @param array $taskInfo
	 * @param \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject
	 * @param string $id
	 * @param string $default
	 * @param string $editValue
	 * @return void
	 */
	protected function setValueForTaskInfo(array &$taskInfo, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject, $id, $default, $editValue) {
		if (!isset($taskInfo[$id])) {
			$taskInfo[$id] = $default;
			if ($parentObject->CMD === 'edit') {
				$taskInfo[$id] = $editValue;
			}
		}
	}

	/**
	 * create a textfield
	 *
	 * @param array $taskInfo
	 * @param string $id
	 * @return string
	 */
	protected function createInputField(array &$taskInfo, $id) {
		$attributes = array(
			'type' => 'text',
			'name' => 'tx_scheduler[' . $id . ']',
			'id' => $id,
			'value' => htmlspecialchars($taskInfo[$id])
		);

		$htmlAttribute = array();
		foreach ($attributes as $attribute => $value) {
			$htmlAttribute[] = $attribute . '="' . $value . '"';
		}

		return '<input ' . implode(' ',  $htmlAttribute) . ' />';
	}

	/**
	 * Checks if the given value is an integer
	 *
	 * @param array $submittedData Reference to the array containing the data submitted by the user
	 * @param \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject Reference to the calling object (Scheduler's BE module)
	 * @return boolean TRUE if validation was ok (or selected class is not relevant), FALSE otherwise
	 */
	public function validateAdditionalFields(array &$submittedData, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject) {
		$result = TRUE;

		if (!is_numeric($submittedData['scheduler_migrationDamCategories_initialParentUid']) || intval($submittedData['scheduler_migrationDamCategories_initialParentUid']) < 0) {
			$result = FALSE;
			$parentObject->addMessage($GLOBALS['LANG']->sL('LLL:EXT:scheduler/mod1/locallang.xlf:msg.invalidNumberOfDays'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
		}

		if (!is_numeric($submittedData['scheduler_migrationDamCategories_storeOnPid']) || intval($submittedData['scheduler_migrationDamCategories_storeOnPid']) < 0) {
			$result = FALSE;
			$parentObject->addMessage($GLOBALS['LANG']->sL('LLL:EXT:scheduler/mod1/locallang.xlf:msg.invalidNumberOfDays'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
		}

		return $result;
	}

	/**
	 * Saves given integer value in task object
	 *
	 * @param array $submittedData Contains data submitted by the user
	 * @param \TYPO3\CMS\Scheduler\Task\AbstractTask $task Reference to the current task object
	 * @return void
	 */
	public function saveAdditionalFields(array $submittedData, \TYPO3\CMS\Scheduler\Task\AbstractTask $task) {
		$task->initialParentUid = intval($submittedData['scheduler_migrationDamCategories_initialParentUid']);
		$task->storeOnPid = intval($submittedData['scheduler_migrationDamCategories_storeOnPid']);
	}

}