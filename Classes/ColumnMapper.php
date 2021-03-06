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
 * @subpackage Tx_RdfExport
 */
class Tx_RdfExport_ColumnMapper {

	/**
	 * Creates an array representing an object in an RDF triple. The array is ready to be used with the Erfurt library.
	 *
	 * @param $value
	 * @param string $dataType
	 * @param string $language
	 * @return array
	 *
	 * TODO implement language handling
	 */
	protected function createObject($value, $dataType = NULL, $language = NULL) {
		$object = array(
			'value' => $value
		);
		if ($dataType !== NULL) {
			$object['type'] = $dataType;
		}

		return $object;
	}

	/**
	 * Creates a predicate => object array for a given field object
	 *
	 * @param t3lib_DataStructure_Element_Field $fieldObject
	 * @param mixed $fieldValue
	 * @return array
	 */
	public function mapFieldValueToStatement(t3lib_DataStructure_Element_Field $fieldObject, $fieldValue, $recordNodeIdentifier = '') {
		$configuration = $fieldObject->getConfiguration();
		$configuration = $configuration['config'];

		switch ($configuration['type']) {
			case 'group':
				list($object, $additionalStatements) = $this->generateGroupFieldValueMapping($fieldObject, $fieldValue);

				break;

			default:
				list($object, $additionalStatements) = $this->generateSimpleFieldValueMapping($fieldObject, $fieldValue);
		}

		return t3lib_div::array_merge_recursive_overrule(array($recordNodeIdentifier => $object), (array)$additionalStatements);
	}

	/**
	 * Maps a value of a field of type "group".
	 *
	 * TODO implement MM handling
	 * TODO implement internal types other than "db" (file, folder, file_reference)
	 *
	 * @throws RuntimeException
	 * @param t3lib_DataStructure_Element_Field $fieldObject
	 * @param $fieldValue
	 * @return array
	 */
	protected function generateGroupFieldValueMapping(t3lib_DataStructure_Element_Field $fieldObject, $fieldValue) {
		$configuration = $fieldObject->getConfiguration();
		$configuration = $configuration['config'];

		switch ($configuration['internal_type']) {
			case 'db':
				if (isset($configuration['MM'])) {
					// TODO resolve MM table
				} else {
					$allowed = $configuration['allowed'];
					$onlySingleTableAllowed = (strpos($allowed, ',') === FALSE && $allowed !== '*');

						// TODO handle single-value relations (maxitems == 1) correctly if a value is set -> don't use a blank node, but export the value directly
					if ($configuration['maxitems'] == 1 && $fieldValue == '0') {
						return(array(
							array(Tx_RdfExport_Helper::getRdfIdentifierForField($fieldObject) => array($this->createObject('rdf:nil'))),
							array()
						));
					}

						// copied from t3lib_TCEforms::getSingleField_typeGroup()
					$temp_itemArray = t3lib_div::trimExplode(',', $fieldValue, TRUE);
					foreach ($temp_itemArray as $dbRead) {
						$recordParts = explode('|', $dbRead);
						list($this_table, $this_uid) = t3lib_BEfunc::splitTable_Uid($recordParts[0]);
							// For the case that no table was found and only a single table is defined to be allowed, use that one:
						if (!$this_table && $onlySingleTableAllowed) {
							$this_table = $allowed;
						}
						$itemArray[] = array('table' => $this_table, 'id' => $this_uid);
					}
				}

				$blankNodeIdentifier = Tx_RdfExport_Helper::generateBlankNodeId();
				$statements[$blankNodeIdentifier] = array(Tx_RdfExport_Helper::canonicalize('rdf:type') => $this->createObject('rdf:Seq'));
				$i = 0;
				foreach ($itemArray as $record) {
					++$i;
					$statements[$blankNodeIdentifier][Tx_RdfExport_Helper::canonicalize("rdf:_$i")] =
						array($this->createObject(Tx_RdfExport_Helper::getRdfIdentifierForRecord($record['table'], $record['id'])));
				}

				$object = array(Tx_RdfExport_Helper::getRdfIdentifierForField($fieldObject) => array($this->createObject($blankNodeIdentifier)));

				break;
			default:
				throw new RuntimeException('Not implemented.', 1313415423);
				// TODO implement other types
		}

		return array($object, $statements);
	}

	/**
	 * Generates a mapping for a "simple" value type, e.g. an integer, a date or a string
	 *
	 * @throws InvalidArgumentException
	 * @param t3lib_DataStructure_Element_Field $fieldObject The values field object
	 * @param mixed $fieldValue
	 * @return array
	 */
	protected function generateSimpleFieldValueMapping(t3lib_DataStructure_Element_Field $fieldObject, $fieldValue) {
		$configuration = $fieldObject->getConfiguration();
		$configuration = $configuration['config'];

		$statements = $this->generateTypeDependentStatements($configuration);

		if (!$statements['_'][Tx_RdfExport_Helper::canonicalize('rdfs:range')][0]['value']) {
			throw new InvalidArgumentException('No usable rdfs:range statement found');
		}

		$dataType = $statements['_'][Tx_RdfExport_Helper::canonicalize('rdfs:range')][0]['value'];
		switch ($dataType) {
			case Tx_RdfExport_Helper::canonicalize('xsd:integer'):
				$fieldValue = intval($fieldValue);

				break;

				// date/time according to ISO 8601
			case Tx_RdfExport_Helper::canonicalize('xsd:dateTime'):
				$fieldValue = gmstrftime('%Y-%m-%dT%H:%M:%SZ', $fieldValue);

				break;

			case Tx_RdfExport_Helper::canonicalize('xsd:date'):
					// correct the field value by half a day; otherwise we get in trouble as TYPO3 saves the value
					// as midnight in the local time zone (which will be converted a time on to the day before when
					// transforming to GMT - at least for all time zones right of GMT, e.g. CE[S]T)
				$fieldValue = gmstrftime('%Y-%m-%d', $fieldValue + 43200);

				break;

			case Tx_RdfExport_Helper::canonicalize('xsd:time'):
				$fieldValue = gmstrftime('%H:%M:%SZ', $fieldValue);

				break;
		}

		$object = array(Tx_RdfExport_Helper::getRdfIdentifierForField($fieldObject) => array($this->createObject($fieldValue, $dataType)));

		return array($object, $additionalStatements);
	}

	/**
	 * Maps a column description (e.g. from TCA) to RDF statements
	 *
	 * @param t3lib_DataStructure_Element_Field $column
	 * @param string $columnNodeName
	 * @return array
	 *
	 * TODO rename to ...ToStatements
	 */
	public function mapColumnDescriptionToRdfDataType(t3lib_DataStructure_Element_Field $column, $columnNodeName = '') {
		$configuration = $column->getConfiguration();
		$configuration = $configuration['config'];

		$statements = $this->generateTypeDependentStatements($configuration);

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
	 * Generates all statements that depend on the type of column, e.g. input, text or group. First and foremost,
	 * this is the rdfs:range statement that denotes the datatype used in the column
	 *
	 * @throws InvalidArgumentException
	 * @param $configuration
	 * @return array
	 */
	protected function generateTypeDependentStatements($configuration) {
		switch ($configuration['type']) {
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

				// TODO implement the missing types here
			default:
				throw new InvalidArgumentException('No mapping found for column type "' . $configuration['type'] . '".', 1310670994);
		}
		return $statements;
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
		$tables = t3lib_div::trimExplode(',', $configuration['allowed']);

		$tableIdentifiers = array();
		foreach ($tables as $table) {
			$tableIdentifiers[] = Tx_RdfExport_Helper::getRdfIdentifierForTable($table);
		}

		list($firstRangeNodeIdentifier, $statements) = Tx_RdfExport_Helper::convertArrayToRdfNodes($tableIdentifiers);

		$rangeNodeIdentifier = Tx_RdfExport_Helper::generateBlankNodeId();
		$statements[$rangeNodeIdentifier] = array(
			Tx_RdfExport_Helper::canonicalize('owl:unionOf') => array($this->createObject($firstRangeNodeIdentifier))
		);
		$statements['_'] = array(
			Tx_RdfExport_Helper::canonicalize('rdfs:range') => array($this->createObject($rangeNodeIdentifier))
		);

		return $statements;
	}
}
