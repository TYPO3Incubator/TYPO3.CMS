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
 * Factory class for VFS objects.
 *
 * NOTE: This class is part of the lowlevel VFS api and should not be used from outside the VFS package.
 *
 * @author  Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package  TYPO3
 * @subpackage  t3lib
 */
// TODO implement constructor-level caching
class t3lib_file_Factory implements t3lib_Singleton {
	/**
	 * Gets a singleton instance of this class.
	 *
	 * @return t3lib_file_Factory
	 */
	public static function getInstance() {
		return t3lib_div::makeInstance('t3lib_file_Factory');
	}

	/**
	 * @var t3lib_file_Storage[]
	 */
	protected $storageInstances = array();

	/**
	 * @var t3lib_file_AbstractCollection[]
	 */
	protected $collectionInstances = array();

	/**
	 * @var t3lib_file_File[]
	 */
	protected $fileInstances = array();

	/**
	 * @var t3lib_file_FileReference[]
	 */
	protected $fileReferenceInstances = array();

	/**
	 * Creates a driver object for a specified storage object.
	 *
	 * @param string $driverIdentificationString The driver class (or identifier) to use.
	 * @param array $driverConfiguration The configuration of the storage
	 * @return t3lib_file_Driver_AbstractDriver
	 * @throws InvalidArgumentException
	 */
	public function getDriverObject($driverIdentificationString, array $driverConfiguration) {
		/** @var $driverRegistry t3lib_file_Driver_DriverRegistry */
		$driverRegistry = t3lib_div::makeInstance('t3lib_file_Driver_DriverRegistry');
		$driverClass = $driverRegistry->getDriverClass($driverIdentificationString);

		$driverObject = t3lib_div::makeInstance($driverClass, $driverConfiguration);

		return $driverObject;
	}

	/**
	 * Creates an instance of the storage from given UID. The $recordData can be supplied to increase performance.
	 *
	 * @param int  $uid  The uid of the storage to instantiate.
	 * @param array  $recordData  The record row from database.
	 * @return t3lib_file_Storage
	 */
	public function getStorageObject($uid, array $recordData = array()) {
		if (!is_numeric($uid)) {
			throw new InvalidArgumentException('uid of Storage has to be numeric.', 1314085991);
		}

		if (!$this->storageInstances[$uid]) {

			$storageConfiguration = NULL;

			if (intval($uid) === 0) { // If the built-in storage with UID=0 is requested:

				$recordData = array(
					'uid' => 0,
					'pid' => 0,
					'name' => 'Default Storage',
					'description' => 'Internal storage, mounting the main TYPO3_site directory.',
					'driver' => 'Local',
					'configuration' => '',
					'is_browsable' => TRUE,
					'is_public' => TRUE,
					'is_writable' => TRUE,
				);

				$storageConfiguration = array(
					'basePath' => '/',
					'pathType' => 'relative'
				);
			} elseif (count($recordData) === 0 || $recordData['uid'] !== $uid) { 	// Get storage data if not already supplied as argument to this function
				/** @var $GLOBALS['TYPO3_DB'] t3lib_DB */
				$recordData = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'sys_file_storage', 'uid=' . intval($uid) . ' AND deleted=0');
				if (!is_array($recordData) || count($recordData) === 0) {
					throw new InvalidArgumentException('No storage found for given UID.', 1314085992);
				}
			}
			$storageObject = $this->createStorageObject($recordData, $storageConfiguration);
			$this->storageInstances[$uid] = $storageObject;
		}

		return $this->storageInstances[$uid];
	}

	/**
	 * Converts a flexform data string to a flat array with key value pairs
	 *
	 * @param string $flexFormData
	 * @return array	Array with key => value pairs of the field data in the FlexForm
	 */
	public function convertFlexFormDataToConfigurationArray($flexFormData) {

		$configuration = array();

		$flexFormContents = t3lib_div::xml2array($flexFormData);
		if (is_array($flexFormContents) && isset($flexFormContents['data']['sDEF']['lDEF']) && is_array($flexFormContents['data']['sDEF']['lDEF'])) {
			foreach ($flexFormContents['data']['sDEF']['lDEF'] as $key => $value) {
				if (isset($value['vDEF'])) {
					$configuration[$key] = $value['vDEF'];
				}
			}
		}

		return $configuration;
	}


	/**
	 * Creates an instance of the collection from given UID. The $recordData can be supplied to increase performance.
	 *
	 * @param int  $uid  The uid of the collection to instantiate.
	 * @param array  $recordData  The record row from database.
	 * @return t3lib_file_AbstractCollection
	 */
	public function getCollectionObject($uid, array $recordData = array()) {
		if (!is_numeric($uid)) {
			throw new InvalidArgumentException('uid of collection has to be numeric.', 1314085999);
		}

		if (!$this->collectionInstances[$uid]) {
				// Get mount data if not already supplied as argument to this function
			if (count($recordData) === 0 || $recordData['uid'] !== $uid) {
				/** @var $GLOBALS['TYPO3_DB'] t3lib_DB */
				$recordData = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'sys_file_collection', 'uid='.intval($uid).' AND deleted=0');
				if (!is_array($recordData) || count($recordData) === 0) {
					throw new InvalidArgumentException('No collection found for given UID.', 1314085992);
				}
			}
			$collectionObject = $this->createCollectionObject($recordData);
			$this->collectionInstances[$uid] = $collectionObject;
		}

		return $this->collectionInstances[$uid];
	}



	/**
	 * Creates a collection object.
	 *
	 * @param array $collectionData The database row of the sys_file_collection record.
	 * @return t3lib_file_AbstractCollection
	 */
	public function createCollectionObject(array $collectionData) {

		switch ($collectionData['type']) {
			case 'static':
				$collection = t3lib_file_Collection_StaticFileCollection::load($collectionData['uid']);
				break;
			case 'filter':
				$collection = t3lib_file_Collection_FilteredFileCollection::load($collectionData['uid']);
				break;
			case 'folder':
				$collection = t3lib_file_Collection_FolderBasedFileCollection::load($collectionData['uid']);
				break;
			default:
				$collection = NULL;
		}

		return $collection;
	}

	/**
	 * Creates a storage object from a storage database row.
	 *
	 * @param array $storageRecord
	 * @param array $storageConfiguration Storage configuration (if given, this won't be extracted from the FlexForm value but the supplied array used instead)
	 * @return t3lib_file_Storage
	 */
	public function createStorageObject(array $storageRecord, array $storageConfiguration=NULL) {
		$className = 't3lib_file_Storage';

		if (!$storageConfiguration) {
			$storageConfiguration = $this->convertFlexFormDataToConfigurationArray($storageRecord['configuration']);
		}

		$driverType = $storageRecord['driver'];
		$driverObject = $this->getDriverObject($driverType, $storageConfiguration);

		/** @var $storage t3lib_file_Storage */
		$storage = t3lib_div::makeInstance($className, $driverObject, $storageRecord);

		// TODO handle publisher

		return $storage;
	}



	/**
	 * Creates a folder to directly access (a part of) a storage.
	 *
	 * @param t3lib_file_Storage $storage The storage the folder belongs to
	 * @param string $identifier The path to the folder. Might also be a simple unique string, depending on the storage driver.
	 * @param string $name The name of the folder (e.g. the folder name)
	 * @return t3lib_file_Folder
	 */
	public function createFolderObject(t3lib_file_Storage $storage, $identifier, $name) {
		return t3lib_div::makeInstance('t3lib_file_Folder', $storage, $identifier, $name);
	}

	protected function createPublisherFromConfiguration(array $configuration) {
		$publishingTarget = $this->getStorageObject($configuration['publisherConfiguration']['publishingTarget']);
		$publisher = t3lib_div::makeInstance($configuration['publisher'], $publishingTarget, $configuration['publisherConfiguration']);
		return $publisher;
	}

#	public function getFolderObject($folderUid) {}
#

	/**
	 * Creates an instance of the file given UID. The $fileData can be supplied to increase performance.
	 *
	 * @param int  $uid  The uid of the file to instantiate.
	 * @param array  $fileData  The record row from database.
	 * @return t3lib_file_File
	 */
	public function getFileObject($uid, array $fileData = array()) {
		if (!is_numeric($uid)) {
			throw new InvalidArgumentException('uid of file has to be numeric.', 1300096564);
		}

		if (!$this->fileInstances[$uid]) {
			// Fetches data in case $fileData is empty
			if (empty($fileData)) {
				/** @var $GLOBALS['TYPO3_DB'] t3lib_DB */
				$fileData = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'sys_file', 'uid=' . intval($uid) . ' AND deleted=0');
				if (!is_array($fileData) || count($fileData) === 0) {
					throw new InvalidArgumentException('No file found for given UID.', 1317178604);
				}
			}
			$this->fileInstances[$uid] = $this->createFileObject($fileData);
		}

		return $this->fileInstances[$uid];
	}

	/**
	 * Gets an file object from an identifier [storage]:[fileId]
	 *
	 * @TODO check naming, inserted by SteffenR while working on filelist
	 * @param string $identifier
	 * @return t3lib_file_File
	 */
	public function getFileObjectFromCombinedIdentifier($identifier) {
		$parts = t3lib_div::trimExplode(':', $identifier);

		if (count($parts) === 2) {
			$storageUid = $parts[0];
			$fileIdentifier = $parts[1];
		} else {
			// We only got a path: Go into backwards compatibility mode and use virtual Storage (uid=0)
			$storageUid = 0;
			$fileIdentifier = $parts[0];
		}


		return $this->getStorageObject($storageUid)->getFile($fileIdentifier);
	}

	/**
	 * Gets an file object from an identifier [storage]:[fileId]
	 *
	 * @TODO check naming, inserted by SteffenR while working on filelist
	 * @param string $identifier
	 * @return t3lib_file_Folder
	 */
	public function getFolderObjectFromCombinedIdentifier($identifier) {
		$parts = t3lib_div::trimExplode(':', $identifier);

		if (count($parts) === 2) {
			$storageUid = $parts[0];
			$folderIdentifier = $parts[1];
		} else {
			// We only got a path: Go into backwards compatibility mode and use virtual Storage (uid=0)
			$storageUid = 0;
			$folderIdentifier = $parts[0];
		}

		return $this->getStorageObject($storageUid)->getFolder($folderIdentifier);
	}

	public function getObjectFromCombinedIdentifier($identifier) {
		list($storageId, $objectIdentifier) = t3lib_div::trimExplode(':', $identifier);
		$storage = $this->getStorageObject($storageId);

		if ($storage->hasFile($objectIdentifier)) {
			return $storage->getFile($objectIdentifier);
		} elseif ($storage->hasFolder($objectIdentifier)) {
			return $storage->getFolder($objectIdentifier);
		}
	}

#	public function getFileObjectFromData($fileData) {
#		if (!is_numeric($fileData['uid'])) {
#			throw new InvalidArgumentException('uid of file has to be numeric.', 1300096565);
#		}
#
#		if (!$this->fileInstances[$fileData['uid']]) {
#			$this->fileInstances[$fileData['uid']] = $this->createFileObject($fileData);
#		}
#
#		return $this->fileInstances[$fileData['uid']];
#	}
#
	/**
	 * Creates a file object from an array of file data. Requires a database row to be fetched.
	 *
	 * @param array $fileData
	 * @return t3lib_file_File
	 */
	public function createFileObject(array $fileData) {
		/** @var t3lib_file_File $fileObject */
		$fileObject = t3lib_div::makeInstance('t3lib_file_File', $fileData);

		if (is_numeric($fileData['storage'])) {
			$storageObject = $this->getStorageObject($fileData['storage']);
			$fileObject->setStorage($storageObject);
		}

		return $fileObject;
	}



	/**
	 * Creates an instance of a FileReference object. The $fileReferenceData can be supplied to increase performance.
	 *
	 * @param int  $uid  The uid of the file usage (sys_file_reference) to instantiate.
	 * @param array  $fileReferenceData  The record row from database.
	 * @return t3lib_file_FileReference
	 */
	public function getFileReferenceObject($uid, array $fileReferenceData = array()) {
		if (!is_numeric($uid)) {
			throw new InvalidArgumentException('uid of fileusage (sys_file_reference) has to be numeric.', 1300086584);
		}

		if (!$this->fileReferenceInstances[$uid]) {
			// Fetches data in case $fileData is empty
			if (empty($fileReferenceData)) {
				/** @var $GLOBALS['TYPO3_DB'] t3lib_DB */
				$fileReferenceData = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'sys_file_reference', 'uid=' . intval($uid) . ' AND deleted=0');
				if (!is_array($fileReferenceData) || count($fileReferenceData) === 0) {
					throw new InvalidArgumentException('No fileusage (sys_file_reference) found for given UID.', 1317178794);
				}
			}
			$this->fileReferenceInstances[$uid] = $this->createFileReferenceObject($fileReferenceData);
		}

		return $this->fileReferenceInstances[$uid];
	}

	/**
	 * Creates a file usage object from an array of fileReference data (from sys_file_reference table).
	 * Requires a database row to be already fetched and present.
	 *
	 * @param array $fileReferenceData
	 * @return t3lib_file_FileReference
	 */
	public function createFileReferenceObject(array $fileReferenceData) {
		/** @var t3lib_file_FileReference $fileReferenceObject */
		$fileReferenceObject = t3lib_div::makeInstance('t3lib_file_FileReference', $fileReferenceData);
		return $fileReferenceObject;
	}

	/**
	 * generates a new object of the type t3lib_file_ProcessedFile
	 * additionally checks if this processed file already exists in the DB
	 *
	 * @param t3lib_file_FileInterface $originalFileObject
	 * @param $context
	 * @param array $configuration
	 * @return t3lib_file_ProcessedFile
	 */
	public function getProcessedFileObject(t3lib_file_FileInterface $originalFileObject, $context, array $configuration) {

		$processedFileObject = t3lib_div::makeInstance(
			't3lib_file_ProcessedFile',
			$originalFileObject,
			$context,
			$configuration);

		/* @var t3lib_file_Repository_ProcessedFileRepository $repository */
		$repository = t3lib_div::makeInstance('t3lib_file_Repository_ProcessedFileRepository');
			// check if this file already exists in the DB
		/** @var t3lib_file_ProcessedFile $processedFileObject */
		$repository->populateDataOfProcessedFileObject($processedFileObject);
		return $processedFileObject;
	}
#
#	protected function injectDependenciesForFileObject(t3lib_file_File $fileObject) {
#		$pid = $fileObject->getValue('pid');
#		$fileObject->setParent($this->getFolderObject($pid));
#	}
#
#	/**
#	 * Creates an instance of the given storage class with the given configuration. Also cares for injecting
#	 * required dependencies into the storage object.
#	 *
#	 * @param string  $storageClass  The storage to instantiate
#	 * @param array  $storageConfiguration  The storage configuration to use
#	 * @return t3lib_file_storage_Abstract
#	 */
#	protected function getStorageInstance($storage, $configuration) {
#		$storageObject = t3lib_div::makeInstance($storage, $configuration);
#
#		return $storageObject;
#	}
}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/class.t3lib_file_factory.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/class.t3lib_file_factory.php']);
}

