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
 * Testcase for the data structure exporter
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage
 */
class Tx_RdfExport_HelperTest extends Tx_RdfExport_TestCase {

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

	/**
	 * @test
	 */
	public function convertArrayToRdfNodesGeneratesBlankNodeForEachEntry() {
		$input = array(
			uniqid(),
			uniqid()
		);

		$statements = Tx_RdfExport_Helper::convertArrayToRdfNodes($input);

		$subjects = array_keys($statements);
		$this->assertStringStartsWith('_:', $subjects[0]);
		$this->assertStringStartsWith('_:', $subjects[1]);
		$this->assertNotEquals($subjects[0], $subjects[1]);
	}

	/**
	 * @test
	 */
	public function convertArrayToRdfNodesAddsValueAsRdfFirstStatement() {
		$input = array(
			uniqid(),
			uniqid(),
			uniqid()
		);

		$statements = Tx_RdfExport_Helper::convertArrayToRdfNodes($input);

		foreach ($statements as $statement) {
			$this->assertContains($statement[$this->prefixes['rdf'] . 'first'], $input);
		}
	}

	/**
	 * @test
	 */
	public function convertArrayToRdfNodesCorrectlyChainsBlankNodes() {
		$input = array(
			uniqid(),
			uniqid(),
			uniqid()
		);

		$statements = Tx_RdfExport_Helper::convertArrayToRdfNodes($input);

			// reverse map from array value to bnode identifier (= subject of the statement)
		$valueMap = array();
		foreach ($statements as $subject => $statement) {
			$value = $statement[$this->prefixes['rdf'] . 'first'];
			$valueMap[$value] = $subject;
		}

			// ignore the last element here, because it will be no reference to a node, but rdf:nil
		for ($i = 0; $i < count($input) - 1; ++$i) {
			$subject = $valueMap[$input[$i]];
			$rdfLastObject = $statements[$subject][$this->prefixes['rdf'] . 'rest'];
			$this->assertEquals($valueMap[$input[$i+1]], $rdfLastObject);
		}
	}

	/**
	 * @test
	 */
	public function convertArrayToRdfNodesSetsNilForRdfRestInLastNode() {
		$input = array(
			uniqid(),
			uniqid(),
			uniqid()
		);

		$statements = Tx_RdfExport_Helper::convertArrayToRdfNodes($input);

		$lastValue = array_pop($input);
		foreach ($statements as $statement) {
			$value = $statement[$this->prefixes['rdf'] . 'first'];
			if ($value == $lastValue) {
				$this->assertEquals($this->prefixes['rdf'] . 'nil', $statement[$this->prefixes['rdf'] . 'rest']);
			}
		}
	}

	/**
	 * This feature is not strictly required for RDF conformance, but it makes parsing the statements easier, as
	 * they will appear in the order the original array was
	 *
	 * @test
	 */
	public function convertArrayToRdfNodesReturnsCorrectOrder() {
		$input = array(
			uniqid(),
			uniqid(),
			uniqid()
		);

		$statements = Tx_RdfExport_Helper::convertArrayToRdfNodes($input);

		$i = 0;
		foreach ($statements as $statement) {
			$value = $statement[$this->prefixes['rdf'] . 'first'];
			$this->assertEquals($input[$i], $value);
			++$i;
		}
	}
}
