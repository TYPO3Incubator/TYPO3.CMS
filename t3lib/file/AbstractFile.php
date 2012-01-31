<?php
/***************************************************************
 *  Copyright notice
 *
 *  @author Ingmar Schlecht <ingmar@typo3.org>
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
 * Abstract file representation in the file abstraction layer.
 *
 * @author Ingmar Schlecht <ingmar@typo3.org>
 * @package  TYPO3
 * @subpackage  t3lib
 */
abstract class t3lib_file_AbstractFile implements t3lib_file_FileInterface {

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



	/*******************************
	 * VARIOUS FILE PROPERTY GETTERS
	 *******************************

	/**
	 * Returns true if the given property key exists for this file.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function hasProperty($key) {
		return isset($this->properties[$key]);
	}

	/**
	 * Returns a property value
	 *
	 * @param string $key
	 * @return mixed Property value
	 */
	public function getProperty($key) {
		return $this->properties[$key];
	}

	/**
	 * Returns the properties of this object.
	 *
	 * @return array
	 */
	public function getProperties() {
		return $this->properties;
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
	 * Returns the date (as UNIX timestamp) the file was last modified.
	 *
	 * @return integer
	 */
	public function getModificationTime() {
		return $this->getProperty('tstamp');
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





	/****************************************
	 * STORAGE AND MANAGEMENT RELATED METHDOS
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
		if($this->getProperty('storage')) {
			/** @var $storageRepository t3lib_file_Repository_StorageRepository */
			$storageRepository = t3lib_div::makeInstance('t3lib_file_Repository_StorageRepository');
			$this->storage = $storageRepository->findByUid($this->getProperty('storage'));
		}
	}

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
	 * Sets the storage this file is located in. This is only meant for t3lib/file/-internal usage; don't use it to move files.
	 *
	 * @internal Should only be used by other parts of the File API (e.g. drivers after moving a file)
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
	 * Set the identifier of this file
	 *
	 * @internal Should only be used by other parts of the File API (e.g. drivers after moving a file)
	 * @param string $identifier
	 * @return string
	 */
	public function setIdentifier($identifier) {
		$this->identifier = $identifier;
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
	 * Deletes this file from its storage. This also means that this object becomes useless.
	 *
	 * @return bool TRUE if deletion succeeded
	 * TODO mark file internally as deleted, throw exceptions on all method calls afterwards
	 * TODO undelete mechanism?
	 */
	public function delete() {
		return $this->getStorage()->deleteFile($this);
	}

	/**
	 * Renames this file.
	 *
	 * @param $newName The new file name
	 * @return t3lib_file_File
	 */
	public function rename($newName) {
		return $this->getStorage()->renameFile($this, $newName);
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
	 * basic array function for the DB update
	 * @return array
	 */
	public function toArray() {
		// @todo: define what we need here
		return array(
			'storage' => $this->getStorage()->getUid(),
			'identifier' => $this->getIdentifier(),
			'name' => $this->getName(),
			'is_processed' => intval($this->isProcessed),
			'checksum' => $this->calculateChecksum(),
			'context' => $this->context,
			'configuration' => serialize($this->processingConfiguration),
			'original' => $this->originalFile->getUid(),
		);
	}

}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/class.t3lib_file_file.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/class.t3lib_file_file.php']);
}

?>