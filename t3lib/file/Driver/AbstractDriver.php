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
 * An abstract implementation of a storage driver.
 *
 * @author  Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package	TYPO3
 * @subpackage	t3lib
 */
abstract class t3lib_file_Driver_AbstractDriver {

	/**
	 * The mount object this driver instance belongs to
	 *
	 * @var t3lib_file_Storage
	 */
	protected $storage;

	/**
	 * A list of all supported hash algorithms, written all lower case and without any dashes etc. (e.g. sha1 instead of SHA-1)
	 *
	 * Be sure to set this in inherited classes!
	 *
	 * @var array
	 */
	protected $supportedHashAlgorithms = array();

	/**
	 * The storage folder that forms the root of this FS tree
	 *
	 * @var t3lib_file_Folder
	 */
	protected $rootLevelFolder;

	/**
	 * The default folder new files should be put into.
	 *
	 * @var t3lib_file_Folder
	 */
	protected $defaultLevelFolder;

	/**
	 * The resource publisher this driver should use for making its files publicly available.
	 *
	 * @var t3lib_file_Service_Publishing_Publisher
	 */
	protected $resourcePublisher;

	/**
	 * The configuration of this driver
	 *
	 * @var array
	 */
	protected $configuration = array();


	public function __construct(array $configuration = array()) {
		$this->configuration = $configuration;
		$this->processConfiguration();
	}


	/**
	 * Initializes this object. This is called by the storage after the driver has been attached.
	 *
	 * @return void
	 */
	abstract public function initialize();


	/**
	 * sets the storage object that works with this driver
	 *
	 * @param t3lib_file_Storage $storage
	 * @return t3lib_file_Driver_AbstractDriver
	 */
	public function setStorage(t3lib_file_Storage $storage) {
		$this->storage = $storage;
		return $this;
	}

	/**
	 * Sets the resource publisher instance for this driver.
	 *
	 * @param t3lib_file_Service_Publishing_Publisher $resourcePublisher
	 * @return t3lib_file_Driver_AbstractDriver
	 */
	public function setResourcePublisher(t3lib_file_Service_Publishing_Publisher $resourcePublisher) {
		$this->resourcePublisher = $resourcePublisher;
		return $this;
	}

	/**
	 * Checks if a configuration is valid for this driver.
	 *
	 * Throws an exception if a configuration will not work.
	 *
	 * @abstract
	 * @param array $configuration
	 * @return void
	 */
	abstract public static function verifyConfiguration(array $configuration);

	/**
	 * processes the configuration, should be overridden by subclasses
	 * but we do this because PHPUnit cannot work if this is an abstract configuration
	 *
	 * @return void
	 */
	protected function processConfiguration() {
		throw new RuntimeException('I should be overridden in subclasses');
	}

	/*******************
	 * CAPABILITIES
	 *******************/

	/**
	 * The capabilities of this driver. See CAPABILITY_* constants for possible values
	 *
	 * @var integer
	 */
	protected $capabilities = 0;

	/**
	 * Capability for being browsable by (backend) users
	 */
	const CAPABILITY_BROWSABLE = 1;
	/**
	 * Capability for publicly accessible drivers (= accessible from the web)
	 */
	const CAPABILITY_PUBLIC = 2;
	/**
	 * Capability for writable drivers
	 */
	const CAPABILITY_WRITABLE = 4;

	/**
	 * Returns the capabilities of this driver.
	 *
	 * @return int
	 * @see CAPABILITY_* constants
	 */
	public function getCapabilities() {
		return $this->capabilities;
	}

	/**
	 * Returns TRUE if this driver has the given capability.
	 *
	 * @param int $capability A capability, as defined in a CAPABILITY_* constant
	 * @return bool
	 */
	public function hasCapability($capability) {
		return $this->capabilities && $capability;
	}

	/*******************
	 * FILE FUNCTIONS
	 *******************/

	/**
	 * Returns a temporary path for a given file, including the file extension.
	 *
	 * @param t3lib_file_FileInterface $file
	 * @return string
	 */
	protected function getTemporaryPathForFile(t3lib_file_FileInterface $file) {
		return t3lib_div::tempnam('fal-tempfile-') . '.' . $file->getExtension();
		// @todo: we need to remove the temporary file again
	}

	/**
	 * Returns the public URL to a file.
	 *
	 * @abstract
	 * @param t3lib_file_FileInterface $file
	 * @return string
	 */
	abstract public function getPublicUrl(t3lib_file_FileInterface $file);

	/**
	 * Returns a list of all hashing algorithms this Storage supports.
	 *
	 * @return array
	 */
	public function getSupportedHashAlgorithms() {
		return $this->supportedHashAlgorithms;
	}

	/**
	 * Creates a (cryptographic) hash for a file.
	 *
	 * @abstract
	 * @param t3lib_file_FileInterface $file
	 * @param string $hashAlgorithm The hash algorithm to use
	 * @return string
	 * TODO switch parameter order?
	 */
	abstract public function hash(t3lib_file_FileInterface $file, $hashAlgorithm);

	/**
	 * Creates a new file and returns the matching file object for it.
	 *
	 * @abstract
	 * @param string $fileName
	 * @param t3lib_file_Folder $parentFolder
	 * @return t3lib_file_File
	 */
	abstract public function createFile($fileName, t3lib_file_Folder $parentFolder);

	/**
	 * Returns the contents of a file. Beware that this requires to load the complete file into memory and also may
	 * require fetching the file from an external location. So this might be an expensive operation (both in terms of
	 * processing resources and money) for large files.
	 *
	 * @param t3lib_file_FileInterface $file
	 * @return string The file contents
	 */
	abstract public function getFileContents(t3lib_file_FileInterface $file);

	/**
	 * Sets the contents of a file to the specified value.
	 *
	 * @param t3lib_file_FileInterface $file
	 * @param string $contents
	 * @return bool TRUE if setting the contents succeeded
	 * @throws RuntimeException if the operation failed
	 */
	abstract public function setFileContents(t3lib_file_FileInterface $file, $contents);

	/**
	 * Adds a file from the local server hard disk to a given path in TYPO3s virtual file system.
	 *
	 * This assumes that the local file exists, so no further check is done here!
	 *
	 * @param string $localFilePath
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName The name to add the file under
	 * @return t3lib_file_FileInterface
	 */
	abstract public function addFile($localFilePath, t3lib_file_Folder $targetFolder, $fileName);

	/**
	 * Checks if a file exists.
	 *
	 * @abstract
	 * @param string $identifier
	 * @return bool
	 */
	abstract public function fileExists($identifier);

	/**
	 * Checks if a file inside a storage folder exists.
	 *
	 * @abstract
	 * @param string $fileName
	 * @param t3lib_file_Folder $folder
	 * @return bool
	 */
	abstract public function fileExistsInFolder($fileName, t3lib_file_Folder $folder);

	/**
	 * Returns a (local copy of) a file for processing it. When changing the file, you have to take care of replacing the
	 * current version yourself!
	 *
	 * @abstract
	 * @param t3lib_file_FileInterface $file
	 * @param bool $writable Set this to FALSE if you only need the file for read operations. This might speed up things, e.g. by using a cached local version. Never modify the file if you have set this flag!
	 * @return string The path to the file on the local disk
	 */
	// TODO decide if this should return a file handle object
	abstract public function getFileForLocalProcessing(t3lib_file_FileInterface $file, $writable = TRUE);

	/**
	 * Returns the permissions of a file as an array (keys r, w) of boolean flags
	 *
	 * @abstract
	 * @param t3lib_file_FileInterface $file
	 * @return array
	 */
	abstract public function getFilePermissions(t3lib_file_FileInterface $file);

	/**
	 * Returns the permissions of a folder as an array (keys r, w) of boolean flags
	 *
	 * @abstract
	 * @param t3lib_file_Folder $folder
	 * @return array
	 */
	abstract public function getFolderPermissions(t3lib_file_Folder $folder);

	/**
	 * Renames a file
	 *
	 * @abstract
	 * @param t3lib_file_FileInterface $file
	 * @param string $newName
	 * @return string The new identifier of the file if the operation succeeds
	 * @throws RuntimeException if renaming the file failed
	 */
	abstract public function renameFile(t3lib_file_FileInterface $file, $newName);

	/**
	 * Replaces the contents (and file-specific metadata) of a file object with a local file.
	 *
	 * @abstract
	 * @param t3lib_file_FileInterface $file
	 * @param string $localFilePath
	 * @return bool
	 */
	abstract public function replaceFile(t3lib_file_FileInterface $file, $localFilePath);

	/**
	 * Returns information about a file for a given file identifier.
	 *
	 * @param string $identifier The (relative) path to the file.
	 * @return array
	 */
	abstract public function getFileInfoByIdentifier($identifier);

	/**
	 * Returns information about a file for a given file object.
	 *
	 * @param t3lib_file_FileInterface $file
	 * @return array
	 */
	public function getFileInfo(t3lib_file_FileInterface $file) {
		return $this->getFileInfoByIdentifier($file->getIdentifier());
	}

	/**
	 * Returns a file object by its identifier.
	 *
	 * @param $identifier
	 * @return t3lib_file_FileInterface
	 */
	public function getFile($identifier) {
		$fileObject = NULL;

		// TODO should we throw an exception if the file does not exist?
		if ($this->fileExists($identifier)) {
			$fileInfo = $this->getFileInfoByIdentifier($identifier);
			$fileObject = $this->getFileObject($fileInfo);
		}

		return $fileObject;
	}

	/**
	 * Creates a file object from a given file data array
	 *
	 * @param array $fileData
	 * @return t3lib_file_FileInterface
	 */
	protected function getFileObject(array $fileData) {
		/** @var $factory t3lib_file_Factory */
		$factory = t3lib_div::makeInstance('t3lib_file_Factory');
		$fileObject = $factory->createFileObject($fileData);

		return $fileObject;
	}

	/**
	 * Returns a folder by its identifier.
	 *
	 * @param $identifier
	 * @return t3lib_file_Folder
	 */
	public function getFolder($identifier) {
		if (!$this->folderExists($identifier)) {
			// @todo possible return NULL instead of triggering an exception
			throw new RuntimeException("Folder $identifier does not exist.", 1320575630);
		}
		/** @var $factory t3lib_file_Factory */
		$factory = t3lib_div::makeInstance('t3lib_file_Factory');
		return $factory->createFolderObject($this->storage, $identifier, '');
	}

	/**
	 * Returns a list of files inside the specified path
	 *
	 * @param string $path
	 * @param string $pattern
	 * @param integer $start The position to start the listing; if not set, start from the beginning
	 * @param integer $numberOfItems The number of items to list; if not set, return all items
	 * @return array
	 */
	abstract public function getFileList($path, $pattern = '', $start = 0, $numberOfItems = 0);

	/**
	 * Copies a file to a temporary path and returns that path.
	 *
	 * @abstract
	 * @param t3lib_file_FileInterface $file
	 * @return string The temporary path
	 */
	abstract public function copyFileToTemporaryPath(t3lib_file_FileInterface $file);

	/**
	 * Moves a file *within* the current storage.
	 * Note that this is only about an intra-storage move action, where a file is just
	 * moved to another folder in the same storage.
	 *
	 * @param t3lib_file_FileInterface $file
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName
	 * @return string The new identifier of the file
	 */
	abstract public function moveFileWithinStorage(t3lib_file_FileInterface $file, t3lib_file_Folder $targetFolder, $fileName);

	/**
	 * Copies a file *within* the current storage.
	 * Note that this is only about an intra-storage copy action, where a file is just
	 * copied to another folder in the same storage.
	 *
	 * @param t3lib_file_FileInterface $file
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName
	 * @return t3lib_file_FileInterface The new (copied) file object.
	 */
	abstract public function copyFileWithinStorage(t3lib_file_FileInterface $file, t3lib_file_Folder $targetFolder, $fileName);


	/**
	 * Folder equivalent to moveFileWithinStorage().
	 *
	 * @param t3lib_file_Folder $folderToMove
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $newFolderName
	 * @return bool
	 */
	abstract public function moveFolderWithinStorage(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetFolder, $newFolderName = NULL);

	/**
	 * Folder equivalent to copyFileWithinStorage().
	 *
	 * @param t3lib_file_Folder $folderToMove
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $newFileName
	 * @return bool
	 */
	abstract public function copyFolderWithinStorage(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetFolder, $newFileName = NULL);


	/**
	 * Removes a file from this storage. This does not check if the file is still used or if it is a bad idea to delete
	 * it for some other reason - this has to be taken care of in the upper layers (e.g. the Storage)!
	 *
	 * @abstract
	 * @param t3lib_file_FileInterface $file
	 * @return bool TRUE if deleting the file succeeded
	 */
	abstract public function deleteFile(t3lib_file_FileInterface $file);

	/**
	 * Removes a folder from this storage.
	 *
	 * @param t3lib_file_Folder $folder
	 * @param bool $deleteRecursively
	 * @return boolean
	 */
	abstract public function deleteFolder(t3lib_file_Folder $folder, $deleteRecursively = FALSE);

	/**
	 * Adds a file at the specified location. This should only be used internally.
	 *
	 * @abstract
	 * @param string $localFilePath
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $targetFileName
	 * @return string The new identifier of the file
	 */
	// TODO check if this is still necessary if we move more logic to the storage
	abstract public function addFileRaw($localFilePath, t3lib_file_Folder $targetFolder, $targetFileName);

	/**
	 * Deletes a file without access and usage checks. This should only be used internally.
	 *
	 * This accepts an identifier instead of an object because we might want to delete files that have no object
	 * associated with (or we don't want to create an object for) them - e.g. when moving a file to another storage.
	 *
	 * @abstract
	 * @param string $identifier
	 * @return bool TRUE if removing the file succeeded
	 */
	abstract public function deleteFileRaw($identifier);


	/*******************
	 * FOLDER FUNCTIONS
	 *******************/

	/**
	 * Returns the root level folder of the storage.
	 *
	 * @abstract
	 * @return t3lib_file_Folder
	 */
	abstract public function getRootLevelFolder();

	/**
	 * Returns the default folder new files should be put into.
	 *
	 * @abstract
	 * @return t3lib_file_Folder
	 */
	abstract public function getDefaultFolder();

	/**
	 * Creates a folder.
	 *
	 * @param string $newFolderName
	 * @param t3lib_file_Folder $parentFolder
	 * @return t3lib_file_Folder The new (created) folder object
	 */
	abstract public function createFolder($newFolderName, t3lib_file_Folder $parentFolder);

	/**
	 * Returns a list of all folders in a given path
	 *
	 * @param string $path
	 * @param string $pattern
	 * @param integer $start The position to start the listing; if not set, start from the beginning
	 * @param integer $numberOfItems The number of items to list; if not set, return all items
	 * @return array
	 */
	abstract public function getFolderList($path, $pattern = '', $start = 0, $numberOfItems = 0);

	/**
	 * Checks if a folder exists
	 *
	 * @abstract
	 * @param $identifier
	 * @return bool
	 */
	abstract public function folderExists($identifier);

	/**
	 * Checks if a file inside a storage folder exists.
	 *
	 * @abstract
	 * @param string $folderName
	 * @param t3lib_file_Folder $folder
	 * @return bool
	 */
	abstract public function folderExistsInFolder($folderName, t3lib_file_Folder $folder);

	/**
	 * Renames a folder in this storage.
	 *
	 * @param t3lib_file_Folder $folder
	 * @param string $newName The target path (including the file name!)
	 * @return string The new identifier of the folder if the operation succeeds
	 * @throws RuntimeException if renaming the folder failed
	 */
	abstract public function renameFolder(t3lib_file_Folder $folder, $newName);

	/**
	 * Checks if a given identifier is within a container, e.g. if a file or folder is within another folder.
	 * This can be used to check for webmounts.
	 *
	 * @abstract
	 * @param t3lib_file_Folder $container
	 * @param string $content
	 * @return bool TRUE if $content is within $container
	 */
	// TODO extend this to also support objects as $content
	abstract public function isWithin(t3lib_file_Folder $container, $content);

	/**
	 * Checks if a folder contains files and (if supported) other folders.
	 *
	 * @param t3lib_file_Folder $folder
	 * @return bool TRUE if there are no files and folders within $folder
	 */
	abstract public function isFolderEmpty(t3lib_file_Folder $folder);
}
?>