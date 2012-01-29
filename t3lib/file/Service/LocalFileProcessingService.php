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
 * Abstract file processing service
 *
 * @author Oliver Hader <oliver.hader@typo3.org>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_Service_LocalFileProcessingService extends t3lib_file_Service_AbstractFileProcessingService {
	/**
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