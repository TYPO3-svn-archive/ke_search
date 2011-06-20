<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Andreas Kiefer (kennziffer.com) <kiefer@kennziffer.com>
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
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(t3lib_extMgm::extPath('ke_search').'indexer/class.tx_kesearch_indexer_types.php');

/**
 * Plugin 'Faceted search' for the 'ke_search' extension.
 *
 * @author	Andreas Kiefer (kennziffer.com) <kiefer@kennziffer.com>
 * @author	Stefan Froemken (kennziffer.com) <froemken@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kesearch
 */
class tx_kesearch_indexer_types_page extends tx_kesearch_indexer_types {
	var $pids        = 0;
	var $pageRecords = array(); // this array contains all data of all pages
	var $indexCTypes = array(
		'text',
		'textpic',
		'bullets',
		'table',
		'html'
	);
	var $counter = 0;
	var $whereClauseForCType = '';

	/**
	 * @var t3lib_queryGenerator
	 */
	var $queryGen;


	/**
	 * Initializes indexer for pages
	 */
	public function __construct($pObj) {
		parent::__construct($pObj);

		$this->counter = 0;
		foreach($this->indexCTypes as $value) {
			$cTypes[] = 'CType="' . $value . '"';
		}
		$this->whereClauseForCType = implode(' OR ', $cTypes);

		// we need this object to get all contained pids
		$this->queryGen = t3lib_div::makeInstance('t3lib_queryGenerator');
	}


	/**
	 * This function was called from indexer object and saves content to index table
	 *
	 * @return string content which will be displayed in backend
	 */
	public function startIndexing() {
		$indexPids = $this->getPagelist();
		$this->pageRecords = $this->getPageRecords($indexPids);
		$this->addTagsToPageRecords($indexPids);

		// loop through pids and collect page content and tags
		foreach($indexPids as $uid) {
			if($uid) $this->getPageContent($uid);
		}

		// show indexer content?
		$content .= '<p><b>Indexer "' . $this->indexerConfig['title'] . '": ' . count($this->pageRecords) . ' pages have been found for indexing.</b></p>' . "\n";
		$content .= '<p><b>Indexer "' . $this->indexerConfig['title'] . '": ' . $this->counter . ' pages have been indexed.</b></p>' . "\n";
		$content .= $this->showTime();

		return $content;
	}


	/**
	 * get all recursive contained pids of given Page-UID
	 *
	 * @return array List of page UIDs
	 */
	function getPagelist() {
		// make array from list
		$pidsRecursive = t3lib_div::trimExplode(',', $this->indexerConfig['startingpoints_recursive'], true);
		$pidsNonRecursive = t3lib_div::trimExplode(',', $this->indexerConfig['single_pages'], true);

		// add recursive pids
		foreach($pidsRecursive as $pid) {
			// index only pages of doktype standard, advanced and "not in menu"
			$where = ' (doktype = 1 OR doktype = 2 OR doktype = 5) ';
			// index only pages which are searchable
			$where .= ' AND no_search <> 1 ';

			// if indexing of content elements with restrictions is not allowed
			// get only pages that have empty group restrictions
			if($this->indexerConfig['index_content_with_restrictions'] != 'yes') {
				$where .= ' AND (fe_group = "" OR fe_group = "0" ';
			}

			$pageList .= $this->queryGen->getTreeList($pid, 99, 0, $where);
		}

		// add non-recursive pids
		foreach($pidsNonRecursive as $pid) {
			$pageList .= $pid.',';
		}

		return t3lib_div::trimExplode(',', $pageList);
	}


	/**
	 * get array with all pages
	 * @param array Array with all page cols
	 */
	protected function getPageRecords($uids) {
		$fields = '*';
		$table = 'pages';
		$where = 'uid IN (' . implode(',', $uids) . ')';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);

		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$pages[$row['uid']] = $row;
		}
		return $pages;
	}


	/**
	 * Add Tags to pages array
	 *
	 * @param array Simple array with uids of pages
	 * @return array extended array with uids and tags for pages
	 */
	protected function addTagsToPageRecords($uids) {
		// add tags which are defined by page properties
		$fields = 'pages.*, REPLACE(GROUP_CONCAT(CONCAT("#", tx_kesearch_filteroptions.tag, "#")), ",", "") as tags';
		$table = 'pages, tx_kesearch_filteroptions';
		$where = 'pages.uid IN (' . implode(',', $uids) . ')';
		$where .= ' AND pages.tx_kesearch_tags <> "" ';
		$where .= ' AND FIND_IN_SET(tx_kesearch_filteroptions.uid, pages.tx_kesearch_tags)';
		$where .= t3lib_befunc::BEenableFields('tx_kesearch_filteroptions');
		$where .= t3lib_befunc::deleteClause('tx_kesearch_filteroptions');

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, 'pages.uid', '', '');
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$this->pageRecords[$row['uid']]['tags'] = $row['tags'];
		}

		// add tags which are defined by filteroption records

		$fields = 'automated_tagging, tag';
		$table = 'tx_kesearch_filteroptions';
		$where = 'automated_tagging <> "" ';
		$where .= t3lib_befunc::BEenableFields('tx_kesearch_filteroptions');
		$where .= t3lib_befunc::deleteClause('tx_kesearch_filteroptions');

		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where);

		// index only pages of doktype standard, advanced and "not in menu"
		$where = ' (doktype = 1 OR doktype = 2 OR doktype = 5) ';
		// index only pages which are searchable
		$where .= ' AND no_search <> 1 ';

		foreach($rows as $row) {
			$pageList = t3lib_div::trimExplode(',', $this->queryGen->getTreeList($row['automated_tagging'], 99, 0, $where));
			foreach($pageList as $uid) {
				if($this->pageRecords[$uid]['tags']) {
					$this->pageRecords[$uid]['tags'] .= ',#' . $row['tag'] . '#';
				} else {
					$this->pageRecords[$uid]['tags'] = '#' . $row['tag'] . '#';
				}
			}
		}
	}


	/**
	 * get content of current page and save data to db
	 * @param $uid page-UID that has to be indexed
	 */
	function getPageContent($uid) {

		// TODO: index all language versions of this page
		// pages.uid <=> pages_language_overlay.pid
		// language id = pages_language_overlay.sys_language_uid

		// get content elements for this page
		$fields = 'header, bodytext, CType, sys_language_uid';
		$table = 'tt_content';
		$where = 'pid = ' . intval($uid);
		$where .= ' AND (' . $this->whereClauseForCType. ')';
		$where .= t3lib_BEfunc::BEenableFields($table);
		$where .= t3lib_BEfunc::deleteClause($table);

		// if indexing of content elements with restrictions is not allowed
		// get only content elements that have empty group restrictions
		if($this->indexerConfig['index_content_with_restrictions'] != 'yes') {
			$where .= ' AND (fe_group = "" OR fe_group = "0") ';
		}

		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where);
		if(count($rows)) {
			foreach($rows as $row) {
				// header
				$pageContent[$row['sys_language_uid']] .= strip_tags($row['header']) . "\n";

				// bodytext
				$bodytext = $row['bodytext'];
				$bodytext = str_replace('<td', ' <td', $bodytext);
				$bodytext = str_replace('<br', ' <br', $bodytext);
				$bodytext = str_replace('<p', ' <p', $bodytext);

				if ($row['CType'] == 'table') {
					// replace table dividers with whitespace
					$bodytext = str_replace('|', ' ', $bodytext);
				}
				$bodytext = strip_tags($bodytext);

				$pageContent[$row['sys_language_uid']] .= $bodytext."\n";
			}
			$this->counter++;
		} else {
			return;
		}

		// get Tags for current page
		$tags = $this->pageRecords[intval($uid)]['tags'];

		// hook for custom modifications of the indexed data, e. g. the tags
		if(is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPagesIndexEntry'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPagesIndexEntry'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->modifyPagesIndexEntry(
					$this->pageRecords[$uid]['title'],
					$pageContent,
					$tags,
					$this->pageRecords[$uid],
					$additionalFields
				);
			}
		}
		
		// store record in index table
		foreach($pageContent as $langKey => $content) {
			$this->pObj->storeInIndex(
				$this->indexerConfig['storagepid'],    // storage PID
				$this->pageRecords[$uid]['title'],     // page title
				'page',                                // content type
				$uid,                                  // target PID: where is the single view?
				$content,                          // indexed content, includes the title (linebreak after title)
				$tags,                                 // tags
				'',                                    // typolink params for singleview
				'',                                    // abstract
				$langKey,                              // language uid
				$this->pageRecords[$uid]['starttime'], // starttime
				$this->pageRecords[$uid]['endtime'],   // endtime
				$this->pageRecords[$uid]['fe_group'],  // fe_group
				false,                                 // debug only?
				$additionalFields                      // additional fields added by hooks
			);			
		}

		return;
	}
}