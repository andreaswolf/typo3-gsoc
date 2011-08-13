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
class Tx_RdfExport_DataStructureExporterTest extends Tx_RdfExport_TestCase {
	/**
	 * @var Tx_RdfExport_DataStructureExporter
	 */
	protected $fixture;

	/**
	 * @var string
	 */
	protected $tableName;

	/**
	 * The data structure fixture
	 *
	 * @var string
	 */
	protected $dataStructureFixture;

	/**
	 * The TCA entry for the data structure fixture
	 *
	 * @var t3lib_DataStructure_Abstract
	 */
	protected $dataStructureTcaFixture;

	/**
	 * @var Tx_RdfExport_DataStructureExporter
	 */
	public function setUp() {
		parent::setUp();

		$bootstrap = new \Erfurt\Core\Bootstrap('Testing');
		$bootstrap->run();

		$this->tableName = uniqid();
		$this->fixture = new Tx_RdfExport_DataStructureExporter($this->tableName);
	}

	protected function createFakeDataStructureObject($tcaEntry) {
		$this->dataStructureTcaFixture = $tcaEntry;
		$this->dataStructureFixture = new t3lib_DataStructure_Tca($this->tableName, $tcaEntry);
	}

	protected function getMockedGraph() {
		return $this->getMock('Erfurt\Domain\Model\Rdfs\Graph');
	}

	/**
	 * Creates a mocked type object, mocking the method getIdentifier()
	 *
	 * @param mixed $typeValue
	 * @return t3lib_DataStructure_Type
	 */
	protected function getMockedType($typeValue) {
		$mockedType = $this->getMock('t3lib_DataStructure_Type', array(), array(), '', FALSE);
		$mockedType->expects($this->any())->method('getIdentifier')->will($this->returnValue($typeValue));
		return $mockedType;
	}

	/**
	 * Mocks a field object, with a return value for getName() and getDataStructure, if given
	 *
	 * @return t3lib_DataStructure_Element_Field
	 */
	protected function getMockedField($fieldName, $dataStructure = NULL) {
		$mockedField = $this->getMock('t3lib_DataStructure_Element_Field', array(), array(), '', FALSE);
		$mockedField->expects($this->any())->method('getName')->will($this->returnValue($fieldName));
		if ($dataStructure) {
			$mockedField->expects($this->any())->method('getDataStructure')->will($this->returnValue($dataStructure));
		}
		return $mockedField;
	}

	/**
	 * @test
	 */
	public function noTriplesAreAddedForEmptyTca() {
		$tca = array('ctrl' => array(), 'columns' => array());
		$this->createFakeDataStructureObject($tca);
		$this->fixture->setGraph($this->getMockedGraph());
		$statements = $this->fixture->exportDataStructure($this->dataStructureFixture);

		$this->assertEmpty($statements);
	}

	/**
	 * @test
	 */
	public function tripleForCreationDateIsAddedIfTableHasCreationDate() {
		$tca = array('ctrl' => array('crdate' => uniqid()), 'columns' => array());
		$this->createFakeDataStructureObject($tca);

		$expectedStatements = array(
			$this->prefixes['rdf'] . 'type' => array(array('value' => $this->prefixes['rdf'] . 'Property')),
			$this->prefixes['owl'] . 'sameAs' => array(array('value' => $this->prefixes['dcterms'] . 'created')), // TODO replace dcterms with real namespace
		);

		$resultingStatements = $this->fixture->exportDataStructure($this->dataStructureFixture);

		$fieldName = $this->dataStructureTcaFixture['ctrl']['crdate'];
		$subject = array_shift(array_keys($resultingStatements));
		$this->assertStringEndsWith($this->tableName . '#' . $fieldName, $subject);
		$this->assertIsSupersetOf($expectedStatements, $resultingStatements[$subject]);
	}

	/**
	 * @test
	 * @group integration
	 */
	public function statementsFromColumnMapperAreAddedAfterMapping() {
		$this->createFakeDataStructureObject(array('columns' => array('someColumn' => array())));

		$subject1 = uniqid(); $subject2 = uniqid();
		$expectedStatements = array(
			$subject1 => array(
				uniqid() => array(array('value' => uniqid())),
				uniqid() => array(array('value' => uniqid()))
			),
			$subject2 => array(
				uniqid() => array(array('value' => uniqid())),
				uniqid() => array(array('value' => uniqid()))
			)
		);

		$mockedColumnMapper = $this->getMock('Tx_RdfExport_ColumnMapper');
		$mockedColumnMapper->expects($this->once())->method('mapColumnDescriptionToRdfDataType')->will($this->returnValue(array('', $expectedStatements)));
		$this->fixture->setColumnMapper($mockedColumnMapper);

		$resultingStatements = $this->fixture->exportDataStructure($this->dataStructureFixture);

		$this->assertArrayHasKey($subject1, $resultingStatements);
		$this->assertIsSupersetOf($expectedStatements[$subject1], $resultingStatements[$subject1]);
		$this->assertArrayHasKey($subject2, $resultingStatements);
		$this->assertIsSupersetOf($expectedStatements[$subject2], $resultingStatements[$subject2]);
	}

	/**
	 * @test
	 */
	public function typeGetsMappedToInstanceOfContentObjectClass() {
		$dataStructureIdentifier = uniqid();$typeIdentifier = uniqid();
		$mockedType = $this->getMockedType($typeIdentifier);
		$mockedDataStructure = $this->getMock('t3lib_DataStructure_Tca');
		$mockedDataStructure->expects($this->any())->method('hasTypeField')->will($this->returnValue(TRUE));
		$mockedDataStructure->expects($this->any())->method('getAvailableTypes')->will($this->returnValue(array($typeIdentifier)));
		$mockedDataStructure->expects($this->any())->method('getTypeObject')->will($this->returnValue($mockedType));
		$mockedDataStructure->expects($this->any())->method('getIdentifier')->will($this->returnValue($dataStructureIdentifier));

		$expectedStatements = array(
			$this->prefixes['rdf'] . 'type' => array(array('value' => $this->prefixes['rdfs'] . 'Class')),
			$this->prefixes['rdf'] . 'subclassOf' => array(array('value' => $this->prefixes['t3o'] . 'ContentType'))
		);

		$statements = $this->fixture->exportDataStructure($mockedDataStructure);

		$key = Tx_RdfExport_Helper::getRdfIdentifierForType($mockedDataStructure, $mockedType);
		$this->assertIsSupersetOf($expectedStatements, $statements[$key]);
	}

	/**
	 * @test
	 */
	public function allTypesFromDataStructureAreExported() {
		$typeValues = array('foo', 'bar', 3);
		$dataStructureIdentifier = uniqid();
		$mockedDataStructure = $this->getMock('t3lib_DataStructure_Tca');
		$mockedDataStructure->expects($this->any())->method('hasTypeField')->will($this->returnValue(TRUE));
		$mockedDataStructure->expects($this->any())->method('getIdentifier')->will($this->returnValue($dataStructureIdentifier));
		$mockedDataStructure->expects($this->any())->method('getAvailableTypes')->will($this->returnValue($typeValues));

		$typeObjects = array();
		foreach ($typeValues as $typeValue) {
			$typeObjects[] = $this->getMockedType($typeValue);
		}
		$mockedDataStructure->expects($this->exactly(3))->method('getTypeObject')->will($this->onConsecutiveCalls(
			$this->returnValue($typeObjects[0]),
			$this->returnValue($typeObjects[1]),
			$this->returnValue($typeObjects[2])
		));

		$statements = $this->fixture->exportDataStructure($mockedDataStructure);

		$i = 0;
		foreach ($typeValues as $type) {
			$typeIdentifier = Tx_RdfExport_Helper::getRdfIdentifierForType($mockedDataStructure, $typeObjects[$i]);
			$this->assertArrayHasKey($typeIdentifier, $statements);
			$this->assertNotEmpty($statements[$typeIdentifier]);

			++$i;
		}
	}

	/**
	 * @test
	 */
	public function exportedTypeContainsReferencesToFields() {
		$fields = array(uniqid(), uniqid());

		$typeValue = uniqid();
		$mockedType = $this->getMockedType($typeValue);
		$mockedType->expects($this->any())->method('getFieldNames')->will($this->returnValue($fields));

		$dataStructureIdentifier = uniqid();
		$mockedDataStructure = $this->getMock('t3lib_DataStructure_Tca');
		$mockedDataStructure->expects($this->any())->method('hasTypeField')->will($this->returnValue(TRUE));
		$mockedDataStructure->expects($this->any())->method('getIdentifier')->will($this->returnValue($dataStructureIdentifier));
		$mockedDataStructure->expects($this->any())->method('getAvailableTypes')->will($this->returnValue(array($typeValue)));
		$mockedDataStructure->expects($this->any())->method('getTypeObject')->will($this->returnValue($mockedType));
		$mockedField1 = $this->getMockedField($fields[0], $mockedDataStructure);
		$mockedField2 = $this->getMockedField($fields[1], $mockedDataStructure);
		$mockedDataStructure->expects($this->exactly(2))->method('getFieldObject')->will($this->onConsecutiveCalls($mockedField1, $mockedField2));

		$statements = $this->fixture->exportDataStructure($mockedDataStructure);

		foreach ($statements as $subject => $subjectStatements) {
			if (array_key_exists($this->canonicalize('rdf:subclassOf'), $subjectStatements)
			  && $subjectStatements[$this->canonicalize('rdf:subclassOf')][0]['value'] == $this->canonicalize('t3o:ContentType')) {
				$fieldNodeId = $subjectStatements[$this->canonicalize('t3o:fields')][0]['value'];
			}
		}
		if (!$fieldNodeId) {
			$this->fail('Could not find t3o:ContentType node in output');
		}

		$this->assertEquals($this->prefixes['rdf'] . 'Bag', $statements[$fieldNodeId][$this->prefixes['rdf'] . 'type'][0]['value']);
		$this->assertEquals(Tx_RdfExport_Helper::getRdfIdentifierForField($mockedField1), $statements[$fieldNodeId][$this->prefixes['rdf'] . '_1'][0]['value']);
		$this->assertEquals(Tx_RdfExport_Helper::getRdfIdentifierForField($mockedField2), $statements[$fieldNodeId][$this->prefixes['rdf'] . '_2'][0]['value']);
	}
}