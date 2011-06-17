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


/**
 * Plugin 'Faceted search' for the 'ke_search' extension.
 *
 * @author	Andreas Kiefer (kennziffer.com) <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kesearch
 */
class tx_kesearch_indexer {
	var $startMicrotime = 0;
	var $counter;
	var $extConf;

	/*
	 * function startIndexing
	 * @param $verbose boolean 	if set, information about the indexing process is returned, otherwise processing is quiet
	 * @param $extConf array			extension config array from EXT Manager
	 * @param $mode string				"CLI" if called from command line, otherwise empty
	 * @return string							only if param $verbose is true
	 */
	function startIndexing($verbose=true, $extConf, $mode='')  {

		// make extConf available
		$this->extConf = $extConf;

		// get configurations
		$configurations = $this->getConfigurations();
		foreach ($configurations as $key => $indexerConfig) {
			$this->startMicrotime = microtime(true);

			switch ($indexerConfig['type']) {

				// indexer for page content (text and textpic)
				case 'page':
					// set pages configuration
					$this->pids['recursive'] = $indexerConfig['startingpoints_recursive'];
					$this->pids['non_recursive'] = $indexerConfig['single_pages'];

					// get pagelist
					$this->getPagelist();

					// make array
					$indexPids = explode(',',$this->pagelist);

					// show indexer content?
					$content .= '<p><b>Indexer "' . $indexerConfig['title'] . '": ' . count($indexPids) . ' pages have been indexed.</b></p>'."\n";

					// get rootline tags
					$this->rootlineTags = $this->getRootlineTags();

					// loop through pids and collect page content and tags
					if (is_array($indexPids)) {
						foreach ($indexPids as $pid) {
							if ($pid) $words = $this->getPageContent($pid, $indexerConfig);
						}
					}

					$content .= $this->showTime($indexerConfig);
					break;

				// indexer for tt_news records
				case 'ttnews':
					$content .= $this->indexNews($indexerConfig);
					$content .= $this->showTime($indexerConfig);
					break;

				// indexer for ke_yac records
				case 'ke_yac':
					$content .= $this->indexYACRecords($indexerConfig);
					$content .= $this->showTime($indexerConfig);
					break;

				// indexer for dam records (ke_downloadshop)
				case 'dam':
					$content .= $this->indexDAMRecords($indexerConfig);
					$content .= $this->showTime($indexerConfig);
					break;

				// indexer for xtypocommerce products
				case 'xtypocommerce':
					$content .= $this->indexXTYPOCommerceRecords($indexerConfig);
					$content .= $this->showTime($indexerConfig);
					break;

				// indexer for tt_address records
				case 'tt_address':
					$content .= $this->indexAddressRecords($indexerConfig);
					$content .= $this->showTime($indexerConfig);
					break;

					// use custom indexer code
				default:
						// hook for custom indexer
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['customIndexer'])) {
						foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['customIndexer'] as $_classRef) {
							$_procObj = & t3lib_div::getUserObj($_classRef);
							$content .= $_procObj->customIndexer($indexerConfig, $this);
						}
					}
					break;
			}
		}

		// process index cleanup?
		if ($extConf['cleanupInterval']) {
			$content .= '<p><b>Index cleanup processed</b></p>'."\n";
			$content .= $this->cleanUpIndex($extConf['cleanupInterval']);
		}

		// send notification in CLI mode
		if ($mode == 'CLI') {

			// send finishNotification
			if ($extConf['finishNotification'] && t3lib_div::validEmail($extConf['notificationRecipient'])) {

				// build message
				$msg = 'Indexing process was finished:'."\n";
				$msg .= "==============================\n\n";
				$msg .= strip_tags($content);
				// send the notification message
				mail($extConf['notificationRecipient'], $extConf['notificationSubject'], $msg);
			}

		}

		// verbose or quiet output? as set in function call!
		if ($verbose) return $content;

	}



	/*
	 * function indexYACRecords
	 * param 		array $indexerConfig
	 */
	function indexYACRecords($indexerConfig) {

		$now = strtotime('today');

		// get YAC records from specified pid
		$fields = '*';
		$table = 'tx_keyac_dates';
		$where = 'pid IN ('.$indexerConfig['sysfolder'].') ';
		$where .= ' AND hidden=0 AND deleted=0 ';
		// do not index passed events?
		if ($indexerConfig['index_passed_events'] == 'no') {
			if (t3lib_extMgm::isLoaded('ke_yac_products')) {
				// special query if ke_yac_products loaded (VNR)
				$where .= '
					AND ((
						tx_keyacproducts_type<>"product"
						AND (startdat >= "'.time().'" OR enddat >= "'.time().'")
					) OR (tx_keyacproducts_type="product" AND tx_keyacproducts_product<>""))';
			} else {
				// "normal" YAC events
				$where .= ' AND (startdat >= "'.time().'" OR enddat >= "'.time().'")';
			}
		}

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$resCount = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		if ($resCount) {
			while ($yacRecord = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				// prepare content for storing in index table
				$title = strip_tags($yacRecord['title']);
				$tags = '';
				$params = '&tx_keyac_pi1[showUid]='.intval($yacRecord['uid']);
				$abstract = str_replace('<br />', chr(13), $yacRecord['teaser']);
				$abstract = str_replace('<br>', chr(13), $abstract);
				$abstract = str_replace('</p>', chr(13), $abstract);
				$abstract = strip_tags($abstract);
				$content = strip_tags($yacRecord['bodytext']);
				$fullContent = $title . "\n" . $abstract . "\n" . $content;
				$targetPID = $indexerConfig['targetpid'];

				// get tags
				$yacRecordTags = t3lib_div::trimExplode(',',$yacRecord['tx_keyacsearchtags_tags'], true);
				$tags = '';
				$clearTextTags = '';
				if (count($yacRecordTags)) {
					foreach ($yacRecordTags as $key => $tagUid)  {
						$tags .= '#'.$this->getTag($tagUid).'#';
						$clearTextTags .= chr(13).$this->getTag($tagUid, true);
					}
				}

				// add clearText Tags to content
				if (!empty($clearTextTags)) $fullContent .= chr(13).$clearTextTags;

				// hook for custom modifications of the indexed data, e. g. the tags
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyYACIndexEntry'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyYACIndexEntry'] as $_classRef) {
						$_procObj = & t3lib_div::getUserObj($_classRef);
						$_procObj->modifyYACIndexEntry(
							$title,
							$abstract,
							$fullContent,
							$params,
							$tags,
							$yacRecord,
							$targetPID,
							$additionalFields
						);
					}
				}

				// store data in index table
				$this->storeInIndex(
					$indexerConfig['storagepid'],				// storage PID
					$title,										// page/record title
					'ke_yac', 									// content type
					$targetPID,									// target PID: where is the single view?
					$fullContent, 								// indexed content, includes the title (linebreak after title)
					$tags,				 						// tags
					$params, 									// typolink params for singleview
					$abstract,									// abstract
					$yacRecord['sys_language_uid'],				// language uid
					$yacRecord['starttime'], 					// starttime
					$yacRecord['endtime'], 						// endtime
					$yacRecord['fe_group'], 					// fe_group
					false 										// debug only?
				);

			}
		}

		$content = '<p><b>Indexer "' . $indexerConfig['title'] . '": ' . $resCount . ' YAC records have been indexed.</b></p>';
		return $content;

	}




	/*
	 * function getTag
	 * @param int $tagUid
	 * @param bool $clearText
	 */

	function getTag($tagUid, $clearText=false) {

		$fields = 'title,tag';
		$table = 'tx_kesearch_filteroptions';
		$where = 'uid="'.intval($tagUid).'" ';
		$where .= t3lib_befunc::BEenableFields($table,$inv=0);
		$where .= t3lib_befunc::deleteClause($table,$inv=0);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);

		if ($clearText) {
			return $row['title'];
		} else {
			return $row['tag'];
		}

	}

	/**
 	* Helper function for generating tags.
 	* Adds tags generated from a db relation (commalist of uids, not mm-table).
 	*
 	* @param   string $from_table
 	* @param   string $titleField
 	* @param   string $uidList
 	* @param   string $prefix prefix for each generated tag, prevents tags shorte than 4 chars
 	* @param   boolean $rewriteTageName if set, the tag name will be rewritte, e.g. spaces will be removed
 	* @return  string list of tags, comma separated
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Mon Jan 10 2011 13:56:24 GMT+0100
 	*/
	public function makeTagsFromDBRelation($from_table, $titleField, $uidList, $prefix = '', $rewriteTageName = true) {
			// get the data
		$where_clause = 'uid IN' . '(' . $uidList . ')';
		$where_clause .= t3lib_BEfunc::deleteClause($from_table);
		$where_clause .= t3lib_BEfunc::BEenableFields($from_table);
		$tags = '';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($titleField, $from_table, $where_clause);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$tag = $row[$titleField];
				if ($rewriteTageName) {
					$tag = $this->reWriteTagName($tag);
				}
				$tags .= '#' . $prefix . $tag . '#';
			}
		}
		return $tags;
	}

	/**
 	* Helper function for generating tags.
 	* Renders a list of tags from a commalist of text values.
 	*
 	* @param   string $valuelist list of comma separated values (string values, not list of uids)
 	* @param   string $prefix prefix for each generated tag, prevents tags shorte than 4 chars
 	* @param   boolean $rewriteTageName if set, the tag name will be rewritte, e.g. spaces will be removed
 	* @return  string list of tags
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Mon Jan 10 2011 14:13:10 GMT+0100
 	*/
	public function makeTagsFromCommalist($valuelist, $prefix = '', $rewriteTagName = true) {
		$tags = '';
		$listArray = t3lib_div::trimExplode(',', $valuelist);
		if (count($listArray)) {
			foreach($listArray as $value) {
				$tag = $value;
				if ($rewriteTageName) {
					$tag = $this->reWriteTagName($tag);
				}
				$tags .= '#' . $prefix . $tag . '#';
			}
		}
		return $tags;
	}

	/**
 	* rewrites a string in order to make it more suitable as a tag
 	* replaces spaces and umlauts
 	*
 	* @param   string
 	* @return  string
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Tue Jan 11 2011 16:14:42 GMT+0100
 	*/
	public function reWriteTagName($tag) {
		$tag = trim(strtolower($tag));
		$tag = str_replace(' ', '_', $tag);
		$tag = str_replace('ä', 'ae', $tag);
		$tag = str_replace('ö', 'oe', $tag);
		$tag = str_replace('ü', 'ue', $tag);
		$tag = str_replace('ß', 'ss', $tag);
		return $tag;
	}

	/**
 	* indexes tt_news
 	*
 	* @param   array $indexerConfig
 	* @return  string message
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Wed Oct 27 2010 16:10:41 GMT+0200
 	*/
	function indexNews($indexerConfig) {
		$content = '';

			// get all the tt_news entries to index
			// don't index hidden or deleted news, BUT
			// get the news with frontend user group access restrictions
			// or time (start / stop) restrictions.
			// Copy those restrictions to the index.
		$fields = '*';
		$table = 'tt_news';
		$where = 'pid IN (' . $indexerConfig['sysfolder'] . ') AND hidden = 0 AND deleted = 0';
		$groupBy = '';
		$orderBy = '';
		$limit = '';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy,$orderBy,$limit);
		$resCount = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		if ($resCount) {
			while ( ($newsRecord = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) ) {

					// compile the information which should go into the index
				$title = strip_tags($newsRecord['title']);
				$abstract = strip_tags($newsRecord['short']);
				$content = strip_tags($newsRecord['bodytext']);
				$fullContent = $title . "\n" . $abstract . "\n" . $content;
				$params = '&tx_ttnews[tt_news]=' . $newsRecord['uid'];
				$tags = '';
				$additionalFields = array();

				// hook for custom modifications of the indexed data, e. g. the tags
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyNewsIndexEntry'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyNewsIndexEntry'] as $_classRef) {
						$_procObj = & t3lib_div::getUserObj($_classRef);
						$_procObj->modifyNewsIndexEntry(
							$title,
							$abstract,
							$fullContent,
							$params,
							$tags,
							$newsRecord,
							$additionalFields
						);
					}
				}

				// ... and store them
				$this->storeInIndex(
					$indexerConfig['storagepid'],    // storage PID
					$title,                          // page title
					'tt_news',                       // content type
					$indexerConfig['targetpid'],     // target PID: where is the single view?
					$fullContent,                    // indexed content, includes the title (linebreak after title)
					$tags,                           // tags
					$params,                         // typolink params for singleview
					$abstract,                       // abstract
					$newsRecord['sys_language_uid'], // language uid
					$newsRecord['starttime'],        // starttime
					$newsRecord['endtime'],          // endtime
					$newsRecord['fe_group'],         // fe_group
					false,                           // debug only?
					$additionalFields                // additional fields added by hooks
				);
			}
			$content = '<p><b>Indexer "' . $indexerConfig['title'] . '": ' . $resCount . ' News have been indexed.</b></p>';
		}
		return $content;
	}



	/**
 	* helper function, shows time used
 	*
 	* @param   array $indexerConfig
 	* @return  string
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Wed Oct 27 2010 16:04:11 GMT+0200
 	*/
	function showTime($indexerConfig) {
		// calculate duration of indexing process
		$endMicrotime = microtime(true);
		$duration = ceil(($endMicrotime - $this->startMicrotime) * 1000);

		// show sec or ms?
		if ($duration > 10000) {
			$duration /= 1000;
			$duration = intval($duration);
			return '<p><i>Indexing process for "' . $indexerConfig['title'] . '" took '.$duration.' s.</i> </p>'."\n\n";
		} else {
			return '<p><i>Indexing process for "' . $indexerConfig['title'] . '" took '.$duration.' ms.</i> </p>'."\n\n";
		}


	}




	/**
	* Delete all index elements that are older than $interval (in hours)
	* @param	int		$interval
	*/
	function cleanUpIndex($interval) {

		$startMicrotime = microtime(true);

		$interval = time() - ($interval * 60 * 60);
		$table = 'tx_kesearch_index';
		$where = 'tstamp <= "'.$interval.'" ';
		$GLOBALS['TYPO3_DB']->exec_DELETEquery($table,$where);

		// calculate duration of indexing process
		$endMicrotime = microtime(true);
		$duration = ceil(($endMicrotime - $startMicrotime) * 1000);
		$content .= '<p><i>Cleanup process took '.$duration.' ms.</i></p>';
		return $content;

	}



	/*
	 * function storeInIndex
	 */
	function storeInIndex($storagepid, $title, $type, $targetpid, $content, $tags='', $params='', $abstract='', $language=0, $starttime=0, $endtime=0, $fe_group, $debugOnly=false, $additionalFields=array()) {

		// check for errors
		$errors = array();
		if ($type != 'xtypocommerce' && empty($storagepid)) $errors[] = 'No storage PID set';
		if (empty($title)) $errors[] = 'No title set';
		if (empty($type)) $errors[] = 'No type set';
		if (empty($targetpid)) $errors[] = 'No target PID set';

		// stop executing if errors occured
		if (sizeof($errors)) {
			foreach ($errors as $error) {
				$errorMessage .= $error.chr(10).chr(13);
			}
			t3lib_div::debug($errorMessage,'ERROR WHILE STORING INDEX FOR PAGE '.$targetpid.' - '.$params);
		}


		$table = 'tx_kesearch_index';
		$now = time();
		$fields_values = array(
			'pid' => intval($storagepid),
			'title' => $title,
			'type' => $type,
			'targetpid' => $targetpid,
			'content' => $content,
			'tags' => $tags,
			'params' => $params,
			'abstract' => $abstract,
			'language' => $language,
			'starttime' => $starttime,
			'endtime' => $endtime,
			'fe_group' => $fe_group,
			'tstamp' => $now,
			'crdate' => $now,
		);

		if (count($additionalFields)) {
			// merge arrays
			$fields_values = array_merge($fields_values, $additionalFields);
		}

		// check if record already exists
		$existingRecordUid = $this->indexRecordExists($storagepid, $targetpid, $type, $params);
		if ($existingRecordUid) {
			// update existing record
			$where = 'uid="'.intval($existingRecordUid).'" ';
			unset($fields_values['crdate']);
			if ($debugOnly) { // do not process - just debug query
				t3lib_div::debug($GLOBALS['TYPO3_DB']->UPDATEquery($table,$where,$fields_values),1);
			} else { // process storing of index record and return uid
				if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$fields_values)) {

					// count record for periodic notification?
					if ($this->extConf['periodicNotification']) $this->periodicNotificationCount();

					return true;
				}
			}
		} else {
			// insert new record
			if ($debugOnly) { // do not process - just debug query
				t3lib_div::debug($GLOBALS['TYPO3_DB']->INSERTquery($table,$fields_values,$no_quote_fields=FALSE),1);
			} else { // process storing of index record and return uid
				$GLOBALS['TYPO3_DB']->exec_INSERTquery($table,$fields_values,$no_quote_fields=FALSE);

				// count record for periodic notification?
				if ($this->extConf['periodicNotification']) $this->periodicNotificationCount();

				return $GLOBALS['TYPO3_DB']->sql_insert_id();
			}
		}
	}


	/*
	 * function getPageContent
	 * @param $pid				pid that has to be indexed
	 * @param $indexerConfig		config record
	 */
	function getPageContent($pid, $indexerConfig) {

		$additionalFields = array();

		// get page record
		$pageRecord = $this->getPageRecord($pid);

		// index only pages of doktype standard, advanced and "not in menu"
		// t3lib_div::debug($pageRecord,1); die();
		if ($pageRecord['doktype'] != 1 && $pageRecord['doktype'] != 2 && $pageRecord['doktype'] != 5) return '[SKIPPED] wrong doktype';

		// do not index pages that have option "no_search" set
		if ($pageRecord['no_search'] == 1) return '[SKIPPED] no_search';

		// TODO: index all language versions of this page
		// pages.uid <=> pages_language_overlay.pid
		// language id = pages_language_overlay.sys_language_uid

		// init page content
		$pageContent = $pageRecord['title']."\n\n";

		// get content elements for this page
		$fields = 'header,bodytext,CType';
		$table = 'tt_content';
		$where = 'pid="'.intval($pid).'" ';

		// index "Text" and "Text with pic" elements
		$where .= 'AND (CType="text" OR CType="textpic" OR CType="bullets" OR CType="table" OR CType="html") ';

		// hidden and deleted content elements are not indexed
		$where .= 'AND hidden=0 AND deleted=0 ';

		// do not index content elements that don't have guilty time settings
		$now = time();
		$where .= ' AND (starttime<='.$now.') AND (endtime=0 OR endtime>'.$now.') ';

		// if indexing of content elements with restrictions is not allowed
		// get only content elements that have empty group restrictions
		if ($indexerConfig['index_content_with_restrictions'] != 'yes') $where .= ' AND fe_group="" ';

		// t3lib_div::debug($GLOBALS['TYPO3_DB']->SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit=''));
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		if ($anz) {
			while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

				// header
				$pageContent .= strip_tags($row['header'])."\n";

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

				$pageContent .= $bodytext."\n";
			}
		} else {
			// no text element founds
			$pageContent = '';
			return 'No content elements found';
		}

		$tags = $this->getTagsForPage($pid);

		// hook for custom modifications of the indexed data, e. g. the tags
		if(is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPagesIndexEntry'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPagesIndexEntry'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->modifyPagesIndexEntry(
					$pageRecord['title'],
					$pageContent,
					$tags,
					$pageRecord,
					$additionalFields
				);
			}
		}

		// store record in index table
		$this->storeInIndex(
			$indexerConfig['storagepid'],   // storage PID
			$pageRecord['title'],           // page title
			'page',                         // content type
			$pid,                           // target PID: where is the single view?
			$pageContent,                   // indexed content, includes the title (linebreak after title)
			$tags,                          // tags
			'',                             // typolink params for singleview
			'',                             // abstract
			0,                              // language uid
			$pageRecord['starttime'],       // starttime
			$pageRecord['endtime'],         // endtime
			$pageRecord['fe_group'],        // fe_group
			false,                          // debug only?
			$additionalFields               // additional fields added by hooks
		);

		return str_word_count($pageContent).' words';
	}



	/*
	 * function clearIndex
	 * @param $storagepid			storage pid
	 * @param $targetpid			target pid for index record
	 * @param $type string		type ("page" or "ke_yac" yet)
	 * @param $params string		needed if other elements than pages are used
	 */
	function clearIndex($storagepid, $targetpid, $type, $params='') {
		$table = 'tx_kesearch_index';
		$where = 'pid="'.intval($storagepid).'" ';
		$where .= 'AND targetpid="'.intval($targetpid).'" ';
		$where .= 'AND type="'.$type.'" ';
		if (!empty($params))$where .= 'AND params="'.$params.'" ';
		$GLOBALS['TYPO3_DB']->exec_DELETEquery($table,$where);
	}


	/*
	 * function indexRecordExists
	 */
	function indexRecordExists($storagepid, $targetpid, $type, $params='') {
		$fields = 'uid';
		$table = 'tx_kesearch_index';
		$where = 'pid="'.intval($storagepid).'" ';
		$where .= 'AND targetpid="'.intval($targetpid).'" ';
		$where .= 'AND type="'.$type.'" ';
		if (!empty($params)) $where .= ' AND params="'.$params.'" ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			return $row['uid'];
		}
		return false;
	}



	/*
	 * function getConfigurations
	 */
	function getConfigurations() {
		$fields = '*';
		$table = 'tx_kesearch_indexerconfig';
		$where = '1=1 ';
		$where .= t3lib_befunc::BEenableFields($table,$inv=0);
		$where .= t3lib_befunc::deleteClause($table,$inv=0);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$results[] = $row;
		}
		return $results;
	}




	/*
	 * function getPageTitle
	 */
	function getPageRecord($pid,$field='') {
		$fields = $field ? $field : '*';
		$table = 'pages';
		$where = 'uid="'.intval($pid).'" ';
		// $where .= t3lib_befunc::BEenableFields($table,$inv=0);
		// $where .= t3lib_befunc::deleteClause($table,$inv=0);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
		// t3lib_div::debug($GLOBALS['TYPO3_DB']->SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1'));
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		if ($field) return $row[$field];
		else return $row;
	}



	/*
	 * function getTagsForPage
	 * @param $pid
	 */
	function getTagsForPage($pid) {

		// TODO: improve query

		$fields = '*';
		$table = 'pages';
		$where = 'uid="'.intval($pid).'" ';
		$where .= ' AND tx_kesearch_tags<>"" ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$tagsContent = '';
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$fields2 = '*';
			$table2 = 'tx_kesearch_filteroptions';
			$where2 = 'uid in ('.$row['tx_kesearch_tags'].')';
			$where2 .= t3lib_befunc::BEenableFields('tx_kesearch_filteroptions');
			$where2 .= t3lib_befunc::deleteClause('tx_kesearch_filteroptions');
			$res2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields2,$table2,$where2);
			while ($row2=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res2)) {
				$tagsContent .= '#'.$row2['tag'].'#';
			}
		}

		// check if rootline Tags have to be set
		$tagsContent = $this->setRootlineTags($pid, $this->rootlineTags, $tagsContent);

		return $tagsContent;
	}



	// GET PAGES FOR INDEXING
	function getPagelist() {

		// make array from list
		$pidsRecursive = t3lib_div::trimExplode(',',$this->pids['recursive'],true);
		$pidsNonRecursive = t3lib_div::trimExplode(',', $this->pids['non_recursive'], true);

		// get recursive pids
		if (count($pidsRecursive)) {
			foreach ($pidsRecursive as $pid) {
				$this->pagelist .= $pid.',';
				$this->getRecursivePids($pid);
			}
		}

		// add non-recursive pids
		foreach ($pidsNonRecursive as $pid) {
			$this->pagelist .= $pid.',';
		}
		$this->pagelist = t3lib_div::rm_endcomma($this->pagelist);
	}



	// GET SUBPAGES
	function getRecursivePids($startPid) {
		// t3lib_div::debug('rec',1);
		$fields = 'uid, hidden, deleted';
		$table = 'pages';
		$where = 'pid='.intval($startPid);
		// $where .= ' AND hidden=0 AND deleted=0';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			// add to pagelist if not hidden and not deleted
			if ($row['deleted'] == 0 && $row['hidden'] == 0) {
				$this->pagelist .= $row['uid'].',';
			}
			$this->getRecursivePids($row['uid']);
		}
	}



	/*
	 * function getRootlineTags
	 */
	function getRootlineTags() {
		$fields = 'automated_tagging as foreign_pid, tag';
		$table = 'tx_kesearch_filteroptions';
		$where = 'automated_tagging <> "" ';
		// $where = 'AND pid in () "" '; TODO
		$where .= t3lib_befunc::BEenableFields($table,$inv=0);
		$where .= t3lib_befunc::deleteClause($table,$inv=0);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$results[] = $row;
		}
		return $results;
	}


	/*
	 * function setRootlineTags
	 * @param $pid
	 */
	function setRootlineTags($pid, $rootlineTags, $tagsContent) {
		if (count($this->rootlineTags)) {
			foreach($this->rootlineTags as $key => $data) {
				if ($this->checkPIDUpInRootline($pid, $data['foreign_pid']) && !strstr($tagsContent,'#'.$data['tag'].'#')) $tagsContent .= '#'.$data['tag'].'#';
			}
		}

		return $tagsContent;
	}


	/*
	 * function checkPIDUpInRootline
	 * @param $localPid
	 * @param $foreignPid
	 */
	function checkPIDUpInRootline($localPid, $foreignPid) {

		if ($localPid == $foreignPid) return true;

		// make instance of t3lib_pageSelect if not exists
		if (!is_object($this->sys_page)) {
			$this->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
		}
		// Get the root line
		$rootline = $this->sys_page->getRootLine($localPid);
		// run through pids and check for parent pid in rootline
		foreach ($rootline as $rootlineNode) {
			if ($rootlineNode['pid'] == $foreignPid) return true;
		}
		return false;
	}



	/*
	 * function indexDAMRecords
	 */
	function indexDAMRecords($indexerConfig) {

		// get categories
		$this->catList = $indexerConfig['index_dam_categories'].',';

		// add recursive categories if set in indexer config
		if ($indexerConfig['index_dam_categories_recursive']) {
			$categoriesArray = t3lib_div::trimExplode(',', $indexerConfig['index_dam_categories'], true);
			foreach ($categoriesArray as $key => $catUid) {
				$this->getRecursiveDAMCategories($catUid);
			}
		}
		// make unique list values
		$this->catList = t3lib_div::uniqueList($this->catList);

		// get dam records from categories
		$fields = 'DISTINCT tx_dam.*';
		$table = 'tx_dam_mm_cat, tx_dam';
		$where = 'uid_foreign IN ('.$this->catList.')';
		$where .= ' AND tx_dam_mm_cat.uid_local = tx_dam.uid ';
		$where .= t3lib_befunc::BEenableFields('tx_dam',$inv=0);
		$where .= t3lib_befunc::deleteClause('tx_dam',$inv=0);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');

		$resCount = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		if ($resCount) {
			while ($damRecord=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

				$additionalFields = array();

				// prepare content for storing in index table
				$title = strip_tags($damRecord['title']);
				$params = '&tx_kedownloadshop_pi1[showUid]='.intval($damRecord['uid']);
				$abstract = str_replace('<br />', chr(13), $damRecord['tx_kedownloadshop_teaser']);
				$abstract = str_replace('<br>', chr(13), $abstract);
				$abstract = str_replace('</p>', chr(13), $abstract);
				$abstract = strip_tags($abstract);
				$content = strip_tags($damRecord['tx_kedownloadshop_description']);
				$pagetitle = strip_tags($damRecord['tx_kedownloadshop_pagetitle']);
				$keywords = strip_tags($damRecord['keywords']);
				$filename = strip_tags($damRecord['file_name']);
				$fullContent = $title . "\n" . $pagetitle . "\n" . $abstract . "\n" . $content . "\n" . $keywords . "\n" . $filename;
				$targetPID = $indexerConfig['targetpid'];

				// get tags for this record
				// needs extension ke_search_dam_tags
				if (t3lib_extMgm::isLoaded('ke_search_dam_tags')) {
					$damRecordTags = t3lib_div::trimExplode(',',$damRecord['tx_kesearchdamtags_tags'], true);
					$tags = '';
					$clearTextTags = '';
					if (count($damRecordTags)) {
						foreach ($damRecordTags as $key => $tagUid)  {
							$tags .= '#'.$this->getTag($tagUid).'#';
							$clearTextTags .= chr(13).$this->getTag($tagUid, true);
						}
					}

				} else {
					$tags = '';
				}

				// hook for custom modifications of the indexed data, e. g. the tags
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyDAMIndexEntry'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyDAMIndexEntry'] as $_classRef) {
						$_procObj = & t3lib_div::getUserObj($_classRef);
						$_procObj->modifyDAMIndexEntry(
							$title,
							$abstract,
							$fullContent,
							$params,
							$tags,
							$damRecord,
							$targetPID,
							$clearTextTags,
							$additionalFields
						);
					}
				}

					// add clearText Tags to content, make them searchable
					// by fulltext search
				if (!empty($clearTextTags)) $fullContent .= $clearTextTags;

				// store data in index table
				$this->storeInIndex(
					$indexerConfig['storagepid'],   // storage PID
					$title,                         // page/record title
					'dam',                          // content type
					$indexerConfig['targetpid'],    // target PID: where is the single view?
					$fullContent,                   // indexed content, includes the title (linebreak after title)
					$tags,                          // tags
					$params,                        // typolink params for singleview
					$abstract,                      // abstract
					$damRecord['sys_language_uid'], // language uid
					$damRecord['starttime'],        // starttime
					$damRecord['endtime'],          // endtime
					$damRecord['fe_group'],         // fe_group
					false,                          // debug only?
					$additionalFields               // additional fields added by hooks
				);

			}
		}

		$content = '<p><b>Indexer "' . $indexerConfig['title'] . '": ' . $resCount . ' DAM records have been indexed.</b></p>';
		return $content;
	}


	/*
	 * function getDAMSubcategories
	 * @param $arg
	 */
	function getRecursiveDAMCategories($catUid) {
		$fields = 'uid, parent_id';
		$table = 'tx_dam_cat';
		$where = 'parent_id="'.intval($catUid).'" ';
		$where .= t3lib_befunc::BEenableFields($table,$inv=0);
		$where .= t3lib_befunc::deleteClause($table,$inv=0);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$this->catList .= $row['uid'].',';
			$this->getRecursiveDAMCategories($row['uid']);
		}
	}



	/*
	 * function indexXTYPOCommerceRecords
	 * @param $indexerConfig
	 */
	function indexXTYPOCommerceRecords($indexerConfig) {
		$content = '';

		// get xtypocommerce products
		$fields = '*';
		$table = 'tx_xtypocommerce_products, tx_xtypocommerce_products_description';
		$where = 'tx_xtypocommerce_products.products_id = tx_xtypocommerce_products_description.products_id';
		$where .= ' AND tx_xtypocommerce_products_description.products_name <> "" ';
		$where .= ' AND tx_xtypocommerce_products_description.products_description <> "" ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$resCount = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		while ($prodRecord=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

			$additionalFields = array();

			// prepare content for storing in index table
			$title = strip_tags($prodRecord['products_name']);
			$tags = '';
			$params = '&xtypocommerce[product]='.intval($prodRecord['products_id']);
			$description = strip_tags($prodRecord['products_description']);

			// keywords
			$keywordsContent = '';
			$keywords = t3lib_div::trimExplode(',', $prodRecord['products_keywords'], true);
			if (is_array($keywords)) {
				foreach ($keywords as $index => $keyword) {
					$keywordsContent .= $keyword."\n";
				}
			}

			// build full content
			$fullContent = $title . "\n" . $description. "\n" . $keywordsContent;

			// set target pid
			$targetPID = $indexerConfig['targetpid'];

			// get tags
			$tagContent = '';
			// categories
			$catRootline = $this->getXTYPOCommerceCategories($prodRecord['products_id']);
			if (is_array($catRootline)) {
				foreach ($catRootline as $productId => $categories) {
					$catArray = t3lib_div::trimExplode(',', $categories);
					foreach($catArray as $key => $catId)  {
						$tagContent .= '#category_'.$catId.'#';
					}
				}
			}

			// regions
			if (!empty($prodRecord['products_region']) || !empty($prodRecord['products_countries'])) {
				$tagContent .= $this->getXTYPOCommerceRegionAndCountryTags($prodRecord['products_region'], $prodRecord['products_countries']);
			}
			// manufacturer
			if (!empty($prodRecord['manufacturers_id'])) {
				// publisher
				$tagContent .= '#publisher_'.$prodRecord['manufacturers_id'].'#';
			}
			// language
			if (!empty($prodRecord['products_language'])) {
				// language
				$lang = $prodRecord['products_language'];
				if (stristr($lang, 'niederl')) $lang = 'niederlaendisch';
				$tagContent .= '#language_'.$lang.'#';
			}
			// publish date
			if (!empty($prodRecord['products_monat']) && !empty($prodRecord['products_jahr'])) {
				// date
				$tagContent .= '#year_'.$prodRecord['products_jahr'].'#';

				// set date as sortdate
				$additionalFields['sortdate'] = mktime(0,0,0,$prodRecord['products_monat'], 1, $prodRecord['products_jahr']);
			}

			// hook for custom modifications of the indexed data, e. g. the tags
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyXTYPOCommerceIndexEntry'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyXTYPOCommerceIndexEntry'] as $_classRef) {
					$_procObj = & t3lib_div::getUserObj($_classRef);
					$_procObj->modifyXTYPOCommerceIndexEntry(
						$title,
						$abstract,
						$fullContent,
						$params,
						$tagContent,
						$prodRecord,
						$additionalFields
					);
				}
			}

			// store in index
			$this->storeInIndex(
				$indexerConfig['storagepid'], // storage PID
				$title,                       // page/record title
				'xtypocommerce',              // content type
				$targetPID,                   // target PID: where is the single view?
				$fullContent,                 // indexed content, includes the title (linebreak after title)
				$tagContent,                  // tags
				$params,                      // typolink params for singleview
				$abstract,                    // abstract
				0,                            // language uid
				0,                            // starttime
				0,                            // endtime
				0,                            // fe_group
				false,                        // debug only?
				$additionalFields             // additional fields added by hooks
			);

		}

		$content = '<p><b>Indexer "' . $indexerConfig['title'] . '": ' . $resCount . ' Products have been indexed.</b></p>';

		return $content;
	}

	/*
	 * function getXTYPOCommerceRegionAndCountryTags()
	 * @param $arg
	 */
	function getXTYPOCommerceRegionAndCountryTags($regions, $countries) {
		$tagContent = '';

		$regions = t3lib_div::trimExplode(',', $regions, true);
		if (is_array($regions)) {
			foreach ($regions as $key => $region) {
				$tagContent .= '#region_'.$region.'#';
			}
		}

		$countries = t3lib_div::trimExplode(',', $countries, true);
		if (is_array($countries)) {
			foreach ($countries as $key => $country) {
				$tagContent .= '#country_'.$country.'#';
			}
		}

		return $tagContent;

	}


	/*
	 * function getRecursiveXTYPOCommerceCategories
	 * @param $product_id
	 */
	function getXTYPOCommerceCategories($product_id) {
		// get products' categories
		$fields = 'categories_id';
		$table = 'tx_xtypocommerce_products_to_categories';
		$where = 'products_id = "'.intval($product_id).'" ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$categories[] = $row['categories_id'];
		}

		// get recursive categories
		if (is_array($categories)) {
			foreach ($categories as $index => $catId) {
				$this->catRoot = '';
				$this->getXTYPOCommerceParentCat($catId);
				$this->catRoot = t3lib_div::rm_endcomma($this->catRoot);
				$catRootline[$catId] = $this->catRoot;
			}

		}
		return $catRootline;

	}


	/*
	 * function getXTYPOCommerceCatRootline
	 * @param $catId int
	 * @return void
	 */
	function getXTYPOCommerceParentCat($catId) {
		$fields = '*';
		$table = 'tx_xtypocommerce_categories';
		$where = 'categories_id="'.intval($catId).'" ';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($row['categories_id'] > 0) {
				$this->catRoot .= $row['categories_id'].',';
			}
			if ($row['parent_id'] > 0) {
				$this->catRoot .= $this->getXTYPOCommerceParentCat($row['parent_id']);
			}
		}
	}



	/*
	 * function periodicNotificationCount
	 * @return void
	 */
	function periodicNotificationCount() {
		// increase counter
		$this->counter += 1;

		// check if number of records configured reached
		if (($this->counter % $this->extConf['periodicNotification']) == 0) {
			// send the notification message
			if (t3lib_div::validEmail($this->extConf['notificationRecipient'])) {
				$msg = $this->counter.' records have been indexed.';
				mail($this->extConf['notificationRecipient'], $this->extConf['notificationSubject'], $msg);
			}
		}
	}



	/*
	 * function indexAddressRecords
	 * @param $indexerConfig
	 */
	function indexAddressRecords($indexerConfig) {

		// get all address records from pid set in indexerConfig
		$fields = '*';
		$table = 'tt_address';
		$where = 'pid IN ('.$indexerConfig['sysfolder'].') ';
		$where .= t3lib_befunc::BEenableFields($table,$inv=0);
		$where .= t3lib_befunc::deleteClause($table,$inv=0);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$resCount = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		// no address records found
		if (!$resCount) {
			$content = '<p>No address records found!</p>';
			return $content;
		}

		// if records found: process them
		while ($addressRow=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$tags = '';
			$abstract = str_replace('<br />', chr(13), $yacRecord['teaser']);
			$abstract = str_replace('<br>', chr(13), $abstract);
			$abstract = str_replace('</p>', chr(13), $abstract);
			$abstract = strip_tags($abstract);
			$content = strip_tags($yacRecord['bodytext']);
			$fullContent = $title . "\n" . $abstract . "\n" . $content;
			$targetPID = $indexerConfig['targetpid'];

			// prepare content for storing in index table
			$title = !empty($addressRow['company']) ? $addressRow['company'] : (!empty($addressRow['name']) ? $addressRow['name'] : ($addressRow['first_name'].' '.$addressRow['last_name']));
			// use description as abstract if set
			if (!empty($addressRow['description'])) $abstract = $addressRow['description'];
			// build content
			if (!empty($addressRow['company'])) $content .= $addressRow['company']."\n"; // company
			if (!empty($addressRow['name'])) $content = $addressRow['name']."\n"; // name
			else $content = $addressRow['title'].' '.$addressRow['first_name'].' '.$addressRow['middle_name'].' '.$addressRow['last_name']."\n"; // splitted naming fields
			if (!empty($addressRow['address'])) $content .=$addressRow['address']."\n";
			if (!empty($addressRow['zip']) || !empty($addressRow['city'])) $content .= $addressRow['zip'].' '.$addressRow['city']."\n";
			if (!empty($addressRow['country'])) $content .= $addressRow['country']."\n";
			if (!empty($addressRow['region'])) $content .=$addressRow['region']."\n";
			if (!empty($addressRow['email'])) $content .=$addressRow['email']."\n";
			if (!empty($addressRow['phone'])) $content .=$addressRow['phone']."\n";
			if (!empty($addressRow['fax'])) $content .=$addressRow['fax']."\n";
			if (!empty($addressRow['mobile'])) $content .=$addressRow['mobile']."\n";
			if (!empty($addressRow['www'])) $content .=$addressRow['www'];
			// put content together
			$fullContent = $title . "\n" . $abstract . "\n" . $content;
			// there is no tt_address default param like this; you have to modify this by hook to fit your needs
			$params = '&tt_address[showUid]='.$addressRow['uid'];
			// no tags yet
			$tagContent = '';
			// set additional fields for sorting
			$additionalFields = array(
				'sortdate' => $addressRow['tstamp'],
			);

			// hook for custom modifications of the indexed data, e. g. the tags
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyAddressIndexEntry'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyAddressIndexEntry'] as $_classRef) {
					$_procObj = & t3lib_div::getUserObj($_classRef);
					$_procObj->modifyAddressIndexEntry(
						$title,
						$abstract,
						$fullContent,
						$params,
						$tagContent,
						$addressRow,
						$additionalFields
					);
				}
			}

			// store in index
			$this->storeInIndex(
				$indexerConfig['storagepid'],	 // storage PID
				$title,                       				// page/record title
				'tt_address',              				// content type
				$targetPID,                   			// target PID: where is the single view?
				$fullContent,                 			// indexed content, includes the title (linebreak after title)
				$tagContent,                 			// tags
				$params,                      			// typolink params for singleview
				$abstract,                    			// abstract
				0,                            					// language uid
				0,                            					// starttime
				0,                            					// endtime
				0,                            					// fe_group
				false,                        				// debug only?
				$additionalFields             		// additional fields added by hooks
			);
		}

		$content = '<p><b>Indexer "' . $indexerConfig['title'] . '": ' . $resCount . ' address records have been indexed.</b></p>';
		return $content;

	}


}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_search/indexer/class.tx_kesearch_indexer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_search/indexer/class.tx_kesearch_indexer.php']);
}

?>
