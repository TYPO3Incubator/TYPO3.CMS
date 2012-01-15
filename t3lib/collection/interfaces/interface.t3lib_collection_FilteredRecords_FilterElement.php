<?php

// @todo This class should be put to t3lib/collection/FilteredRecords/ --olly
interface t3lib_collection_FilteredRecords_FilterElement {

	/**
	 * generates
	 *
	 * @param stdClass $elementObject representation des filter
	 * @return string SQL
	 */
	public function generateSQLRepresentation(stdClass $elementObject);

	/**
	 * checks wether the handed row matches the filter
	 *
	 * @param stdClass $elementObject
	 * @param array $row
	 * @return boolean
	 */
	public function doesArrayMatchFilter(stdClass $elementObject, array $row);

}