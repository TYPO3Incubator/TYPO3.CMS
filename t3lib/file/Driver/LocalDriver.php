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
 * Driver for the local file system
 *
 * @author  Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package	TYPO3
 * @subpackage	t3lib
 */
class t3lib_file_Driver_LocalDriver extends t3lib_file_Driver_AbstractDriver {
	/**
	 * The absolute base path. It always contains a trailing slash.
	 *
	 * @var string
	 */
	protected $absoluteBasePath;

	/**
	 * A list of all supported hash algorithms, written all lower case.
	 *
	 * @var array
	 */
	protected $supportedHashAlgorithms = array('sha1', 'md5');

	/**
	 * The base URL that points to this driver's storage. As long is this is not set, it is assumed that this folder
	 * is not publicly available
	 *
	 * @var string
	 */
	protected $baseUri;

	/**
	 * @var t3lib_cs
	 */
	protected $charsetConversion;

	/**
	 * Checks if a configuration is valid for this storage.
	 *
	 * @param array $configuration The configuration
	 * @return void
	 * @throws RuntimeException
	 */
	public static function verifyConfiguration(array $configuration) {
		self::calculateBasePath($configuration);
	}

	/**
	 * @return void
	 */
	protected function processConfiguration() {
		$this->absoluteBasePath = $this->calculateBasePath($this->configuration);
	}

	/**
	 * Initializes this object. This is called by the storage after the driver has been attached.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->determineBaseUrl();

			// The capabilities of this driver. See CAPABILITY_* constants for possible values
		$this->capabilities = (self::CAPABILITY_BROWSABLE && self::CAPABILITY_PUBLIC && self::CAPABILITY_WRITABLE);
	}

	/**
	 * Checks a fileName for validity
	 *
	 * @param string $fileName
	 * @return bool TRUE if file name is valid
	 *
	 * TODO should this be protected/moved to AbstractDriver or Storage?
	 */
	public function isValidFilename($fileName) {
		if (strpos($fileName, '/') !== FALSE) {
			return FALSE;
		}
		if (!preg_match('/^[[:alnum:][:blank:]\.-_]*$/iu', $fileName)) {
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Determines the base URL for this driver, from the configuration or the TypoScript frontend object
	 *
	 * @return void
	 */
	protected function determineBaseUrl() {
		if (t3lib_div::isFirstPartOfStr($this->absoluteBasePath, PATH_site)) {
			// TODO use site URL (where to get it from e.g. on CLI?)
			if (is_object($GLOBALS['TSFE'])) {
				$this->baseUri = $GLOBALS['TSFE']->absRefPrefix;
			} else {
					// use site-relative URLs
				// TODO add unit test
				$this->baseUri = substr($this->absoluteBasePath, strlen(PATH_site));
			}
		} elseif (isset($this->configuration['baseUri']) && t3lib_div::isValidUrl($this->configuration['baseUri'])) {
			$this->baseUri = rtrim($this->configuration['baseUri'], '/') . '/';
		} else {
			// TODO throw exception? -> not if we have a publisher set
		}
	}

	/**
	 * Calculates the absolute path to this drivers storage location.
	 *
	 * @throws RuntimeException
	 * @param array $configuration
	 * @return string
	 */
	protected function calculateBasePath(array $configuration) {
		if($configuration['pathType'] ==='relative') {
			$relativeBasePath = $configuration['basePath'];
			$absoluteBasePath = PATH_site.$relativeBasePath;
		} else {
			$absoluteBasePath = $configuration['basePath'];
		}

		$absoluteBasePath = rtrim($absoluteBasePath, '/') . '/';

		if (!is_dir($absoluteBasePath)) {
			throw new RuntimeException("Base path $absoluteBasePath does not exist or is no directory.", 1299233097);
		}
		return $absoluteBasePath;
	}

	/**
	 * Returns the public URL to a file. This can also be relative to the current website, if no absolute URL is available
	 *
	 * @param t3lib_file_File $file
	 * @return string
	 */
	// TODO check if this can be moved to AbstractDriver.
	public function getPublicUrl(t3lib_file_File $file) {

		// inserted by ingmar to get image gallery working. It seems like the whole baseUri/basePath stuff needs to be cleaned up,
		// as before this change getPublicUrl returned only relative paths to the site root, even though there was a basePath in the setting.
		// When fixing this, please test with the media_gallery extension from Forge SVN and with the sysext/file/BackwardsCompatibility/TslibContentAdapter.php
		// changed by Steffen to get relative URL only for 0: Storage Fallback
		if ($this->configuration['pathType']==='relative' && rtrim($this->configuration['basePath'], '/') !== '') {
			return rtrim($this->configuration['basePath'], '/') . '/' . ltrim($file->getIdentifier(), '/');
		}
		// end inserted by ingmar

		if (isset($this->baseUri)) {
			return $this->baseUri . ltrim($file->getIdentifier(), '/');
		} else {
			// TODO check if publisher is available, if not, throw exception
			return $this->resourcePublisher->publishFile($file);
		}
	}

	/**
	 * Returns the root level folder of the storage.
	 *
	 * @return t3lib_file_Folder
	 */
	public function getRootLevelFolder() {
		if (!$this->rootLevelFolder) {
			/** @var $factory t3lib_file_Factory */
			$factory = t3lib_div::makeInstance('t3lib_file_Factory');
			$this->rootLevelFolder = $factory->createFolderObject($this->storage, '/', '');
		}

		return $this->rootLevelFolder;
	}

	/**
	 * Returns the default folder new files should be put into.
	 *
	 * @return t3lib_file_Folder
	 */
	public function getDefaultFolder() {
		if (!$this->defaultLevelFolder) {
			if (!file_exists($this->absoluteBasePath . '_temp_/')) {
				mkdir($this->absoluteBasePath . '_temp_/');
			}

			/** @var $factory t3lib_file_Factory */
			$factory = t3lib_div::makeInstance('t3lib_file_Factory');
			$this->defaultLevelFolder = $factory->createFolderObject($this->storage, '/_temp_/', '');
		}

		return $this->defaultLevelFolder;
	}

	/**
	 * Creates a folder.
	 *
	 * @param string $newFolderName
	 * @param t3lib_file_Folder $parentFolder
	 * @return t3lib_file_Folder The new (created) folder object
	 */
	public function createFolder($newFolderName, t3lib_file_Folder $parentFolder) {
		// TODO we should have some convenience method to create a deeper directory structure. Maybe this would fit into Storage
		$newFolderName = $this->sanitizeFileName($newFolderName);

		$newFolderPath = $this->getAbsolutePath($parentFolder) . trim($newFolderName, '/');

		if (!$this->folderExists($parentFolder->getIdentifier())) {
			throw new RuntimeException("Cannot create folder $newFolderName because the parent folder does not exist.", 1315401669);
		}

		t3lib_div::mkdir($newFolderPath);

		/** @var $factory t3lib_file_Factory */
		$factory = t3lib_div::makeInstance('t3lib_file_Factory');
		return $factory->createFolderObject($this->storage, rtrim($parentFolder->getIdentifier(),'/').$newFolderName, $newFolderName);
	}

	/**
	 * Returns information about a file.
	 *
	 * @param string $fileIdentifier In the case of the LocalDriver, this is the (relative) path to the file.
	 * @return array
	 */
	public function getFileInfoByIdentifier($fileIdentifier) {

			// Makes sure the Path given as parameter is valid
		$this->checkFilePath($fileIdentifier);

		$dirPath = dirname($fileIdentifier);
		if ($dirPath !== '' && $dirPath !== '/') {
			$dirPath = '/' . trim($dirPath, '/') . '/';
		}

		$absoluteFilePath = $this->absoluteBasePath . ltrim($fileIdentifier, '/');

			// don't use $this->fileExists() because we need the absolute path to the file anyways, so we can directly
			// use PHP's filesystem method.
		if (!file_exists($absoluteFilePath)) {
			throw new InvalidArgumentException("File $fileIdentifier does not exist.", 1314516809);
		}

		return $this->extractFileInformation(new SplFileInfo($absoluteFilePath), $dirPath);
	}

	/**
	 * Wrapper for t3lib_div::validPathStr()
	 *
	 * @param string $theFile Filepath to evaluate
	 * @return boolean TRUE if no '/', '..' or '\' is in the $theFile
	 * @see t3lib_div::validPathStr()
	 */
	protected function isPathValid($theFile) {
		return t3lib_div::validPathStr($theFile);
	}

	/**
	 * Returns a string where any character not matching [.a-zA-Z0-9_-] is substituted by '_'
	 * Trailing dots are removed
	 *
	 * previously in t3lib_basicFileFunctions::cleanFileName()
	 *
	 * @param string $fileName Input string, typically the body of a fileName
	 * @param string $charset Charset of the a fileName (defaults to current charset; depending on context)
	 * @return string Output string with any characters not matching [.a-zA-Z0-9_-] is substituted by '_' and trailing dots removed
	 */
	protected function sanitizeFileName($fileName, $charset = '') {
			// Handle UTF-8 characters
		if ($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] == 'utf-8' && $GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem']) {
				// allow ".", "-", 0-9, a-z, A-Z and everything beyond U+C0 (latin capital letter a with grave)
			$cleanFileName = preg_replace('/[\x00-\x2C\/\x3A-\x3F\x5B-\x60\x7B-\xBF]/u', '_', trim($fileName));

			// Handle other character sets
		} else {
				// Define character set
			if (!$charset) {
				if (TYPO3_MODE == 'FE') {
					$charset = $GLOBALS['TSFE']->renderCharset;
				} elseif (is_object($GLOBALS['LANG'])) { // BE assumed:
					$charset = $GLOBALS['LANG']->charSet;
				} else { // best guess
					$charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];
				}
			}

				// If a charset was found, convert fileName
			if ($charset) {
				$fileName = $this->getCharsetConversion()->specCharsToASCII($charset, $fileName);
			}

				// Replace unwanted characters by underscores
			$cleanFileName = preg_replace('/[^.[:alnum:]_-]/', '_', trim($fileName));
		}
			// Strip trailing dots and return

		$cleanFileName = preg_replace('/\.*$/', '', $cleanFileName);
		if (!$cleanFileName) {
			throw new t3lib_file_exception_InvalidFileNameException("File name $cleanFileName is invalid.", 1320288991);
		}
		return $cleanFileName;
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
	// TODO add unit tests
	// TODO implement pattern matching
	public function getFileList($path, $pattern = '', $start = 0, $numberOfItems = 0) {
		return $this->getDirectoryItemList($path, $start, $numberOfItems, 'getFileList_itemCallback');
	}

	/**
	 * Returns a list of all folders in a given path
	 *
	 * @param string $path
	 * @param string $pattern
	 * @param integer $start The position to start the listing; if not set, start from the beginning
	 * @param integer $numberOfItems The number of items to list; if not set, return all items
	 * @return array
	 */
	// TODO implement pattern matching
	public function getFolderList($path, $pattern = '', $start = 0, $numberOfItems = 0) {
		return $this->getDirectoryItemList($path, $start, $numberOfItems, 'getFolderList_itemCallback');
	}

	/**
	 * Generic wrapper for extracting a list of items from a path. The extraction itself is done by the given handler method
	 *
	 * @param string $path
	 * @param string $itemHandlerMethod The method (in this class) that handles the single iterator elements.
	 * @param integer $start The position to start the listing; if not set, start from the beginning
	 * @param integer $numberOfItems The number of items to list; if not set, return all items
	 * @return array
	 */
	protected function getDirectoryItemList($path, $start = 0, $numberOfItems = 0, $itemHandlerMethod) {
		$realPath = $this->absoluteBasePath . trim($path, '/') . '/';

		if (!is_dir($realPath)) {
			throw new InvalidArgumentException("Cannot list items in directory $path - does not exist or is no directory", 1314349666);
		}

		if ($start > 0) {
			--$start;
		}

			// Fetch the files and folders and sort them by name; we have to do this here because the directory iterator
			// does return them in an arbitrary order
		$items = $this->getFileAndFoldernamesInPath($realPath);
		natcasesort($items);
		$iterator = new ArrayIterator($items);
		$iterator->seek($start);

		if ($path !== '' && $path != '/') {
			$path = '/' . trim($path, '/') . '/';
		}

		$c = ($numberOfItems > 0) ? $numberOfItems : -1;

		$items = array();
		while ($iterator->valid() && ($numberOfItems == 0 || $c > 0)) {
			--$c;
			$iteratorItem = $iterator->current();
			$iterator->next();
			list($key, $item) = $this->$itemHandlerMethod($iteratorItem, $path);

			if (empty($item)) {
				++$c;
				continue;
			}
			$items[$key] = $item;
		}

		return $items;
	}

	/**
	 * Handler for items in a file list.
	 *
	 * @param string $fileName
	 * @param string $path
	 * @return array
	 */
	protected function getFileList_itemCallback($fileName, $path) {
		$filePath = $this->getAbsolutePath($path . $fileName);

			// also don't show hidden files
		if (!is_file($filePath) || t3lib_div::isFirstPartOfStr($fileName, '.') === TRUE) {
			return array('', array());
		}
		$fileInfo = new SplFileInfo($filePath);

		return array($fileName, $this->extractFileInformation($fileInfo, $path));
	}

	/**
	 * Handler for items in a directory listing.
	 *
	 * @param string $fileName
	 * @param string $path
	 * @return array
	 */
	protected function getFolderList_itemCallback($fileName, $path) {
		$filePath = $this->getAbsolutePath($path . $fileName);
		if (!is_dir($filePath)) {
			return array('', array());
		}
			// also don't show hidden files
		if ($fileName == '..' || $fileName == '.' || $fileName == '' || t3lib_div::isFirstPartOfStr($fileName, '.') === TRUE) {
			return array('', array());
		}
		$fileInfo = new SplFileInfo($filePath);

		return array($fileName, array(
			'name' => $fileName,
			'identifier' => $path . $fileName . '/',
			'creationDate' => $fileInfo->getCTime(),
			'storage' => $this->storage->getUid()
			// TODO add more information
		));
	}

	/**
	 * Returns a list with the names of all files and folders in a path.
	 *
	 * @param string $path
	 * @return array
	 */
	protected function getFileAndFoldernamesInPath($path) {
		$dirHandle = opendir($path);
		rewinddir($dirHandle);

		$entries = array();
		while (false !== ($entry = readdir($dirHandle))) {
				// skip nonfiles/nonfolders, and empty entries
			if ((!is_file($path . $entry) && !is_dir($path . $entry)) || $entry == '') {
				continue;
			}

			$entries[] = $entry;
		}

		return $entries;
	}

	/**
	 * Extracts information about a file from an SplFileInfo object.
	 *
	 * @param SplFileInfo $splFileObject
	 * @param string $containerPath
	 * @return array
	 */
	protected function extractFileInformation(SplFileInfo $splFileObject, $containerPath) {
		$filePath = $splFileObject->getPathname();
		$fileInfo = new finfo();

		$fileInformation = array(
			'size' => $splFileObject->getSize(),
			'atime' => $splFileObject->getATime(),
			'mtime' => $splFileObject->getMTime(),
			'ctime' => $splFileObject->getCTime(),
			'mimetype' => $fileInfo->file($filePath, FILEINFO_MIME_TYPE),
			'name' => $splFileObject->getFilename(),
			'identifier' => $containerPath . $splFileObject->getFilename(),
			'storage' => $this->storage->getUid()
		);


		return $fileInformation;
	}


	/**
	 * Returns the absolute path of the folder this driver operates on.
	 *
	 * @return string
	 */
	public function getAbsoluteBasePath() {
		return $this->absoluteBasePath;
	}

	/**
	 * Returns the absolute path of a file or folder.
	 *
	 * @param t3lib_file_File|t3lib_file_Folder|string $file
	 * @return string
	 */
	public function getAbsolutePath($file) {
		if ($file instanceof t3lib_file_File) {
			$path = $this->absoluteBasePath . ltrim($file->getIdentifier(), '/');
		} elseif ($file instanceof t3lib_file_Folder) {
				// We can assume a trailing slash here because it is added by the folder object on construction.
			$path = $this->absoluteBasePath . ltrim($file->getIdentifier(), '/');
		} elseif (is_string($file)) {
			$path = $this->absoluteBasePath . ltrim($file, '/');
		} else {
			throw new RuntimeException('Type "' . gettype($file) . '" is not supported.', 1325191178);
		}

		return $path;
	}

	/**
	 * Returns metadata of a file (size, times, mimetype)
	 *
	 * @param t3lib_file_File $file
	 * @return array
	 */
	public function getLowLevelFileInfo(t3lib_file_File $file) {
		// TODO define which data should be returned
		// TODO write unit test
		// TODO cache this info. Registry?
		$filePath = $this->getAbsolutePath($file);
		$fileStat = stat($filePath);
		$fileInfo = new finfo();

		$stat = array(
			'size' => filesize($filePath),
			'atime' => $fileStat['atime'],
			'mtime' => $fileStat['mtime'],
			'ctime' => $fileStat['ctime'],
			'nlink' => $fileStat['nlink'],
			'type' => $fileInfo->file($filePath, FILEINFO_MIME_TYPE),
			'mimetype' => $fileInfo->file($filePath, FILEINFO_MIME_TYPE),
		);
		return $stat;
	}

	/**
	 * Creates a (cryptographic) hash for a file.
	 *
	 * @param t3lib_file_File $file
	 * @param string $hashAlgorithm The hash algorithm to use
	 * @return string
	 */
	public function hash(t3lib_file_File $file, $hashAlgorithm) {
		if (!in_array($hashAlgorithm, $this->getSupportedHashAlgorithms())) {
			throw new InvalidArgumentException("Hash algorithm $hashAlgorithm is not supported.", 1304964032);
		}

		switch ($hashAlgorithm) {
			case 'sha1':
				$hash = sha1_file($this->getAbsolutePath($file));

				break;
			case 'md5':
				$hash = md5_file($this->getAbsolutePath($file));

				break;
		}

		return $hash;
	}

	/**
	 * Adds a file from the local server hard disk to a given path in TYPO3s virtual file system.
	 *
	 * This assumes that the local file exists, so no further check is done here!
	 *
	 * @param string $localFilePath
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName The name to add the file under
	 * @return t3lib_file_File
	 */
	public function addFile($localFilePath, t3lib_file_Folder $targetFolder, $fileName) {
		if (t3lib_div::isFirstPartOfStr($localFilePath, $this->absoluteBasePath)) {
			throw new InvalidArgumentException("Cannot add a file that is already part of this storage.", 1314778269);
		}

		$relativeTargetPath = ltrim($targetFolder->getIdentifier(), '/');
		$relativeTargetPath .= $fileName ? $fileName : basename($localFilePath);
		$targetPath = $this->absoluteBasePath . $relativeTargetPath;

		if (is_uploaded_file($localFilePath)) {
			$moveResult = move_uploaded_file($localFilePath, $targetPath);
		}
		else {
			$moveResult = rename($localFilePath, $targetPath);
		}

		if ($moveResult !== TRUE) {
			throw new RuntimeException("Moving file $localFilePath to $targetPath failed.", 1314803096);
		}

		clearstatcache();
		t3lib_div::fixPermissions($targetPath); // Change the permissions of the file

		$fileInfo = $this->getFileInfoByIdentifier($relativeTargetPath);
		$fileObject = $this->getFileObject($fileInfo);

		return $fileObject;
	}

	/**
	 * Checks if a file exists.
	 *
	 * @param string $identifier
	 * @return bool
	 */
	public function fileExists($identifier) {
		$absoluteFilePath = $this->absoluteBasePath . ltrim($identifier, '/');

		return is_file($absoluteFilePath);
	}

	/**
	 * Checks if a file inside a storage folder exists
	 *
	 * @param string $fileName
	 * @param t3lib_file_Folder $folder
	 * @return bool
	 */
	public function fileExistsInFolder($fileName, t3lib_file_Folder $folder) {
		$identifier = ltrim($folder->getIdentifier(), '/') . $fileName;

		return $this->fileExists($identifier);
	}

	/**
	 * Checks if a folder exists.
	 *
	 * @param string $identifier
	 * @return bool
	 */
	public function folderExists($identifier) {
		$absoluteFilePath = $this->absoluteBasePath . ltrim($identifier, '/');

		return is_dir($absoluteFilePath);
	}


	/**
	 * Checks if a file inside a storage folder exists.
	 *
	 * @param string $folderName
	 * @param t3lib_file_Folder $folder
	 * @return bool
	 */
	public function folderExistsInFolder($folderName, t3lib_file_Folder $folder) {
		$identifier = ltrim($folder->getIdentifier(), '/') . $folderName;

		return $this->folderExists($identifier);
	}

	/**
	 * Replaces the contents (and file-specific metadata) of a file object with a local file.
	 *
	 * @param t3lib_file_File $file
	 * @param string $localFilePath
	 * @return bool TRUE if the operation succeeded
	 */
	public function replaceFile(t3lib_file_File $file, $localFilePath) {
		$filePath = $this->getAbsolutePath($file);

		$result = rename($localFilePath, $filePath);
		if ($result === FALSE) {
			throw new RuntimeException("Replacing file $filePath with $localFilePath failed.", 1315314711);
		}

		$fileInfo = $this->getFileInfoByIdentifier($file->getIdentifier());
		$file->updateProperties($fileInfo);
		// TODO update index

		return $result;
	}

	/**
	 * Adds a file at the specified location. This should only be used internally.
	 *
	 * @param string $localFilePath
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $targetFileName
	 * @return bool TRUE if adding the file succeeded
	 */
	public function addFileRaw($localFilePath, t3lib_file_Folder $targetFolder, $targetFileName) {
		$fileIdentifier = $targetFolder->getIdentifier() . $targetFileName;
		$absoluteFilePath = $this->absoluteBasePath . $fileIdentifier;
		$result = copy($localFilePath, $absoluteFilePath);

		if ($result === FALSE || !file_exists($absoluteFilePath)) {
			throw new RuntimeException("Adding file $localFilePath at $fileIdentifier failed.");
		}
		return $fileIdentifier;
	}

	/**
	 * Deletes a file without access and usage checks. This should only be used internally.
	 *
	 * This accepts an identifier instead of an object because we might want to delete files that have no object
	 * associated with (or we don't want to create an object for) them - e.g. when moving a file to another storage.
	 *
	 * @param string $identifier
	 * @return bool TRUE if removing the file succeeded
	 */
	public function deleteFileRaw($identifier) {
		$targetPath = $this->absoluteBasePath . ltrim($identifier, '/');
		$result = unlink($targetPath);

		if ($result === FALSE || file_exists($targetPath)) {
			throw new RuntimeException("Deleting file $identifier failed.", 1320381534);
		}
		return TRUE;
	}

	/**
	 * Copies a file *within* the current storage.
	 * Note that this is only about an intra-storage move action, where a file is just
	 * moved to another folder in the same storage.
	 *
	 * @param t3lib_file_File $file
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName
	 * @return t3lib_file_File The new (copied) file object.
	 */
	public function copyFileWithinStorage(t3lib_file_File $file, t3lib_file_Folder $targetFolder, $fileName) {
		// TODO add unit test
		$sourcePath = $this->getAbsolutePath($file);
		$targetPath = ltrim($targetFolder->getIdentifier(), '/') . $fileName;

		copy($sourcePath, $this->absoluteBasePath . $targetPath);

		return $this->getFile($targetPath);
	}

	/**
	 * Moves a file *within* the current storage.
	 * Note that this is only about an intra-storage move action, where a file is just
	 * moved to another folder in the same storage.
	 *
	 * @param t3lib_file_File $file
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName
	 * @return bool
	 */
	public function moveFileWithinStorage(t3lib_file_File $file, t3lib_file_Folder $targetFolder, $fileName) {
		$sourcePath = $this->getAbsolutePath($file);
		$targetIdentifier = $targetFolder->getIdentifier() . $fileName;

		$result = rename($sourcePath, $this->absoluteBasePath . $targetIdentifier);
		if ($result === FALSE) {
			throw new RuntimeException("Moving file $sourcePath to $targetIdentifier failed.", 1315314712);
		}

		return $targetIdentifier;
	}

	/**
	 * Copies a file to a temporary path and returns that path.
	 *
	 * @param t3lib_file_File $file
	 * @return string The temporary path
	 */
	public function copyFileToTemporaryPath(t3lib_file_File $file) {
		$sourcePath = $this->getAbsolutePath($file);
		$temporaryPath = $this->getTemporaryPathForFile($file);

		$result = copy($sourcePath, $temporaryPath);
		if ($result === FALSE) {
			throw new RuntimeException('Copying file ' . $file->getIdentifier() . ' to temporary path failed.', 1320577649);
		}
		return $temporaryPath;
	}

	/**
	 * Folder equivalent to moveFileWithinStorage().
	 *
	 * @param t3lib_file_Folder $folderToMove
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $newFolderName
	 * @return bool
	 */
	public function moveFolderWithinStorage(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetFolder, $newFolderName = NULL) {
		$sourcePath = $this->getAbsolutePath($folderToMove);
		$targetPath = $this->getAbsolutePath($targetFolder);
		$targetPath .= ($newFolderName ? $newFolderName : $folderToMove->getName()) . '/';

		$result = rename($sourcePath, $targetPath);
		if ($result === FALSE) {
			throw new RuntimeException("Moving folder $sourcePath to $targetPath failed.", 1320711817);
		}

		return $result;
	}

	/**
	 * Folder equivalent to copyFileWithinStorage().
	 *
	 * @param t3lib_file_Folder $folderToCopy
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $newFolderName
	 * @return bool
	 */
	public function copyFolderWithinStorage(t3lib_file_Folder $folderToCopy, t3lib_file_Folder $targetFolder, $newFolderName = NULL) {
		if ($newFolderName == '') {
			$newFolderName = $folderToCopy->getName();
		}
			// This target folder path already includes the topmost level, i.e. the folder this method knows as $folderToCopy.
			// We can thus rely on this folder being present and just create the subfolder we want to copy to.
		$targetFolderPath = $this->getAbsolutePath($targetFolder) . $newFolderName . '/';
		mkdir($targetFolderPath);

		$sourceFolderPath = $this->getAbsolutePath($folderToCopy);

		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceFolderPath));

		while ($iterator->valid()) {
			/** @var $current RecursiveDirectoryIterator */
			$current = $iterator->current();
			$itemSubPath = $iterator->getSubPathname();

			if ($current->isDir()) {
				mkdir($targetFolderPath . $itemSubPath);
			} else if ($current->isFile()) {
				$result = copy($sourceFolderPath . $itemSubPath, $targetFolderPath . $itemSubPath);
				if ($result === FALSE) {
					// TODO throw exception
				}
			}

			$iterator->next();
		}

		return TRUE;
	}


	/**
	 * Renames a file in this storage.
	 *
	 * @param t3lib_file_File $file
	 * @param string $newName The target path (including the file name!)
	 * @return string The identifier of the file after renaming
	 */
	public function renameFile(t3lib_file_File $file, $newName) {
			// Makes sure the Path given as parameter is valid
		$newName = $this->sanitizeFileName($newName);
		$newIdentifier = rtrim(dirname($file->getIdentifier()), '/') . '/' . $newName;

		// The target should not exist already
		if ($this->fileExists($newIdentifier)) {
			throw new t3lib_file_exception_ExistingTargetFileNameException("The target file already exists.", 1320291063);
		}

		$sourcePath = $this->getAbsolutePath($file);
		$targetPath = $this->absoluteBasePath . '/' . ltrim($newIdentifier, '/');

		$result = rename($sourcePath, $targetPath);
		if ($result === FALSE) {
			throw new RuntimeException("Renaming file $sourcePath to $targetPath failed.", 1320375115);
		}

		return $newIdentifier;
	}

	/**
	 * Makes sure the Path given as parameter is valid
	 *
	 * @param string $filePath The file path (including the file name!)
	 * @return void
	 */
	protected function checkFilePath($filePath) {

			// filePath must be valid
		if (!$this->isPathValid($filePath)) {
			throw new t3lib_file_exception_InvalidPathException("File $filePath is not valid (\"..\" and \"//\" is not allowed in path).", 1320286857);
		}
	}

	/**
	 * Renames a folder in this storage.
	 *
	 * @param t3lib_file_Folder $folder
	 * @param string $newName The target path (including the file name!)
	 * @return string The new identifier of the folder if the operation succeeds
	 * @throws RuntimeException if renaming the folder failed
	 */
	public function renameFolder(t3lib_file_Folder $folder, $newName) {
			// Makes sure the Path given as parameter is valid
		$newName = $this->sanitizeFileName($newName);
		$sourceIdentifier = $folder->getIdentifier();
		$newIdentifier = rtrim(dirname($sourceIdentifier), '/') . '/' . $newName . '/';

		$sourcePath = $this->getAbsolutePath($folder);
		$targetPath = $this->getAbsolutePath($newIdentifier);

		$result = rename($sourcePath, $targetPath);
		if ($result === FALSE) {
			//throw new RuntimeException("Renaming folder $sourceIdentifier to $newIdentifier failed.", 1320375116);
		}

		return $newIdentifier;
	}

	/**
	 * Removes a file from this storage.
	 *
	 * @param t3lib_file_File $file
	 * @return boolean TRUE if deleting the file succeeded
	 */
	public function deleteFile(t3lib_file_File $file) {
		$filePath = $this->getAbsolutePath($file);

		$result = unlink($filePath);
		if ($result === FALSE) {
			throw new RuntimeException("Deletion of file " . $file->getIdentifier() . " failed.", 1320855304);
		}

		return $result;
	}

	/**
	 * Removes a folder from this storage.
	 *
	 * @param t3lib_file_Folder $folder
	 * @param bool $deleteRecursively
	 * @return boolean
	 */
	public function deleteFolder(t3lib_file_Folder $folder, $deleteRecursively = FALSE) {
		$folderPath = $this->getAbsolutePath($folder);

		$result = t3lib_div::rmdir($folderPath, $deleteRecursively);
		if ($result === FALSE) {
			// TODO throw exception
		}
		return $result;
	}

	/**
	 * Checks if a folder contains files and (if supported) other folders.
	 *
	 * @param t3lib_file_Folder $folder
	 * @return bool TRUE if there are no files and folders within $folder
	 */
	public function isFolderEmpty(t3lib_file_Folder $folder) {
		$path = $this->getAbsolutePath($folder);

		$dirHandle = opendir($path);
		while ($entry = readdir($dirHandle)) {
			if ($entry !== '.' && $entry !== '..') {
				closedir($dirHandle);
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * Returns a (local copy of) a file for processing it. This makes a copy first when in writable mode, so if you change the file,
	 * you have to update it yourself afterwards.
	 *
	 * @param t3lib_file_File $file
	 * @param bool $writable Set this to FALSE if you only need the file for read operations. This might speed up things, e.g. by using a cached local version. Never modify the file if you have set this flag!
	 * @return string The path to the file on the local disk
	 */
	public function getFileForLocalProcessing(t3lib_file_File $file, $writable = TRUE) {
		if ($writable === FALSE) {
				// TODO check if this is ok or introduce additional measures against file changes
			return $this->getAbsolutePath($file);
		} else {
				// TODO check if this might also serve as a dump basic implementation in the abstract driver.
			return $this->copyFileToTemporaryPath($file);
		}
	}

	/**
	 * Returns the permissions of a file as an array (keys r, w) of boolean flags
	 *
	 * @param t3lib_file_File $file The file object to check
	 * @return array
	 * @throws RuntimeException If fetching the permissions failed
	 */
	public function getFilePermissions(t3lib_file_File $file) {
		$filePath = $this->getAbsolutePath($file);

		return $this->getPermissions($filePath);
	}

	/**
	 * Returns the permissions of a folder as an array (keys r, w) of boolean flags
	 *
	 * @param t3lib_file_Folder $folder
	 * @return array
	 * @throws RuntimeException If fetching the permissions failed
	 */
	public function getFolderPermissions(t3lib_file_Folder $folder) {
		$folderPath = $this->getAbsolutePath($folder);

		return $this->getPermissions($folderPath);
	}

	/**
	 * Helper function to unify access to permission information
	 *
	 * @param $path
	 * @return array
	 * @throws RuntimeException If fetching the permissions failed
	 */
	protected function getPermissions($path) {
		$permissionBits = fileperms($path);

		if ($permissionBits === FALSE) {
			throw new RuntimeException('Error while fetching permissions for ' . $path, 1319455097);
		}

		return array(
			'r' => (bool)is_readable($path),
			'w' => (bool)is_writable($path)
		);
	}

	/**
	 * Checks if a given identifier is within a container, e.g. if a file or folder is within another folder.
	 * This can be used to check for webmounts.
	 *
	 * @param t3lib_file_Folder $container
	 * @param string $content
	 * @return bool TRUE if $content is within $container, always FALSE if $container is not within this storage
	 */
	public function isWithin(t3lib_file_Folder $container, $content) {
		if ($container->getStorage() != $this->storage) {
			return FALSE;
		}

		if ($content instanceof t3lib_file_File || $content instanceof t3lib_file_Folder) {
			$content = $container->getIdentifier();
		}
		$folderPath = $container->getIdentifier();
		$content = '/' . ltrim($content, '/');

		return t3lib_div::isFirstPartOfStr($content, $folderPath);
	}

	/**
	 * Creates a new file and returns the matching file object for it.
	 *
	 * @param string $fileName
	 * @param t3lib_file_Folder $parentFolder
	 * @return t3lib_file_File
	 */
	public function createFile($fileName, t3lib_file_Folder $parentFolder) {
		if (!$this->isValidFilename($fileName)) {
			throw new t3lib_file_exception_InvalidFileNameException("Invalid characters in fileName '$fileName'.", 1320572272);
		}
		$filePath = $parentFolder->getIdentifier() . ltrim($fileName, '/');
		// TODO set permissions of new file
		$result = touch($this->absoluteBasePath . $filePath);
		clearstatcache();
		if ($result !== TRUE) {
			throw new RuntimeException("Creating file $filePath failed.", 1320569854);
		}
		$fileInfo = $this->getFileInfoByIdentifier($filePath);
		return $this->getFileObject($fileInfo);
	}

	/**
	 * Returns the contents of a file. Beware that this requires to load the complete file into memory and also may
	 * require fetching the file from an external location. So this might be an expensive operation (both in terms of
	 * processing resources and money) for large files.
	 *
	 * @param t3lib_file_File $file
	 * @return string The file contents
	 */
	public function getFileContents(t3lib_file_File $file) {
		$filePath = $this->getAbsolutePath($file);

		return file_get_contents($filePath);
	}

	/**
	 * Sets the contents of a file to the specified value.
	 *
	 * @param t3lib_file_File $file
	 * @param string $contents
	 * @return bool TRUE if setting the contents succeeded
	 * @throws RuntimeException if the operation failed
	 */
	public function setFileContents(t3lib_file_File $file, $contents) {
		$filePath = $this->getAbsolutePath($file);

		$result = file_put_contents($filePath, $contents);

		if ($result === FALSE) {
			throw new RuntimeException('Setting contents of file ' . $file->getIdentifier() . ' failed.', 1325419305);
		}

		return TRUE;
	}

	/**
	 * @return t3lib_cs
	 */
	protected function getCharsetConversion() {
		if (isset($this->charsetConversion) === FALSE) {
			if (TYPO3_MODE == 'FE') {
				$this->charsetConversion = $GLOBALS['TSFE']->csConvObj;
			} elseif (is_object($GLOBALS['LANG'])) { // BE assumed:
				$this->charsetConversion = $GLOBALS['LANG']->csConvObj;
			} else { // The object may not exist yet, so we need to create it now. Happens in the Install Tool for example.
				$this->charsetConversion = t3lib_div::makeInstance('t3lib_cs');
			}
		}

		return $this->charsetConversion;
	}
}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/Storage/class.t3lib_file_Storage_local.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/Storage/class.t3lib_file_Storage_local.php']);
}

?>