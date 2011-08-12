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
 * 
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage 
 */
class Tx_RdfExport_Controller_ExportController extends Tx_Extbase_MVC_Controller_ActionController {
	/**
	 * @var string Key of the extension this controller belongs to
	 */
	protected $extensionName = 'MocExtbaseDemo';

	/**
	 * @var t3lib_PageRenderer
	 */
	protected $pageRenderer;

	/**
	 * @var integer
	 */
	protected $pageId;

	/**
	 * @var template
	 */
	protected $template;

	/**
	 * Initializes the controller before invoking an action method.
	 *
	 * @return void
	 */
	protected function initializeAction() {
		//$this->pageRenderer->addInlineLanguageLabelFile('EXT:workspaces/Resources/Private/Language/locallang.xml');

		$bootstrap = new \Erfurt\Core\Bootstrap('Production');
		$bootstrap->run();
	}

	public function indexAction() {
		$dataStructureList = array_keys($GLOBALS['TCA']);
		sort($dataStructureList);
		$dataStructureList = array_combine($dataStructureList, $dataStructureList);

		$this->view->assign('datastructures', $dataStructureList);
	}

	/**
	 * Exports a data structure
	 */
	public function exportAction() {
		$dataStructureName = $this->request->getArgument('datastructure');
		$this->view->assign('datastructure', $dataStructureName);

		$format = $this->request->getArgument('exportformat');
		$this->view->assign('format', $format);

		/** @var $dsResolver t3lib_DataStructure_Resolver_Tca */
		$dsResolver = t3lib_div::makeInstance('t3lib_DataStructure_Resolver_Tca');
			// TODO get data structure from parameter
		$dataStructure = $dsResolver->resolveDataStructure($dataStructureName);

		/** @var $exporter Tx_RdfExport_DataStructureExporter */
		$exporter = t3lib_div::makeInstance('Tx_RdfExport_DataStructureExporter');
		$exporter->setColumnMapper(t3lib_div::makeInstance('Tx_RdfExport_ColumnMapper'));

		$statements = $exporter->exportDataStructure($dataStructure);

		switch ($format) {
			case 'html':
				$this->exportHtml($statements);
				break;
			case 'turtle':
				$this->exportTurtle($statements);
				break;
			default:
				// TODO throw error
		}
	}

	protected function exportTurtle(array $statements) {
		/** @var $turtleSerializer Erfurt\Syntax\RdfSerializer\Adapter\Turtle */
		$turtleSerializer = t3lib_div::makeInstance('Erfurt\Syntax\RdfSerializer\Adapter\Turtle');
		foreach (Tx_RdfExport_Helper::getPrefixes() as $prefix => $ns) {
			$turtleSerializer->handleNamespace($prefix, $ns);
		}
		$turtleSerializer->startRdf('');
		foreach ($statements as $subject => $subjectStatements) {
			foreach ($subjectStatements as $predicate => $object) {
				if (substr($object, 0, 2) == '_:') {
					$oType = 'bnode';
				} elseif (Tx_RdfExport_Helper::isIri($object)) {
					$oType = 'iri';
				} else {
					$oType = '';
				}
				if (Tx_RdfExport_Helper::isIri($subject)) {
					$sType = 'iri';
				} else {
					$sType = '';
				}

					// TODO add $lang and $dType
				$turtleSerializer->handleStatement($subject, $predicate, $object, $sType, $oType);
			}
		}
		$turtle = $turtleSerializer->endRdf();

		$this->view->assign('turtle', $turtle);
	}

	protected function exportHtml(array $statements) {
		$this->view->assign('statements', $statements);
	}

	/**
	 * Processes a general request. The result can be returned by altering the given response.
	 *
	 * @param Tx_Extbase_MVC_RequestInterface $request The request object
	 * @param Tx_Extbase_MVC_ResponseInterface $response The response, modified by this handler
	 * @throws Tx_Extbase_MVC_Exception_UnsupportedRequestType if the controller doesn't support the current request type
	 * @return void
	 */
	public function processRequest(Tx_Extbase_MVC_RequestInterface $request, Tx_Extbase_MVC_ResponseInterface $response) {
		$this->template = t3lib_div::makeInstance('template');
		$this->pageRenderer = $this->template->getPageRenderer();

		$GLOBALS['SOBE'] = new stdClass();
		$GLOBALS['SOBE']->doc = $this->template;

		parent::processRequest($request, $response);

		$pageHeader = $this->template->startpage('Foobar'
			//$GLOBALS['LANG']->sL('LLL:EXT:workspaces/Resources/Private/Language/locallang.xml:module.title')
		);
		$pageEnd = $this->template->endPage();

		$response->setContent($pageHeader . $response->getContent() . $pageEnd);
	}
}
