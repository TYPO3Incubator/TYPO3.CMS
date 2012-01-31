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
	 * @param t3lib_file_ProcessedFile $processedFile
	 * @param t3lib_file_File $file
	 * @param string $context
	 * @param array $configuration
	 */
	protected function emitPreProcess(t3lib_file_ProcessedFile $processedFile, t3lib_file_File $file, $context, array $configuration = array()) {
		return t3lib_SignalSlot_Dispatcher::getInstance()->dispatch(
			't3lib_file_Service_FileProcessingService',
			self::SIGNAL_PreProcess,
			array($this->storage, $this->driver, $processedFile, $file, $context, $configuration)
		);
	}

	/**
	 * Processes the file.
	 *
	 * @param t3lib_file_File $file
	 * @param string $context
	 * @param array $configuration
	 * @return t3lib_file_ProcessedFile
	 */
	public function process(t3lib_file_File $file, $context, array $configuration = array()) {

		// first step, create "processed file"
		$fileFactory = t3lib_div::makeInstance('t3lib_file_Factory');
		/* @var $processedFile t3lib_file_ProcessedFile */
		$processedFile = $fileFactory->getProcessedFileObject($file, $context, $configuration);

			// call a signal to process the file by another way
		$this->emitPreProcess($processedFile, $file, $context, $configuration);

			// only do something if the file is not processed yet
			// (maybe modified or already processed by a signal)
			// or (in case of preview images) already in the DB/in the processing folder
		if (!$processedFile->isProcessed()) {
			switch ($context) {
				case $file::PROCESSINGCONTEXT_IMAGEPREVIEW:
					$this->processImagePreview($processedFile, $file, $configuration);
					break;
				default:
					throw new RuntimeException('Unknown processing context ' . $context);
			}
		}

			// call a signal to process the file by another way
		$this->emitPostProcess($processedFile, $file, $context, $configuration);

		return $processedFile;
	}

	/**
	 * Emits post-processing signal.
	 *
	 * @param t3lib_file_ProcessedFile $processedFile
	 * @param t3lib_file_File $file
	 * @param $context
	 * @param array $configuration
	 */
	protected function emitPostProcess(t3lib_file_ProcessedFile $processedFile, t3lib_file_File $file, $context, array $configuration = array()) {
		t3lib_SignalSlot_Dispatcher::getInstance()->dispatch(
			't3lib_file_Service_FileProcessingService',
			self::SIGNAL_PostProcess,
			array($this->storage, $this->driver, $processedFile, $file, $context, $configuration)
		);
	}

	/**
	 * this method actually does the processing of files locally
	 *
	 * takes the original file (on remote storages this will be fetched from the remote server)
	 * does the IM magic on the local server by creating a temporary typo3temp/ file
	 * copies the typo3temp/ file to the processingfolder of the target storage
	 * removes the typo3temp/ file
	 *
	 * @param t3lib_file_ProcessedFile $processedFile
	 * @param t3lib_file_File $file
	 * @param array $configuration
	 * @return string
	 */
	protected function processImagePreview(t3lib_file_ProcessedFile $processedFile, t3lib_file_File $file, array $configuration) {

			// merge custom configuration with default configuration
		$configuration = array_merge(
			array('width' => 64, 'height' => 64),
			$configuration
		);

		$configuration['width'] = t3lib_utility_Math::forceIntegerInRange($configuration['width'], 1, 1000);
		$configuration['height'] = t3lib_utility_Math::forceIntegerInRange($configuration['height'], 1, 1000);

		$originalFileName = $file->getForLocalProcessing(FALSE);

			// create a temporary file in typo3temp/
		if ($file->getExtension() == 'jpg') {
			$targetFileExtension = '.jpg';
		} else {
			$targetFileExtension = '.png';
		}
		$targetFolder = $this->storage->getProcessingFolder();
		$targetFilename = 'preview_' . $processedFile->calculateChecksum() . $targetFileExtension;

			// do the actual processing
		if (!$targetFolder->hasFile($targetFilename)) {
				// create the thumb filename in typo3temp/preview_....jpg
			$temporaryFileName = t3lib_div::tempnam('preview_') . $targetFileExtension;

				// Check file extension
			if ($file->getType() != $file::FILETYPE_IMAGE && !t3lib_div::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], $file->getExtension())) {
				// create a default image
				$this->getTemporaryImageWithText($temporaryFileName, 'Not imagefile!', 'No ext!', $file->getName());
			} else {
					// create the temporary file
				if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['im']) {
					$parameters = '-sample ' . $configuration['width'] . 'x' . $configuration['height'] .  ' ' . $this->wrapFileName($originalFileName) . '[0] ' . $this->wrapFileName($temporaryFileName);
					$cmd = t3lib_div::imageMagickCommand('convert', $parameters);
					t3lib_utility_Command::exec($cmd);
					if (!file_exists($temporaryFileName)) {
						// create a error gif
						$this->getTemporaryImageWithText($temporaryFileName, 'No thumb','generated!', $file->getName());
					}
				}

			}


			if (file_exists($temporaryFileName)) {
				t3lib_div::fixPermissions($temporaryFileName);

					// copy the temporary file to the processedFolder
				$this->driver->addFileRaw($temporaryFileName, $targetFolder, $targetFilename);

					// @todo: update the DB properties and the object properties

					// remove the temporary file as it's not necessary anymore
				//t3lib_div::unlink_tempfile($temporaryFileName);
			}
		}
	}


	protected function processFilePreview() {
		// if jpg => image preview
		// if font => font preview
		// if other => icon
	}

	/**
	 * Escapes a file name so it can safely be used on the command line.
	 *
	 * @param string $inputName filename to safeguard, must not be empty
	 *
	 * @return string $inputName escaped as needed
	 */
	protected function wrapFileName($inputName) {
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem']) {
			$currentLocale = setlocale(LC_CTYPE, 0);
			setlocale(LC_CTYPE, $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLocale']);
		}
		$escapedInputName = escapeshellarg($inputName);
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem']) {
			setlocale(LC_CTYPE, $currentLocale);
		}
		return $escapedInputName;
	}

	/**
	 * Creates error image based on gfx/notfound_thumb.png
	 * Requires GD lib enabled, otherwise it will exit with the three textstrings outputted as text.
	 * Outputs the image stream to browser and exits!
	 *
	 * @param	string		Text line 1
	 * @param	string		Text line 2
	 * @param	string		Text line 3
	 * @return	void
	 */
	protected function getTemporaryImageWithText($filename, $textline1, $textline2, $textline3) {
		if (!$GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib']) {
			throw new RuntimeException(
				'TYPO3 Fatal Error: No gdlib. ' . $textline1 . ' ' . $textline2 . ' ' . $textline3,
				1270853952
			);
		}

			// Creates the basis for the error image
		if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib_png']) {
			$im = imagecreatefrompng(PATH_typo3 . 'gfx/notfound_thumb.png');
		} else {
			$im = imagecreatefromgif(PATH_typo3 . 'gfx/notfound_thumb.gif');
		}

			// Sets background color and print color.
		$white = imageColorAllocate($im, 255, 255, 255);
		$black = imageColorAllocate($im, 0, 0, 0);

			// Prints the text strings with the build-in font functions of GD
		$x=0;
		$font=0;
		if ($textline1) {
			imagefilledrectangle($im, $x, 9, 56, 16, $white);
			imageString($im, $font, $x, 9, $textline1, $black);
		}
		if ($textline2) {
			imagefilledrectangle($im, $x, 19, 56, 26, $white);
			imageString($im, $font, $x, 19, $textline2, $black);
		}
		if ($textline3) {
			imagefilledrectangle($im, $x, 29, 56, 36, $white);
			imageString($im, $font, $x, 29, substr($textline3, -14), $black);
		}

			// Outputting the image stream and exit
		if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['gdlib_png']) {
			imagePng($im, $filename);
		} else {
			imageGif($im, $filename);
		}
	}

}
?>