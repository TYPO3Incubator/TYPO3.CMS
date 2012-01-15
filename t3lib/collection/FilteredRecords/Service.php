<?php

class t3lib_collection_FilteredRecords_Service {
	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @var array[]
	 */
	protected $query;

	public function __construct($table) {
		$this->table = $table;
	}

	// @todo If we want the collection to be generic, array should be used instead of JSON --olly
	// @todo If query is a class member it should be part of the constructor instead, otherwise it could be singleton and submitted to getVirtualFileFilters --olly
	public function initializeQuery($query) {
		$this->query = json_decode($query);
		$this->groupQueryByOperatorBinding();
	}

	public function getVirtualFieldFilters() {
		$found = array();
		foreach ($this->query as $filter) {
			$found = array_merge($found, $this->getVirtualFieldFilterSingle($filter));
		}
		return $found;
	}

	protected function getVirtualFieldFilterSingle($filter) {
		$found = array();
		if ($filter->type == 'field' && substr($filter->field, 0, 1) == '_') {
				$found[] = $filter;
		} elseif($filter->type == 'operator') {
				$found = array_merge($found, $this->getVirtualFieldFilterSingle($filter->left));
				$found = array_merge($found, $this->getVirtualFieldFilterSingle($filter->right));
		}
		return $found;
	}

	public function generateWhereClause() {
		$array = array();
		foreach ($this->query AS $object) {
			$array[] = $this->buildClauseSingle($object);
		}
		return implode(' AND ', $array);
	}

	protected function groupQueryByOperatorBinding() {
			// remove and operators as they are implicit
		foreach($this->query as $index => $filterLabel) {
			if ($filterLabel == '&&') {
				unset($this->query[$index]);
			}
		}
			// remove double || operators
		for ($i = 0; $i < count($this->query) - 1; $i++) {
			if ($this->query[$i] == '||' && $this->query[$i + 1] == '||') {
				unset($this->query[$i]);
			}
		}
			// reindex after removal
		$this->query = array_values($this->query);

			// remove operators at beginning and ending
		if ($this->query[0] == '||') {
			unset($this->query[0]);
		}
		if ($this->query[count($this->query) - 1] == '||') {
			unset($this->query[count($this->query) - 1]);
		}

			// group the or operators;
		while (in_array('||', $this->query)) {
			for($i = 0; $i < count($this->query) - 1; $i++) {
				if ($this->query[$i] == '||') {
					// @todo Does not seem to be a good idea to override array elements with objects here, use real call additionally --olly
					$this->query[$i] = new stdClass();
					$this->query[$i]->type = 'or';
					$this->query[$i]->left = $this->query[$i - 1];
					$this->query[$i]->right = $this->query[$i + 1];
					unset($this->query[$i - 1]);
					unset($this->query[$i + 1]);
				}
			}
			$this->query = array_values($this->query);
		}
	}

	protected function buildClauseSingle(stdClass $filterParam) {
		switch ($filterParam->type) {
			case 'or':
				$result = $this->buildClauseSingle_or($filterParam);
				break;
			case 'fulltext':
				$result = $this->buildClauseSingle_fulltext($filterParam);
				break;
			case 'field':
				$result = $this->buildClauseSingle_field($filterParam);
				break;
			case 'collection':
				$result = $this->buildClauseSingle_collection($filterParam);
				break;
			default:
				if (NULL !== $processor = $this->getProcessorClass($filterParam->type)) {
					$result = $processor->generateSQLRepresentation($filterParam);
				} else {
					$result = ' 1=1 ';
				}
				break;
		}
		return $result;
	}

	protected function buildClauseSingle_or(stdClass $filterParam) {
		return ' (' . $this->buildClauseSingle($filterParam->left) . ' OR ' . $this->buildClauseSingle($filterParam->right) . ') ';
	}


	protected function buildClauseSingle_fulltext(stdClass $filterParam) {
		$searchFields = t3lib_div::trimExplode(',', $GLOBALS['TCA'][$this->table]['ctrl']['searchFields'], TRUE);
		if (!is_array($searchFields) || !count($searchFields)) {
			$searchFields = array($GLOBALS['TCA'][$this->table]['ctrl']['label']);
		}
		$array = array();
		$like = '\'%' . $GLOBALS['TYPO3_DB']->quoteStr($GLOBALS['TYPO3_DB']->escapeStrForLike($filterParam->string, $this->table), $this->table) . '%\'';

		foreach ($searchFields AS $field) {
			$array[] = $field . ' LIKE ' . $like;
		}
		return ' (' . implode(' OR ', $array) . ') ';
	}

	protected function buildClauseSingle_field(stdClass $filterParam) {
		$field = $GLOBALS['TYPO3_DB']->quoteStr(trim($filterParam->field), $this->table);
		$operator = $filterParam->operator;
		if (substr($filterParam->field, 0, 1) == '_') {
			return ' 1=1 ';
		}

		if ($operator == 'rel' || $operator == '!rel') {
			return $this->buildClauseSingle_relation($filterParam);
		} else {
			$search = $filterParam->search;

			switch ($operator) {
				case 'l':
				case '!l':
					$search = '\'%' . $GLOBALS['TYPO3_DB']->quoteStr($GLOBALS['TYPO3_DB']->escapeStrForLike($search, $this->table), $this->table) . '%\'';
					$operator = ($operator == '!l' ? ' NOT LIKE ' : ' LIKE ');
					break;
				case '=':
				case '!=':
					if (t3lib_utility_Math::canBeInterpretedAsInteger($search)) {
						$search = intval($search);
					} else {
						$search = "'" . $GLOBALS['TYPO3_DB']->quoteStr($search, $this->table) . "'";
					}
					$operator = ($operator == '!=' ? ' != ' : ' = ');
					break;
				default:
					$field = 1;
					$operator = 1;
					$search = 1;
			}
			return $field . $operator . $search;
		}
	}

	protected function buildClauseSingle_relation(stdClass $filterParam) {
		$field = $GLOBALS['TYPO3_DB']->quoteStr(trim($filterParam->field), $this->table);
		$relatedTable = $filterParam->relatedTable;
		$relatedUid = intval($filterParam->search);

		/** @var t3lib_TcaRelationService $relationService */
		$relationService = t3lib_div::makeInstance('t3lib_TcaRelationService', $relatedTable, NULL, $this->table, $field);

			// lets get all uids of type "$this->table" which have an relation to "$relatedTable" via column "$field"
		$uids = $relationService->getRecordUidsWithRelationToCurrentRecord(array('uid' => $relatedUid));

		if (count($uids) == 0) {
			return ' 1=1 ';
		} elseif ($filterParam->operator == 'rel') {
			return ' uid IN (' . implode(',', $uids) . ') ';
		} else {
			return ' uid NOT IN (' . implode(',', $uids) . ') ';
		}
	}

	protected function buildClauseSingle_collection($filterParam) {
		$uid = intval($filterParam->value);
		$collection = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'sys_collection', 'uid='. $uid);

		if ($collection['table_name'] !== $this->table) {
			return ' 1=1 ';
		} else {
			// @todo Complexity can be reduced, no default return value given --olly
			if ($collection['type'] == 'filter') {
				$filterService = new self($this->table);
				$filterService->initializeQuery($collection['criteria']);

				$sql = ' ( ' . $filterService->generateWhereClause() . ' ) ';
				if ($filterParam->operator == '!=') {
					$sql = ' NOT' . $sql;
				}
				return $sql;
			} elseif($collection['type'] == 'static') {
				/** @var $relationService t3lib_TcaRelationService */
				$relationService = t3lib_div::makeInstance('t3lib_TcaRelationService', 'sys_collection', 'items', $this->table);
				$entries = $relationService->getRecordUidsWithRelationFromCurrentRecord($collection);

				if ($filterParam->operator == '=') {
					return ' uid IN (' . implode(',', $entries) . ') ';
				} else {
					return ' uid NOT IN (' . implode(',', $entries) . ') ';
				}
			}
		}
	}

	/**
	 * if a custom filter is registered return it as handler
	 *
	 * @param string $type
	 * @return t3lib_collection_FilteredRecords_FilterElement
	 */
	protected function getProcessorClass($type) {
		// @todo Using things objects from EXTCONF is not the way we should proceed --olly
		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['vidi']['FilterBar']['availableFilterElements'][$type])) {
			$object = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['vidi']['FilterBar']['availableFilterElements'][$type]['processorClass'];
			if (!$object instanceof t3lib_collection_FilteredRecords_FilterElement) {
				$object = null;
			}
		} else {
			$object = null;
		}

		return $object;
	}

	public static function registerFilterLabel($xtype, $serialisationId, $title, $processingClass, $unique = FALSE) {
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['vidi']['FilterBar']['availableFilterElements'][$serialisationId] = array(
			'widgetName'	=> $xtype,
			'title'			=> $title,
			'processorClass'=> $processingClass,
			'unique'		=> $unique,
		);

	}
}