<?php

class t3lib_collection_RecordCollectionRepository {

	/**
	 * the table name collections are stored to
	 *
	 * @var string
	 */
	protected $table = 'sys_collection';

	/**
	 * @param $uid
	 * @return void
	 */
	public function findByUid($uid) {
		$data = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('uid', $this->table, 'uid = ' . intval($uid) . " " . t3lib_BEfunc::deleteClause('sys_collection'));
		return $this->createDomainObject($data);
	}

	public function findByRecordType($tableName) {
		return $this->findByTypeAndRecord(NULL, $tableName);
	}

	public function findByType($type) {
		return $this->findByTypeAndRecord($type, NULL);
	}

	public function findByTypeAndRecord($type, $tableName) {

		// @TODO: Check if we should escape the SQL below. Or have a positive list of types.

		$where = array();
		if ($type !== NULL) {
			$where[] = 'type = \'' . $type . "'";
		}

		if ($tableName !== NULL) {
			$where[] = 'table_name = \'' . $tableName . "'";
		}

		$data = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, type',
			$this->table,
			implode(' AND ', $where) . t3lib_BEfunc::deleteClause($this->table)
		);
		return $this->createManyDomainObjects($data);
	}

	public function deleteByUid($uid) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->table, 'uid = ' . intval($uid), array('deleted' => 1, 'tstamp' => time()));
	}

	/**
	 * @param array $record
	 * @return t3lib_collection_RecordCollection
	 */
	protected function createDomainObject(array $record) {
		switch ($record['type']) {
			case 'static':
				$collection = t3lib_collection_StaticRecordCollection::load($record['uid']);
				break;
			case 'filter':
				$collection = t3lib_collection_FilteredRecordCollection::load($record['uid']);
				break;
		}
		return $collection;
	}

	protected function createManyDomainObjects(array $data) {
		$collections = array();
		foreach ($data AS $collection) {
			$collections[] = $this->createDomainObject($collection);
		}
		return $collections;
	}
}
