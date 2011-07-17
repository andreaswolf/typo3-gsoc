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
 * The column mapper for mapping database column descriptions to semantic data types
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage rdf_export
 */
class Tx_RdfExport_ColumnMapper {
	/**
	 * @param array $columnDescription
	 * @return
	 */
	public function mapColumnDescriptionToRdfDataType(t3lib_DataStructure_Element_Field $column) {
		/**
		 * - examine column configuration
		 * - choose a type that fits
		 *
		 * tbd:
		 *  - define sensible types for each column type defined in TCA (also respect e.g. eval for input)
		 *  - find out if any kind of automagic conversion might make sense here
		 */
		$configuration = $column->getConfiguration();

		$result = '';
		switch($configuration['type']) {
			case 'input':
				$result = $this->mapInputFieldToDataType($configuration);

				break;
			default:
				throw new InvalidArgumentException('No mapping found for column type ' . $configuration['type'], 1310670994);
		}

		return $result;
	}

	protected function mapInputFieldToDataType($configuration) {
		$type = '';
		$statements = array();

		if (!isset($configuration['eval'])) {
			$type = 'http://www.w3.org/2001/XMLSchema#string';
		} else {
			if (preg_match('/int/', $configuration['eval'])) {
				// TODO add mapping for ranges here
				$type = 'http://www.w3.org/2001/XMLSchema#integer';
			} elseif (preg_match('/datetime/', $configuration['eval'])) {
				$type = 'http://www.w3.org/2001/XMLSchema#dateTime';
			} elseif (preg_match('/date/', $configuration['eval'])) {
				$type = 'http://www.w3.org/2001/XMLSchema#date';
			} elseif (preg_match('/time|timesec/', $configuration['eval'])) {
				$type = 'http://www.w3.org/2001/XMLSchema#time';
			}
		}

		if ($type === '') {
			throw new InvalidArgumentException('No mapping found for input column.', 1310670995);
		}

		$statements['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] = $type;

		return $statements;
	}
}
