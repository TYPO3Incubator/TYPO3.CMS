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
 * A collection containing a static set of files. This collection is persisted to the database with references to all
 * files it contains.
 *
 * @author Steffen Ritter
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_Collection_StaticFileCollection extends t3lib_file_Collection_AbstractFileCollection {


	/**
	 * the type of file collection. Either static, filter or folder.
	 *
	 * @var string
	 */
	protected static $type = 'static';

	/**
	 * the items field name (ususally either criteria, items or folder)
	 *
	 * @var string
	 */
	protected static $itemsCriteriaField = 'items';

	/**
	 * table name of the records stored in this collection
	 *
	 * @var string
	 */
	protected $itemTableName = 'sys_file_reference';

	// same as function in StaticRecordCollection
	public function loadContents() {
		/**
		 * @var t3lib_file_Repository_FileRepository $fileRepository
		 */
		$fileRepository = t3lib_div::makeInstance('t3lib_file_Repository_FileRepository');
		$fileReferences = $fileRepository->findByRelation('sys_file_collection', 'files', $this->getIdentifier());

		foreach ($fileReferences as $file) {
			$this->add($file);
		}
	}

}