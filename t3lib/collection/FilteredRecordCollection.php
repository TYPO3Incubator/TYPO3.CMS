<?php

class t3lib_collection_FilteredRecordCollection extends t3lib_collection_AbstractRecordCollection {

	/**
	 * serialized filter criteria
	 *
	 * @var string
	 */
	protected $criteria;
	
	public function fromArray(array $array) {
		parent::fromArray($array);
		$this->setCriteria($array['criteria']);
	}

	public function toArray() {
		$array = parent::toArray();
		$array['criteria'] = $this->getCriteria();
		return $array;
	}


	protected function getPersistableDataArray() {
		return array(
			'title' => $this->getTitle(),
			'type'	=> 'filter',
			'table_name' => $this->getItemTableName(),
			'description' => $this->getDescription(),
			'criteria' => $this->getCriteria()
		);
	}

	public function loadContents() {
		// TODO: Implement loadContents() method.
	}

	/**
	 * @param string $criteria
	 */
	public function setCriteria($criteria) {
		$this->criteria = $criteria;
	}

	/**
	 * @return string
	 */
	public function getCriteria() {
		return $this->criteria;
	}

}
