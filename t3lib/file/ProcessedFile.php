<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Ingmar Schlecht <ingmar@typo3.org>
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
 * Representation of a specific processing of a file.
 *
 * @author Ingmar Schlecht <ingmar@typo3.org>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_ProcessedFile implements t3lib_file_FileInterface {

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
	 * Processing context
	 *
	 * @var string
	 */
	protected $context;

	/**
	 * check if the file is processed
	 *
	 * @var boolean
	 */
	protected $isProcessed;

	/**
	 * Processing configuration
	 *
	 * @var array
	 */
	protected $processingConfiguration;

	/**
	 * Reference to the original File object underlying this FileReference.
	 *
	 * @var t3lib_file_File
	 */
	protected $originalFile;


	/**
	 * Constructor for a file processing object. Should normally not be used directly, use the corresponding factory methods instead.
	 *
	 * @param t3lib_file_File $originalFile
	 * @param string $context
	 * @param array $processingConfiguration
	 */
	public function __construct(t3lib_file_File $originalFile, $context, array $processingConfiguration) {
		$this->originalFile = $originalFile;
		$this->context = $context;
		$this->processingConfiguration = $processingConfiguration;
	}



	/*******************************
	 * VARIOUS FILE PROPERTY GETTERS
	 ************************


	/**
	 * Returns true if the given key exists for this file.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function hasProperty($key) {
		return isset($this->properties[$key]);
	}

	/**
	 * Returns true if the given key exists for this file.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function getProperty($key) {
		return $this->properties[$key];
	}

	public function getProperties() {
		return $this->properties;
	}

	/**
	 * Returns the name of this file
	 *
	 * @return string
	 */
	public function getName() {
		return $this->properties['name'];
	}

	/**
	 * Returns the uid of the original file
	 *
	 * @return int
	 */
	public function getUid() {
		return $this->properties['uid'];
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
	 * Returns the Sha1 of this file
	 *
	 * @return string
	 */
	public function getSha1() {
		return $this->getStorage()->hashFile($this, 'sha1');
	}

	/**
	 * Returns the Sha1 of this file
	 *
	 * @return string
	 */
	public function calculateChecksum() {
		return t3lib_div::shortMD5($this->originalFile->getUid() . $this->context . serialize($this->configuration));
	}


	/**
	 * Get the file extension of this file
	 *
	 * @return string The file extension
	 */
	public function getExtension() {
		// @todo: check how this gets fetched
		return $this->properites['extension'];
	}

	/**
	 * Get the MIME type of this file
	 *
	 * @return array file information
	 */
	public function getMimeType() {
		// @todo: check how this gets fetched
		return $this->properties['mimetype'];
	}

	/**
	 * Returns the fileType of this file
	 *
	 * @return int $fileType
	 */
	public function getType() {
		// @todo: check how this gets fetched
		return $this->originalFile->getType();
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
		// @todo: check how this gets fetched
		// TODO: Make this return contents of processed file
		return $this->originalFile->getContents();
	}

	/**
	 * Replace the current file contents with the given string
	 *
	 * @param string $contents The contents to write to the file.
	 * @return t3lib_file_File The file object (allows chaining).
	 */
	public function setContents($contents) {
		throw new Exception('Setting contents not possible for processed file.', 1305438528);
	}




	/****************************************
	 * STORAGE AND MANAGEMENT RELATED METHDOS
	 ****************************************/

	/**
	 * Get the storage the original file is located in
	 *
	 * @return t3lib_file_Storage
	 */
	public function getStorage() {
		// TODO: Make this return storage of processed file
		return $this->originalFile->getStorage();
	}

	/**
	 * Returns the identifier of the underlying original file
	 *
	 * @return string
	 */
	public function getIdentifier() {
		return $this->properties['identifier'];
	}

	/**
	 * Deletes this file from its storage. This also means that this object becomes useless.
	 *
	 * @return bool TRUE if deletion succeeded
	 */
	public function delete() {
		$this->getStorage->deleteFile($this);
	}

	/**
	 * Renames this file.
	 *
	 * @param $newName The new file name
	 * @return t3lib_file_File
	 */
	public function rename($newName) {
		return $this;
	}

	/**
	 * Returns TRUE if this file is indexed
	 *
	 * @return bool
	 */
	public function isIndexed() {
		return FALSE;
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
	 * Returns TRUE if this file is already processed.
	 *
	 * @return bool
	 */
	public function isProcessed() {
		return $this->isProcessed;
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
		// TODO: Make this return local processing path of processed file
		// return $this->getForLocalProcessing($writable);
	}

	/**
	 * @return \t3lib_file_File
	 */
	public function getOriginalFile() {
		return $this->originalFile;
	}

	/**
	 * called right after the object is instantiated and additionally populated with
	 * data from the DB
	 *
	 * @param array $properties
	 */
	public function updateProperties(array $properties) {
		// @todo: define what is allowed to update
		$this->properties = array_merge($this->properties, $properties);
	}

	/**
	 * called when the processed file is processed
	 *
	 * @param boolean $isProcessed
	 * @return void
	 */
	public function setIsProcessed(boolean $isProcessed) {
		$this->isProcessed = $isProcessed;

			// DB-query to insert the info
		$processedFileRepository = t3lib_div::makeInstance('t3lib_file_Repository_ProcessedFileRepository');
		$processedFileRepository->add($this);
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
			'is_processed' => $this->isProcessed,
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