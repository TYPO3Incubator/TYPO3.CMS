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
 * A "mount point" inside the TYPO3 file handling.
 *
 * A "storage" object handles
 *  - abstraction to the driver
 *  - permissions (from the driver, and from the user, + capabilities)
 *  - an entry point for files, folders, and for most other operations
 *
 * == Driver entry point
 * The driver itself, that does the actual work on the file system,
 * is inside the storage but completely shadowed by
 * the storage, as the storage also handles the abstraction to the
 * driver
 *
 * The storage can be on the local system, but can also be on a remote
 * system. The combination of driver + configurable capabilities (storage
 * is read-only e.g.) allows for flexible uses.
 *
 *
 * == Permission system
 * As all requests have to run through the storage, the storage knows about the
 * permissions of a BE/FE user, the file permissions / limitations of the driver
 * and has some configurable capabilities.
 * Additionally, a BE user can use "filemounts" (known from previous installations)
 * to limit his/her work-zone to only a subset (identifier and its subfolders/subfolders)
 * of the user itself.
 *
 * Check 1: "User Permissions" [is the user allowed to write a file) [is the user allowed to write a file]
 * Check 2: "File Mounts" of the User (act as subsets / filters to the identifiers) [is the user allowed to do something in this folder?]
 * Check 3: "Capabilities" of Storage (then: of Driver) [is the storage/driver writable?]
 * Check 4: "File permissions" of the Driver [is the folder writable?]
 *
 *
 * @author  Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @author  Ingmar Schlecht <ingmar@typo3.org>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_Storage {

	const SIGNAL_PreProcessConfiguration = 'preProcessConfiguration';
	const SIGNAL_PostProcessConfiguration = 'postProcessConfiguration';
	const SIGNAL_PreFileCopy = 'preFileCopy';
	const SIGNAL_PostFileCopy = 'postFileCopy';
	const SIGNAL_PreFileMove = 'preFileMove';
	const SIGNAL_PostFileMove = 'postFileMove';

	/**
	 * The storage driver instance belonging to this storage.
	 *
	 * @var t3lib_file_Driver_AbstractDriver
	 */
	protected $driver;

	/**
	 * The database record for this storage
	 *
	 * @var array
	 */
	protected $storageRecord;

	/**
	 * The configuration belonging to this storage (decoded from the configuration field).
	 *
	 * @var array
	 */
	protected $configuration;

	/**
	 * The base URI to this storage.
	 *
	 * @var string
	 */
	protected $baseUri;

	/**
	 * @var t3lib_file_Service_Publishing_Publisher
	 */
	protected $publisher;

	/**
	 * User filemounts, added as an array, and used as filters
	 *
	 * @var array
	 */
	protected $fileMounts = array();

	/**
	 * The file permissions of the user (and their group) merged together and available as an array
	 *
	 * @var array
	 */
	protected $userPermissions = array();

	/**
	 * The capabilities of this storage as defined in the storage record. Also see the CAPABILITY_* constants below
	 *
	 * @var integer
	 */
	protected $capabilities;

	/**
	 * @var t3lib_SignalSlot_Dispatcher
	 */
	protected $signalSlotDispatcher;

	/**
	 * Capability for being browsable by (backend) users
	 */
	const CAPABILITY_BROWSABLE = 1;
	/**
	 * Capability for publicly accessible storages (= accessible from the web)
	 */
	const CAPABILITY_PUBLIC = 2;
	/**
	 * Capability for writable storages. This only signifies writability in general - this might also be further limited by configuration.
	 */
	const CAPABILITY_WRITABLE = 4;


	/**
	 * Constructor for a storage object.
	 *
	 * @param t3lib_file_Driver_AbstractDriver $driver
	 * @param array $storageRecord The storage record row from the database
	 */
	public function __construct(t3lib_file_Driver_AbstractDriver $driver, array $storageRecord) {
		$this->storageRecord = $storageRecord;
		$this->configuration = json_decode($storageRecord['configuration'], TRUE);
		$this->driver = $driver;
		$this->driver->setStorage($this);
		$this->driver->initialize();
		$this->capabilities = ($this->storageRecord['is_browsable'] && $this->driver->hasCapability(self::CAPABILITY_BROWSABLE) ? self::CAPABILITY_BROWSABLE : 0)
			+ ($this->storageRecord['is_public'] && $this->driver->hasCapability(self::CAPABILITY_PUBLIC) ? self::CAPABILITY_PUBLIC : 0)
			+ ($this->storageRecord['is_writable'] && $this->driver->hasCapability(self::CAPABILITY_WRITABLE) ? self::CAPABILITY_WRITABLE : 0);
		$this->processConfiguration();
	}

	public function setPublisher(t3lib_file_Service_Publishing_Publisher $publisher) {
		$this->publisher = $publisher;
		return $this;
	}

	public function getPublisher() {
		return $this->publisher;
	}

	/**
	 * Gets the configuration
	 *
	 * @return array
	 */
	public function getConfiguration() {
		return $this->configuration;
	}

	/**
	 * Sets the configuration.
	 *
	 * @param array $configuration
	 */
	public function setConfiguration(array $configuration) {
		$this->configuration = $configuration;
	}

	/**
	 * Gets the storage record.
	 *
	 * @return array
	 */
	public function getStorageRecord() {
		return $this->storageRecord;
	}


	/**
	 * Processes the configuration of this storage.
	 *
	 * @throws InvalidArgumentException If a required configuration option is not set or has an invalid value.
	 * @return void
	 */
	protected function processConfiguration() {
		$this->emitPreProcessConfigurationSignal();

		if (isset($this->configuration['baseUri'])) {
			$this->baseUri = rtrim($this->configuration['baseUri'], '/') . '/';
		}

		$this->emitPostProcessConfigurationSignal();
	}

	/**
	 * Returns the base URI of this storage; all files are reachable via URLs beginning with this string.
	 *
	 * @return string
	 */
	public function getBaseUri() {
		return $this->baseUri;
	}

	/**
	 * Sets the storage that belongs to this storage.
	 *
	 * @param t3lib_file_Driver_AbstractDriver $driver
	 * @return t3lib_file_Storage
	 */
	public function setDriver(t3lib_file_Driver_AbstractDriver $driver) {
		$this->driver = $driver;
		return $this;
	}

	/**
	 * Returns the driver object belonging to this storage.
	 *
	 * @return t3lib_file_Driver_AbstractDriver
	 */
	protected function getDriver() {
		return $this->driver;
	}

	/**
	 * Deprecated function, don't use it. Will be removed in some later revision.
	 *
	 * @param string $identifier
	 */
	public function getFolderByIdentifier($identifier) {
		throw new Exception('Function t3lib_file_Storage::getFolderByIdentifier() has been renamed to just getFolder(). Please fix the metho call.');
	}

	/**
	 * Deprecated function, don't use it. Will be removed in some later revision.
	 *
	 * @param string $identifier
	 */
	public function getFileByIdentifier($identifier) {
		throw new Exception('Function t3lib_file_Storage::getFileByIdentifier() has been renamed to just getFileInfoByIdentifier().  Please fix the metho call.');
	}

	/**
	 * Returns the name of this storage.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->storageRecord['name'];
	}

	/**
	 * Returns the uid of this storage.
	 *
	 * @return integer
	 */
	public function getUid() {
		return $this->storageRecord['uid'];
	}

	/**
	 * tells whether there are children in this storage
	 *
	 * @abstract
	 * @return boolean
	 */
	public function hasChildren() {
		return true;
	}


	/*********************************
	 * Capabilities
	 ********************************/

	/**
	 * Returns the capabilities of this storage.
	 *
	 * @return int
	 * @see CAPABILITY_* constants
	 */
	public function getCapabilities() {
		return $this->capabilities;
	}

	/**
	 * Returns TRUE if this storage has the given capability.
	 *
	 * @param int $capability A capability, as defined in a CAPABILITY_* constant
	 * @return bool
	 */
	protected function hasCapability($capability) {
		return $this->capabilities && $capability;
	}

	/**
	 * Returns TRUE if this storage is publicly available. This is just a configuration option and does not mean that it
	 * really *is* public. OTOH a storage that is marked as not publicly available will trigger the file publishing mechanisms
	 * of TYPO3.
	 *
	 * @return bool
	 */
	public function isPublic() {
		return $this->hasCapability(self::CAPABILITY_PUBLIC);
	}

	/**
	 * Returns TRUE if this storage is writable. This is determined by the driver and the storage configuration; user
	 * permissions are not taken into account.
	 *
	 * @return bool
	 */
	public function isWritable() {
		return $this->hasCapability(self::CAPABILITY_WRITABLE);
	}

	/**
	 * Returns TRUE if this storage is browsable by a (backend) user of TYPO3.
	 *
	 * @return bool
	 */
	public function isBrowsable() {
		return $this->hasCapability(self::CAPABILITY_BROWSABLE);
	}


	/*********************************
	 * User Permissions / File Mounts
	 ********************************/

	/**
	 * Adds a filemount as a "filter" for users to only work on a subset of a storage object
	 *
	 * @param string $folderIdentifier
	 * @param array $additionalData
	 * @return void
	 */
	public function injectFileMount($folderIdentifier, $additionalData = array()) {
		if (empty($additionalData)) {
			$additionalData = array(
				'path' => $folderIdentifier,
				'title' => $folderIdentifier,
				'folder' => $this->getFolder($folderIdentifier)
			);
		} else {
			$additionalData['folder'] = $this->getFolder($folderIdentifier);
			if (!isset($additionalData['title'])) {
				$additionalData['title'] = $folderIdentifier;
			}
		}
		$this->fileMounts[$folderIdentifier] = $additionalData;
	}

	/**
	 * Returns all file mounts that are registered with this storage.
	 *
	 * @return array
	 */
	public function getFileMounts() {
		return $this->fileMounts;
	}

	/**
	 * Checks if the given subject is within one of the registered user filemounts. If not, working with the file
	 * is not permitted for the user.
	 *
	 * @param $subject
	 * @return bool
	 */
	public function isWithinFileMountBoundaries($subject) {
		$isWithinFilemount = TRUE;
		if (is_array($this->fileMounts)) {
			$isWithinFilemount = FALSE;

			if (!$subject) {
				$subject = $this->getRootLevelFolder();
			}
			$identifier = $subject->getIdentifier();

				// check if the identifier of the subject is within at
				// least one of the file mounts
			foreach ($this->fileMounts as $fileMount) {
				if ($this->driver->isWithin($fileMount['folder'], $identifier)) {
					$isWithinFilemount = TRUE;
					break;
				}
			}
		}
		return $isWithinFilemount;
	}

	/**
	 * adds user permissions to the storage
	 *
	 * @param  array $userPermissions
	 * @return void
	 */
	public function injectUserPermissions(array $userPermissions) {
		$this->userPermissions = $userPermissions;
	}

	/**
	 * check if the ACL settings allow for a certain action
	 * (is a user allowed to read a file or copy a folder)
	 *
	 * @param	string	$action
	 * @param	string	$type	either File or Folder
	 * @return	bool
	 */
	public function checkUserActionPermission($action, $type) {
		// TODO decide if we should return TRUE if no permissions are set
		if (!empty($this->userPermissions)) {
			$action = strtolower($action);
			$type = ucfirst(strtolower($type));
			if ($this->userPermissions[$action . $type] == 0) {
				return FALSE;
			} else {
				return TRUE;
			}
		} else {
			return TRUE;
		}
	}

	/**
	 * Check if a file operation (= action) is allowed on a File/Folder/Storage (= subject).
	 *
	 * This method, by design, does not throw exceptions or do logging.
	 * Besides the usage from other methods in this class, it is also used by the File List UI to check whether
	 * an action is allowed and whether action related UI elements should thus be shown (move icon, edit icon, etc.)
	 *
	 * @param string $action, can be read, write, delete
	 * @param t3lib_file_File $file
	 * @return boolean
	 */
	public function checkFileActionPermission($action, t3lib_file_File $file) {
			// check 1: Does the user have permission to perform the action? e.g. "readFile"
		if ($this->checkUserActionPermission($action, 'File') === FALSE) {
			return FALSE;
		}

			// check 2: Does the user have the right to perform the action?
			// (= is he/she within the file mount borders)
		if (is_array($this->fileMounts) && count($this->fileMounts) && !$this->isWithinFileMountBoundaries($file)) {
			return FALSE;
		}


		$isReadCheck = FALSE;
		if (in_array($action, array('read'))) {
			$isReadCheck = TRUE;
		}

		$isWriteCheck = FALSE;
		if (in_array($action, array('write', 'delete'))) {
			$isWriteCheck = TRUE;
		}

			// check 3: Check the capabilities of the storage (and the driver)
		if ($isReadCheck && !$this->isBrowsable()) {
			return FALSE;
		}
		if ($isWriteCheck && !$this->isWritable()) {
			return FALSE;
		}

			// check 4: "File permissions" of the driver
		$filePermissions = $this->driver->getFilePermissions($file);
		if ($isReadCheck && !$filePermissions['r']) {
			return FALSE;
			# we can't thrown an exception here, as this function is just for asking whether the user has the permission. It's also used by the UI. Therefore, the following exception is commented out.
			#throw new t3lib_file_exception_InsufficientFileReadPermissionsException("TYPO3 has no permission to read file " . $file->getIdentifier());
		}
		if ($isWriteCheck && !$filePermissions['w']) {
			return FALSE;
			# see comment above.
			#throw new t3lib_file_exception_InsufficientFileWritePermissionsException("TYPO3 has no permission to write to file " . $file->getIdentifier());
		}

		return TRUE;
	}

	/**
	 * Check if a folder operation (= action) is allowed on a Folder
	 *
	 * This method, by design, does not throw exceptions or do logging.
	 * See the checkFileActionPermission() method above for the reasons.
	 *
	 * @param string $action
	 * @param t3lib_file_Folder $folder
	 * @return boolean
	 */
	public function checkFolderActionPermission($action, t3lib_file_Folder $folder = NULL) {
			// check 1: Does the user have permission to perform the action? e.g. "writeFolder"
		if ($this->checkUserActionPermission($action, 'Folder') === FALSE) {
			return FALSE;
		}

			// check 2: Does the user have the right to perform the action?
			// (= is he/she within the file mount borders)
		if (is_array($this->fileMounts) && count($this->fileMounts) && !$this->isWithinFileMountBoundaries($folder)) {
			return FALSE;
		}


		$isReadCheck = FALSE;
		if (in_array($action, array('read'))) {
			$isReadCheck = TRUE;
		}

		$isWriteCheck = FALSE;
		if (in_array($action, array('write', 'delete', 'deleteRecursive'))) {
			$isWriteCheck = TRUE;
		}

			// check 3: Check the capabilities of the storage (and the driver)
		if ($isReadCheck && !$this->isBrowsable()) {
			return FALSE;
		}
		if ($isWriteCheck && !$this->isWritable()) {
			return FALSE;
		}

			// check 4: "Folder permissions" of the driver
		$folderPermissions = $this->driver->getFolderPermissions($folder);
		if ($isReadCheck && !$folderPermissions['r']) {
			return FALSE;
			# we can't thrown an exception here, as this function is just for asking whether the user has the permission. It's also used by the UI. Therefore, the following exception is commented out.
			# throw new t3lib_file_exception_InsufficientFolderReadPermissionsException("TYPO3 has no permission to read from folder " . $folder->getIdentifier());
		}
		if ($isWriteCheck && !$folderPermissions['w']) {
			return FALSE;
			# see comment above
			# throw new t3lib_file_exception_InsufficientFolderWritePermissionsException("TYPO3 has no permission to write to folder " . $folder->getIdentifier());
		}

		return TRUE;
	}

	/**
	 * If the fileName is given, check it against the TYPO3_CONF_VARS[BE][fileDenyPattern] +
	 * and if the file extension is allowed
	 *
	 * @param	string		Full fileName
	 * @return	boolean		TRUE if extension/fileName is allowed
	 * if (!$this->checkIfAllowed($fI['fileext'], $theDest, $fI['file'])) {
	 * @formallyknownas t3lib_basicfilefunc::checkIfAllowed(), and is_allowed()
	 */
	protected function checkFileExtensionPermission($fileName) {
		$isAllowed = t3lib_div::verifyFilenameAgainstDenyPattern($fileName);

		if ($isAllowed) {
			$fileInfo = t3lib_div::split_fileref($fileName);

				// set up the permissions for the file extension
			$fileExtensionPermissions = $GLOBALS['TYPO3_CONF_VARS']['BE']['fileExtensions']['webspace'];
			$fileExtensionPermissions['allow'] = t3lib_div::uniqueList(strtolower($fileExtensionPermissions['allow']));
			$fileExtensionPermissions['deny'] = t3lib_div::uniqueList(strtolower($fileExtensionPermissions['deny']));

			$fileExtension = strtolower($fileInfo['fileext']);
			if ($fileExtension !== '') {
					// If the extension is found amongst the allowed types, we return TRUE immediately
				if ($fileExtensionPermissions['allow'] === '*' || t3lib_div::inList($fileExtensionPermissions['allow'], $fileExtension)) {
					return TRUE;
				}
					// If the extension is found amongst the denied types, we return FALSE immediately
				if ($fileExtensionPermissions['deny'] === '*' || t3lib_div::inList($fileExtensionPermissions['deny'], $fileExtension)) {
					return FALSE;
				}
					// If no match we return TRUE
				return TRUE;

				// no file extension
			} else {
				if ($fileExtensionPermissions['allow'] === '*') {
					return TRUE;
				}
				if ($fileExtensionPermissions['deny'] === '*') {
					return FALSE;
				}
				return TRUE;
			}
		}
		return FALSE;
	}




	/********************
	 * FILE ACTIONS
	 ********************/


	/**
	 * Moves a file from the local filesystem to this storage.
	 *
	 * @param string $localFilePath The file on the server's hard disk to add.
	 * @param t3lib_file_Folder $targetFolder The target path, without the fileName
	 * @param string $fileName The fileName. If not set, the local file name is used.
	 * @param string $conflictMode possible value are 'cancel', 'replace', 'changeName'
	 * @return t3lib_file_File
	 */
	public function addFile($localFilePath, t3lib_file_Folder $targetFolder, $fileName = '', $conflictMode = 'changeName') {
		// TODO check permissions (write on target, upload, ...)

		if (!file_exists($localFilePath)) {
			throw new InvalidArgumentException("File $localFilePath does not exist.", 1319552745);
		}

		$targetFolder = $targetFolder ? $targetFolder : $this->getDefaultFolder();
		$fileName = $fileName ? $fileName : basename($localFilePath);

		if ($conflictMode == 'cancel' && $this->driver->fileExistsInFolder($fileName, $targetFolder)) {
			throw new t3lib_file_exception_ExistingTargetFileNameException("File $fileName already exists in folder "
				. $targetFolder->getIdentifier(), 1322121068);
		} else if ($conflictMode == 'changeName') {
			$fileName = $this->getUniqueName($targetFolder, $fileName);
		} // we do not care whether the file exists if $conflictMode is "replace", so just use the name as is in that case

		return $this->driver->addFile($localFilePath, $targetFolder, $fileName);
	}

	public function hashFile(t3lib_file_File $fileObject, $hash) {
		return $this->driver->hash($fileObject, $hash);
	}

	/**
	 * Returns a publicly accessible URL for a file.
	 *
	 * WARNING: Access to the file may be restricted by further means, e.g. some web-based authentication. You have to take care of this
	 * yourself.
	 *
	 * @param t3lib_file_File $fileObject The file object
	 * @return string
	 */
	public function getPublicUrlForFile(t3lib_file_File $fileObject) {
		return $this->driver->getPublicUrl($fileObject);
	}

	/**
	 * Get the file form the storage for local processing
	 *
	 * @param t3lib_file_File $fileObject
	 * @param bool $writable
	 * @return string Path to local file (either original or copied to some temporary local location)
	 */
	public function getFileForLocalProcessing(t3lib_file_File $fileObject, $writable = TRUE) {
		return $this->driver->getFileForLocalProcessing($fileObject, $writable);
	}

	/**
	 * Get file by identifier
	 *
	 * @param string $identifier
	 * @return t3lib_file_File
	 */
	public function getFile($identifier) {
		return $this->driver->getFile($identifier);
	}


	/**
	 * Get file by identifier
	 *
	 * @param string $identifier
	 * @return t3lib_file_File
	 */
	public function getFileInfo($file) {
		return $this->driver->getFileInfo($file);
	}

	/**
	 * Get file by identifier
	 *
	 * @deprecated To be removed before final release of FAL. Use combination of getFileInfoByIdentifier() with a file object as argument instead.
	 *
	 * @param string $identifier
	 * @return t3lib_file_File
	 */
	public function getFileInfoByIdentifier($identifier) {
		return $this->driver->getFileInfoByIdentifier($identifier);
	}


	/**
	 * Returns a list of files in a given path.
	 *
	 * @param string $path The path to list
	 * @param string $pattern The pattern the files have to match
	 * @param integer $start The position to start the listing; if not set or 0, start from the beginning
	 * @param integer $numberOfItems The number of items to list; if not set, return all items
	 * @return array Information about the files found.
	 */
	// TODO check if we should use a folder object instead of $path
	public function getFileList($path, $pattern = '', $start = 0, $numberOfItems = 0) {
		$items = $this->driver->getFileList($path, $pattern, $start, $numberOfItems);
		uksort($items, 'strnatcasecmp');

		return $items;
	}

	/**
	 * Returns TRUE if the specified file exists.
	 *
	 * @param string $identifier
	 * @return bool
	 */
	public function hasFile($identifier) {
			// @todo: access check?
		return $this->driver->fileExists($identifier);
	}

	/**
	 * Checks if the queried file in the given folder exists.
	 *
	 * @param string $fileName
	 * @param t3lib_file_Folder $folder
	 * @return bool
	 */
	public function hasFileInFolder($fileName, t3lib_file_Folder $folder) {
		return $this->driver->fileExistsInFolder($fileName, $folder);
	}


	/**
	 * Get contents of a file object
	 *
	 * @param	t3lib_file_File	$file
	 * @return	string
	 */
	public function getFileContents($file) {
			// check if $file is readable
		if (!$this->checkFileActionPermission('read', $file)) {
			// todo: implement correct exception
			throw new RuntimeException();
		}

		return $this->driver->getFileContents($file);
	}

	/**
	 * Set contents of a file object.
	 *
	 * @param t3lib_file_File $file
	 * @param string $contents
	 * @return bool TRUE if the operation succeeded
	 * TODO check if we should align the return value with file_put_contents (which returns the number of bytes that have been written to the file).
	 *      This would also require changes to the drivers
	 */
	public function setFileContents(t3lib_file_File $file, $contents) {
			// TODO does setting file contents require update permission?
			// check if user is allowed to update
		if (!$this->checkUserActionPermission('update', 'File')) {
			// todo: implement correct exception
			throw new t3lib_file_exception_InsufficientUserPermissionsException();
		}

			// check if $file is writable
		if (!$this->checkFileActionPermission('write', $file)) {
			// todo: implement correct exception
			throw new RuntimeException();
		}

			// call driver method to update the file
		try {
			$result = $this->driver->setFileContents($file, $contents);

			$fileInfo = $this->driver->getFileInfo($file);
			$fileInfo['sha1'] = $this->driver->hash($file, 'sha1');
			$file->updateProperties($fileInfo);
		} catch (RuntimeException $e) {
			// todo: bubble exception?
		}

		return TRUE;
	}

	/**
	 * Creates a new file
	 *
	 * previously in t3lib_extFileFunc::func_newfile()
	 *
	 * @param string $fileName
	 * @param t3lib_file_Folder $targetFolderObject
	 * @return t3lib_file_File The file object
	 */
	public function createFile($fileName, t3lib_file_Folder $targetFolderObject) {
		if (!$this->checkFolderActionPermission('createFile', $targetFolderObject)) {
			throw new t3lib_file_exception_InsufficientFolderWritePermissionsException('You are not allowed to create directories on this storage "' . $targetFolderObject->getIdentifier() . '"', 1323059807);
		}
		return $this->driver->createFile($fileName, $targetFolderObject);
	}

	/**
	 * previously in t3lib_extFileFunc::deleteFile()
	 * @param $fileObject t3lib_file_File
	 * @return bool TRUE if deletion succeeded
	 *
	 * TODO throw FileInUseException when the file is still used anywhere
	 */
	public function deleteFile($fileObject) {
			// check if $file is readable
		if (!$this->checkFileActionPermission('delete', $fileObject)) {
			throw new t3lib_file_exception_InsufficientFileAccessPermissionsException('You are not allowed to access the file "' . $fileObject->getIdentifier() . "'", 1319550425);
		}

		$this->driver->deleteFile($fileObject);
	}

	/**
	 * previously in t3lib_extFileFunc::func_copy()
	 * copies a source file (from any location) in to the target
	 * folder, the latter has to be part of this storage
	 *
	 * @param	t3lib_file_File	$file
	 * @param	t3lib_file_Folder $targetFolder
	 * @param	string	$conflictMode	"overrideExistingFile", "renameNewFile", "cancel"
	 * @param	string	$targetFileName	an optional destination fileName
	 * @return t3lib_file_File
	 */
	public function copyFile(t3lib_file_File $file, t3lib_file_Folder $targetFolder, $targetFileName = NULL, $conflictMode = 'renameNewFile') {
		$this->emitPreFileCopySignal($file, $targetFolder);

		$this->checkFileCopyPermissions($file, $targetFolder, $targetFileName);

		if ($targetFileName === NULL) {
			$targetFileName = $file->getName();
		}

		// file exists and we should abort, let's abort
		if ($conflictMode === 'cancel' && $targetFolder->hasFile($targetFileName)) {
			throw new t3lib_file_exception_ExistingTargetFileNameException('The target file already exists.', 1320291063);
		}

		// file exists and we should find another name, let's find another one
		if ($conflictMode === 'renameNewFile' && $targetFolder->hasFile($targetFileName)) {
			$targetFileName = $this->getUniqueName($targetFolder, $targetFileName);
		}

		$sourceStorage = $file->getStorage();
			// call driver method to create a new file from an existing file object,
			// and return the new file object
		try {
			if ($sourceStorage == $this) {
				$newFileObject = $this->driver->copyFileWithinStorage($file, $targetFolder, $targetFileName);
			} else {
				$tempPath = $file->getForLocalProcessing();
				$newFileObject = $this->driver->addFile($tempPath, $targetFolder, $targetFileName);
				// TODO update metadata
			}
		} catch (t3lib_file_exception_AbstractFileOperationException $e) {
			throw $e;
		}

		$this->emitPostFileCopySignal($file, $targetFolder);

		return $newFileObject;
	}

	/**
	 * Check if a file has the permission to be uploaded to a Folder/Storage,
	 * if not throw an exception
	 *
	 * @param string $localFilePath the temporary file name from $_FILES['file1']['tmp_name']
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $targetFileName the destination file name $_FILES['file1']['name']
	 * @param int $uploadedFileSize
	 * @return void
	 */
	protected function checkFileUploadPermissions($localFilePath, $targetFolder, $targetFileName, $uploadedFileSize) {

			// Makes sure the user is allowed to upload
		if (!$this->checkUserActionPermission('upload', 'File')) {
			throw new t3lib_file_exception_InsufficientUserPermissionsException('You are not allowed to upload files to this storage "' . $this->getUid() . '"', 1322112430);
		}

			// Makes sure this is an uploaded file
		if (!is_uploaded_file($localFilePath)) {
			throw new t3lib_file_exception_UploadException("The upload has failed, no uploaded file found!", 1322110455);
		}

			// max upload size (kb) for files. Remember that PHP has an inner limit often set to 2 MB
		$maxUploadFileSize = t3lib_div::getMaxUploadFileSize() * 1024;
		if ($uploadedFileSize >= $maxUploadFileSize) {
			throw new t3lib_file_exception_UploadSizeException("The uploaded file exceeds the size-limit of $maxUploadFileSize bytes", 1322110041);
		}

			// check if targetFolder is writable
		if (!$this->checkFolderActionPermission('write', $targetFolder)) {
			throw new t3lib_file_exception_InsufficientFolderWritePermissionsException('You are not allowed to write to the target folder "' . $targetFolder->getIdentifier() . '"', 1322120356);
		}

			// check for a valid file extension
		if (!$this->checkFileExtensionPermission($targetFileName)) {
			throw new t3lib_file_exception_IllegalFileExtensionException("Extension of file name is not allowed in \"$targetFileName\"!", 1322120271);
		}
	}

	/**
	 * Check if a file has the permission to be copied on a File/Folder/Storage,
	 * if not throw an exception
	 *
	 * @param t3lib_file_File $file
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $targetFileName
	 * @return void
	 */
	protected function checkFileCopyPermissions(t3lib_file_File $file, t3lib_file_Folder $targetFolder, $targetFileName) {
			// check if targetFolder is within this storage, this should never happen
		if ($this->getUid() != $targetFolder->getStorage()->getUid()) {
			throw new t3lib_file_exception_AbstractFileException('The operation of the folder cannot be called by this storage "' . $this->getUid() . '"', 1319550405);
		}

			// check if user is allowed to copy
		if (!$this->checkUserActionPermission('copy', 'File')) {
			throw new t3lib_file_exception_InsufficientUserPermissionsException('You are not allowed to copy files to this storage "' . $this->getUid() . '"', 1319550415);
		}

			// check if $file is readable
		if (!$this->checkFileActionPermission('read', $file)) {
			throw new t3lib_file_exception_InsufficientFileReadPermissionsException('You are not allowed to read the file "' . $file->getIdentifier() . "'", 1319550425);
		}

			// check if targetFolder is writable
		if (!$this->checkFolderActionPermission('write', $targetFolder)) {
			throw new t3lib_file_exception_InsufficientFolderWritePermissionsException('You are not allowed to write to the target folder "' . $targetFolder->getIdentifier() . '"', 1319550435);
		}

			// check for a valid file extension
		if (!$this->checkFileExtensionPermission($targetFileName)) {
			throw new t3lib_file_exception_IllegalFileExtensionException('You are not allowed to copy a file of that type.', 1319553317);
		}
	}

	/**
	 * Moves a $file into a $targetFolder
	 * the target folder has to be part of this storage
	 *
	 * previously in t3lib_extFileFunc::func_move()
	 * @param	t3lib_file_File	$file
	 * @param	t3lib_file_Folder $targetFolder
	 * @param	string	$conflictMode	"overrideExistingFile", "renameNewFile", "cancel"
	 * @param	string	$targetFileName	an optional destination fileName
	 * @return t3lib_file_File
	 */
	public function moveFile($file, $targetFolder, $targetFileName = NULL, $conflictMode = 'renameNewFile') {
		// TODO emit signal -- should we do this after checking permissions?

		$this->checkFileMovePermissions($file, $targetFolder);

		if ($targetFileName === NULL) {
			$targetFileName = $file->getName();
		}

			// file exists and we should abort, let's abort
		if ($conflictMode === 'cancel' && $targetFolder->hasFile($targetFileName)) {
			// todo: implement correct exception
			throw new RuntimeException();
		}

			// file exists and we should find another name, let's find another one
		if ($conflictMode === 'renameNewFile' && $targetFolder->hasFile($targetFileName)) {
			$targetFileName = $this->getUniqueName($targetFolder, $targetFileName);
		}

		$this->emitPreFileMoveSignal($file, $targetFolder);

		$sourceStorage = $file->getStorage();
			// call driver method to move the file
			// that also updates the file object properties
		try {
			if ($sourceStorage == $this) {
				$newIdentifier = $this->driver->moveFileWithinStorage($file, $targetFolder, $targetFileName);

				$this->updateFile($file, $newIdentifier);
			} else {
				$tempPath = $file->getForLocalProcessing();
				$newIdentifier = $this->driver->addFileRaw($tempPath, $targetFolder, $targetFileName);
				$sourceStorage->driver->deleteFileRaw($file->getIdentifier());

				$this->updateFile($file, $newIdentifier, $this);
			}
		} catch (t3lib_exception $e) {
			echo $e->getMessage();
			// TODO rollback things that have happened
			// TODO emit FileMoveFailedSignal?
		}

		$this->emitPostFileMoveSignal($file, $targetFolder);

		return $file;
	}

	/**
	 * @param t3lib_file_File $file
	 * @param string $identifier
	 * @param t3lib_file_Storage $storage
	 * @return void
	 */
	protected function updateFile(t3lib_file_File $file, $identifier = '', $storage = NULL) {
		if ($identifier == '') {
			$identifier = $file->getIdentifier();
		}
		$fileInfo = $this->driver->getFileInfoByIdentifier($identifier);
		// TODO extend mapping
		$newProperties = array(
			'storage' => $fileInfo['storage'],
			'identifier' => $fileInfo['identifier'],
			'tstamp' => $fileInfo['mtime'],
			'crdate' => $fileInfo['ctime'],
			'mime_type' => $fileInfo['mimetype'],
			'size' => $fileInfo['size'],
			'tstamp' => $fileInfo['mtime']
		);
		if ($storage !== NULL) {
			$newProperties['storage'] = $storage->getUid();
		}

		$file->updateProperties($newProperties);

		/** @var $fileRepository t3lib_file_Repository_FileRepository */
		$fileRepository = t3lib_div::makeInstance('t3lib_file_Repository_FileRepository');
		$fileRepository->update($file);
	}

	protected function checkFileMovePermissions(t3lib_file_File $file, t3lib_file_Folder $targetFolder) {
			// check if targetFolder is within this storage
		if ($this->getUid() != $targetFolder->getStorage()->getUid()) {
			throw new RuntimeException();
		}

			// check if user is allowed to move
		if (!$this->checkUserActionPermission('move', 'File')) {
			throw new t3lib_file_exception_InsufficientUserPermissionsException('You are not allowed to move files to storage "' . $this->getUid() . '"', 1319219349);
		}

			// check if $file is readable
		if (!$this->checkFileActionPermission('read', $file)) {
			throw new t3lib_file_exception_InsufficientFileReadPermissionsException('You are not allowed to read the file "' . $file->getIdentifier() . "'", 1319219349);
		}

			// check if $file is writable
		if (!$this->checkFileActionPermission('write', $file)) {
			throw new t3lib_file_exception_InsufficientFileWritePermissionsException('You are not allowed to move the file "' . $file->getIdentifier() . "'", 1319219349);
		}

			// check if targetFolder is writable
		if (!$this->checkFolderActionPermission('write', $targetFolder)) {
			throw new t3lib_file_exception_InsufficientFolderAccessPermissionsException('You are not allowed to write to the target folder "' . $targetFolder->getIdentifier() . '"', 1319219349);
		}
	}

	/**
	 * previously in t3lib_extFileFunc::func_rename()
	 *
	 * @param	t3lib_file_File	$file
	 * @param	string	$targetFileName
	 * @return t3lib_file_File
	 */
	public function renameFile($file, $targetFileName) {

			// The name should be different from the current.
		if ($file->getIdentifier() == $targetFileName) {
			return $file;
		}

			// check if user is allowed to rename
		if (!$this->checkUserActionPermission('rename', 'File')) {
			throw new t3lib_file_exception_InsufficientUserPermissionsException('You are not allowed to rename files."', 1319219349);
		}

			// check if $file is readable
		if (!$this->checkFileActionPermission('read', $file)) {
			throw new t3lib_file_exception_InsufficientFileReadPermissionsException('You are not allowed to read the file "' . $file->getIdentifier() . "'", 1319219349);
		}

			// check if $file is writable
		if (!$this->checkFileActionPermission('write', $file)) {
			throw new t3lib_file_exception_InsufficientFileWritePermissionsException('You are not allowed to rename the file "' . $file->getIdentifier() . "'", 1319219349);
		}

			// call driver method to rename the file
			// that also updates the file object properties
		try {
			$newIdentifier = $this->driver->renameFile($file, $targetFileName);

			$this->updateFile($file, $newIdentifier);
			/** @var $fileRepository t3lib_file_Repository_FileRepository */
			$fileRepository = t3lib_div::makeInstance('t3lib_file_Repository_FileRepository');
			$fileRepository->update($file);
		} catch(RuntimeException $e) {
			// rename failed (maybe because file existed?)
			// todo: bubble exception?
		}

		return $file;
	}

	/**
	 * Replaces a file with a local file (e.g. a freshly uploaded file)
	 *
	 * @param t3lib_file_File $file
	 * @param string $localFilePath
	 * @return t3lib_file_File
	 */
	public function replaceFile(t3lib_file_File $file, $localFilePath) {
		if (!file_exists($localFilePath)) {
			throw new InvalidArgumentException("File '$localFilePath' does not exist.", 1325842622);
		}

		// TODO check permissions

		// TODO emit pre-replace signal
		return $this->driver->replaceFile($file, $localFilePath);
		// TODO emit post-replace signal
	}

	/**
	 * Adds an uploaded file into the Storage. Previously in t3lib_extFileFunc::file_upload()
	 *
	 * @param array $uploadedFileData contains information about the uploaded file given by $_FILES['file1']
	 * @param t3lib_file_Folder $targetFolder the target folder
	 * @param string $targetFileName the file name to be written
	 * @param string $conflictMode possible value are 'cancel', 'replace'
	 * @return t3lib_file_File The file object
	 */
	public function addUploadedFile(array $uploadedFileData, t3lib_file_Folder $targetFolder = NULL, $targetFileName = NULL, $conflictMode = 'cancel') {

		$localFilePath = $uploadedFileData['tmp_name'];
		if ($targetFolder === NULL) {
			$targetFolder = $this->getDefaultFolder();
		}

		if ($targetFileName === NULL) {
			$targetFileName = $uploadedFileData['name'];
		}

		// TODO handle conflict mode

		$this->checkFileUploadPermissions($localFilePath, $targetFolder, $targetFileName, $uploadedFileData['size']);

		$resultObject = $this->addFile($localFilePath, $targetFolder, $targetFileName);
		return $resultObject;
	}

	/********************
	 * FOLDER ACTIONS
	 ********************/

	/**
	 * previously in t3lib_extFileFunc::folder_move()
	 *
	 * @param	t3lib_file_Folder	$folderToMove The folder to move.
	 * @param	t3lib_file_Folder	$targetParentFolder The target parent folder
	 * @return t3lib_file_Folder
	 */
	public function moveFolder(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetParentFolder) {

	}

	/**
	 * Copy folder
	 *
	 * @param t3lib_file_Folder $folderToCopy The folder to copy
	 * @param t3lib_file_Folder $targetParentFolder The target folder
	 * @param string $newFolderName
	 * @param string $conflictMode  "overrideExistingFolder", "renameNewFolder", "cancel"
	 * @return t3lib_file_Folder The new (copied) folder object
	 */
	public function copyFolder(t3lib_file_Folder $folderToCopy, t3lib_file_Folder $targetParentFolder, $newFolderName = NULL, $conflictMode = 'renameNewFolder') {
		// TODO implement the $conflictMode handling
		// TODO permission checks

		$sourceStorage = $folderToCopy->getStorage();
			// call driver method to move the file
			// that also updates the file object properties
		try {
			if ($sourceStorage == $this) {
				$this->driver->copyFolderWithinStorage($folderToCopy, $targetParentFolder, $newFolderName);
			} else {
				$this->copyFolderBetweenStorages($folderToCopy, $targetParentFolder, $newFolderName);
			}
		} catch (t3lib_exception $e) {
			echo $e->getMessage();
			// TODO rollback things that have happened
		}
	}

	protected function copyFolderBetweenStorages(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetParentFolder, $newFolderName = NULL) {
		/**
		 * TODO:
		 * - get all folders, call this method for each of them
		 * - get all files
		 *   - get a local copy
		 *   - put it into the other storage
		 */
		
	}

	/**
	 * previously in t3lib_extFileFunc::folder_move()
	 * @param t3lib_file_Folder $folderObject
	 * @param string $newName
	 * @return bool TRUE if the operation succeeded
	 * @throws RuntimeException if an error occurs during renaming
	 */
	public function renameFolder($folderObject, $newName) {
		// TODO unit tests
		// TODO access checks

		if ($this->driver->folderExistsInFolder($newName, $folderObject)) {
			throw new InvalidArgumentException("The folder $newName already exists in folder " . $folderObject->getIdentifier(), 1325418870);
		}

		// TODO emit pre-update signal

		$newIdentifier = $this->driver->renameFolder($folderObject, $newName);

		// TODO emit post-update signal, update files and subfolders
	}

	/**
	 * previously in t3lib_extFileFunc::folder_delete()
	 *
	 * @param t3lib_file_Folder	$folderObject
	 * @param bool $deleteRecursively
	 * @return bool
	 */
	public function deleteFolder($folderObject, $deleteRecursively = FALSE) {

		if (!$this->checkFolderActionPermission('delete', $folderObject)) {
			throw new t3lib_file_exception_InsufficientFileAccessPermissionsException('You are not allowed to access the folder "' . $folderObject->getIdentifier() . "'", 1323423953);
		}

		$this->driver->deleteFolder($folderObject, $deleteRecursively);
	}

	/**
	 * Returns a list of files in a given path.
	 *
	 * @param string $path The path to list
	 * @param string $pattern The pattern the files have to match
	 * @param integer $start The position to start the listing; if not set or 0, start from the beginning
	 * @param integer $numberOfItems The number of items to list; if not set, return all items
	 * @return array Information about the files found.
	 */
	public function getFolderList($path, $pattern = '', $start = 0, $numberOfItems = 0) {
		$items = $this->driver->getFolderList($path, $pattern, $start, $numberOfItems);
		uksort($items, 'strnatcasecmp');

		return $items;
	}

	/**
	 * Returns TRUE if the specified folder exists.
	 *
	 * @param $identifier
	 * @return bool
	 */
	public function hasFolder($identifier) {
		return $this->driver->folderExists($identifier);
	}

	/**
	 * Creates a new folder.
	 *
	 * previously in t3lib_extFileFunc::func_newfolder()
	 *
	 * @param string $folderName the new folder name
	 * @param t3lib_file_Folder $parentFolder The parent folder to create the new folder inside of
	 * @return t3lib_file_Folder The new folder object
	 */
	public function createFolder($folderName, t3lib_file_Folder $parentFolder) {
		if (!$this->checkFolderActionPermission('createFolder', $parentFolder)) {
			throw new t3lib_file_exception_InsufficientFolderWritePermissionsException('You are not allowed to create directories on this storage "' . $parentFolder->getIdentifier() . '"', 1323059807);
		}

		if (!$this->driver->folderExists($parentFolder->getIdentifier())) {
			throw new InvalidArgumentException('Parent folder "' . $parentFolder->getIdentifier() . '" does not exist.', 1325689164);
		}

		return $this->driver->createFolder($folderName, $parentFolder);
	}

	/**
	 * Returns the default folder where new files are stored if no other folder is given.
	 *
	 * @return t3lib_file_Folder
	 */
	public function getDefaultFolder() {
		return $this->driver->getDefaultFolder();
	}

	/**
	 * @param string $identifier
	 * @return t3lib_file_Folder
	 */
	public function getFolder($identifier) {
		$folderObject = $this->driver->getFolder($identifier);
		if ($this->fileMounts && !$this->isWithinFileMountBoundaries($folderObject)) {
			// @todo: throw an exception instead when the requested folder is not within the filemount boundaries
			return FALSE;
		} else {
			return $folderObject;
		}
	}

	/**
	 * Returns the folders on the root level of the storage
	 * or the first mount point of this storage for this user
	 *
	 * @return t3lib_file_Folder
	 */
	public function getRootLevelFolder() {
		if (count($this->fileMounts)) {
			$mount = reset($this->fileMounts);
			return $mount['folder'];
		} else {
			return $this->driver->getRootLevelFolder();
		}
	}

	protected function emitPreProcessConfigurationSignal() {
		$this->getSignalSlotDispatcher()->dispatch(
			't3lib_file_Storage',
			self::SIGNAL_PreProcessConfiguration,
			array($this)
		);
	}

	protected function emitPostProcessConfigurationSignal() {
		$this->getSignalSlotDispatcher()->dispatch(
			't3lib_file_Storage',
			self::SIGNAL_PostProcessConfiguration,
			array($this)
		);
	}

	protected function emitPreFileCopySignal(t3lib_file_File $file, $targetFolder) {
		$this->getSignalSlotDispatcher()->dispatch(
			't3lib_file_Storage',
			self::SIGNAL_PreFileCopy,
			array($file, $targetFolder)
		);
	}

	protected function emitPostFileCopySignal(t3lib_file_File $file, $targetFolder) {
		$this->getSignalSlotDispatcher()->dispatch(
			't3lib_file_Storage',
			self::SIGNAL_PostFileCopy,
			array($file, $targetFolder)
		);
	}

	protected function emitPreFileMoveSignal(t3lib_file_File $file, $targetFolder) {
		$this->getSignalSlotDispatcher()->dispatch(
			't3lib_file_Storage',
			self::SIGNAL_PreFileMove,
			array($file, $targetFolder)
		);
	}

	protected function emitPostFileMoveSignal(t3lib_file_File $file, $targetFolder) {
		$this->getSignalSlotDispatcher()->dispatch(
			't3lib_file_Storage',
			self::SIGNAL_PostFileMove,
			array($file, $targetFolder)
		);
	}

	/**
	 * Returns the destination path/fileName of a unique fileName/foldername in that path.
	 * If $theFile exists in $theDest (directory) the file have numbers appended up to $this->maxNumber. Hereafter a unique string will be appended.
	 * This function is used by fx. TCEmain when files are attached to records and needs to be uniquely named in the uploads/* folders
	 *
	 * @param t3lib_file_Folder $folder
	 * @param	string	$theFile	The input fileName to check
	 * @param	boolean	$dontCheckForUnique	If set the fileName is returned with the path prepended without checking whether it already existed!
	 * @return	string		A unique fileName inside $folder, based on $theFile.
	 * @see t3lib_basicFileFunc::getUniqueName()
	 */
	// TODO check if this should be moved back to t3lib_file_Folder
	protected function getUniqueName(t3lib_file_Folder $folder, $theFile, $dontCheckForUnique = FALSE) {
		static $maxNumber = 99, $uniqueNamePrefix = '';

		$origFileInfo = t3lib_div::split_fileref($theFile); // Fetches info about path, name, extention of $theFile
		if ($uniqueNamePrefix) { // Adds prefix
			$origFileInfo['file'] = $uniqueNamePrefix . $origFileInfo['file'];
			$origFileInfo['filebody'] = $uniqueNamePrefix . $origFileInfo['filebody'];
		}

			// Check if the file exists and if not - return the fileName...
		$fileInfo = $origFileInfo;
		$theDestFile = $fileInfo['file']; // The destinations file
		if (!$folder->hasFile($theDestFile) || $dontCheckForUnique) { // If the file does NOT exist we return this fileName
			return $theDestFile;
		}

			// Well the fileName in its pure form existed. Now we try to append numbers / unique-strings and see if we can find an available fileName...
		$theTempFileBody = preg_replace('/_[0-9][0-9]$/', '', $origFileInfo['filebody']); // This removes _xx if appended to the file
		$theOrigExt = $origFileInfo['realFileext'] ? '.' . $origFileInfo['realFileext'] : '';

		for ($a = 1; $a <= ($maxNumber + 1); $a++) {
			if ($a <= $maxNumber) { // First we try to append numbers
				$insert = '_' . sprintf('%02d', $a);
			} else { // .. then we try unique-strings...
				$insert = '_' . substr(md5(uniqId('')), 0, 6); // TODO remove constant 6
			}
			$theTestFile = $theTempFileBody . $insert . $theOrigExt;
			$theDestFile = $theTestFile; // The destinations file
			if (!$folder->hasFile($theDestFile)) { // If the file does NOT exist we return this fileName
				return $theDestFile;
			}
		}

		throw new RuntimeException('Last possible name "' . $theDestFile . '" is already taken.', 1325194291);
	}

	/**
	 * @return t3lib_SignalSlot_Dispatcher
	 */
	protected function getSignalSlotDispatcher() {
		if (!isset($this->signalSlotDispatcher)) {
			$this->signalSlotDispatcher = t3lib_div::makeInstance('t3lib_SignalSlot_Dispatcher');
		}
		return $this->signalSlotDispatcher;
	}
}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/file/Storage.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/file/Storage.php']);
}

?>
