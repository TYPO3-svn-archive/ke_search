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
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */


$LANG->includeLLFile('EXT:ke_search/mod1/locallang.xml');
require_once(PATH_t3lib . 'class.t3lib_scbase.php');
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.
	// DEFAULT initialization of a module [END]



/**
 * Module 'Indexer' for the 'ke_search' extension.
 *
 * @author	Andreas Kiefer (kennziffer.com) <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kesearch
 */
class  tx_kesearch_module1 extends t3lib_SCbase {
	var $pageinfo;

	/**
	 * Initializes the Module
	 * @return	void
	 */
	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		parent::init();
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	void
	 */
	function menuConfig()	{
		global $LANG;
		$this->MOD_MENU = Array (
			'function' => Array (
				'1' => $LANG->getLL('function1'),
				'2' => $LANG->getLL('function2'),
				'3' => $LANG->getLL('function3'),
				'4' => $LANG->getLL('function4'),
			)
		);
		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 * If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	 *
	 * @return	[type]		...
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id))	{

				// Draw the header.
			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form='<form action="" method="post" enctype="multipart/form-data">';

				// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{
						document.location = URL;
					}
				</script>
			';
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = 0;
				</script>
			';

			// add some css
			$this->doc->inDocStyles = '

				.clearer {
					line-height: 1px;
					height: 1px;
					clear: both;
					display:block;
				}

				.box {
					-moz-border-radius: 10px;
					border-radius: 10px;
					border: 1px solid #666;
					padding: 5px;
					margin-top: 20px;
					background: #DDD;
					box-shadow: 5px 5px 5px #AFAFAF;
					-moz-box-shadow: 5px 5px 5px #AFAFAF;
					-webkit-box-shadow: 5px 5px 5px #AFAFAF;
				}
				.box .headline {
					-moz-border-radius: 8px;
					border-radius: 8px;
					border: 1px solid #666;
					padding: 5px;
					background: #888;
					color: white;
					font-weight: bold;
					margin-bottom: 6px;
					text-transform: uppercase;
					font-size: 110%;
				}
				.box .content {
					-moz-border-radius: 8px;
					border-radius: 8px;
					border: 1px solid #666;
					padding: 5px;
					background: white;
				}

				table.tags td {
					padding: 2px 4px;
					-moz-border-radius: 8px;
					border-radius: 8px;
					border: 1px solid black;
					background: white;
				}

				.summary {
					-moz-border-radius: 10px;
					border-radius: 10px;
					border: 3px solid #666;
					padding: 10px;
					margin-top: 20px;
					background: white;
					box-shadow: 5px 5px 5px #AFAFAF;
					-moz-box-shadow: 5px 5px 5px #AFAFAF;
					-webkit-box-shadow: 5px 5px 5px #AFAFAF;

				}
				.summary .title {
					font-size: 120%;
					font-weight: bold;
					margin-bottom: 8px;
					display:block;
				}
				.summary .leftcol {
					float: left;
					width: 30px;
				}
				.summary .rightcol {
					float: right;
					text-align: left;
					width: 410px;
				}
				.label {
					float:left;
					font-weight: bold;
					width: 120px;
				}
				.value {
					float: right:
					text-align: left;
					margin-left: 125px;
				}



				.reindex-button,
				.index-button {
					-moz-border-radius: 7px;
					border-radius: 7px;
					border: 1px solid white;
					box-shadow: 5px 5px 5px #AFAFAF;
					-moz-box-shadow: 5px 5px 5px #AFAFAF;
					-webkit-box-shadow: 5px 5px 5px #AFAFAF;
					background: green;
					padding: 5px;
					width: 100px;
					display: block;
					text-align: center;
					font-weight: bold;
					color: white
				}

				.reindex-button {
					margin-top: 20px;
				}

				table.statistics {
					-moz-border-radius: 10px;
					border-radius: 10px;
					border: 3px solid #666;
					padding: 10px;
					margin-top: 20px;
					background: white;
					box-shadow: 5px 5px 5px #AFAFAF;
					-moz-box-shadow: 5px 5px 5px #AFAFAF;
					-webkit-box-shadow: 5px 5px 5px #AFAFAF;
					border-spacing: 0;
				}

				table.statistics th {
					background: #666;
					color: white;
					padding: 3px;
				}

				table.statistics td {
					padding: 3px;
				}

				table.statistics td.even {
					background: #CCC;
				}

				table.statistics td.odd {
					background: white;
				}

				table.statistics td.times {
					text-align: right;
				}

				.error {
					font-weight: bold;
					color: red;
					-moz-border-radius: 10px;
					border-radius: 10px;
					border: 3px solid red;
					padding: 10px;
					margin-top: 20px;
					background: white;
					box-shadow: 5px 5px 5px red;
					-moz-box-shadow: 5px 5px 5px red;
					-webkit-box-shadow: 5px 5px 5px red;

				}

			';

			$headerSection = $this->doc->getHeader('pages',$this->pageinfo,$this->pageinfo['_thePath']).'<br />'.$LANG->sL('LLL:EXT:lang/locallang_core.xml:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'],50);
			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->section('',$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,'SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function'])));
			$this->content.=$this->doc->divider(5);

			// Render content:
			$this->moduleContent();


			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section('',$this->doc->makeShortcutIcon('id',implode(',',array_keys($this->MOD_MENU)),$this->MCONF['name']));
			}

			$this->content.=$this->doc->spacer(10);
		} else {
				// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $BACK_PATH;

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}

	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void
	 */
	function printContent()	{

		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content
	 *
	 * @return	void
	 */
	function moduleContent()	{

		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['ke_search']);

		switch((string)$this->MOD_SETTINGS['function'])	{

			// start indexing process
			case 1:
				if (t3lib_div::_GET('do') == 'startindexer') {
					// make indexer instance and init

					require_once(t3lib_extMgm::extPath('ke_search').'indexer/class.tx_kesearch_indexer.php');
					$indexer = t3lib_div::makeInstance('tx_kesearch_indexer');

					$verbose = true;
					$cleanup = $this->extConf['cleanupInterval'];
					$content = $indexer->startIndexing(true, $this->extConf); // start indexing in verbose mode with cleanup process


					// Reload - for Debugging only
					$content .= '<br /><br /><input type="button" value="Reload Page" onClick="window.location.reload()">';

				} else {
					// show "start indexer" link
					$content='<br /><br /><a class="index-button" href="mod.php?id='.$this->id.'&M=web_txkesearchM1&do=startindexer">Start Indexer</a>';
				}
				$this->content.=$this->doc->section('INDEXER FOR KE_SEARCH',$content,0,1);
			break;

			// show indexed content
			case 2:
				if ($this->id) {

					if (t3lib_div::_GET('do') == 'reindex') {
						require_once(t3lib_extMgm::extPath('ke_search').'indexer/class.tx_kesearch_indexer.php');
						$indexer = t3lib_div::makeInstance('tx_kesearch_indexer');
						$verbose = true;
						$cleanup = $this->extConf['cleanupInterval'];
						$content = $indexer->startIndexing(true, $this->extConf); // start indexing in verbose mode with cleanup process
					}

					// page is selected: get indexed content
					$content = '<h2>Index content for page '.$this->id.'</h2>';
					$content .= $this->getIndexedContent($this->id);

					// Start RE-Indexing for current page
					// TODO
					// $content .= '<a href="mod.php?id='.$this->id.'&M=web_txkesearchM1&do=reindex" class="reindex-button">Re-Index</a>';

				} else {
					// no page selected: show message
					$content = 'Select page first';
				}

				$this->content.=$this->doc->section('FACETED SEARCH',$content,0,1);
				break;


			// index table information
			case 3:

				$content = $this->renderIndexTableInformation();
				$this->content.=$this->doc->section('FACETED SEARCH',$content,0,1);

				break;

			// searchword statistics
			case 4:

				$content = $this->getSearchwordStatistics($this->id);
				$this->content.=$this->doc->section('FACETED SEARCH',$content,0,1);

				break;


		}
	}


	/*
	 * function renderIndexTableInformation
	 */
	function renderIndexTableInformation() {

		$table = 'tx_kesearch_index';

		// get table status
		$query = 'SHOW TABLE STATUS';
		$res = $GLOBALS['TYPO3_DB']->sql_query($query);
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($row['Name'] == $table) {

				$dataLength = $this->formatFilesize($row['Data_length']);
				$indexLength = $this->formatFilesize($row['Index_length']);
				$completeLength = $this->formatFilesize($row['Data_length'] + $row['Index_length']);

				$content .= '
					<h2>Index table information</h2>
					<table>
						<tr>
							<td class="label">Rows: </td>
							<td>'.$row['Rows'].'</td>
						</tr>
						<tr>
							<td class="label">Data length: </td>
							<td>'.$dataLength.'</td>
						</tr>
						<tr>
							<td class="label">Index length: </td>
							<td>'.$indexLength.'</td>
						</tr>
						<tr>
							<td class="label">Complete table length: </td>
							<td>'.$completeLength.'</td>
						</tr>
					</table>';
			}
		}


		return $content;
	}


	/**
	* format file size from bytes to human readable format
	*/
	function formatFilesize($size, $decimals=0) {
		$sizes = array(" B", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
		if ($size == 0) {
			return('n/a');
		} else {
			return (round($size/pow(1024, ($i = floor(log($size, 1024)))), $decimals) . $sizes[$i]);
		}
	}

	/*
	 * function getIndexedContent
	 * @param $pageUid page uid
	 */
	function getIndexedContent($pageUid) {

		$fields = '*';
		$table = 'tx_kesearch_index';
		$where = '(type="page" AND targetpid="'.intval($pageUid).'")  ';
		$where .= 'OR (type<>"page" AND pid="'.intval($pageUid).'")  ';
		$where .= t3lib_befunc::BEenableFields($table,$inv=0);
		$where .= t3lib_befunc::deleteClause($table,$inv=0);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		// t3lib_div::debug($GLOBALS['TYPO3_DB']->SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit=''),1);
		// $anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

			// build type image path
			switch($row['type']) {
				case 'page':
					$imagePath = t3lib_extMgm::extRelPath('ke_search').'selicon_tx_kesearch_indexerconfig_type_0.gif';
					break;
				case 'ke_yac':
					$imagePath = t3lib_extMgm::extRelPath('ke_search').'selicon_tx_kesearch_indexerconfig_type_1.gif';
					break;
				default:
					$imagePath = t3lib_extMgm::extRelPath('ke_search').'selicon_tx_kesearch_indexerconfig_type_2.gif';
					break;

			}


			// build tag table
			$tagTable = '<table class="tags" >';
			$cols = 5;
			$tags = t3lib_div::trimExplode('#', $row['tags'], true);
			$i=1;
			foreach ($tags as $tag) {
				// write leading tr
				if ($i % $cols == 1) $tagTable .= '<tr>';
				//write Tags
				$tagTable .= '<td>'.$tag.'</td>';
				// write trailing tr
				if ($i % $cols == 0) $tagTable .= '</tr>';
				$i++;
			}
			$tagTable .= '</table>';

			// build content
			$content .= '
				<div class="summary">
					<div class="leftcol">
						<img src="'.$imagePath.'" border="0">
					</div>
					<div class="rightcol">
						<span class="title">'.$row['title'].'</span>
						<div class="clearer">&nbsp;</div>

						<div class="label">Type:</div>
						<div class="value">'.$row['type'].'</div>
						<div class="clearer">&nbsp;</div>

						<div class="label">Words:</div>
						<div class="value">'.str_word_count($row['content']).'</div>
						<div class="clearer">&nbsp;</div>

						<div class="label">Language (UID):</div>
						<div class="value">'.$row['language'].'</div>
						<div class="clearer">&nbsp;</div>
					</div>
					<div class="clearer">&nbsp;</div>
				</div>

				<div class="box">
					<div class="headline">Content</div>
					<div class="content">
						'.wordwrap(nl2br($row['content']), 70, '<br />', 1).'
					</div>
				</div>

				<div class="box">
					<div class="headline">Tags</div>
					'.$tagTable.'
				</div>

				<div class="box">
					<div class="headline">Further information</div>
					<div class="content">
						<table class="info">
							<tr>
								<td class="label">Index created</td>
								<td class="value">'.strftime('%d.%m.%Y %H:%M', $row['crdate']).'</td>
							</tr>
							<tr>
								<td class="label">Last modification</td>
								<td class="value">'.strftime('%d.%m.%Y %H:%M', $row['tstamp']).'</td>
							</tr>
						</table>
					</div>
				</div>
				';

		}

		return $content;

	}


	/*
	 * function getSearchwordStatistics
	 */
	function getSearchwordStatistics($pageUid) {

		if (!$pageUid) {
			$content = '<div class="error">Select page first!</div>';
			return $content;
		}

		// calculate statistic start
		$timestampStart = time() - (10*60*60*24);

		$content = '<h2>Searchword statistics for last 10 days</h2>';
		$content .= '(from '.strftime('%d.%m.%Y %H:%M', $timestampStart).' \'til now)';

		// get data from sysfolder or from single page?
		$pidWhere = $this->checkSysfolder() ? ' AND pid="'.$pageUid.'" ' : ' AND pageid="'.$pageUid.'" ';

		// get statistic data from db
		$fields = 'count(word) as num, word';
		$table = 'tx_kesearch_stat_word';
		$where = 'tstamp > "'.$timestampStart.'" ';
		$where .= $pidWhere;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='word HAVING count(word)>0',$orderBy='num desc',$limit='');
		$numResults = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		if (!$numResults) {
			$content .= '<div class="error">No statistic data found!</div>';
			return $content;
		}

		// get statistic
		$i=1;
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$cssClass = ($i%2==0) ?  'even' : 'odd';
			$rows .= '<tr>';
			$rows .= '	<td class="'.$cssClass.'">'.$row['word'].'</td>';
			$rows .= '	<td class="times '.$cssClass.'">'.$row['num'].'</td>';
			$rows .= '</tr>';
			$i++;
		}

		$content .= '<table class="statistics">
						<tr>
							<th>Word</th>
							<th>Times</th>
						</tr>'
						.$rows.
					'</table>';



		return $content;

	}


	/*
	 * check if selected page is a sysfolder
	 *
	 * @return boolean
	 */
	function checkSysfolder() {

		$fields = 'doktype';
		$table = 'pages';
		$where = 'uid="'.$this->id.'" ';
		$where .= t3lib_befunc::BEenableFields($table,$inv=0);
		$where .= t3lib_befunc::deleteClause($table,$inv=0);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		if ($row['doktype'] == 254) {
			return TRUE;
		} else {
			return FALSE;
		}
	}



}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_search/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_search/mod1/index.php']);
}




// Make instance:
$SOBE = t3lib_div::makeInstance('tx_kesearch_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>
