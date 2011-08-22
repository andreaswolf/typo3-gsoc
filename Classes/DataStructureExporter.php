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
class Tx_RdfExport_DataStructureExporter extends Tx_RdfExport_AbstractExporter {

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @var \Erfurt\Object\ObjectManager
	 */
	protected $objectManager;

	/**
	 * The base URI of the graph
	 *
	 * @var string
	 */
	protected $graphBaseUri;


	public function __construct($table) {
		$this->table = $table;
	}

	public function injectObjectManager(\Erfurt\Object\ObjectManager $manager) {
		$this->objectManager = $manager;
		return $this;
	}

	public function initializeObject() {
	}

	/**
	 * Exports a table definition (from the TYPO3 Table Configuration Array) as RDF-Schema
	 *
	 * @param t3lib_DataStructure_Abstract $dataStructureObject The data structure to export
	 * @return array The statements that represent the data structure
	 */
	public function exportDataStructure(t3lib_DataStructure_Abstract $dataStructureObject) {
		$this->statements = array();

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
}

