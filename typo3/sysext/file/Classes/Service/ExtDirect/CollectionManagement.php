<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Steffen Ritter <typo3@steffen-ritter.net>
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
 * ExtDirect Endpoint for Stores of Vidi Model Type "StaticCollection"
 * which uses FileCollections as Storage
 *
 * @version $Id$
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @author Steffen Ritter <typo3@steffen-ritter.net>
 */
class Tx_File_Service_ExtDirect_CollectionManagement {

	/**
	 * @var t3lib_file_Repository_FileCollectionRepository
	 */
	protected $collectionRepository;

	public function __construct() {
		/** @var t3lib_file_Repository_FileCollectionRepository $collectionRepository */
		$this->collectionRepository = t3lib_div::makeInstance('t3lib_file_Repository_FileCollectionRepository');
	}

	/**
	 * loads all filter-Objects from the repository
	 *
	 * @param stdObject $params		Not used with file-collections as they are not module dependent
	 * @return array
	 */
	public function read($params) {
		$collectionObjects = $this->collectionRepository->findByType(t3lib_collection_RecordCollectionRepository::TYPE_Static);

		$filters = array();
		/** @var t3lib_file_Collection_StaticFileCollection $collection */
		foreach ($collectionObjects AS $collection) {
			$filters[] = $collection->toArray();

		}

		return array(
			'data' => $filters,
			'total'=> count($filters),
			'debug'=> mysql_error()
		);
	}

	/**
	 * creates a new filter
	 *
	 * @param stdObject $newFilter
	 * @return void
	 */
	public function create($newFilter) {
		/** @var t3lib_file_Collection_StaticFileCollection $filter */
		$filter = t3lib_div::makeInstance('t3lib_file_Collection_StaticFileCollection');

		$dataArray = array(
			'uid'			=> 0,
			'table_name'	=> $newFilter->tableName,
			'description'	=> $newFilter->description,
			'title'			=> $newFilter->title
		);

		$filter->fromArray($dataArray);
		foreach ($newFilter->items AS $uid) {
			$filter->add(array('uid' => $uid));
		}
		$filter->persist();
	}

	/**
	 * updates an already persisted $filter within the underlying storage
	 *
	 * @param stdObject $filterToUpdate
	 * @return void
	 */
	public function update($filterToUpdate) {
		/** @var t3lib_file_Collection_StaticFileCollection $filter */
		$filter = t3lib_div::makeInstance('t3lib_file_Collection_StaticFileCollection');
		$filter->fromArray(array(
			'uid'			=> $filterToUpdate->uid,
			'table_name'	=> $filterToUpdate->tableName ,
			'description'	=> $filterToUpdate->description,
			'title'			=> $filterToUpdate->title,
		));

		$filter->persist();
	}

	/**
	 * deletes single Filter from the persistence
	 *
	 * @param stdObject $filter
	 * @return void
	 */
	public function destroy($filter) {
		$this->collectionRepository->deleteByUid($filter->uid);
	}
}
