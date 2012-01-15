<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Andreas Wolf <andreas.wolf@ikt-werk.de>
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
 * Repository for accessing the collections stored in the database
 *
 * @author  Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @author  Ingmar Schlecht <ingmar@typo3.org>
 * @package	TYPO3
 * @subpackage	t3lib
 */
class t3lib_file_Repository_FileCollectionRepository extends t3lib_collection_RecordCollectionRepository {

	/**
	 * @var string
	 */
	protected $table = 'sys_file_collection';

	/**
	 * @var string
	 */
	protected $typeField = 'type';



	/**
	 * Returns all objects of this repository.
	 *
	 * ### Method copied from t3lib/file/Repository/AbstractRepository.php ###
	 *
	 * @return array An array of objects, empty if no objects found
	 * @api
	 */
	public function findAll() {
		$itemList = array();

		$whereClause = 'deleted = 0';
		if ($this->type !== '') {
			$whereClause .= ' AND ' . $this->typeField . ' = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->type, $this->table);
		}

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->table, $whereClause);

		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$itemList[] = $this->createDomainObject($row);
		}

		return $itemList;
	}


	/**
	 * Returns all objects of a particular type in this repository.
	 *
	 * @param	$type	The type string to search for.
	 *
	 * @return array An array of objects, empty if no objects found.
	 * @api
	 */
	public function findByType($type) {
		$itemList = array();

		$whereClause = 'deleted = 0';
		$whereClause .= ' AND ' . $this->typeField . ' = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($type, $this->table);

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->table, $whereClause);

		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$itemList[] = $this->createDomainObject($row);
		}

		return $itemList;
	}


	/**
	 * Finds an object matching the given identifier.
	 *
	 * ### Method copied from t3lib/file/Repository/AbstractRepository.php ###
	 *
	 * @param int $uid The identifier of the object to find
	 * @return object The matching object if found, otherwise NULL
	 * @api
	 */
	public function findByUid($uid) {
		/** @var $TYPO3_DB t3lib_DB */
		global $TYPO3_DB;

		if (!is_numeric($uid)) {
			throw new InvalidArgumentException("uid has to be numeric.", 1316779798);
		}

		$row = $TYPO3_DB->exec_SELECTgetSingleRow('*', $this->table, 'uid=' . intval($uid) . ' AND deleted=0');

		if (count($row) == 0) {
			throw new RuntimeException("Could not find row with uid $uid in table $this->table.", 1314354065);
		}

		return $this->createDomainObject($row);
	}

	/**
	 * @param $record
	 * @return t3lib_file_Collection_AbstractFileCollection
	 */
	protected function createDomainObject($record) {
		/**
		 * @var $factory t3lib_file_Factory
		 */
		$factory = t3lib_div::makeInstance('t3lib_file_Factory');

		return $factory->createCollectionObject($record);
	}

}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/class.t3lib_file_repository.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/class.t3lib_file_repository.php']);
}

?>