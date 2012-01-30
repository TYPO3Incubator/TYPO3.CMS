<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Oliver Hader <oliver.hader@typo3.org>
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
 * File processing service
 *
 * @author Oliver Hader <oliver.hader@typo3.org>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_Service_FileProcessingService {
	const SIGNAL_PreProcess = 'preProcess';
	const SIGNAL_PostProcess = 'postProcess';

	/**
	 * @var t3lib_file_Storage
	 */
	protected $storage;

	/**
	 * @var t3lib_file_Driver_AbstractDriver
	 */
	protected $driver;

	/**
	 * Creates this object.
	 *
	 * @param t3lib_file_Storage $storage
	 * @param t3lib_file_Driver_AbstractDriver $driver
	 */
	public function __construct(t3lib_file_Storage $storage, t3lib_file_Driver_AbstractDriver $driver) {
		$this->storage = $storage;
		$this->driver = $driver;
	}

	/**
	 * Emits pre-processing signal.
	 *
	 * @param t3lib_file_File $file
	 * @param string $context
	 * @param array $configuration
	 */
	protected function emitPreProcess(t3lib_file_File $file, $context, array $configuration = array()) {
		t3lib_SignalSlot_Dispatcher::getInstance()->dispatch(
			't3lib_file_Service_FileProcessingService',
			self::SIGNAL_PreProcess,
			array($this->storage, $this->driver, $file, $context, $configuration)
		);
	}

	/**
	 * Processes the file.
	 *
	 * @param t3lib_file_File $file
	 * @param string $context
	 * @param array $configuration
	 * @return string
	 */
	public function process(t3lib_file_File $file, $context, array $configuration = array()) {
		switch ($context) {
			case $file::PROCESSINGCONTEXT_IMAGEPREVIEW:
				$url = $this->getPreviewUrl($file, $configuration);
				break;
			default:
				throw new RuntimeException('Unknown processing context ' . $context);
		}

		return $url;
	}

	/**
	 * Emits post-processing signal.
	 *
	 * @param t3lib_file_File $file
	 * @param $context
	 * @param array $configuration
	 */
	protected function emitPostProcess(t3lib_file_File $file, $context, array $configuration = array()) {
		t3lib_SignalSlot_Dispatcher::getInstance()->dispatch(
			't3lib_file_Service_FileProcessingService',
			self::SIGNAL_PostProcess,
			array($this->storage, $this->driver, $file, $context, $configuration)
		);
	}

	/**
	 * Gets the preview URL.
	 *
	 * @param t3lib_file_File $file
	 * @param array $configuration
	 * @return string
	 */
	protected function getPreviewUrl(t3lib_file_File $file, array $configuration) {
		$configuration = array_merge(
			array('width' => 64, 'height' => 64,),
			$configuration
		);

		$parameters = array(
			'dummy' => $GLOBALS['EXEC_TIME'],
			'file' => intval(($file->getUid())),
			'md5sum' => md5($file->getCombinedIdentifier() . '|' . $file->getMimeType() . '|' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']),
			'size' => $configuration['width'] . 'x' . $configuration['height'],
		);

		$url = t3lib_div::getRelativePathTo(PATH_typo3) . 'thumbs.php?' . t3lib_div::implodeArrayForUrl('', $parameters);
		return $url;
	}
}
?>