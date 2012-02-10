<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2011 Ingmar Schlecht <ingmar.schlecht@typo3.org>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Collection for filtered files.
 *
 * @author Ingmar Schlecht <ingmar.schlecht@typo3.org>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_Collection_FilteredFileCollection extends t3lib_file_Collection_AbstractFileCollection {
	/**
	 * @var string
	 */
	protected static $type = 'filter';

	/**
	 * @var string
	 */
	protected static $itemsCriteriaField = 'criteria';

	/**
	 * Loads the contents (= collected elements) of this colletction.
	 */
	public function loadContents() {
		/** @var t3lib_collection_FilteredRecords_Service $filterService */
		$filterService = t3lib_div::makeInstance('t3lib_collection_FilteredRecords_Service');
		$filterService->initializeQuery($this->itemsCriteria);

		$validFiles = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'sys_files',
			' 1=1 ' . $filterService->generateWhereClause()
		);

		$this->removeAll();

		/** @var t3lib_file_Factory $fileFactory */
		$fileFactory = t3lib_div::makeInstance('t3lib_file_Factory');

		foreach ($validFiles as $entry) {
			$this->add($fileFactory->createFileObject($entry));
		}
	}

	/**
	 * Shorthand function for parent::setCriteria().
	 *
	 * @param string $criteria
	 * @todo Check whether we can remove this
	 */
	public function setCriteria($criteria) {
		parent::setItemsCriteria($criteria);
	}

	/**
	 * Shorthand function for parent::getItemsCriteria().
	 *
	 * @return string Criteria
	 * @todo Check whether we can remove this
	 */
	public function getCriteria() {
		return parent::getItemsCriteria();
	}

}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/file/Collection/FilteredFileCollection.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/file/Collection/FilteredFileCollection.php']);
}

?>