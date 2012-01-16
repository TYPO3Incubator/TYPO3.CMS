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
 * Repository for accessing files
 * it also serves as the public API for the indexing part of files in general
 *
 * @author  Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @author  Ingmar Schlecht <ingmar@typo3.org>
 * @package	TYPO3
 * @subpackage	t3lib
 */
class t3lib_file_Repository_FileRepository extends t3lib_file_Repository_AbstractRepository {

	/**
	 * The main object type of this class. In some cases (fileReference) this repository can also return
	 * FileReference objects, implementing the common FileInterface.
	 *
	 * @var string
	 */
	protected $objectType = 't3lib_file_File';

	/**
     * Main File object storage table. Note that this repository also works on the sys_file_reference table
     * when returning FileReference objects.
     *
	 * @var string
	 */
	protected $table = 'sys_file';
	
	
	/**
	 * @var t3lib_file_Service_IndexerService
	 */
	protected $indexerService = NULL;

	
	/**
	 * internal function to retrieve the indexer service,
	 * if it does not exist, an instance will be created
	 *
	 * @return t3lib_file_Service_IndexerService
	 */
	protected function getIndexerService() {
		if ($this->indexerService === NULL) {
			$this->indexerService = t3lib_div::makeInstance('t3lib_file_Service_IndexerService');
		}

		return $this->indexerService;
	}

	/**
	 * Creates an object managed by this repository.
	 *
	 * @param array $databaseRow
	 * @return t3lib_file_File
	 */
	protected function createDomainObject(array $databaseRow) {
		return $this->factory->getFileObject($databaseRow['uid'], $databaseRow);
	}

	/**
	 * Index a file object given as parameter
	 * @TODO: Check if the indexing functions really belong into the repository and shouldn't be part of an
	 * @TODO: indexing service, right now it's fine that way as this function will serve as the public API
	 *
	 * @param t3lib_file_File $fileObject
	 * @return array The indexed file data
	 */
	public function addToIndex(t3lib_file_File $fileObject) {
		return $this->getIndexerService()->indexFile($fileObject, FALSE);
	}

	/**
	 * Checks the index status of a file and returns FALSE if the file is not indexed, the uid otherwise.
	 * @TODO: Check if the indexing functions really belong into the repository and shouldn't be part of an
	 * @TODO: indexing service, right now it's fine that way as this function will serve as the public API
	 *
	 * @param t3lib_file_File $fileObject
	 * @return bool|int
	 */
	public function getFileIndexStatus(t3lib_file_File $fileObject) {
		$mount = $fileObject->getStorage()->getUid();
		$identifier = $fileObject->getIdentifier();

		$row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('uid,storage,identifier', $this->table,
			sprintf('storage=%u AND identifier=%s', $mount, $GLOBALS['TYPO3_DB']->fullQuoteStr($identifier, $this->table))
		);

		if (!$row) {
			return FALSE;
		} else {
			return $row['uid'];
		}
	}

	/**
	 * Returns an index record of a file, or FALSE if the file is not indexed.
	 *
	 * @param t3lib_file_File $fileObject
	 * @return bool|array
	 */
	public function getFileIndexRecord(t3lib_file_File $fileObject) {
		$mount = $fileObject->getStorage()->getUid();
		$identifier = $fileObject->getIdentifier();

		$row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', $this->table,
			sprintf('storage=%u AND identifier=%s', $mount, $GLOBALS['TYPO3_DB']->fullQuoteStr($identifier, $this->table))
		);

		if (!$row) {
			return FALSE;
		} else {
			return $row;
		}
	}

	/**
	 * Returns all files with the corresponding SHA-1 hash. This is queried against the database, so only indexed files
	 * will be found
	 *
	 * @param string $hash A SHA1 hash of a file
	 * @return array
	 */
	public function findBySha1Hash($hash) {
		if (preg_match('/[^a-f0-9]*/i', $hash)) {
			// TODO does not validate -> throw exception; also check for hash length
		}

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->table,
			'sha1=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($hash, $this->table));

		$objects = array();
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$objects[] = $this->createDomainObject($row);
		}

		return $objects;
	}


	/**
	 * Find FileReference objects by relation to other records
	 *
	 * @param int $tableName Table name of the related record
	 * @param int $fieldName Field name of the related record
	 * @param int $uid The UID of the related record
	 * @return array An array of objects, empty if no objects found
	 * @api
	 */
	public function findByRelation($tableName, $fieldName, $uid) {
		/** @var $TYPO3_DB t3lib_DB */
		global $TYPO3_DB;
		$itemList = array();

		if (!is_numeric($uid)) {
			throw new InvalidArgumentException("uid of related record has to be numeric.", 1316789798);
		}

		$res = $TYPO3_DB->exec_SELECTquery(
			'*',
			'sys_file_reference',
			'tablenames = '.$TYPO3_DB->fullQuoteStr($tableName, 'sys_file_reference').
				' AND deleted=0'.
				' AND hidden=0'.
				' AND uid_foreign='.intval($uid).
				' AND fieldname='.$TYPO3_DB->fullQuoteStr($fieldName, 'sys_file_reference'),
			'',
			'sorting_foreign'
		);

		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$itemList[] = $this->createFileReferenceObject($row);
		}

		return $itemList;
	}

	/**
	 * Find FileReference objects by uid
	 *
	 * @param int $uid The UID of the sys_file_reference record
	 * @return t3lib_file_FileReference
	 * @api
	 */
	public function findFileReferenceByUid($uid) {
		/** @var $TYPO3_DB t3lib_DB */
		global $TYPO3_DB;
		$itemList = array();

		if (!is_numeric($uid)) {
			throw new InvalidArgumentException("uid of record has to be numeric.", 1316889798);
		}

		$res = $TYPO3_DB->exec_SELECTquery(
			'*',
			'sys_file_reference',
			'uid = '.$uid.
				' AND deleted=0'.
				' AND hidden=0'
		);

		if($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$fileReferenceObject = $this->createFileReferenceObject($row);
			return $fileReferenceObject;
		} else {
			return false;
		}
	}

	/**
	 * Updates an existing file object in the database
	 *
	 * @param t3lib_file_File $modifiedObject
	 * @return void
	 */
	public function update($modifiedObject) {
		// TODO check if $modifiedObject is an instance of t3lib_file_File
		// TODO check if $modifiedObject is indexed

		$changedProperties = $modifiedObject->getUpdatedProperties();
		$properties = $modifiedObject->getProperties();

		$updateFields = array();
		foreach ($changedProperties as $propertyName) {
			$updateFields[$propertyName] = $properties[$propertyName];
		}

		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_file',
			'uid=' . $modifiedObject->getUid(),
			$updateFields
		);
	}


	/**
	 * Creates a FileReference object
	 *
	 * @param array $databaseRow
	 * @return t3lib_file_FileReference
	 */
	protected function createFileReferenceObject(array $databaseRow) {
		return $this->factory->getFileReferenceObject($databaseRow['uid'], $databaseRow);
	}

}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/file/Repository/FileRepository.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/file/Repository/FileRepository.php']);
}

?>