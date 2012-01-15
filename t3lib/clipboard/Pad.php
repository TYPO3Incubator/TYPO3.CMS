<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2012 Steffen Ritter <typo3@steffen-ritter.net>
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
 * Pad represents an collection of ClipBoard entries which might be records of several types.
 * Also the mode of each Pad ist place
 *
 * @author Steffen Ritter <typo3@steffen-ritter.net>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_clipboard_Pad implements t3lib_collection_Collection, t3lib_collection_Persistable {

	const SPLIT_CHAR = '|';

	const MODE_CUT  = 0;
	const MODE_COPY = 1;

	/**
	 * the current Clipboard Pad
	 *
	 * @var array
	 */
	protected $data = array();
	/**
	 * the Number of the ClipBoard-Pad
	 *
	 * @var int
	 */
	protected $identifier;

	/**
	 * the clipBoard mode
	 * @var int
	 */
	protected $mode = self::MODE_CUT;

	/**
	 * save all instances - each for each each pad
	 *
	 * @var array
	 */
	protected static $instances = array();

	/**
	 * constructor beeing protected to only have one Instance per Pad
	 */
	protected function __construct() {
	}

	/**
	 * destructor automatically should persist the clipboard
	 */
	public function __destruct() {
		//$this->persist();
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Return the current element
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return mixed Can return any type.
	 */
	public function current() {
		return key($this->data);
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Move forward to next element
	 * @link http://php.net/manual/en/iterator.next.php
	 * @return void Any returned value is ignored.
	 */
	public function next() {
		next($this->data);
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Return the key of the current element
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return scalar scalar on success, integer
	 * 0 on failure.
	 */
	public function key() {
		return key($this->data);
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Checks if current position is valid
	 * @link http://php.net/manual/en/iterator.valid.php
	 * @return boolean The return value will be casted to boolean and then evaluated.
	 * Returns true on success or false on failure.
	 */
	public function valid() {
		return current($this->data) !== FALSE;
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Rewind the Iterator to the first element
	 * @link http://php.net/manual/en/iterator.rewind.php
	 * @return void Any returned value is ignored.
	 */
	public function rewind() {
		reset($this->data);
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or &null;
	 */
	public function serialize() {
		return serialize($this->toArray());
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized <p>
	 * The string representation of the object.
	 * </p>
	 * @return mixed the original value unserialized.
	 */
	public function unserialize($serialized) {
		// TODO: Implement unserialize() method.
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Count elements of an object
	 * @link http://php.net/manual/en/countable.count.php
	 * @return int The custom count as an integer.
	 * </p>
	 * <p>
	 * The return value is cast to an integer.
	 */
	public function count() {
		return count($this->data);
	}

	/**
	 * Getter for the Identifier
	 * represents the Pad Number within the clipboard
	 * 
	 * @return int
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * sets the Pad Identifier which basically is the pad number
	 *
	 * @param int $id
	 * @return void
	 */
	public function setIdentifier($id) {
		$this->identifier = intval($id);
	}

	/**
	 * factory function to create an pad for $id
	 *
	 * @static
	 * @param string $id
	 * @param boolean $fillItems
	 * @return t3lib_collection_Collection
	 */
	public static function load($id, $fillItems = false) {
		$id = intval($id);
		t3lib_clipboard_Clipboard::$doPersist = $GLOBALS['BE_USER']->getTSConfigVal('options.saveClipboard');
		if (!key_exists($id, self::$instances)) {
			/** @var t3lib_clipboard_Pad $instance */
			$instance = new self();
			$instance->setIdentifier($id);
			$instance->loadContents();

			self::$instances[$id] = $instance;
		}
		return self::$instances[$id];
	}

	/**
	 * creates an array representation of this clipboard pad
	 *
	 * @return array
	 */
	protected function toArray() {
		return array(
			'mode' => $this->getMode(),
			'elements' => $this->data
		);
	}

	/**
	 * persist the clipboard to the underlying storage
	 * 
	 * @return void
	 */
	public function persist() {
		$this->setData($this->toArray());
	}

	/**
	 * loads the clipboard data from its underlying storage
	 *
	 * @return void
	 */
	public function loadContents() {
		$data = $this->getData();
		$this->clear();
		$this->mode = $data['mode'] == 1 ? self::MODE_COPY : self::MODE_CUT;
		foreach ((array)$data['elements'] AS $element => $selected) {
			list($type, $identifier) = t3lib_div::trimExplode(self::SPLIT_CHAR, $element, FALSE, 2);
			switch ($type) {
				case '_FILE':
					$valid = t3lib_div::makeInstance('t3lib_file_Factory')
								->getFileObjectFromCombinedIdentifier($identifier) !== NULL;
					break;
				case '_FOLDER':
					$valid = t3lib_div::makeInstance('t3lib_file_Factory')
								->getFolderObjectFromCombinedIdentifier($identifier) !== NULL;
					break;
				default:
					$valid = is_array(t3lib_BEfunc::getRecord($type, $identifier));
			}
			if ($valid) {
				$this->add($type, $identifier, $selected);
			}
		}
	}

	/**
	 * @param int $mode should only be self::MODE_COPY or self::MODE_MOVE
	 */
	public function setMode($mode) {
		$this->mode = $mode;
	}

	/**
	 * returns the mode of the clipboard pad
	 * should be COPY or MOVE
	 *
	 * @return int
	 */
	public function getMode() {
		return $this->mode;
	}

	/**
	 * adds an entry to the clipboard pad
	 *
	 * @param string $type
	 * @param string $identifier
	 * @param bool $selectionStatus
	 * @return bool
	 */
	public function add($type, $identifier, $selectionStatus = false) {
		if (!$this->has($type, $identifier)) {
			$this->data[$type . self::SPLIT_CHAR . $identifier] = $selectionStatus;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * checks if an entry is present in this clipboard pad
	 *
	 * @param $type
	 * @param $identifier
	 * @return bool
	 */
	public function has($type, $identifier) {
		$entry = $type . self::SPLIT_CHAR . $identifier;
		return key_exists($entry, $this->data);
	}

	/**
	 * returns the selection status of 
	 *
	 * @param $type
	 * @param $identifier
	 * @return bool
	 */
	public function isSelected($type, $identifier) {
		return $this->data[$type . self::SPLIT_CHAR . $identifier] === true;
	}

	/**
	 * removes an entry of this clipboard pad
	 *
	 * @param $type
	 * @param $identifier
	 * @return bool	success
	 */
	public function remove($type, $identifier) {
		if ($this->has($type, $identifier)) {
			unset($this->data[$type . self::SPLIT_CHAR . $identifier]);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * returns array of all entries which are selected
	 *
	 * @return array
	 */
	public function getSelected() {
		return array_keys(array_filter($this->data, function($entry){ return $entry == true;}));
	}

	/**
	 * reset the current Pad
	 * @return void
	 */
	public function clear() {
		$this->data = array();
	}

	protected function getData() {
		if (t3lib_clipboard_Clipboard::$doPersist) {
			return (array)$GLOBALS['BE_USER']->uc['moduleData']['clipboard']['pads'][$this->getIdentifier()];
		} else {
			$sessionData = $GLOBALS['BE_USER']->getSessionData('clipboard');
			return (array)$sessionData['pads'][$this->getIdentifier()];
		}
	}

	protected function setData(array $array) {
		if (t3lib_clipboard_Clipboard::$doPersist) {
			$GLOBALS['BE_USER']->uc['moduleData']['clipboard']['pads'][$this->getIdentifier()] = $array;
		} else {
			$sessionData = $GLOBALS['BE_USER']->getSessionData('clipboard');
			$sessionData['pads'][$this->getIdentifier()] = $array;
			$GLOBALS['BE_USER']->setAndSaveSessionData('clipboard', $sessionData);
		}
	}
}
