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
 * Testcase for the data structure exporter
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage
 */
class Tx_RdfExport_HelperTest extends Tx_Phpunit_TestCase {

	protected $mockedField;
	protected $mockedFieldName;
	protected $mockedDataStructure;
	protected $mockedDataStructureIdentifier;

	/**
	 * @test
	 */
	public function getRdfIdentifierForDataStructureReturnsCorrectIdentifier() {
		$dataStructureIdentifier = uniqid();
		$dataStructure = $this->getMock('t3lib_DataStructure_Tca');
		$dataStructure->expects($this->any())->method('getIdentifier')->will($this->returnValue($dataStructureIdentifier));

		$rdfIdentifier = Tx_RdfExport_Helper::getRdfIdentifierForDataStructure($dataStructure);

		$this->assertEquals('http://typo3.org/semantic/datastructure/' . $dataStructureIdentifier, $rdfIdentifier);
	}


	protected function mockFieldObject($fieldName, $dataStructureIdentifier) {
		$dataStructureObject = $this->getMock('t3lib_DataStructure_Tca');
		$dataStructureObject->expects($this->any())->method('getIdentifier')->will($this->returnValue($dataStructureIdentifier));

		$fieldObject = $this->getMock('t3lib_DataStructure_Element_Field');
		$fieldObject->expects($this->any())->method('getName')->will($this->returnValue($fieldName));
		$fieldObject->expects($this->any())->method('getDataStructure')->will($this->returnValue($dataStructureObject));

		return $fieldObject;
	}

	/**
	 * @test
	 */
	public function getRdfIdentifierForFieldUsesCorrectPrefix() {
		$this->mockedFieldName = uniqid();
		$this->mockedDataStructureIdentifier = uniqid();
		$mockedField = $this->mockFieldObject($this->mockedFieldName, $this->mockedDataStructureIdentifier);

		$rdfIdentifier = Tx_RdfExport_Helper::getRdfIdentifierForField($mockedField);

		$this->assertStringStartsWith('http://typo3.org/semantic/datastructure/', $rdfIdentifier);
	}

	/**
	 * @test
	 */
	public function getRdfIdentifierForFieldReturnsCorrectIdentifier($rdfIdentifier) {
		$this->mockedFieldName = uniqid();
		$this->mockedDataStructureIdentifier = uniqid();
		$mockedField = $this->mockFieldObject($this->mockedFieldName, $this->mockedDataStructureIdentifier);

		$rdfIdentifier = Tx_RdfExport_Helper::getRdfIdentifierForField($mockedField);

		$this->assertStringEndsWith($this->mockedDataStructureIdentifier . '#' . $this->mockedFieldName, $rdfIdentifier);
	}


}