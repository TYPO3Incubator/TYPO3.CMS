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
	 * @param array $processingConfiguration
	 */
	public function __construct(t3lib_file_File $originalFile, array $processingConfiguration) {
		$this->propertiesOfFileReference = $processingConfiguration;
		$this->originalFile = $originalFile;
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
		return $this->originalFile->hasProperty($key);
	}

	/**
	 * Returns true if the given key exists for this file.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function getProperty($key) {
		return $this->originalFile->getProperty($key);
	}

	public function getProperties() {
		return $this->originalFile->getProperties();
	}

	/**
	 * Returns the name of this file
	 *
	 * @return string
	 */
	public function getName() {
		return $this->originalFile->getName();
	}

	/**
	 * Returns the title text to this image
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->originalFile->getTitle();
	}


	/**
	 * Returns the alternative text to this image
	 *
	 * @return string
	 */
	public function getAlternative() {
		return $this->originalFile->getAlternative();
	}

	/**
	 * Returns the description text to this file
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->originalFile->getDescription();
	}

	/**
	 * Returns the link that should be active when clicking on this image
	 *
	 * @return string
	 */
	public function getLink() {
		return $this->originalFile->getLink();
	}

	/**
	 * Returns the uid of the original file
	 *
	 * @return int
	 */
	public function getUid() {
		return $this->originalFile->getUid();
	}

	/**
	 * Returns the size of this file
	 *
	 * @return int
	 */
	public function getSize() {

		// TODO: Make this return size of processed file

		return $this->originalFile->getSize();
	}

	/**
	 * Returns the Sha1 of this file
	 *
	 * @return string
	 */
	public function getSha1() {

		// TODO: Make this return sha1 of processed file

		return $this->originalFile->getSha1();
	}


	/**
	 * Get the file extension of this file
	 *
	 * @return string The file extension
	 */
	public function getExtension() {

		// TODO: Make this return extension of processed file

		return $this->originalFile->getExtension();
	}

	/**
	 * Get the MIME type of this file
	 *
	 * @return array file information
	 */
	public function getMimeType() {

		// TODO: Make this return mime type of processed file

		return $this->originalFile->getMimeType();
	}

	/**
	 * Returns the fileType of this file
	 *
	 * @return int $fileType
	 */
	public function getType() {
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
		return $this->originalFile->getIdentifier();
	}


	/**
	 * Returns a combined identifier of the underlying original file
	 *
	 * @return string Combined storage and file identifier, e.g. StorageUID:path/and/fileName.png
	 */
	public function getCombinedIdentifier() {
		return $this->originalFile->getCombinedIdentifier();
	}

	/**
	 * Deletes only this particular FileReference from the persistence layer (database table sys_file_reference)
	 * but leaves the original file untouched.
	 *
	 * @return bool TRUE if deletion succeeded
	 */
	public function delete() {
		return $this->originalFile->delete();
	}

	/**
	 * Renames the fileName in this particular usage.
	 *
	 * @param $newName The new name
	 * @return t3lib_file_FileReference
	 */
	public function rename($newName) {
		return $this->originalFile->rename($newName);
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

		// TODO: Make this return public URL of processed file

		return $this->originalFile->getPublicUrl();
	}

	/**
	 * Returns TRUE if this file is indexed.
	 * This is always false for ProcessedFile objects, as they are only generated on the fly.
	 *
	 * @return bool
	 */
	public function isIndexed() {
		return false;
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

		return $this->originalFile->getForLocalProcessing($writable);
	}

	/**
	 * Returns an array representation of the file.
	 * (This is used by the generic listing module vidi when displaying file records.)
	 *
	 * @return array Array of main data of the file. Don't rely on all data to be present here, it's just a selection of the most relevant information.
	 */
	public function toArray() {
		return $this->originalFile->toArray();
	}

	/**
	 * @return \t3lib_file_File
	 */
	public function getOriginalFile() {
		return $this->originalFile;
	}
}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/class.t3lib_file_file.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/vfs/class.t3lib_file_file.php']);
}

?>