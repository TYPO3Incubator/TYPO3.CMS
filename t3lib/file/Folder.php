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
 * A folder that groups files in a storage. This may be a folder on the local disk, a bucket in Amazon S3
 * or a user or a tag in Flickr.
 *
 * This object is not persisted in TYPO3 locally, but created on the fly by storage drivers for the folders they "offer".
 *
 * Some folders serve as a physical container for files (e.g. folders on the local disk, S3 buckets or Flickr users).
 * Other folders just group files by a certain criterion, e.g. a tag.
 * The way this is implemented depends on the storage driver.
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @author Ingmar Schlecht <ingmar@typo3.org>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_Folder {

	/**
	 * The storage this folder belongs to.
	 *
	 * @var t3lib_file_Storage
	 */
	protected $storage;

	/**
	 * The identifier of this folder to identify it on the storage.
	 * On some drivers, this is the path to the folder, but drivers could also just
	 * provide any other unique identifier for this folder on the specific storage.
	 *
	 * @var string
	 */
	protected $identifier;

	/**
	 * The name of this folder
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * initialization of the folder
	 *
	 * @param t3lib_file_Storage $storage
	 * @param $identifier
	 * @param $name
	 */
	public function __construct(t3lib_file_Storage $storage, $identifier, $name) {
		$this->storage = $storage;
		$this->identifier = rtrim($identifier, '/') . '/';
		$this->name = $name;
	}

	/**
	 * Returns the name of this folder.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * sets a new name of the folder
	 * currently this does not trigger the "renaming process"
	 * as the name is more seen as a label
	 *
	 * @param $name the new name
	 * @return void
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Returns the storage this folder belongs to.
	 *
	 * @return t3lib_file_Storage
	 */
	public function getStorage() {
		return $this->storage;
	}

	/**
	 * Returns the path of this folder inside the storage. It depends on the type of storage whether this is a real
	 * path or just some unique identifier.
	 *
	 * @return string
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * Returns a combined identifier of this folder, i.e. the storage UID and the folder identifier
	 * separated by a colon ":".
	 *
	 * @return string Combined storage and folder identifier, e.g. StorageUID:folder/path/
	 */
	public function getCombinedIdentifier() {
		// @todo $this->properties is never defined nor used here

		if (is_array($this->properties) && t3lib_utility_Math::canBeInterpretedAsInteger($this->properties['storage'])) {
			$combinedIdentifier = $this->properties['storage'].':'.$this->getIdentifier();
		} else {
			$combinedIdentifier = $this->getStorage()->getUid().':'.$this->getIdentifier();
		}

		return $combinedIdentifier;
	}

	/**
	 * Returns a list of files in this folder, optionally filtered by the given pattern
	 * for performance reasons only a subset of files may be queried
	 * 
	 * @param string $pattern
	 * @param int $start
	 * @param int $limit
	 * @return t3lib_file_File[]
	 */
	public function getFiles($pattern = '', $start = 0, $limit = -1) {
		/** @var $factory t3lib_file_Factory */
		$factory = t3lib_div::makeInstance('t3lib_file_Factory');
		$fileArray = array_values($this->storage->getFileList($this->identifier, $pattern));
		$fileObjects = array();
		if ($limit == -1) {
			$limit = count($fileArray) - $start;
		} else {
			$limit = $start + $limit;
		}
		for ($i = $start; $i < $limit && $i < count($fileArray); $i++) {
			$fileObjects[] = $factory->createFileObject($fileArray[$i]);
		}
		return $fileObjects;
	}

	/**
	 * Returns amount of all files within this folder, optionally filtered by the given pattern
	 * @param string $pattern
	 * @return int
	 */
	public function getFileCount($pattern = '') {
		return count($this->storage->getFileList($this->identifier, $pattern));
	}

	/**
	 * Returns the object for a subfolder of the current folder, if it exists.
	 *
	 * @param  $name
	 * @return t3lib_file_Folder
	 */
	public function getSubfolder($name) {

	}

	/**
	 * Returns a list of all subfolders
	 *
	 * @return t3lib_file_Folder[]
	 */
	public function getSubfolders() {
		$folderObjects = array();

		$folderArray = $this->storage->getFolderList($this->identifier);

		if (count($folderArray) > 0) {
			/** @var $factory t3lib_file_Factory */
			$factory = t3lib_div::makeInstance('t3lib_file_Factory');

			foreach ($folderArray as $folder) {
				$folderObjects[] = $factory->createFolderObject($this->storage, $this->identifier . $folder['name'] . '/', $folder['name']);
			}
		}

		return $folderObjects;
	}

	/**
	 * Adds a file from the local server disk. If the file already exists and overwriting is disabled,
	 *
	 * @param string $localFilePath
	 * @param string $fileName
	 * @param string $conflictMode possible value are 'cancel', 'replace'
	 * @return t3lib_file_File The file object
	 */
	public function addFile($localFilePath, $fileName = NULL, $conflictMode = 'cancel') {
		$fileName = $fileName ? $fileName : basename($localFilePath);
		return $this->storage->addFile($localFilePath, $this, $fileName, $conflictMode);
	}

	/**
	 * Adds an uploaded file into the Storage.
	 *
	 * @param array $uploadedFileData contains information about the uploaded file given by $_FILES['file1']
	 * @param string $conflictMode possible value are 'cancel', 'replace'
	 * @return t3lib_file_File The file object
	 */
	public function addUploadedFile(array $uploadedFileData, $conflictMode = 'cancel') {
		return $this->storage->addUploadedFile($uploadedFileData, $this, $uploadedFileData['name'], $conflictMode);
	}

	/**
	 * Renames this folder.
	 *
	 * @param string $newName
	 * @return t3lib_file_Folder
	 */
	public function rename($newName) {
		return $this->storage->renameFolder($this, $newName);
	}

	/**
	 * Deletes this folder from its storage. This also means that this object becomes useless.
	 *
	 * @return bool TRUE if deletion succeeded
	 * @param	bool $deleteRecursively
	 *
	 * TODO mark folder internally as deleted, throw exceptions on all method calls afterwards
	 * TODO undelete mechanism? From Reycler Folder?
	 */
	public function delete($deleteRecursively = TRUE) {
		return $this->storage->deleteFolder($this, $deleteRecursively);
	}


	/**
	 * Creates a new blank file
	 *
	 * @param string $fileName
	 * @return t3lib_file_File The new file object
	 */
	public function createFile($fileName) {
		return $this->storage->createFile($fileName, $this);
	}

	/**
	 * Creates a new folder
	 *
	 * @param string $folderName
	 * @return t3lib_file_Folder The new folder object
	 */
	public function createFolder($folderName) {
		return $this->storage->createFolder($folderName, $this);
	}

	/**
	 * Copies folder to a target folder
	 *
	 * @param t3lib_file_Folder $targetFolder Target folder to copy to.
	 * @param string $targetFolderName an optional destination fileName
	 * @param string $conflictMode "overrideExistingFile", "renameNewFile" or "cancel"
	 *
	 * @return t3lib_file_Folder New (copied) folder object.
	 */
	public function copyTo(t3lib_file_Folder $targetFolder, $targetFolderName = NULL, $conflictMode = 'renameNewFile') {
		return $this->storage->copyFolder($this, $targetFolder, $targetFolderName, $conflictMode);
	}

	/**
	 * Movies folder to a target folder
	 *
	 * @param t3lib_file_Folder $targetFolder Target folder to move to.
	 * @param string $targetFolderName an optional destination fileName
	 * @param string $conflictMode "overrideExistingFile", "renameNewFile" or "cancel"
	 *
	 * @return t3lib_file_Folder New (copied) folder object.
	 */
	public function moveTo(t3lib_file_Folder $targetFolder, $targetFolderName = NULL, $conflictMode = 'renameNewFile') {
		return $this->storage->moveFolder($this, $targetFolder, $targetFolderName, $conflictMode);
	}

	/**
	 * Checks if a file exists in this folder
	 *
	 * @param $fileName
	 * @return bool
	 */
	public function hasFile($fileName) {
		// TODO check if this also works for non-hierarchical storages
		return $this->storage->hasFile($this->identifier . $fileName);
	}

	/**
	 * Checks if a folder exists in this folder.
	 *
	 * @param $name
	 * @return bool
	 */
	public function hasFolder($name) {
		// TODO check if this also works for non-hierarchical storages
		return $this->storage->hasFolder($this->identifier . $name);
	}

	/**
	 * Check if a file operation (= action) is allowed on this folder
	 *
	 * @param	string	$action, can be read, write, delete
	 * @return boolean
	 */
	public function checkActionPermission($action) {
		return $this->getStorage()->checkFolderActionPermission($action, $this);
	}

	/**
	 * Updates the properties of this folder, e.g. after re-indexing or moving it.
	 *
	 * NOTE: This method should not be called from outside the File Abstraction Layer (FAL)!
	 *
	 * @param array $properties
	 * @return void
	 * @internal
	 */
	public function updateProperties(array $properties) {
			// setting identifier and name to update values
		if (isset($properties['identifier'])) {
			$this->identifier = $properties['identifier'];
		}
		if (isset($properties['name'])) {
			$this->name = $properties['name'];
		}
	}
}
