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
class t3lib_file_ProcessedFile extends t3lib_file_AbstractFile {

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
	 * Returns the Sha1 of this file
	 *
	 * @return string
	 */
	public function calculateChecksum() {
		return t3lib_div::shortMD5($this->originalFile->getUid() . $this->context . serialize($this->configuration));
	}





	/******************
	 * CONTENTS RELATED
	 ******************/

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
	 * Returns TRUE if this file is already processed.
	 *
	 * @return bool
	 */
	public function isProcessed() {
		return $this->isProcessed;
	}

	/**
	 * called when the processed file is processed
	 *
	 * @param boolean $isProcessed
	 * @return void
	 */
	public function setIsProcessed($isProcessed) {
		$this->isProcessed = $isProcessed;

			// DB-query to insert the info
		/** @var $processedFileRepository t3lib_file_Repository_ProcessedFileRepository */
		$processedFileRepository = t3lib_div::makeInstance('t3lib_file_Repository_ProcessedFileRepository');
		$processedFileRepository->add($this);
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
		if($properties['name']) {
			$this->name = $properties['name'];
		}
		if($properties['identifier']) {
			$this->identifier = $properties['identifier'];
		}
		if(t3lib_utility_Math::canBeInterpretedAsInteger($properties['storage'])) {
			$this->setStorage($properties['storage']);
		}
		$this->properties = array_merge($this->properties, $properties);
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