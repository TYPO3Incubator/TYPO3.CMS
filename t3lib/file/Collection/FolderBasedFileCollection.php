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
 * A collection containing a static set of files. This collection is persisted to the database with references to all
 * files it contains.
 *
 * @author Steffen Ritter
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_Collection_FolderBasedFileCollection extends t3lib_file_Collection_AbstractFileCollection {

	/**
	 * the table name collections are stored to
	 *
	 * @var string
	 */
	protected static $storageTableName = 'sys_file_collection';

	/**
	 * the type of file collection. Either static, filter or folder.
	 *
	 * @var string
	 */
	protected static $type = 'folder';

	/**
	 * the items field name (ususally either criteria, items or folder)
	 *
	 * @var string
	 */
	protected static $itemsCriteriaField = 'folder';

	/**
	 * The folder
	 *
	 * @var t3lib_file_Folder
	 */
	protected $folder;

	public function loadContents() {
		$entries = $this->folder->getFiles();
		foreach ($entries as $entry) {
			$this->add($entry);
		}
	}

	public function getItemsCriteria() {
		return $this->folder->getCombinedIdentifier();
	}


	// same as method as in in AbstractFileCollection, just with the fields folder and storage additionally
	protected function getPersistableDataArray() {
		return array(
			'title' => $this->getTitle(),
			'type'	=> self::$type,
			'description' => $this->getDescription(),
			'folder' => $this->folder->getIdentifier(),
			'storage' => $this->folder->getStorage()->getUid(),
		);
	}

	// same as method as in in AbstractFileCollection, just with functionality to initialize the folder
	public function fromArray(array $record) {
		$this->uid			= $record['uid'];
		$this->title		= $record['title'];
		$this->description	= $record['description'];
		if($record['folder'] && $record['storage']) {
			/**  @var $storageRepository t3lib_file_Repository_StorageRepository */
			$storageRepository = t3lib_div::makeInstance('t3lib_file_Repository_StorageRepository');
			/**  @var $storage t3lib_file_Storage */
			$storage = $storageRepository->findByUid($record['storage']);
			if($storage) {
				$this->folder = $storage->getFolder($record['folder']);
			}
		}
	}


}