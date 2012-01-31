<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Susanne Moog <typo3@susanne-moog.de>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

require_once('fixture/t3lib_utility_math_fixture_classWithStringRepresentation.php');

/**
 * Testcase for class t3lib_utility_Math
 *
 * @author Susanne Moog <typo3@susanne-moog.de>
 *
 * @package TYPO3
 * @subpackage t3lib
 */

class t3lib_utility_MathTest extends tx_phpunit_testcase {

	//////////////////////////////////
	// Tests concerning forceIntegerInRange
	//////////////////////////////////
	/**
	 * Data provider for forceIntegerInRangeForcesIntegerIntoBoundaries
	 *
	 * @return array expected values, arithmetic expression
	 */
	public function forceIntegerInRangeForcesIntegerIntoDefaultBoundariesDataProvider() {
		return array(
			'negativeValue' => array(0, -10),
			'normalValue' => array(30, 30),
			'veryHighValue' => array(2000000000, 3000000001),
			'zeroValue' => array(0, 0),
			'anotherNormalValue' => array(12309, 12309)
		);
	}

	/**
	 * @test
	 * @dataProvider forceIntegerInRangeForcesIntegerIntoDefaultBoundariesDataProvider
	 */
	public function forceIntegerInRangeForcesIntegerIntoDefaultBoundaries($expected, $value) {
		 $this->assertEquals($expected, t3lib_utility_Math::forceIntegerInRange($value, 0));
	}

	/**
	 * @test
	 */
	public function forceIntegerInRangeSetsDefaultValueIfZeroValueIsGiven() {
		 $this->assertEquals(42, t3lib_utility_Math::forceIntegerInRange('', 0, 2000000000, 42));
	}

	//////////////////////////////////
	// Tests concerning convertToPositiveInteger
	//////////////////////////////////
	/**
	 * @test
	 */
	public function convertToPositiveIntegerReturnsZeroForNegativeValues() {
		$this->assertEquals(0, t3lib_utility_Math::convertToPositiveInteger(-123));
	}

	/**
	 * @test
	 */
	public function convertToPositiveIntegerReturnsTheInputValueForPositiveValues() {
		$this->assertEquals(123, t3lib_utility_Math::convertToPositiveInteger(123));
	}

	///////////////////////////////
	// Tests concerning testInt
	///////////////////////////////

	/**
	 * Data provider for canBeInterpretedAsIntegerReturnsTrue
	 *
	 * @return array Data sets
	 */
	public function functionCanBeInterpretedAsIntegerValidDataProvider() {
		return array(
			'int' => array(32425),
			'negative int' => array(-32425),
			'largest int' => array(PHP_INT_MAX),
			'int as string' => array('32425'),
			'negative int as string' => array('-32425'),
			'zero' => array(0),
			'zero as string' => array('0'),
		);
	}

	/**
	 * @test
	 * @dataProvider functionCanBeInterpretedAsIntegerValidDataProvider
	 */
	public function testIntReturnsTrue($int) {
		$this->assertTrue(t3lib_utility_Math::canBeInterpretedAsInteger($int));
	}

	/**
	 * Data provider for testIntReturnsFalse
	 *
	 * @return array Data sets
	 */
	public function functionCanBeInterpretedAsIntegerInvalidDataProvider() {
		$objectWithNumericalStringRepresentation = new t3lib_utility_math_fixture_classWithStringRepresentation();
		$objectWithNumericalStringRepresentation->setString('1234');
		$objectWithNonNumericalStringRepresentation = new t3lib_utility_math_fixture_classWithStringRepresentation();
		$objectWithNonNumericalStringRepresentation->setString('foo');
		$objectWithEmptyStringRepresentation = new t3lib_utility_math_fixture_classWithStringRepresentation();
		$objectWithEmptyStringRepresentation->setString('');
		return array(
			'int as string with leading zero' => array('01234'),
			'positive int as string with plus modifier' => array('+1234'),
			'negative int as string with leading zero' => array('-01234'),
			'largest int plus one' => array(PHP_INT_MAX + 1),
			'string' => array('testInt'),
			'empty string' => array(''),
			'int in string' => array('5 times of testInt'),
			'int as string with space after' => array('5 '),
			'int as string with space before' => array(' 5'),
			'int as string with many spaces before' => array('     5'),
			'float' => array(3.14159),
			'float as string' => array('3.14159'),
			'float as string only a dot' => array('10.'),
			'float as string trailing zero would evaluate to int 10' => array('10.0'),
			'float as string trailing zeros	 would evaluate to int 10' => array('10.00'),
			'null' => array(NULL),
			'empty array' => array(array()),
			'int in array' => array(array(32425)),
			'int as string in array' => array(array('32425')),
			'object without string representation' => array(new stdClass()),
			'object with numerical string representation' => array($objectWithNumericalStringRepresentation),
			'object without numerical string representation' => array($objectWithNonNumericalStringRepresentation),
			'object with empty string representation' => array($objectWithEmptyStringRepresentation),
		);
	}

	/**
	 * @test
	 * @dataProvider functionCanBeInterpretedAsIntegerInvalidDataProvider
	 */
	public function canBeInterpretedAsIntegerReturnsFalse($int) {
		$this->assertFalse(t3lib_utility_Math::canBeInterpretedAsInteger($int));
	}

	//////////////////////////////////
	// Tests concerning calculateWithPriorityToAdditionAndSubtraction
	//////////////////////////////////

	/**
	 * Data provider for calculateWithPriorityToAdditionAndSubtraction
	 *
	 * @return array expected values, arithmetic expression
	 */
	public function calculateWithPriorityToAdditionAndSubtractionDataProvider() {
		return array(
			'add' => array(9, '6 + 3'),
			'substract with positive result' => array(3, '6 - 3'),
			'substract with negative result' => array(-3, '3 - 6'),
			'multiply' => array(6, '2 * 3'),
			'divide' => array(2.5, '5 / 2'),
			'modulus' => array(1, '5 % 2'),
			'power' => array(8, '2 ^ 3'),
			'three operands with non integer result' => array(6.5, '5 + 3 / 2'),
			'three operands with power' => array(14, '5 + 3 ^ 2'),
			'three operads with modulus' => array(4, '5 % 2 + 3'),
			'four operands' => array(3, '2 + 6 / 2 - 2'),
			'division by zero when dividing' => array('ERROR: dividing by zero', '2 / 0'),
			'division by zero with modulus' => array('ERROR: dividing by zero', '2 % 0')
		);
	}

	/**
	 * @test
	 * @dataProvider calculateWithPriorityToAdditionAndSubtractionDataProvider
	 */
	public function calculateWithPriorityToAdditionAndSubtractionCorrectlyCalculatesExpression($expected, $expression) {
		$this->assertEquals($expected, t3lib_utility_Math::calculateWithPriorityToAdditionAndSubtraction($expression));
	}


	//////////////////////////////////
	// Tests concerning calcParenthesis
	//////////////////////////////////

	/**
	 * Data provider for calcParenthesis
	 *
	 * @return array expected values, arithmetic expression
	 */
	public function calculateWithParenthesesDataProvider() {
		return array(
			'starts with parenthesis' => array(18, '(6 + 3) * 2'),
			'ends with parenthesis' => array(6, '2 * (6 - 3)'),
			'multiple parentheses' => array(-6, '(3 - 6) * (4 - 2)'),
			'nested parentheses' => array(22, '2 * (3 + 2 + (3 * 2))'),
			'parenthesis with division' => array(15, '5 / 2 * (3 * 2)'),
		);
	}

	/**
	 * @test
	 * @dataProvider calculateWithParenthesesDataProvider
	 */
	public function calculateWithParenthesesCorrectlyCalculatesExpression($expected, $expression) {
		$this->assertEquals($expected, t3lib_utility_Math::calculateWithParentheses($expression));
	}
}

?>