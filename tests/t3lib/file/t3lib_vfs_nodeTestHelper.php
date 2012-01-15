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
 *  the Freef Software Foundation; either version 2 of the License, or
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
 * Helper methods for the test cases for Node and its subclasses
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_NodeTestHelper {
	/**
	 * Prepares a hierarchy of mocked nodes for testing path generation.
	 * Each node knows about its name, its mountpoint and its direct parent.
	 *
	 * @param array $mockedNodesData
	 *        A two dimensional array: nodes[name, type]. type directly corresponds to the node classes t3lib_file_*
	 * @return array contents: array[mockedNodes], array[pathParts]
	 */
	public static function prepareNodeHierarchyForPathTests($mockedNodesData, Tx_PhpUnit_TestCase $testCase) {
		$pathParts = $mockedNodes = array();
		$parentNode = $lastMount = NULL;
		foreach ($mockedNodesData as $nodeData) {
			list ($name, $type) = $nodeData;
				// ignore root node for path creation;
			if ($type != 'RootNode') {
				$pathParts[] = $name;
			}

			$mockedMethods = array();
			switch($type) {
				case 'Mount':
					$mockedMethods = array('getName', 'isMountpoint', 'getMountpoint', 'getParent');
					break;
				case 'Folder':
				case 'File':
					$mockedMethods = array('getName', 'getMountpoint', 'getParent');
					break;
			}

			$mockedNode = $testCase->getMock('t3lib_file_' . $type, $mockedMethods, array(), '', FALSE);
			$mockedNode->expects($testCase->any())->method('getName')->will($testCase->returnValue($name));
			switch ($type) {
				case 'Mount':
					$mockedNode->expects(Tx_PhpUnit_TestCase::any())->method('isMountpoint')->will($testCase->returnValue(TRUE));
					$lastMount = $mockedNode;
					break;
				case 'RootNode':
					$mockedNode = new t3lib_file_RootNode();
					break;
			}
			if ($parentNode) {
				$mockedNode->expects($testCase->any())->method('getParent')->will($testCase->returnValue($parentNode));
			}
			if ($lastMount) {
				$mockedNode->expects($testCase->any())->method('getMountpoint')->will($testCase->returnValue($lastMount));
			}

			$parentNode = $mockedNodes[] = $mockedNode;
		}

		return array($mockedNodes, $pathParts);
	}
}

?>