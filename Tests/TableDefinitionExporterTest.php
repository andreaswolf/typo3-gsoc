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
 * Testcase for the table definition exporter
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage 
 */
class Tx_RdfExport_TableDefinitionExporterTest extends Tx_Phpunit_TestCase {
	/**
	 * @var Tx_RdfExport_TableDefinitionExporter
	 */
	protected $fixture;

	/**
	 * @var string
	 */
	protected $tableName;

	/**
	 * @var Tx_RdfExport_TableDefinitionExporter
	 */
	public function setUp() {
		parent::setUp();

		$this->tableName = uniqid();
		$this->fixture = new Tx_RdfExport_TableDefinitionExporter($this->tableName);
	}

	protected function addFakeTcaTable($tcaEntry) {
		$GLOBALS['TCA'][$this->tableName] = $tcaEntry;
	}

	protected function getMockedGraph() {
		return $this->getMock('\Erfurt\Domain\Model\Rdfs\Graph');
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
		$this->addFakeTcaTable($tca);
		$this->fixture->setGraph($this->getMockedGraph());
		$this->fixture->exportTable($this->tableName);

		$graphStore = $this->fixture->getGraphStore();
		$graph = $this->fixture->getGraph();
		//print_r($this->readAttribute($graphStore, 'backendAdapter'));

		//$this->markTestIncomplete('This test can be implemented if the statement Store class has a getMatchingStatements() method.');
		$this->assertEquals(0, count($graph->getMatchingStatements(NULL, NULL, NULL)));
	}

	/**
	 * @test
	 */
	public function tripleForCreationDateIsAddedIfTableHasCreationDate() {
		$tca = array('ctrl' => array('crdate' => uniqid()), 'columns' => array());
		$this->addFakeTcaTable($tca);

		/** @var $graph \Erfurt\Domain\Model\Rdfs\Graph */
		$graph = $this->getMockedGraph();
		$graph->expects($this->once())->method('addMultipleStatements')
		      ->will($this->returnCallback(array($this, 'tripleForCreationDateIsAddedIfTableHasCreationDate_checkParametersCallback')));

		$this->fixture->setGraph($graph);

		$this->fixture->initializeObject();
		$this->fixture->exportTable($this->tableName);
	}

	public function tripleForCreationDateIsAddedIfTableHasCreationDate_checkParametersCallback() {
		$args = func_get_args();
		$statements = $args[0];
		$predicates = array_shift(array_values($statements));
		$fieldName = $GLOBALS['TCA'][$this->tableName]['ctrl']['crdate'];

		$this->assertStringEndsWith($this->tableName . '.' . $fieldName, array_shift(array_keys($statements)));
		$this->assertArrayHasKey('rdf:type', $predicates);
		$this->assertEquals('rdf:property', $predicates['rdf:type']);
		$this->assertArrayHasKey('rdfs:subPropertyOf', $predicates);
		$this->assertEquals('dcterms:created', $predicates['rdf:sameAs']);
	}
}
