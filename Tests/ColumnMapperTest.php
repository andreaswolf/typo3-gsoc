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
		$column->expects($this->any())->method('getIdentifier')->will($this->returnValue($this->columnName));
		$column->expects($this->any())->method('getConfiguration')->will($this->returnValue(array('config' => $columnConfiguration)));
		//$column->expects($this->any())->method('getDataStructure')->will($this->returnValue($dataStructure));
		$column->setDataStructure($dataStructure);
		return $column;
	}


	/*************************************
	 * Column configuration mapping
	 *************************************/

	/**
	 * Checks for some properties that all column nodes should have, e.g. rdfs:domain, rdfs:subclassOf and rdf:type
	 *
	 * @param $statements
	 * @return void
	 */
	protected function verifyCommonColumnNodeProperties($statements) {
		$this->assertArrayHasKey($this->prefixes['rdfs'] . 'domain', $statements);
		$this->assertEquals($this->prefixes['t3ds'] . $this->dataStructureIdentifier, $statements[$this->prefixes['rdfs'] . 'domain'][0]['value']);
		$this->assertArrayHasKey($this->prefixes['rdfs'] . 'subclassOf', $statements);
		$this->assertEquals($this->prefixes['rdf'] . 'Property', $statements[$this->prefixes['rdfs'] . 'subclassOf'][0]['value']);
		$this->assertArrayHasKey($this->prefixes['rdf'] . 'type', $statements);
		$this->assertEquals($this->prefixes['rdf'] . 'Class', $statements[$this->prefixes['rdf'] . 'type'][0]['value']);
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

		list($columnSubject, $resultingStatements) = $this->fixture->mapColumnDescriptionToRdfDataType($column);

		$this->assertEquals($expectedDataType, $resultingStatements[$columnSubject][$this->prefixes['rdfs'] . 'range'][0]['value']);
		$this->verifyCommonColumnNodeProperties($resultingStatements[$columnSubject]);
	}

	/**
	 * @test
	 */
	public function databaseRelationColumnWithOneForeignTableIsCorrectlyMapped($columnConfiguration, $expectedStatements) {
		$column = $this->createMockedColumn(array(
			'type' => 'group',
			'internal_type' => 'db',
			'allowed' => 'tt_content'
		));

		list($columnSubject, $resultingStatements) = $this->fixture->mapColumnDescriptionToRdfDataType($column);

		$this->assertArrayHasKey($this->prefixes['rdfs'] . 'range', $resultingStatements[$columnSubject]);
			// check if the blank node containing the owl:unionOf statement exists
		$rangeNodeId = $resultingStatements[$columnSubject][$this->prefixes['rdfs'] . 'range'][0]['value'];
		$this->assertArrayHasKey($rangeNodeId, $resultingStatements);
			// check if the range node has the correct statement
		$this->assertArrayHasKey($this->prefixes['owl'] . 'unionOf', $resultingStatements[$rangeNodeId]);
			// there is a chained list of nodes behind the unionOf statement; see
			// Tx_RdfExport_Helper::convertArrayToRdfNodes() for more info
		$firstChainedNodeId = $resultingStatements[$rangeNodeId][$this->prefixes['owl'] . 'unionOf'][0]['value'];
		$this->assertArrayHasKey($this->prefixes['rdf'] . 'first', $resultingStatements[$firstChainedNodeId]);
		$this->assertEquals($this->prefixes['t3ds'] . 'tt_content', $resultingStatements[$firstChainedNodeId][$this->prefixes['rdf'] . 'first'][0]['value']);

		$this->verifyCommonColumnNodeProperties($resultingStatements[$columnSubject]);
	}

	/**
	 * @test
	 */
	public function databaseRelationColumnWithMultipleForeignTablesProducesCorrectResult() {
		$allowedTables = array('tt_content', 'tt_news');
		$column = $this->createMockedColumn(array(
			'type' => 'group',
			'internal_type' => 'db',
			'allowed' => implode(',', $allowedTables)
		));

		list($columnSubject, $resultingStatements) = $this->fixture->mapColumnDescriptionToRdfDataType($column);

		$rangeNodeId = $resultingStatements[$columnSubject][$this->prefixes['rdfs'] . 'range'][0]['value'];
		$this->assertNotEmpty($rangeNodeId, 'Could not find a node name in rdfs:range');
		$firstTableNodeId = $resultingStatements[$rangeNodeId][$this->prefixes['owl'] . 'unionOf'][0]['value'];
		$this->assertNotEmpty($firstTableNodeId, 'Could not find a node name for the first table.');
		$this->assertStringEndsWith($allowedTables[0], $resultingStatements[$firstTableNodeId][$this->prefixes['rdf'] . 'first'][0]['value']);
		$secondTableNodeId = $resultingStatements[$firstTableNodeId][$this->prefixes['rdf'] . 'rest'][0]['value'];
		$this->assertNotEmpty($secondTableNodeId, 'Could not find a node name for the second table.');
		$this->assertStringEndsWith($allowedTables[1], $resultingStatements[$secondTableNodeId][$this->prefixes['rdf'] . 'first'][0]['value']);

		$this->verifyCommonColumnNodeProperties($resultingStatements[$columnSubject]);
	}

	/**
	 * Tests if the mapping fails if an undefined column type is used.
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

	/**
	 * @test
	 */
	public function columnIsMappedToBlankNodeByDefault() {
		$column = $this->createMockedColumn(array('type' => 'input'));

		list($subject, $statements) = $this->fixture->mapColumnDescriptionToRdfDataType($column);

		$subject = array_shift(array_keys($statements));

		$this->assertStringStartsWith('_:', $subject);
	}

	/**
	 * @test
	 */
	public function columnMappingUsesGivenIdentifierForColumnNode() {
		$columnConfiguration = $this->createMockedColumn(array('type' => 'input'));
		$columnIdentifier = 'urn:' . uniqid();

		list($subject, $statements) = $this->fixture->mapColumnDescriptionToRdfDataType($columnConfiguration, $columnIdentifier);

		$this->assertEquals($columnIdentifier, $subject);
	}

	/**
	 * @test
	 */
	public function columnNameIsExportedAsLabel() {
		$columnConfiguration = $this->createMockedColumn(array('type' => 'input'));

		list($subject, $statements) = $this->fixture->mapColumnDescriptionToRdfDataType($columnConfiguration, $columnIdentifier);

		$this->assertEquals($this->columnName, $statements[$subject][$this->canonicalize('rdfs:label')][0]['value']);
	}


	/*************************************
	 * Field value mapping
	 *************************************/

	/**
	 * @test
	 */
	public function fieldValueMappingUsesCorrectPredicate() {
		$column = $this->createMockedColumn(array('type' => 'input'));

		list($statement) = $this->fixture->mapFieldValueToStatement($column, $fieldValue);

		$this->assertArrayHasKey(Tx_RdfExport_Helper::getRdfIdentifierForField($column), $statement);
	}

	/**
	 * @test
	 */
	public function integerValuesAreCorrectlyMapped() {
		$column = $this->createMockedColumn(array('type' => 'input', 'eval' => 'int'));

		$fieldValue = rand(1, 100);

		list($statement) = $this->fixture->mapFieldValueToStatement($column, $fieldValue);

		$predicate = Tx_RdfExport_Helper::getRdfIdentifierForField($column);
		$this->assertArrayHasKey($predicate, $statement);

		$this->assertEquals($this->canonicalize('xsd:integer'), $statement[$predicate][0]['type']);
		$this->assertEquals($fieldValue, $statement[$predicate][0]['value']);
	}

	/**
	 * @test
	 */
	public function datetimeValuesAreCorrectlyMapped() {
		$column = $this->createMockedColumn(array('type' => 'input', 'eval' => 'datetime'));

			// TODO add timezone support
		$fieldValue = 1313337742;
		$formattedFieldValue = '2011-08-14T16:02:22Z';

		list($statement) = $this->fixture->mapFieldValueToStatement($column, $fieldValue);

		$predicate = Tx_RdfExport_Helper::getRdfIdentifierForField($column);
		$this->assertArrayHasKey($predicate, $statement);

		$this->assertEquals($this->canonicalize('xsd:dateTime'), $statement[$predicate][0]['type']);
		$this->assertEquals($formattedFieldValue, $statement[$predicate][0]['value']);
	}

	/**
	 * @test
	 */
	public function dateValuesAreCorrectlyMapped() {
		$column = $this->createMockedColumn(array('type' => 'input', 'eval' => 'date'));

		$fieldValue = 1313337742;
		$formattedFieldValue = '2011-08-14';

		list($statement) = $this->fixture->mapFieldValueToStatement($column, $fieldValue);

		$predicate = Tx_RdfExport_Helper::getRdfIdentifierForField($column);
		$this->assertArrayHasKey($predicate, $statement);

		$this->assertEquals($this->canonicalize('xsd:date'), $statement[$predicate][0]['type']);
		$this->assertEquals($formattedFieldValue, $statement[$predicate][0]['value']);
	}

	/**
	 * @test
	 */
	public function timeValuesAreCorrectlyMapped() {
		$column = $this->createMockedColumn(array('type' => 'input', 'eval' => 'timesec'));

			// TODO add timezone support
		$fieldValue = 1313337742;
		$formattedFieldValue = '16:02:22Z';

		list($statement) = $this->fixture->mapFieldValueToStatement($column, $fieldValue);

		$predicate = Tx_RdfExport_Helper::getRdfIdentifierForField($column);
		$this->assertArrayHasKey($predicate, $statement);

		$this->assertEquals($this->canonicalize('xsd:time'), $statement[$predicate][0]['type']);
		$this->assertEquals($formattedFieldValue, $statement[$predicate][0]['value']);
	}

	public function relationsWithCsv_dataProvider() {
		return array(
			'one table allowed' => array(
				'tt_content',
				'1,2',
				array(
					'tt_content/1',
					'tt_content/2'
				)
			),
			'two tables allowed' => array(
				'tt_content,pages',
				'tt_content_2,pages_1',
				array(
					'tt_content/2',
					'pages/1',
				)
			),
			'all tables allowed' => array(
				'*',
				'tt_content_2,pages_1',
				array(
					'tt_content/2',
					'pages/1',
				)
			)
		);
	}

	/**
	 * Tests regular relations that are stored as CSV lists inside the field
	 * Related records are exported as a rdf:Seq of URIs pointing to the record
	 * @tests
	 * @dataProvider relationsWithCsv_dataProvider
	 */
	public function relationsWithCsvAreCorrectlyMapped($table, $fieldValue, $expectedRelationUrls) {
		$column = $this->createMockedColumn(array('type' => 'group', 'internal_type' => 'db', 'allowed' => $table));

		list($statement, $additionalStatements) = $this->fixture->mapFieldValueToStatement($column, $fieldValue);

		$predicate = Tx_RdfExport_Helper::getRdfIdentifierForField($column);
		$this->assertArrayHasKey($predicate, $statement);

		$blankNodeId = $statement[$predicate][0]['value'];
		if (!$blankNodeId) { $this->fail('Could not get id of blank node.'); }
		$this->assertArrayHasKey($blankNodeId, $additionalStatements);

		$counter = 0;
		foreach ($expectedRelationUrls as $expectedUrl) {
			++$counter;
			$this->assertStringEndsWith($expectedUrl,
			  $additionalStatements[$blankNodeId][$this->canonicalize('rdf:_' . $counter)][0]['value']);
		}
	}
}

?>