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
 * Exports the definition of a table to RDF. The results are written to an (in-memory) graph object that may be injected
 * or is created during initialization
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage rdf_export
 */
class Tx_RdfExport_DataStructureExporter {

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @var \Erfurt\Object\ObjectManager
	 */
	protected $objectManager;

	/**
	 * @var \Erfurt\Store\Store
	 */
	protected $graphStore;

	/**
	 * @var \Erfurt\Domain\Model\Rdf\Graph
	 */
	protected $graph;

	/**
	 * The base URI of the graph
	 *
	 * @var string
	 */
	protected $graphBaseUri;

	/**
	 * @var Tx_RdfExport_ColumnMapper
	 */
	protected $columnMapper;


	public function __construct($table) {
		$this->table = $table;

			// TODO make this "dynamic"
		$this->graphBaseUri = 'http://typo3.org/semantic/datastructure/';
	}

	public function injectObjectManager(\Erfurt\Object\ObjectManager $manager) {
		$this->objectManager = $manager;
		return $this;
	}

	public function injectStore(\Erfurt\Store\Store $store) {
		$this->graphStore = $store;
		return $this;
	}

	public function initializeObject() {
		//$bootstrap = new \Erfurt\Core\Bootstrap('Development');
		//$bootstrap->run();
		//$this->objectManager = $bootstrap->getObjectManager();
		//$this->graphStore = $this->objectManager->get('\Erfurt\Store\Store');
		//$this->graphStore->setBackendAdapter($this->objectManager->get('\Erfurt\Store\Adapter\Memory'));
		//print_R($this->graphStore);
		if (!$this->graph) {
			$this->graph = $this->graphStore->getNewGraph($this->graphBaseUri);
		}
		if (!$this->columnMapper) {
			$this->columnMapper = new Tx_RdfExport_ColumnMapper();
		}
	}

	public function setGraph($graph) {
		$this->graph = $graph;
	}

	public function getGraph() {
		return $this->graph;
	}

	public function getGraphStore() {
		return $this->graphStore;
	}

	public function setColumnMapper($columnMapper) {
		$this->columnMapper = $columnMapper;
	}

	/**
	 * Exports a table definiton (from the TYPO3 Table Configuration Array) as RDF-Schema
	 *
	 * @param  $table
	 * @return void
	 */
	public function exportTable(t3lib_DataStructure_Abstract $dataStructureObject) {
		/**
		 * - get table description object (t3lib_DataStructure)
		 * - create graph
		 * - generate a sensible RDFS description for each column
		 */
		/** @var $dataStructureResolver t3lib_DataStructure_Resolver_Tca */
		//$dataStructureResolver = t3lib_div::makeInstance('t3lib_DataStructure_Resolver_Tca');
		//$tableDefinitionObject = $dataStructureResolver->resolveDataStructure($table);

		/** @var $RdfParser \Erfurt\Syntax\RdfParser */
		//$RdfParser = $this->objectManager->get('\Erfurt\Syntax\RdfParser', 'rdfxml');
		//$RdfParser->initializeObject();
		//$parsedOntology = $RdfParser->parseToStore('/tmp/typo3tables.rdf', \Erfurt\Syntax\RdfParser::LOCATOR_FILE, 'http://typo3.org');

		//$this->graphStore->addStatement();

		$this->addDataStructureMetadataStatementsToStore($dataStructureObject);

		foreach ($dataStructureObject->getFieldNames() as $fieldName) {
			$fieldObject = $dataStructureObject->getFieldObject($fieldName);

			$statements = array();
			try {
				// get $statements from ColumnMapper object
				$statements = $this->columnMapper->mapColumnDescriptionToRdfDataType($fieldObject);
			} catch (RuntimeException $e) {
				// handle exception: column could not be mapped
			}

			// add statements to store
			$this->graph->addMultipleStatements($statements);
		}

		// check here if any columns could not be mapped, TODO decide how this will be handled
	}

	protected function addDataStructureMetadataStatementsToStore(t3lib_DataStructure_Abstract $dataStructureObject) {
		$table = $dataStructureObject->getIdentifier();

		if ($dataStructureObject->hasControlValue('crdate')) {
			$columnName = $dataStructureObject->getControlValue('crdate');
			$subject = $this->graphBaseUri . $this->table . '.' . $columnName;

			$this->graph->addMultipleStatements(array(
				$subject => array(
					'rdf:type' => 'rdf:property',
					'rdfs:subPropertyOf' => 'dcterms:created', // TODO can sameAs be used for properties? Should it be used here?
				)
			));
		}
		//
	}

	/**
	 * 
	 *
	 * @param string $table
	 * @param string $predicate
	 * @param string $object
	 * @return void
	 */
	protected function addTupleForTable($table, $predicate, $object) {
	}
}

