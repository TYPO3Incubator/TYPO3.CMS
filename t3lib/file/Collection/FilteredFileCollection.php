<?php

class t3lib_file_Collection_FilteredFileCollection extends t3lib_file_Collection_AbstractFileCollection {

	/**
	 * the type of file collection. Either static, filter or folder.
	 *
	 * @var string
	 */
	protected static $type = 'filter';

	/**
	 * the items field name (ususally either criteria, items or folder)
	 *
	 * @var string
	 */
	protected static $itemsCriteriaField = 'criteria';


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
	 * Shorthand function for parent::setCriteria(), might be needed in case vidi uses getCriteria and setCriteria to set and get filter criteria.
	 * Would be be better if we could remove it.
	 *
	 * @param string $criteria
	 */
	public function setCriteria($criteria) {
		parent::setItemsCriteria($criteria);
	}

	/**
	 * Shorthand function for parent::getItemsCriteria(), might be needed in case vidi uses getCriteria and setCriteria to set and get filter criteria.
	 * Would be be better if we could remove it.
	 *
	 * @return string Criteria
	 */
	public function getCriteria() {
		return parent::getItemsCriteria();
	}

}
