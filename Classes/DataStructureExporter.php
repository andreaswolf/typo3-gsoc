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
 * Exports the definition of a table to RDF. The results are written to an (in-memory) graph object that may be injected
 * or is created during initialization
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage Tx_RdfExport
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

	/**
	 * @var array
	 */
	protected $statements = array();


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
	 * Exports a table definition (from the TYPO3 Table Configuration Array) as RDF-Schema
	 *
	 * @param t3lib_DataStructure_Abstract $dataStructureObject The data structure to export
	 * @return array The statements that represent the data structure
	 */
	public function exportDataStructure(t3lib_DataStructure_Abstract $dataStructureObject) {
		$this->statements = array();
		// TODO import relevant Ontologies here if neccessary; or do this during initalization
		/** @var $RdfParser \Erfurt\Syntax\RdfParser */
		//$RdfParser = $this->objectManager->get('\Erfurt\Syntax\RdfParser', 'rdfxml');
		//$RdfParser->initializeObject();
		//$parsedOntology = $RdfParser->parseToStore('/tmp/typo3tables.rdf', \Erfurt\Syntax\RdfParser::LOCATOR_FILE, 'http://typo3.org');

		if ($dataStructureObject->hasTypeField()) {
			$types = $dataStructureObject->getAvailableTypes();
			foreach ($types as $type) {
				$typeObject = $dataStructureObject->getTypeObject($type);
				$this->mapTypeObjectToStatements($dataStructureObject, $typeObject);
			}
		} else {
			// TODO get default type, export it
		}

		$this->mapDataStructureMetadataToStatements($dataStructureObject);

			// Looping over all fields, exporting them to triples
		foreach ($dataStructureObject->getFieldNames() as $fieldName) {
			$fieldObject = $dataStructureObject->getFieldObject($fieldName);

			$statements = array();
			try {
				$columnNodeName = Tx_RdfExport_Helper::getRdfIdentifierForField($fieldObject);
				list(, $statements) = $this->columnMapper->mapColumnDescriptionToRdfDataType($fieldObject, $columnNodeName);
			} catch (InvalidArgumentException $e) {
				// handle exception: column could not be mapped
			}

			$this->addMultipleStatements($statements);
		}

		// check here if any columns could not be mapped, TODO decide how this will be handled

		return $this->statements;
	}

	protected function mapDataStructureMetadataToStatements(t3lib_DataStructure_Abstract $dataStructureObject) {
		$table = $dataStructureObject->getIdentifier();

		if ($dataStructureObject->hasControlValue('crdate')) {
			$columnName = $dataStructureObject->getControlValue('crdate');
			$columnObject = $dataStructureObject->getFieldObject($columnName);
			$subject = Tx_RdfExport_Helper::getRdfIdentifierForField($columnObject);

			$this->addMultipleStatements(array(
				$subject => array(
					'rdf:type' => array(array('value' => 'rdf:Property')),
					'owl:sameAs' => array(array('value' => 'dcterms:created')),
				)
			));
		}
			// TODO check for labelAlt
		if ($dataStructureObject->hasControlValue('label')) {
			$columnName = $dataStructureObject->getControlValue('label');
			$columnObject = $dataStructureObject->getFieldObject($columnName);
			$subject = Tx_RdfExport_Helper::getRdfIdentifierForField($columnObject);

			$this->addMultipleStatements(array(
				$subject => array(
					'owl:sameAs' => array(array('value' => 'dc:title'))
				)
			));
		}
	}

	protected function mapTypeObjectToStatements(t3lib_DataStructure_Abstract $dataStructure, t3lib_DataStructure_Type $typeObject) {
		$typeUri = Tx_RdfExport_Helper::getRdfIdentifierForType($dataStructure, $typeObject);

		$this->addStatement($typeUri, 'rdf:type', 'rdfs:Class');
		$this->addStatement($typeUri, 'rdf:subclassOf', 't3o:ContentType');
		$this->addStatement($typeUri, 'rdfs:comment', sprintf('Type %s in data structure %s', $typeObject->getIdentifier(), $dataStructure->getIdentifier()));

		$fieldNamesBlankNodeId = Tx_RdfExport_Helper::generateBlankNodeId();
		$this->addStatement($typeUri, 't3o:fields', $fieldNamesBlankNodeId);
		$this->addStatement($fieldNamesBlankNodeId, 'rdf:type', 'rdf:Bag');

		$i = 0;
		foreach ($typeObject->getFieldNames() as $fieldName) {
			++$i;

			$fieldObject = $dataStructure->getFieldObject($fieldName);
			$this->addStatement($fieldNamesBlankNodeId, 'rdf:_' . $i, Tx_RdfExport_Helper::getRdfIdentifierForField($fieldObject));
		}
	}

	protected function addStatement($subject, $predicate, $object) {
		if (!is_array($object)) {
			$object = array('value' => $object);
		}
		$subject = Tx_RdfExport_Helper::canonicalize($subject);
		$predicate = Tx_RdfExport_Helper::canonicalize($predicate);
		$object['value'] = Tx_RdfExport_Helper::canonicalize($object['value']);
		$this->statements = t3lib_div::array_merge_recursive_overrule($this->statements, array($subject => array($predicate => array($object))));
	}

	protected function addMultipleStatements($statements) {
		foreach ($statements as $subject => $subjectStatements) {
			foreach ($subjectStatements as $predicate => $objects) {
				foreach ($objects as $object) {
					$this->addStatement($subject, $predicate, $object);
				}
			}
		}
	}
}

