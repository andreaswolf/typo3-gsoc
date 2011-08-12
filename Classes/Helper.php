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
 * A generic helper class for the TYPO3 RDF exporter
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage rdf_export
 */
class Tx_RdfExport_Helper {
	protected static $prefixes = array(
		'dc' => 'http://purl.org/dc/elements/1.1/',
		'dcterms' => 'http://purl.org/dc/terms/',
		'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
		'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
		'owl' => 'http://www.w3.org/2002/07/owl#',
		't3o' => 'http://typo3.org/semantic#',
		't3ds' => 'http://typo3.org/semantic/datastructures/',
		't3dt' => 'http://typo3.org/semantic/datatypes/',
		'xsd' => 'http://www.w3.org/2001/XMLSchema#'
	);

	public static function canonicalize($identifier) {
		if (strpos($identifier, ':') > 0 && $identifier{0} !== '_') {
			$prefix = substr($identifier, 0, strpos($identifier, ':'));
			if (self::isDefinedPrefix($prefix)) {
				$identifier = Tx_RdfExport_Helper::resolvePrefix($prefix) . substr($identifier, strpos($identifier, ':') + 1);
			}
		}

		return $identifier;
	}

	public static function getPrefixes() {
		return self::$prefixes;
	}

	public static function isDefinedPrefix($prefix) {
		return array_key_exists($prefix, self::$prefixes);
	}

	public static function resolvePrefix($prefix) {
		return self::$prefixes[$prefix];
	}

	public static function getRdfIdentifierForDataStructure(t3lib_DataStructure_Abstract $dataStructure) {
		return self::$prefixes['t3ds'] . $dataStructure->getIdentifier();
	}

	public static function getRdfIdentifierForType(t3lib_DataStructure_Abstract $dataStructure, t3lib_DataStructure_Type $type) {
		return self::$prefixes['t3dt'] . $dataStructure->getIdentifier() . '-' . $type->getIdentifier();
	}

	public static function getRdfIdentifierForField(t3lib_DataStructure_Element_Field $fieldObject) {
		$dataStructure = $fieldObject->getDataStructure();
		$fieldIdentifier = $fieldObject->getName();

		return self::getRdfIdentifierForDataStructure($dataStructure) . '#' . $fieldIdentifier;
		#return sprintf('urn:uuid:%s', sha1(self::getRdfIdentifierForDataStructure($dataStructure) . '#' . $fieldIdentifier));
	}

	public static function generateBlankNodeId() {
		return uniqid('_:bNode');
	}

	public static function isIri($iri) {
		if (substr($iri, 0, 4) == 'urn:' || substr($iri, 0, 5) == 'http:' || substr($iri, 0, 6) == 'https:') {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Returns a unique identifier for a database table.
	 *
	 * @static
	 * @param $table
	 * @return string
	 */
	public static function getRdfIdentifierForTable($table) {
		return self::resolvePrefix('t3ds') . $table;
	}

	/**
	 * Converts an array to a chained structure of anonymous RDF nodes with rdf:first and rdf:rest properties.
	 *
	 * @static
	 * @param array $sourceArray The array to convert
	 * @return array Statements with the subject as the first-level key, predicates as the second-level key and objects as the values
	 * @see http://www.w3.org/TR/2004/REC-rdf-primer-20040210/#collections
	 */
	public static function convertArrayToRdfNodes($sourceArray) {
		$sourceArray = array_reverse($sourceArray);

		$statements = array();
		$previousNode = '';
		foreach ($sourceArray as $entry) {
			$nodeIdentifier = '_:' . uniqid();

			$statement = array();
			$statement[self::resolvePrefix('rdf') . 'first'] = $entry;

			if ($previousNode == '') {
				$statement[self::resolvePrefix('rdf') . 'rest'] = self::resolvePrefix('rdf') . 'nil';
			} else {
				$statement[self::resolvePrefix('rdf') . 'rest'] = $previousNode;
			}

			$previousNode = $nodeIdentifier;
			$statements[$nodeIdentifier] = $statement;
		}

		return array($previousNode, array_reverse($statements));
	}
}
