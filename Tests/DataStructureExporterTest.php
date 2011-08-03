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
	 * @test
	 */
	public function exporterContainsGraphObjectAfterInitialization() {
		$this->fixture->setGraph($this->getMockedGraph());
		$this->fixture->initializeObject();
		$this->assertInternalType('object', $this->fixture->getGraph());
		$this->assertInstanceOf('\Erfurt\Domain\Model\Rdf\Graph', $this->fixture->getGraph());
	}

	/**
	 * @test
	 */
	public function noTriplesAreAddedForEmptyTca() {
		$tca = array('ctrl' => array(), 'columns' => array());
		$this->createFakeDataStructureObject($tca);
		$this->fixture->setGraph($this->getMockedGraph());
		$this->fixture->exportDataStructure($this->dataStructureFixture);

		$graphStore = $this->fixture->getGraphStore();
		$graph = $this->fixture->getGraph();

		$this->assertEquals(0, count($graph->getMatchingStatements(NULL, NULL, NULL)));
	}

	/**
	 * @test
	 */
	public function tripleForCreationDateIsAddedIfTableHasCreationDate() {
		$tca = array('ctrl' => array('crdate' => uniqid()), 'columns' => array());
		$this->createFakeDataStructureObject($tca);

		/** @var $graph \Erfurt\Domain\Model\Rdfs\Graph */
		$graph = $this->getMockedGraph();
		$graph->expects($this->once())->method('addMultipleStatements')
		      ->will($this->returnCallback(array($this, 'tripleForCreationDateIsAddedIfTableHasCreationDate_checkParametersCallback')));

		$this->fixture->setGraph($graph);
		$this->fixture->initializeObject();

		$this->fixture->exportDataStructure($this->dataStructureFixture);

		// all assertions are done in the callback below
	}

	public function tripleForCreationDateIsAddedIfTableHasCreationDate_checkParametersCallback() {
		$args = func_get_args();
		$statements = $args[0];
		$predicates = array_shift(array_values($statements));
		$fieldName = $this->dataStructureTcaFixture['ctrl']['crdate'];

		$this->assertStringEndsWith($this->tableName . '.' . $fieldName, array_shift(array_keys($statements)));
		$this->assertArrayHasKey('rdf:type', $predicates);
		$this->assertEquals('rdf:property', $predicates['rdf:type']);
		$this->assertArrayHasKey('rdfs:subPropertyOf', $predicates);
		$this->assertEquals('dcterms:created', $predicates['rdfs:subPropertyOf']);
	}

	/**
	 * @test
	 * @group integration
	 */
	public function statementsFromColumnMapperAreAddedAfterMapping() {
		$this->createFakeDataStructureObject(array('columns' => array('someColumn' => array())));

		$expectedStatements = array(
			uniqid() => array(
				uniqid() => uniqid(),
				uniqid() => uniqid()
			)
		);

		/** @var $graph \Erfurt\Domain\Model\Rdfs\Graph */
		$graph = $this->getMockedGraph();
		$graph->expects($this->once())->method('addMultipleStatements')
		      ->with($expectedStatements);
		$mockedColumnMapper = $this->getMock('Tx_RdfExport_ColumnMapper');
		$mockedColumnMapper->expects($this->once())->method('mapColumnDescriptionToRdfDataType')->will($this->returnValue($expectedStatements));

		$this->fixture->setGraph($graph);
		$this->fixture->setColumnMapper($mockedColumnMapper);
		$this->fixture->initializeObject();

		$this->fixture->exportDataStructure($this->dataStructureFixture);
	}
}
