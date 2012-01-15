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
 * extracted Data-Layer which represents the clipboard of TYPO3 Backend
 *
 * @author Steffen Ritter <typo3@steffen-ritter.net>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_clipboard_Clipboard implements t3lib_Singleton {

	/**
	 * is persistence configured for user
	 */
	public static $doPersist = true;

	/**
	 * how many pads will the clipboard provide
	 * @var int
	 */
	protected $numberOfPads = 3;

	/**
	 * is locked to pad 0
	 * @var bool
	 */
	protected $lockedToNormal = false;

	/**
	 * storage to store display specific options
	 * @var array
	 */
	protected $renderData = array();

	/**
	 * reference to the current pad
	 * @var t3lib_clipboard_Pad
	 */
	protected $currentPad = NULL;

	public function __construct() {
		self::$doPersist = $GLOBALS['BE_USER']->getTSConfigVal('options.saveClipboard');

			// get The number of pads from user TS
		$configuredAmount = $GLOBALS['BE_USER']->getTSConfigVal('options.clipboardNumberPads');
		if (t3lib_utility_Math::canBeInterpretedAsInteger($configuredAmount) && $configuredAmount >= 0) {
			$this->numberOfPads = t3lib_utility_Math::forceIntegerInRange($configuredAmount, 0, 20);
		}

		$moduleData = $this->getData();
		$this->currentPad = $this->createPad(intval($moduleData['current']));
		$this->renderData = $moduleData['renderData'];
	}
	/**
	 * object destructor
	 */
	public function __destruct() {
		//	$this->persist();
	}

	/**
	 * returns the clipboard pad
	 * ensures that the pad is not higher than allowed
	 *
	 * @param int $id
	 * @return t3lib_clipboard_Pad
	 */
	protected function createPad($id) {
		return t3lib_clipboard_Pad::load(t3lib_utility_Math::forceIntegerInRange($id, 0, $this->numberOfPads - 1, 0));
	}

	/**
	 * switch the current pad
	 *
	 * @param int $newPadId
	 * @return void
	 */
	public function switchPad($newPadId) {
		if (!$this->lockedToNormal) {
			$this->currentPad = $this->createPad($newPadId);
		}
	}

	public function lockToNormal() {
		$this->lockedToNormal = true;
		$this->currentPad = $this->createPad(0);
	}

	/**
	 * persists the clipboard to the storage
	 *
	 * @return void
	 */
	public function persist() {
		for ($i = 0; $i < $this->numberOfPads; $i++) {
			$this->createPad($i)->persist();
		}
		$moduleData = $this->getData();
		$moduleData['current'] = $this->getActivePadId();
		$moduleData['renderData'] = $this->renderData;
		$this->setData($moduleData);
	}

	/**
	 * returns the current active clipboard pad
	 *
	 * @param int $id the id of the pad which should be returned. If noone given the current is returned
	 * @return t3lib_clipboard_Pad
	 */
	public function getPad($id = -1) {
		if ($id === -1) {
			return $this->currentPad;
		} else {
			return $this->createPad(intval($id));
		}
	}

	/**
	 * checks wether a property for display purposes is set
	 *
	 * @param string $property
	 * @return bool
	 */
	public function hasDispplayProperty($property) {
		return isset($this->renderData[$property]);
	}

	/**
	 * returns a property for display purposes if set, otherwise NULL
	 *
	 * @param string $property
	 * @return int|string|null
	 */
	public function getDisplayProperty($property) {
		return $this->renderData[$property];
	}

	/**
	 * sets a value of a display related property
	 *
	 * @param string $property
	 * @param int|string $value
	 * @return void
	 */
	public function setDisplayProperty($property, $value) {
		$this->renderData[$property] = $value;
	}

	/**
	 * returns the identifier of the active Pad
	 *
	 * @return int
	 */
	public function getActivePadId() {
		return $this->currentPad->getIdentifier();
	}

	/**
	 * @param int $numberOfPads
	 */
	public function setNumberOfPads($numberOfPads) {
		$this->numberOfPads = $numberOfPads;
	}

	/**
	 * @return int
	 */
	public function getNumberOfPads() {
		return $this->numberOfPads;
	}

		protected function getData() {
		if (t3lib_clipboard_Clipboard::$doPersist) {
			return (array)$GLOBALS['BE_USER']->uc['moduleData']['clipboard'];
		} else {
			return (array)$sessionData = $GLOBALS['BE_USER']->getSessionData('clipboard');
		}
	}

	protected function setData(array $array) {
		if (t3lib_clipboard_Clipboard::$doPersist) {
			$GLOBALS['BE_USER']->uc['moduleData']['clipboard'] = $array;
		} else {
			$GLOBALS['BE_USER']->setAndSaveSessionData('clipboard', $array);
		}
	}

}
