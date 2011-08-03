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
 * Testcase for the column mapper
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage 
 */
class Tx_RdfExport_ColumnMapperTest extends Tx_RdfExport_TestCase {

	/**
	 * @var Tx_RdfExport_ColumnMapper
	 */
	protected $fixture;

	/**
	 * The identifier of the data structure the mocked column belongs to
	 *
	 * @var string
	 */
	protected $dataStructureIdentifier;

	/**
	 * The name of the mocked column
	 *
	 * @var string
	 */
	protected $columnName;

	protected $prefixes = array(
		'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
		't3' => 'http://typo3.org/semantic/elements#',
		't3ds' => 'http://typo3.org/semantic/datastructure/'
	);

	public function setUp() {
		$this->fixture = new Tx_RdfExport_ColumnMapper();

		$this->dataStructureIdentifier = uniqid();
		$this->columnName = uniqid();
	}

	protected function createMockedColumn($columnConfiguration) {
			// using TCA DataStructure here because PHPUnit 3.5 can't mock concrete methods in abstract classes
		$dataStructure = $this->getMock('t3lib_DataStructure_Tca');
		$dataStructure->expects($this->any())->method('getIdentifier')->will($this->returnValue($this->dataStructureIdentifier));

		$column = $this->getMock('t3lib_DataStructure_Element_Field', array('getConfiguration'), array($this->columnName));
		$column->expects($this->any())->method('getConfiguration')->will($this->returnValue($columnConfiguration));
		//$column->expects($this->any())->method('getDataStructure')->will($this->returnValue($dataStructure));
		$column->setDataStructure($dataStructure);
		return $column;
	}

	/**
	 * Generic verifier that checks for an rdfs:domain statement in an array of statements.
	 *
	 * @param $statements
	 * @return void
	 */
	protected function verifyDomainProperty($statements) {
		$this->assertArrayHasKey($this->prefixes['rdf'] . 'domain', $statements);
		$this->assertEquals($this->prefixes['t3ds'] . $this->dataStructureIdentifier, $statements[$this->prefixes['rdf'] . 'domain']);
	}

	public function primitiveTypesDataProvider() {
		return array(
			'simple string input' => array(
				array(
					'type' => 'input'
				),
				'http://www.w3.org/2001/XMLSchema#string'
			),
			'integer input' => array(
				array(
					'type' => 'input',
					'eval' => 'int'
				),
				'http://www.w3.org/2001/XMLSchema#integer'
			),
			'datetime input' => array(
				array(
					'type' => 'input',
					'eval' => 'datetime'
				),
				'http://www.w3.org/2001/XMLSchema#dateTime'
			),
			'date input' => array(
				array(
					'type' => 'input',
					'eval' => 'date'
				),
				'http://www.w3.org/2001/XMLSchema#date'
			),
			'time input without seconds' => array(
				array(
					'type' => 'input',
					'eval' => 'time'
				),
				'http://www.w3.org/2001/XMLSchema#time'
			),
			'time input with seconds' => array(
				array(
					'type' => 'input',
					'eval' => 'timesec'
				),
				'http://www.w3.org/2001/XMLSchema#time'
			),
			'text input' => array(
				array(
					'type' => 'text'
				),
				'http://www.w3.org/2001/XMLSchema#string'
			)
			/*'checkbox' => array(
				array(
					'type' => 'check',
				)
			)*/
		);
	}

	/**
	 * @test
	 * @dataProvider primitiveTypesDataProvider
	 *
	 * @param array $columnConfiguration The configuration of the column to test
	 * @param string $expectedDataType The RDF type expected for this column
	 */
	public function primitiveDataTypesAreCorrectlyMapped($columnConfiguration, $expectedDataType) {
		$column = $this->createMockedColumn($columnConfiguration);

		$resultingStatements = $this->fixture->mapColumnDescriptionToRdfDataType($column);

		$this->assertEquals($expectedDataType, $resultingStatements[$this->prefixes['rdf'] . 'type']);
		$this->verifyDomainProperty($resultingStatements);
	}

	public function relationColumnsDataProvider() {
		return array(
			'internal_type db with one table' => array(
				array(
					'type' => 'group',
					'internal_type' => 'db',
					'allowed' => 'tt_content'
				),
				array(
					$this->prefixes['rdfs'] . 'range' => $this->prefixes['t3ds'] . 'tt_content',
					// TODO add a subtypeOf property with sth. like relatedTo (skos:related?)
				)
			)
		);
	}

	/**
	 * @test
	 * @dataProvider relationColumnsDataProvider
	 *
	 * @param array $columnConfiguration The configuration for this column
	 * @param array $expectedStatements The statements that should result from the mapping; note that there may be other
	 *                                  statements than these in the mapping
	 */
	public function relationColumnsAreCorrectlyMapped($columnConfiguration, $expectedStatements) {
		$column = $this->createMockedColumn($columnConfiguration);

		$resultingStatements = $this->fixture->mapColumnDescriptionToRdfDataType($column);

		foreach ($expectedStatements as $subject => $subjectStatements) {
			foreach ($subjectStatements as $predicate => $object) {
				//
			}
		}
		$this->verifyDomainProperty($resultingStatements);
		$this->markTestIncomplete();
	}

	/**
	 * @test
	 */
	public function mappingColumnDescriptionFailsForInvalidColumnType() {
		$this->setExpectedException('InvalidArgumentException', '', 1310670994);

		$column = $this->createMockedColumn(array('type' => uniqid()));

		$this->fixture->mapColumnDescriptionToRdfDataType($column);
	}

	/**
	 * Tests if unknown/not implemented subtypes of the "input" field type (e.g. some eval-values) make the mapping fail
	 * @test
	 */
	public function mappingColumnDescriptionFailsForInvalidInputColumnType() {
		$this->setExpectedException('InvalidArgumentException', '', 1310670995);

		$column = $this->createMockedColumn(array('type' => 'input', 'eval' => uniqid()));

		$this->fixture->mapColumnDescriptionToRdfDataType($column);
	}
}

?>