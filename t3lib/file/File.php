<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Ingo Renner <ingo@typo3.org>
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
 * File representation in the file abstraction layer.
 *
 * @author  Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package  TYPO3
 * @subpackage  t3lib
 */
class t3lib_file_File implements t3lib_file_FileInterface {

	/**
	 * Various file properties
	 *
	 * Note that all properties, which only the persisted (indexed) files have are stored in this
	 * overall properties array only. The only properties which really exist as object properties of
	 * the file object are the storage, the identifier, the fileName and the indexing status.
	 *
	 * @var array
	 */
	protected $properties;

	/**
	 * The storage this file is located in
	 *
	 * @var t3lib_file_Storage
	 */
	protected $storage;

	/**
	 * The identifier of this file to identify it on the storage.
	 * On some drivers, this is the path to the file, but drivers could also just
	 * provide any other unique identifier for this file on the specific storage.
	 *
	 * @var string
	 */
	protected $identifier;

	/**
	 * The file name of this file
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * File indexing status. True, if the file is indexed in the database; NULL is the default value, this means that the index status is unknown
	 *
	 * @var bool
	 */
	protected $indexed = NULL;

	/**
	 * Set to TRUE while this file is being indexed - used to prevent some endless loops
	 *
	 * @var bool
	 */
	protected $indexingInProgress = FALSE;

	/**
	 * Contains the names of all properties that have been update since the instantiation of this object
	 *
	 * @var array
	 */
	protected $updatedProperties = array();

	/**
	 * Contains a list of properties that are available for files. May be set from the outside by using setAvailableProperties()
	 *
	 * @var array
	 */
	protected static $availableProperties = array('uid', 'storage', 'identifier', 'name', 'sha1', 'size');


	/*********************************************
	 * GENERIC FILE TYPES
	 * these are generic filetypes or -groups,
	 * don't mix it up with mime types
	 *********************************************/

	/**
	 * any other file
	 */
	const FILETYPE_UNKNOWN = 0;

	/**
	 * Any kind of text
	 */
	const FILETYPE_TEXT = 1;

	/**
	 * Any kind of image
	 */
	const FILETYPE_IMAGE = 2;

	/**
	 * Any kind of audio file
	 */
	const FILETYPE_AUDIO = 3;

	/**
	 * Any kind of video
	 */
	const FILETYPE_VIDEO = 4;

	/**
	 * Any kind of software, often known as "application"
	 */
	const FILETYPE_SOFTWARE = 5;

	/*********************************************
	 * FILE PROCESSING CONTEXTS
	 *********************************************/

	/**
	 * basic processing context to get a processed
	 * image with smaller width/height
	 */
	const PROCESSINGCONTEXT_IMAGEPREVIEW = 'image.preview';

	/**
	 * Constructor for a file object. Should normally not be used directly, use the corresponding factory methods instead.
	 *
	 * @param array $fileData
	 * @param t3lib_file_Storage $storage
	 */
	public function __construct(array $fileData, $storage = NULL) {
		if (isset($fileData['uid']) && intval($fileData['uid']) > 0) {
			$this->indexed = TRUE;
		}

		$this->identifier = $fileData['identifier'];
		$this->name = $fileData['name'];

		$this->properties = $fileData;
		if (is_object($storage)) {
			$this->storage = $storage;
		} elseif (isset($fileData['storage']) && is_object($fileData['storage'])) {
			$this->storage = $fileData['storage'];
		}
	}


	/*******************************
	 * VARIOUS FILE PROPERTY GETTERS
	 *******************************/

	/**
	 * Checks if this file exists. This should normally always return TRUE; it might only return FALSE when
	 * this object has been created from an index record without checking for.
	 *
	 * @return bool TRUE if this file physically exists
	 */
	public function exists() {
		return $this->storage->hasFile($this->getIdentifier());
	}

	/**
	 * Returns true if the given key exists for this file.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function hasProperty($key) {
		return in_array($key, self::$availableProperties);
	}

	public function getProperty($key) {
		if (!$this->hasProperty($key)) {
			throw new InvalidArgumentException('Property "'.$key.'" was not found.', 1314226805);
		}
		if ($this->indexed === NULL) {
			$this->loadIndexRecord();
		}

		return $this->properties[$key];
	}

	/**
	 * Returns the properties of this object.
	 *
	 * @return array
	 */
	public function getProperties() {
		if ($this->indexed === NULL) {
			$this->loadIndexRecord();
		}
		return $this->properties;
	}

	/**
	 * Returns the name of this file
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Returns the size of this file
	 *
	 * @return int
	 */
	public function getSize() {
		return $this->properties['size'];
	}

	/**
	 * Returns the uid of this file
	 *
	 * @return int
	 */
	public function getUid() {
		return $this->getProperty('uid');
	}

	/**
	 * Returns the Sha1 of this file
	 *
	 * @return string
	 */
	public function getSha1() {
		return $this->getStorage()->hashFile($this, 'sha1');
	}


	/**
	 * Get the extension of this file
	 *
	 * @return string The file extension
	 */
	public function getExtension() {
		return pathinfo($this->getName(), PATHINFO_EXTENSION);
	}

	/**
	 * Get the MIME type of this file
	 *
	 * @return array file information
	 */
	public function getMimeType() {
		$stat = $this->getStorage()->getFileInfo($this);
		return $stat['mimetype'];
	}

	/**
	 * Returns the fileType of this file
	 * basically there are only five main "file types"
	 * "audio"
	 * "image"
	 * "software"
	 * "text"
	 * "video"
	 * "other"
	 * see the constants in this class
	 *
	 * @return int $fileType
	 */
	public function getType() {
			// this basically extracts the mimetype and guess the filetype based on the first part of the mimetype
			// works for 99% of all cases, and we don't need to make an SQL statement like EXT:media does currently
		if (!$this->properties['type']) {
			$mimeType = $this->getMimeType();
			list($fileType) = explode('/', $mimeType);

			switch (strtolower($fileType)) {
				case 'text':
					$this->properties['type'] = self::FILETYPE_TEXT;
				break;
				case 'image':
					$this->properties['type'] = self::FILETYPE_IMAGE;
				break;
				case 'audio':
					$this->properties['type'] = self::FILETYPE_AUDIO;
				break;
				case 'video':
					$this->properties['type'] = self::FILETYPE_VIDEO;
				break;
				case 'application':
				case 'software':
					$this->properties['type'] = self::FILETYPE_SOFTWARE;
				break;
				default:
					$this->properties['type'] = self::FILETYPE_UNKNOWN;
				break;
			}
		}

		return $this->properties['type'];

			// @todo: this functionality belongs to the Media part, move it there, by overriding the string
		if (!$this->properties['type']) {
			$row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('file_type', 'static_file_mimetypes', 'mime_type = "' . $this->getMimeType() . '"');
			$this->properties['type'] = $row['file_type'];
		}

		return $this->properties['type'];
	}




	/******************
	 * CONTENTS RELATED
	 ******************/

	/**
	 * Get the contents of this file
	 *
	 * @return string File contents
	 */
	public function getContents() {
		return $this->getStorage()->getFileContents($this);
	}

	/**
	 * Replace the current file contents with the given string
	 *
	 * @param string $contents The contents to write to the file.
	 * @return t3lib_file_File The file object (allows chaining).
	 */
	public function setContents($contents) {
		$this->getStorage()->setFileContents($this, $contents);
		return $this;
	}







	/***********************
	 * INDEX RELATED METHODS
	 ***********************/

	/**
	 * Returns TRUE if this file is indexed
	 *
	 * @return bool
	 */
	public function isIndexed() {
		if ($this->indexed === NULL && !$this->indexingInProgress) {
			$this->loadIndexRecord();
		}

		return $this->indexed;
	}

	/**
	 * @param bool $indexIfNotIndexed
	 * @return void
	 */
	protected function loadIndexRecord($indexIfNotIndexed = TRUE) {
		if ($this->indexed !== NULL) {
			return;
		}

		/** @var $repo t3lib_file_Repository_FileRepository */
		$repo = t3lib_div::makeInstance('t3lib_file_Repository_FileRepository');
		$indexRecord = $repo->getFileIndexRecord($this);
		if ($indexRecord === FALSE && $indexIfNotIndexed) {
			$this->indexingInProgress = TRUE;
			$indexRecord = $repo->addToIndex($this);
			$this->mergeIndexRecord($indexRecord);
			$this->indexed = TRUE;
			$this->indexingInProgress = FALSE;
		} elseif ($indexRecord !== FALSE) {
			$this->mergeIndexRecord($indexRecord);
			$this->indexed = TRUE;
		} else {
			throw new RuntimeException('Could not load index record for ' . $this->getIdentifier(), 1321288316);
		}
	}

	/**
	 * Merges the contents of this file's index record into the file properties.
	 *
	 * @param array $recordData The index record as fetched from the database
	 * @return void
	 */
	protected function mergeIndexRecord(array $recordData) {
		if ($this->properties['uid'] != 0) {
			throw new InvalidArgumentException("uid property is already set. Cannot merge index record.", 1321023156);
		}

		$this->properties = array_merge($this->properties, $recordData);
		// TODO check for any properties that come from the driver and would change -- these might have to be updated in the index record
	}

	/**
	 * Updates the properties of this file, e.g. after re-indexing or moving it.
	 * By default, only properties that exist as a key in the $properties array are overwritten. If you want to
	 * explicitly unset a property, set the corresponding key to NULL in the array.
	 *
	 * NOTE: This method should not be called from outside the File Abstraction Layer (FAL)!
	 *
	 * @param array $properties
	 * @return void
	 * @internal
	 */
	public function updateProperties(array $properties) {
			// setting identifier and name to update values; we have to do this here because we might need a new identifier
			// when loading (and thus possibly indexing) a file.
		if (isset($properties['identifier'])) {
			$this->identifier = $properties['identifier'];
		}
		if (isset($properties['name'])) {
			$this->name = $properties['name'];
		}

		if ($this->indexed === NULL && !isset($properties['uid'])) {
			$this->loadIndexRecord();
		}

		if ($this->properties['uid'] != 0 && isset($properties['uid'])) {
			unset($properties['uid']);
		}

		foreach ($properties as $key => $value) {
			if ($this->properties[$key] !== $value) {
				if (!in_array($key, $this->updatedProperties)) {
					$this->updatedProperties[] = $key;
				}
				// TODO check if we should completely remove properties that are set to NULL
				$this->properties[$key] = $value;
			}
		}

			// updating indexing status
		if (isset($properties['uid']) && intval($properties['uid']) > 0) {
			$this->indexed = TRUE;
		}

		if (isset($properties['storage'])) {
			$this->loadStorage();
		}

		// TODO notify Factory if identifier or storage changed
		// TODO find some more clever notifications we could use here
	}

	/**
	 * Returns the names of all properties that have been updated in this record
	 *
	 * @return array
	 */
	public function getUpdatedProperties() {
		return $this->updatedProperties;
	}

	/**
	 * Sets the property names that are available in this class
	 *
	 * @static
	 * @param array $properties
	 * @return void
	 */
	public static function setAvailableProperties(array $properties) {
		self::$availableProperties = $properties;
	}

	public static function getAvailableProperties() {
		return self::$availableProperties;
	}






	/****************************************
	 * STORAGE AND MANAGEMENT RELATED METHODS
	 ****************************************/

	/**
	 * Get the storage this file is located in
	 *
	 * @return t3lib_file_Storage
	 */
	public function getStorage() {
		if (!$this->storage) {
			$this->loadStorage();
		}

		return $this->storage;
	}

	protected function loadStorage() {
		/** @var $storageRepository t3lib_file_Repository_StorageRepository */
		$storageRepository = t3lib_div::makeInstance('t3lib_file_Repository_StorageRepository');
		$this->storage = $storageRepository->findByUid($this->getProperty('storage'));
	}

	/**
	 * Sets the storage this file is located in. This is only meant for t3lib/file/-internal usage; don't use it to move files.
	 *
	 * @param integer|t3lib_file_Storage $storage
	 * @return t3lib_file_File
	 */
	public function setStorage($storage) {
		if (is_object($storage) && $storage instanceof t3lib_file_Storage) {
			$this->storage = $storage;
			$this->properties['storage'] = $storage->getUid();
		} else {
			$this->properties['storage'] = $storage;
			$this->storage = NULL;
		}
		return $this;
	}

	/**
	 * Returns the identifier of this file
	 *
	 * @return string
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * Set the identifier of this file
	 *
	 * @internal Should only be used by other parts of the File API (e.g. drivers after moving a file)
	 * @return string
	 */
	public function setIdentifier() {
		return $this->identifier;
	}


	/**
	 * Returns a combined identifier of this file, i.e. the storage UID and the folder identifier
	 * separated by a colon ":".
	 *
	 * @return string Combined storage and file identifier, e.g. StorageUID:path/and/fileName.png
	 */
	public function getCombinedIdentifier() {
		if(is_array($this->properties) && t3lib_utility_Math::canBeInterpretedAsInteger($this->properties['storage'])) {
			$combinedIdentifier = $this->properties['storage'].':'.$this->getIdentifier();
		} else {
			$combinedIdentifier = $this->getStorage()->getUid().':'.$this->getIdentifier();
		}

		return $combinedIdentifier;
	}


	/**
	 * Check if a file operation (= action) is allowed for this file
	 *
	 * @param	string	$action, can be read, write, delete
	 * @return boolean
	 */
	public function checkActionPermission($action) {
		return $this->getStorage()->checkFileActionPermission($action, $this);
	}

	/**
	 * Deletes this file from its storage. This also means that this object becomes useless.
	 *
	 * @return bool TRUE if deletion succeeded
	 * TODO mark file internally as deleted, throw exceptions on all method calls afterwards
	 * TODO undelete mechanism?
	 */
	public function delete() {
		return $this->storage->deleteFile($this);
	}

	/**
	 * Renames this file.
	 *
	 * @param $newName The new file name
	 * @return t3lib_file_File
	 */
	public function rename($newName) {
		return $this->storage->renameFile($this, $newName);
	}


	/**
	 * Copies this file into a target folder
	 *
	 * @param t3lib_file_Folder $targetFolder Folder to copy file into.
	 * @param string $targetFileName an optional destination fileName
	 * @param string $conflictMode overrideExistingFile", "renameNewFile", "cancel"
	 *
	 * @return t3lib_file_File The new (copied) file.
	 */
	public function copyTo(t3lib_file_Folder $targetFolder, $targetFileName = NULL, $conflictMode = 'renameNewFile') {
		return $targetFolder->getStorage()->copyFile($this, $targetFolder, $targetFileName, $conflictMode);
	}

	/**
	 * Moves the file into the target folder
	 *
	 * @param t3lib_file_Folder $targetFolder Folder to move file into.
	 * @param string $targetFileName an optional destination fileName
	 * @param string $conflictMode overrideExistingFile", "renameNewFile", "cancel"
	 *
	 * @return t3lib_file_File This file object, with updated properties.
	 */
	public function moveTo(t3lib_file_Folder $targetFolder, $targetFileName = NULL, $conflictMode = 'renameNewFile') {
		return $targetFolder->getStorage()->moveFile($this, $targetFolder, $targetFileName, $conflictMode);
	}





	/*****************
	 * SPECIAL METHODS
	 *****************/

	/**
	 * creates a MD5 hash checksum based on the combined identifier of the file,
	 * the files' mimetype and the systems' encryption key.
	 * used to generate a thumbnail, and this hash is checked if valid
	 *
	 * @static
	 * @param t3lib_file_File $file the file to create the checksum from
	 * @return string the MD5 hash
	 */
	public function calculateChecksum() {
		return md5($this->getCombinedIdentifier() . '|' . $this->getMimeType() . '|' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
	}

	/**
	 * Returns a publicly accessible URL for this file
	 *
	 * WARNING: Access to the file may be restricted by further means, e.g. some web-based authentication. You have to take care of this
	 * yourself.
	 *
	 * @return string
	 */
	public function getPublicUrl() {
		return $this->getStorage()->getPublicUrlForFile($this);
	}

	/**
	 * Returns a modified version of the file.
	 *
	 * @param string $context the context of the configuration (see above)
	 * @param array $configuration the processing configuration, see manual for that
	 * @return string the URL ready to output
	 */
	public function getProcessedUrl($context, $configuration) {
		return $this->getStorage()->getProcessedUrlForFile($this, $context, $configuration);
	}

	/**
	 * Returns a path to a local version of this file to process it locally (e.g. with some system tool).
	 * If the file is normally located on a remote storages, this creates a local copy.
	 * If the file is already on the local system, this only makes a new copy if $writable is set to TRUE.
	 *
	 * @param bool $writable Set this to FALSE if you only want to do read operations on the file.
	 * @return string
	 */
	public function getForLocalProcessing($writable = TRUE) {
		return $this->getStorage()->getFileForLocalProcessing($this, $writable);
	}

	/**
	 * Returns an array representation of the file.
	 * (This is used by the generic listing module vidi when displaying file records.)
	 *
	 * @return array Array of main data of the file. Don't rely on all data to be present here, it's just a selection of the most relevant information.
	 */
	public function toArray() {
		$array = array(
			'id'		=> $this->getCombinedIdentifier(),
			'name'		=> $this->getName(),
			'extension'	=> $this->getExtension(),
			'type'		=> $this->getType(),
			'mimetype'	=> $this->getMimeType(),
			'size'		=> $this->getSize(),
			'url'		=> $this->getPublicUrl(),
			'indexed'	=> $this->indexed,
			'uid'		=> $this->getUid(),
			'permissions'=> array(
				'read'	=> $this->checkActionPermission('read'),
				'write' => $this->checkActionPermission('write'),
				'delete'=> $this->checkActionPermission('delete'),
			),
			'checksum' => $this->calculateChecksum()
		);
		foreach($this->properties AS $key => $value) {
			$array[$key] = $value;
		}
		$stat = $this->storage->getFileInfo($this);
		foreach ($stat AS $key => $value) {
			$array[$key] = $value;
		}
		return $array;
	}
}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/class.t3lib_file_file.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/class.t3lib_file_file.php']);
}

?>