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
 * Abstract resource publisher
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage t3lib
 */
abstract class t3lib_file_Service_Publishing_AbstractPublisher implements t3lib_file_Service_Publishing_Publisher {

	/**
	 * The base URI this publisher uses
	 *
	 * @var string
	 */
	protected $baseUri;

	/**
	 * The target to be used by this publisher
	 *
	 * @var t3lib_file_Storage
	 */
	protected $publishingTarget;

	/**
	 * The configuration for this publisher
	 *
	 * @var array
	 */
	protected $configuration;

	/**
	 * The directory inside the publishing target we should copy files to.
	 *
	 * @var string
	 */
	protected $baseDir;

	public function __construct(t3lib_file_Storage $publishingTarget, array $configuration) {
		$this->setPublishingTarget($publishingTarget);

		$this->configuration = $configuration;
		if (isset($this->configuration['baseDir'])) {
			$this->baseDir = '/' . trim($this->configuration['baseDir'], '/');

			if (!$this->publishingTarget->hasFolder($this->baseDir)) {
				$this->publishingTarget->createFolder($this->baseDir, $publishingTarget->getRootLevelFolder());
			}
		}
	}

	protected function setPublishingTarget(t3lib_file_Storage $publishingTarget) {
		// TODO check if publishing target is publicly accessible
		$this->publishingTarget = $publishingTarget;
		$this->baseUri = $this->publishingTarget->getBaseUri();
	}

	/**
	 * Returns the path a file should be published under.
	 *
	 * @param t3lib_file_File $file
	 * @return string
	 */
	protected function getPublicPathForFile(t3lib_file_File $file) {
		if (isset($this->baseDir)) {
			return $this->baseDir . $file->getIdentifier();
		} else {
			return $file->getIdentifier();
		}
	}
}