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
 * The column mapper for mapping database column descriptions to semantic data types
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage rdf_export
 */
class Tx_RdfExport_ColumnMapper {

	protected function createObject($value, $dataType = NULL, $language = NULL) {
		return array(
			'value' => $value
		);
	}
	/**
	 * Maps a column description (e.g. from TCA) to RDF statements
	 *
	 * @param array $columnDescription
	 * @return
	 */
	public function mapColumnDescriptionToRdfDataType(t3lib_DataStructure_Element_Field $column, $columnNodeName = '') {
		/**
		 * - examine column configuration
		 * - choose a type that fits
		 *
		 * tbd:
		 *  - define sensible types for each column type defined in TCA (also respect e.g. eval for input)
		 *  - find out if any kind of automagic conversion might make sense here
		 */
		$configuration = $column->getConfiguration();
		$configuration = $configuration['config'];

		$statements = array();
		switch($configuration['type']) {
			case 'input':
				$statements = $this->mapInputFieldToStatements($configuration);

				break;

			case 'text':
				$statements = $this->mapTextFieldToStatements($configuration);

				break;

			case 'group':
				switch ($configuration['internal_type']) {
					case 'db':
						$statements = $this->mapDatabaseRelationFieldToStatements($configuration);

						break;

					default:
						throw new InvalidArgumentException('No mapping found for column of type "group", internal type "'
						                                   . $configuration['internal_type'] . '".', 1312379153);
				}

				break;

			default:
				throw new InvalidArgumentException('No mapping found for column type "' . $configuration['type'] . '".', 1310670994);
		}

		$statements['_'][Tx_RdfExport_Helper::canonicalize('rdfs:domain')] = array(
			$this->createObject(Tx_RdfExport_Helper::getRdfIdentifierForDataStructure($column->getDataStructure()))
		);
		$statements['_'][Tx_RdfExport_Helper::canonicalize('rdfs:subclassOf')] = array(
			$this->createObject(Tx_RdfExport_Helper::canonicalize('rdf:Property'))
		);
		$statements['_'][Tx_RdfExport_Helper::canonicalize('rdf:type')] = array(
			$this->createObject(Tx_RdfExport_Helper::canonicalize('rdf:Class'))
		);
		$statements['_'][Tx_RdfExport_Helper::canonicalize('rdfs:comment')] = array(
			$this->createObject('Column ' . $column->getName())
		);
		$statements['_'][Tx_RdfExport_Helper::canonicalize('rdfs:label')] = array(
			$this->createObject($column->getName())
		);

			// rename the column node from the placeholder _ to the specified name
		if ($columnNodeName == '') {
			$columnNodeName = '_:' . uniqid();
		}
		$statements[$columnNodeName] = $statements['_'];
		unset($statements['_']);

		return array($columnNodeName, $statements);
	}

	/**
	 * Maps a field of type "input" to RDF statements.
	 *
	 * @param array $configuration The column configuration
	 * @return array Some statements describing the column (predicate as key, object as value)
	 * @throws InvalidArgumentException If no mapping could be determined (i.e. the eval type is not supported)
	 */
	protected function mapInputFieldToStatements($configuration) {
		$type = '';
		$statements = array();

		if (!isset($configuration['eval'])) {
			$type = Tx_RdfExport_Helper::canonicalize('xsd:string');
		} else {
			if (preg_match('/int/', $configuration['eval'])) {
				// TODO add mapping for ranges here
				$type = Tx_RdfExport_Helper::canonicalize('xsd:integer');
			} elseif (preg_match('/datetime/', $configuration['eval'])) {
				$type = Tx_RdfExport_Helper::canonicalize('xsd:dateTime');
			} elseif (preg_match('/date/', $configuration['eval'])) {
				$type = Tx_RdfExport_Helper::canonicalize('xsd:date');
			} elseif (preg_match('/time|timesec/', $configuration['eval'])) {
				$type = Tx_RdfExport_Helper::canonicalize('xsd:time');
			}
		}

		if ($type === '') {
			throw new InvalidArgumentException('No mapping found for input column.', 1310670995);
		}

		$statements['_'][Tx_RdfExport_Helper::canonicalize('rdfs:range')] = array($this->createObject($type));

		return $statements;
	}

	/**
	 * Maps a field of type "text" to RDF statements.
	 *
	 * @param array $configuration The column configuration
	 * @return array Some statements describing the column (predicate as key, object as value)
	 */
	protected function mapTextFieldToStatements($configuration) {
		return array(
			'_' => array(
				Tx_RdfExport_Helper::canonicalize('rdfs:range') => array(
					$this->createObject(Tx_RdfExport_Helper::canonicalize('xsd:string'))
				)
			)
		);
	}

	/**
	 * Maps a db relation field (type "group", internal type "db") to RDF statements
	 *
	 * @param array $configuration The column configuration
	 * @return array Some statements describing the column (predicate as key, object as value)
	 */
	protected function mapDatabaseRelationFieldToStatements($configuration) {
		$statements = array();

		$tables = t3lib_div::trimExplode(',', $configuration['allowed']);

		$tableIdentifiers = array();
		foreach ($tables as $table) {
			$tableIdentifiers[] = Tx_RdfExport_Helper::getRdfIdentifierForTable($table);
		}

		list($firstRangeNodeIdentifier, $statements) = Tx_RdfExport_Helper::convertArrayToRdfNodes($tableIdentifiers);

		$rangeNodeIdentifier = '_:' . uniqid();
		$statements[$rangeNodeIdentifier] = array(
			Tx_RdfExport_Helper::canonicalize('owl:unionOf') => array($this->createObject($firstRangeNodeIdentifier))
		);
		$statements['_'] = array(
			Tx_RdfExport_Helper::canonicalize('rdfs:range') => array($this->createObject($rangeNodeIdentifier))
		);

		return $statements;
	}
}
