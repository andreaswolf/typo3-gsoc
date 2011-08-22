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
 * An abstract base for the exporter classes.
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage Tx_RdfExport
 */
abstract class Tx_RdfExport_AbstractExporter {

	/**
	 * @var Tx_RdfExport_ColumnMapper
	 */
	protected $columnMapper;

	/**
	 * @var array
	 */
	protected $statements = array();

	public function setColumnMapper($columnMapper) {
		$this->columnMapper = $columnMapper;
	}

	/**
	 * Adds a statement to the internal statement store; this is a convenience function that also does de-references
	 * the prefixes.
	 *
	 * @param string $subject
	 * @param string $predicate
	 * @param mixed $object The object; can be an array (for  or a string, which
	 * @return void
	 *
	 * @see addMultipleStatements
	 */
	protected function addStatement($subject, $predicate, $object) {
		if (!is_array($object)) {
			$object = array('value' => $object);
		}
		$subject = Tx_RdfExport_Helper::canonicalize($subject);
		$predicate = Tx_RdfExport_Helper::canonicalize($predicate);
		$object['value'] = Tx_RdfExport_Helper::canonicalize($object['value']);
		$this->statements = t3lib_div::array_merge_recursive_overrule($this->statements, array($subject => array($predicate => array($object))));
	}

	/**
	 * Adds multiple statements to the internal statement store. This also allows multiple objects for each
	 * subject/predicate combination.
	 *
	 * @param array $statements
	 * @return void
	 */
	protected function addMultipleStatements(array $statements) {
		foreach ($statements as $subject => $subjectStatements) {
			foreach ($subjectStatements as $predicate => $objects) {
				foreach ($objects as $object) {
					$this->addStatement($subject, $predicate, $object);
				}
			}
		}
	}
}