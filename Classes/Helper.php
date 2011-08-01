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
		'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
		'rdfs' => '',
		'owl' => '',
		't3ds' => 'http://typo3.org/semantic/datastructure/',
	);

	public static function resolvePrefix($prefix) {
		return self::$prefixes[$prefix];
	}

	public static function getRdfIdentifierForDataStructure(t3lib_DataStructure_Abstract $dataStructure) {
		return self::$prefixes['t3ds'] . $dataStructure->getIdentifier();
	}

	public static function getRdfIdentifierForField(t3lib_DataStructure_Element_Field $fieldObject) {
		$dataStructure = $fieldObject->getDataStructure();
		$fieldIdentifier = $fieldObject->getName();

		return self::getRdfIdentifierForDataStructure($dataStructure) . '#' . $fieldIdentifier;
	}
}