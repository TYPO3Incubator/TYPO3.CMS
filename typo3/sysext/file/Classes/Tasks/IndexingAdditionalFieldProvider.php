<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010 - 2011 Michael Miousse (michael.miousse@infoglobe.ca)
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
/**
 * This class provides Scheduler Additional Field plugin implementation
 *
 * @author Lorenz Ulrich <lorenz.ulrich@visol.ch>
 * @package TYPO3
 * @subpackage file
 */
class Tx_File_Tasks_IndexingAdditionalFieldProvider implements tx_scheduler_AdditionalFieldProvider {

	/**
	 * Render additional information fields within the scheduler backend.
	 *
	 * @param array $taskInfo Array information of task to return
	 * @param task $task Task object
	 * @param tx_scheduler_Module $schedulerModule Reference to the calling object (BE module of the Scheduler)
	 * @return array Additional fields
	 * @see interfaces/tx_scheduler_AdditionalFieldProvider#getAdditionalFields($taskInfo, $task, $schedulerModule)
	 */
	public function getAdditionalFields(array &$taskInfo, $task, tx_scheduler_Module $schedulerModule) {

		$additionalFields = array();
		if (empty($taskInfo['indexingConfiguration'])) {
			if ($schedulerModule->CMD == 'add') {
				$taskInfo['indexingConfiguration'] = '';
			} elseif ($schedulerModule->CMD == 'edit') {
				$taskInfo['indexingConfiguration'] = $task->getIndexingConfiguration();
			} else {
				$taskInfo['indexingConfiguration'] = $task->getIndexingConfiguration();
			}
		}

		if (empty($taskInfo['paths'])) {
			if ($schedulerModule->CMD == 'add') {
				$taskInfo['paths'] = array();
			} elseif ($schedulerModule->CMD == 'edit') {
				$taskInfo['paths'] = $task->getPaths();
			} else {
				$taskInfo['paths'] = $task->getPaths();
			}
		}

		$fieldID = 'task_indexingConfiguration';
		$fieldCode = '<input type="text"  name="tx_scheduler[file][indexingConfiguration]" id="' . $fieldID . '" value="' . htmlspecialchars($taskInfo['indexingConfiguration']) . '" />';
		$label = $GLOBALS['LANG']->sL('LLL:EXT:file/Resources/Private/Language/locallang_file.xlf:tasks.validate.indexingConfiguration');
		$label = t3lib_BEfunc::wrapInHelp('file', $fieldID, $label);
		$additionalFields[$fieldID] = array(
			'code' => $fieldCode,
			'label' => $label
		);

		$indexingConfigurationRepository = t3lib_div::makeInstance('Tx_File_Domain_Repository_IndexingRepository');
		$indexingConfigurations = $indexingConfigurationRepository->findAll();
		$fieldID = 'task_indexingConfiguration';
		$fieldCode = '<select name="tx_scheduler[file][indexingConfiguration]" id="' . $fieldID . '" />';
		foreach ($indexingConfigurations as $indexingConfiguration) {
			$fieldCode .= '<option value="' . $indexingConfiguration['uid'] . '" ' . $this->getSelectedState($taskInfo['indexingConfiguration'], $indexingConfiguration['uid']) . '>' . $indexingConfiguration['name'] . '</option>';
		}
		$fieldCode .= '</select>';
		$label = $GLOBALS['LANG']->sL('LLL:EXT:file/Resources/Private/Language/locallang_file.xlf:tasks.validate.indexingConfiguration');
		$label = t3lib_BEfunc::wrapInHelp('file', $fieldID, $label);
		$additionalFields[$fieldID] = array(
			'code' => $fieldCode,
			'label' => $label
		);

		$fieldID = 'task_paths';
		$fieldCode = '<textarea  name="tx_scheduler[file][paths]" id="' . $fieldID . '" >' . htmlspecialchars($taskInfo['paths']) . '</textarea>';
		$label = $GLOBALS['LANG']->sL('LLL:EXT:file/Resources/Private/Language/locallang_file.xlf:tasks.validate.paths');
		$label = t3lib_BEfunc::wrapInHelp('file', $fieldID, $label);
		$additionalFields[$fieldID] = array(
			'code' => $fieldCode,
			'label' => $label
		);

		return $additionalFields;

	}


	/**
	 * Mark current value as selected by returning the "selected" attribute
	 *
	 * @param array $configurationArray Array of configuration
	 * @param string $currentValue Value of selector object
	 * @return string Html fragment for a selected option or empty
	 */
	protected function getSelectedState($savedValue, $currentElement) {
		$selected = '';
		if (strcmp($savedValue, $currentElement) === 0) {
			$selected = 'selected="selected" ';
		}
		return $selected;
	}


	/**
	 * This method checks any additional data that is relevant to the specific task.
	 * If the task class is not relevant, the method is expected to return TRUE.
	 *
	 * @param array $submittedData Reference to the array containing the data submitted by the user
	 * @param tx_scheduler_Module $schedulerModule Reference to the calling object (BE module of the Scheduler)
	 * @return boolean TRUE if validation was ok (or selected class is not relevant), FALSE otherwise
	 */
	public function validateAdditionalFields(array &$submittedData, tx_scheduler_Module $schedulerModule) {
		$isValid = TRUE;

		if (empty($submittedData['file']['indexingConfiguration'])) {
			$isValid = FALSE;
			$schedulerModule->addMessage(
				$GLOBALS['LANG']->sL('LLL:EXT:file/Resources/Private/Language/locallang_file.xlf:tasks.validate.invalidIndexingConfiguration'),
				t3lib_FlashMessage::ERROR
			);
		}

		if (empty($submittedData['file']['paths'])) {
			$isValid = FALSE;
			$schedulerModule->addMessage(
				$GLOBALS['LANG']->sL('LLL:EXT:file/Resources/Private/Language/locallang_file.xlf:tasks.validate.invalidPaths'),
				t3lib_FlashMessage::ERROR
			);
		}

		return $isValid;
	}


	/**
	 * This method is used to save any additional input into the current task object
	 * if the task class matches.
	 *
	 * @param array $submittedData Array containing the data submitted by the user
	 * @param tx_scheduler_Task $task Reference to the current task object
	 * @return void
	 */
	public function saveAdditionalFields(array $submittedData, tx_scheduler_Task $task) {
		$task->setIndexingConfiguration($submittedData['file']['indexingConfiguration']);
		$task->setPaths($submittedData['file']['paths']);
	}

}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/file/Classes/Tasks/IndexingAdditionalFieldprovider.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/file/Classes/Tasks/IndexingAdditionalFieldprovider.php']);
}

?>