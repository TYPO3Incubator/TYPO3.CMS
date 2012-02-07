<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2012 Steffen Ritter <typo3@steffen-ritter.net>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Implements a record collection that is filtered by several criteria.
 *
 * @author Steffen Ritter <typo3@steffen-ritter.net>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_collection_FilteredRecordCollection extends t3lib_collection_AbstractRecordCollection {

	/**
	 * serialized filter criteria
	 *
	 * @var string
	 */
	protected $criteria;

	/**
	 * Initializes the object by using an array.
	 *
	 * @param array $array
	 */
	public function fromArray(array $array) {
		parent::fromArray($array);
		$this->setCriteria($array['criteria']);
	}

	/**
	 * Gets the array representation of this object.
	 *
	 * @return array
	 */
	public function toArray() {
		$array = parent::toArray();
		$array['criteria'] = $this->getCriteria();
		return $array;
	}

	/**
	 * Gets the data array to be persisted.
	 *
	 * @return array
	 */
	protected function getPersistableDataArray() {
		return array(
			'title' => $this->getTitle(),
			'type'	=> 'filter',
			'table_name' => $this->getItemTableName(),
			'description' => $this->getDescription(),
			'criteria' => $this->getCriteria()
		);
	}

	/**
	 * Loads the contents.
	 *
	 * @todo Implement loadContents() method.
	 */
	public function loadContents() {

	}

	/**
	 * Sets the filter criteria.
	 *
	 * @param string $criteria
	 */
	public function setCriteria($criteria) {
		$this->criteria = $criteria;
	}

	/**
	 * Gets the filter criteria.
	 *
	 * @return string
	 */
	public function getCriteria() {
		return $this->criteria;
	}
}
?>