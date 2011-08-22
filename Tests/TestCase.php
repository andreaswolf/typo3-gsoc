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
 * Base test case for the RDF export extension
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage Tx_RdfExport
 */
abstract class Tx_RdfExport_TestCase extends Tx_Phpunit_TestCase {
	protected $prefixes = array(
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

	public function assertIsSupersetOf($expectedSubset, $superset) {
		foreach ($expectedSubset as $key => $value) {
			if (!array_key_exists($key, $superset) || $superset[$key] !== $value) {
				$this->fail(sprintf("Failed asserting that\n%s\nis a superset of\n%s", print_r($superset, TRUE), print_r($expectedSubset, TRUE)));
			}
		}
	}
}