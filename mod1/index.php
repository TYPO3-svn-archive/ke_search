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


$GLOBALS['LANG']->includeLLFile('EXT:ke_search/mod1/locallang.xml');

if (TYPO3_VERSION_INTEGER < 6002000) {
	require_once(PATH_t3lib . 'class.t3lib_scbase.php');
}

$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.
	// DEFAULT initialization of a module [END]

/**
 * Module 'Indexer' for the 'ke_search' extension.
 *
 * @author	Andreas Kiefer (kennziffer.com) <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kesearch
 */

if (TYPO3_VERSION_INTEGER >= 7000000) {
	class tx_kesearch_module_baseclass extends \TYPO3\CMS\Backend\Module\BaseScriptClass { }
} else {
	class tx_kesearch_module_baseclass extends t3lib_SCbase { }
}

class  tx_kesearch_module1 extends tx_kesearch_module_baseclass {
	var $pageinfo;
	var $registry;

	/**
	 * Initializes the Module
	 * @return	void
	 */
	function init()	{
		global $BE_USER,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		parent::init();
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	void
	 */
	function menuConfig()	{
		$this->MOD_MENU = Array (
			'function' => Array (
				'1' => $GLOBALS['LANG']->getLL('function1'),
				'2' => $GLOBALS['LANG']->getLL('function2'),
				'3' => $GLOBALS['LANG']->getLL('function3'),
				'4' => $GLOBALS['LANG']->getLL('function4'),
				'5' => $GLOBALS['LANG']->getLL('function5'),
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
		global $BE_USER,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		if (TYPO3_VERSION_INTEGER >= 7000000) {
			$this->pageinfo = \TYPO3\CMS\Backend\Utility\BackendUtility::readPageAccess($this->id,$this->perms_clause);
		} else {
			$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		}
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($GLOBALS['BE_USER']->user['admin'] && !$this->id))	{

				// Draw the header.
			if (TYPO3_VERSION_INTEGER >= 7000000) {
				$this->doc = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
			} else if (TYPO3_VERSION_INTEGER >= 6002000) {
				$this->doc = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('mediumDoc');
			} else {
				$this->doc = t3lib_div::makeInstance('mediumDoc');
			}
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
					-moz-border-radius: 5px;
					border-radius: 5px;
					border: 1px solid #666;
					padding: 5px;
					margin-top: 10px;
					background: #DDD;
					box-shadow: 2px 2px 2px #AFAFAF;
					-moz-box-shadow: 2px 2px 2px #AFAFAF;
					-webkit-box-shadow: 2px 2px 2px #AFAFAF;
				}
				.box .headline {
					-moz-border-radius: 5px;
					border-radius: 5px;
					border: 1px solid #666;
					padding: 1px 1px 1px 5px;
					background: #888;
					color: white;
					margin-bottom: 3px;
					text-transform: uppercase;
				}
				.box .content {
					-moz-border-radius: 5px;
					border-radius: 5px;
					border: 1px solid #666;
					padding: 1px 1px 1px 5px;
					background: white;
				}

				.info, .tag {
					padding: 2px 4px;
					-moz-border-radius: 5px;
					border-radius: 5px;
					border: 1px solid black;
					background: white;
					margin-left:2px;
				}
				.info {
					width:48%;
					float:left;
				}

				.summary {
					-moz-border-radius: 10px;
					border-radius: 10px;
					border: 3px solid #666;
					padding: 10px;
					margin-top: 10px;
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
				}
				.summary .rightcol {
					float: right;
					text-align: left;
				}
				.label {
					font-weight: bold;
				}
				.value {
					text-align: left;
				}

				.reindex-button,
				a.index-button:hover,
				.index-button {
					-moz-border-radius: 7px;
					border-radius: 7px;
					box-shadow: 5px 5px 5px #AFAFAF;
					-moz-box-shadow: 5px 5px 5px #AFAFAF;
					-webkit-box-shadow: 5px 5px 5px #AFAFAF;
					background: green;
					padding: 5px;
					width: 250px;
					display: block;
					text-align: center;
					font-weight: bold;
					color: white
				}

				a.index-button:hover {
					background: darkgreen;
				}

				.reindex-button {
					margin-top: 20px;
				}

				a.lock-button:hover,
				.lock-button {
					-moz-border-radius: 7px;
					border-radius: 7px;
					box-shadow: 5px 5px 5px #AFAFAF;
					-moz-box-shadow: 5px 5px 5px #AFAFAF;
					-webkit-box-shadow: 5px 5px 5px #AFAFAF;
					background: red;
					padding: 5px;
					width: 250px;
					display: block;
					text-align: center;
					font-weight: bold;
					color: white
				}

				a.lock-button:hover {
					background: #d60008;
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
					border: 1px solid red;
					padding: 10px;
					margin: 10px;
					background: white;
					box-shadow: 2px 2px 2px red;
					-moz-box-shadow: 5px 5px 5px red;
					-webkit-box-shadow: 5px 5px 5px red;
				}

			';

			$this->content .= '<div id="typo3-docheader"><div class="typo3-docheader-functions">';

			if (TYPO3_VERSION_INTEGER >= 7000000) {
				$this->content .= \TYPO3\CMS\Backend\Utility\BackendUtility::getFuncMenu($this->id,'SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function']);
			} else {
				$this->content .= t3lib_BEfunc::getFuncMenu($this->id,'SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function']);
			}
			$this->content .= '</div></div>';
			$this->content .= '<div id="typo3-docbody"><div id="typo3-inner-docbody">';
			$this->content .= $this->doc->startPage($GLOBALS['LANG']->getLL('title'));
			$this->content .= $this->doc->header($GLOBALS['LANG']->getLL('title'));

			// Render content:
			$this->moduleContent();

			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section('',$this->doc->makeShortcutIcon('id',implode(',',array_keys($this->MOD_MENU)),$this->MCONF['name']));
			}

			$this->content.=$this->doc->spacer(10);
		} else {
				// If no access or if ID == zero

			if (TYPO3_VERSION_INTEGER >= 6002000) {
				$this->doc = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('mediumDoc');
			} else {
				$this->doc = t3lib_div::makeInstance('mediumDoc');
			}
			$this->doc->backPath = $BACK_PATH;
			$this->content.= $GLOBALS['LANG']->getLL('select_a_page');
			$this->content.=$this->doc->spacer(10);
		}
			$this->content.='</div></div>';

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

		if (TYPO3_VERSION_INTEGER >= 7000000) {
			$do = TYPO3\CMS\Core\Utility\GeneralUtility::_GET('do');
		} else {
			$do = t3lib_div::_GET('do');
		}

		switch((string)$this->MOD_SETTINGS['function'])	{

			// start indexing process
			case 1:
				$content = '';

				if (TYPO3_VERSION_INTEGER >= 7000000) {
					$this->registry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Registry');
				} else if (TYPO3_VERSION_INTEGER >= 6002000) {
					$this->registry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('t3lib_Registry');
				} else {
					$this->registry = t3lib_div::makeInstance('t3lib_Registry');
				}

				if ($do == 'startindexer') {
					// make indexer instance and init
					if (TYPO3_VERSION_INTEGER >= 6002000) {
						$indexer = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_kesearch_indexer');
					} else {
						$indexer = t3lib_div::makeInstance('tx_kesearch_indexer');
					}
					$cleanup = $this->extConf['cleanupInterval'];
					$content .= $indexer->startIndexing(true, $this->extConf); // start indexing in verbose mode with cleanup process
				} else if ($do == 'rmLock') {
					// remove lock from registry - admin only!
					if ($GLOBALS['BE_USER']->user['admin']) {
						$this->registry->removeAllByNamespace('tx_kesearch');
					} else {
						$content .= '<p>' . $GLOBALS['LANG']->getLL('not_allowed_remove_indexer_lock') . '</p>';
					}
				}

				// check for index process lock in registry
				$lockTime = $this->registry->get('tx_kesearch', 'startTimeOfIndexer');
				if ($lockTime !== null) {
					// lock is set
					$compareTime = time() - (60*60*12);
					if ($lockTime < $compareTime) {
						// lock is older than 12 hours
						// remove lock and show "start index" button
						$this->registry->removeAllByNamespace('tx_kesearch');
						if (TYPO3_VERSION_INTEGER < 6002000) {
							$content .= '<br /><a class="index-button" href="mod.php?id='.$this->id.'&M=web_txkesearchM1&do=startindexer">' . $GLOBALS['LANG']->getLL('start_indexer') . '</a>';
						} else {
							$moduleUrl = TYPO3\CMS\Backend\Utility\BackendUtility::getModuleUrl('web_txkesearchM1', array('id' => $this->id, 'do' => 'startindexer'));
							$content .= '<br /><a class="index-button" href="' . $moduleUrl . '">' . $GLOBALS['LANG']->getLL('start_indexer') . '</a>';
						}
					} else {
						// lock is not older than 12 hours
						if (!$GLOBALS['BE_USER']->user['admin']) {
							// print warning message for non-admins
							$content .= '<br /><p style="color: red; font-weight: bold;">WARNING!</p>';
							$content .= '<p>The indexer is already running and can not be started twice.</p>';
						} else {
							// show 'remove lock' button for admins
							$content .= '<br /><p>The indexer is already running and can not be started twice.</p>';
							$content .= '<p>The indexing process was started at '.strftime('%c', $lockTime).'.</p>';
							$content .= '<p>You can remove the lock by clicking the following button.</p>';
							if (TYPO3_VERSION_INTEGER < 6002000) {
								$content .= '<br /><a class="lock-button" href="mod.php?id='.$this->id.'&M=web_txkesearchM1&do=rmLock">RemoveLock</a>';
							} else{
								$moduleUrl = TYPO3\CMS\Backend\Utility\BackendUtility::getModuleUrl('web_txkesearchM1', array('id' => $this->id, 'do' => 'rmLock'));
								$content .= '<br /><a class="lock-button" href="' . $moduleUrl . '">RemoveLock</a>';
							}
						}
					}
				} else {
					// no lock set - show "start indexer" link
					if (TYPO3_VERSION_INTEGER < 6002000) {
						$content .= '<br /><a class="index-button" href="mod.php?id='.$this->id.'&M=web_txkesearchM1&do=startindexer">' . $GLOBALS['LANG']->getLL('start_indexer') . '</a>';
					} else {
						$moduleUrl = TYPO3\CMS\Backend\Utility\BackendUtility::getModuleUrl('web_txkesearchM1', array('id' => $this->id, 'do' => 'startindexer'));
						$content .= '<br /><a class="index-button" href="' . $moduleUrl . '">' . $GLOBALS['LANG']->getLL('start_indexer') . '</a>';
					}
				}

				$this->content.=$this->doc->section('Start Indexer',$content,0,1);
			break;


			// show indexed content
			case 2:
				if ($this->id) {

					if ($do == 'reindex') {
						if (TYPO3_VERSION_INTEGER >= 6002000) {
							$indexer = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_kesearch_indexer');
						} else {
							$indexer = t3lib_div::makeInstance('tx_kesearch_indexer');
						}
						$cleanup = $this->extConf['cleanupInterval'];
						$content = $indexer->startIndexing(true, $this->extConf); // start indexing in verbose mode with cleanup process
					}

					// page is selected: get indexed content
					$content = '<h2>Index content for page '.$this->id.'</h2>';
					if (TYPO3_VERSION_INTEGER > 7000000) {
						$content .= $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xml:labels.path').': '.  TYPO3\CMS\Core\Utility\GeneralUtility::fixed_lgd_cs($this->pageinfo['_thePath'],-50);
					} else {
						$content .= $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xml:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'],-50);
					}
					$content .= $this->getIndexedContent($this->id);
				} else {
					// no page selected: show message
					$content = 'Select page first';
				}

				$this->content.=$this->doc->section('Show Indexed Content',$content,0,1);
				break;


			// index table information
			case 3:

				$content = $this->renderIndexTableInformation();
				$this->content.=$this->doc->section('Index Table Information',$content,0,1);

				break;

			// searchword statistics
			case 4:

				// days to show
				$days = 30;
				$content = $this->getSearchwordStatistics($this->id, $days);
				$this->content.=$this->doc->section('Searchword Statistics for the last ' . $days . ' days', $content, 0, 1);

				break;

			// clear index
			case 5:
				$content = '';

					// admin only access
				if ($GLOBALS['BE_USER']->user['admin'])	{
					$table = 'tx_kesearch_index';

					if ($do == 'clear') {
						$query = 'TRUNCATE TABLE ' . $table;
						$res = $GLOBALS['TYPO3_DB']->sql_query($query);
					}

					$query = 'SELECT COUNT(*) AS number_of_records FROM ' . $table;
					$res = $GLOBALS['TYPO3_DB']->sql_query($query);
					$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
					$content .= '<p>Search index table contains ' . $row['number_of_records'] . ' records.</p>';

					// show "clear index" link
					if (TYPO3_VERSION_INTEGER < 6002000) {
						$content .= '<br /><a class="index-button" href="mod.php?id='.$this->id.'&M=web_txkesearchM1&do=clear">Clear whole search index!</a>';
					} else {
						$moduleUrl = TYPO3\CMS\Backend\Utility\BackendUtility::getModuleUrl('web_txkesearchM1', array('id' => $this->id, 'do' => 'clear'));
						$content .= '<br /><a class="index-button" href="' . $moduleUrl . '">Clear whole search index!</a>';
					}
				} else {
					$content .= '<p>Clear search index: This function is available to admins only.</p>';
				}


				$this->content.=$this->doc->section('Clear Index',$content,0,1);

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
					<table class="statistics">
						<tr>
							<td class="label">Records: </td>
							<td>'.$row['Rows'].'</td>
						</tr>
						<tr>
							<td class="label">Data size: </td>
							<td>'.$dataLength.'</td>
						</tr>
						<tr>
							<td class="label">Index size: </td>
							<td>'.$indexLength.'</td>
						</tr>
						<tr>
							<td class="label">Complete table size: </td>
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

		if (TYPO3_VERSION_INTEGER >= 7000000) {
			$where .= \TYPO3\CMS\Backend\Utility\BackendUtility::BEenableFields($table,$inv=0);
			$where .= \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause($table,$inv=0);
		} else {
			$where .= t3lib_befunc::BEenableFields($table,$inv=0);
			$where .= t3lib_befunc::deleteClause($table,$inv=0);
		}

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');

		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

			// build type image path
			switch($row['type']) {
				case 'page':
					$imagePath = t3lib_extMgm::extRelPath('ke_search').'res/img/types_backend/selicon_tx_kesearch_indexerconfig_type_0.gif';
					break;
				case 'ke_yac':
					$imagePath = t3lib_extMgm::extRelPath('ke_search').'res/img/types_backend/selicon_tx_kesearch_indexerconfig_type_1.gif';
					break;
				default:
					$imagePath = t3lib_extMgm::extRelPath('ke_search').'res/img/types_backend/selicon_tx_kesearch_indexerconfig_type_2.gif';
					break;
			}

			// build tag table
			$tagTable = '<div class="tags" >';
			$cols = 3;
			$tags = t3lib_div::trimExplode(',', $row['tags'], true);
			$i=1;
			foreach ($tags as $tag) {
				$tagTable .= '<span class="tag">' . $tag . '</span>';
			}
			$tagTable .= '</div>';

			// build content
			$timeformat = '%d.%m.%Y %H:%M';
			$content .= '
				<div class="summary">'
				. '<img src="'.$imagePath.'" border="0" style="float:right;">'
				. '<span class="title">'.$row['title'].'</span>'
				. '<div class="clearer">&nbsp;</div>'
				. $this->renderFurtherInformation('Type', $row['type'])
				. $this->renderFurtherInformation('Words', str_word_count($row['content']))
				. $this->renderFurtherInformation('Language', $row['language'])
				. $this->renderFurtherInformation('Created', strftime($timeformat, $row['crdate']))
				. $this->renderFurtherInformation('Modified', strftime($timeformat, $row['tstamp']))
				. $this->renderFurtherInformation('Sortdate', ($row['sortdate'] ? strftime($timeformat, $row['sortdate']) : ''))
				. $this->renderFurtherInformation('Starttime', ($row['starttime'] ? strftime($timeformat, $row['starttime']) : ''))
				. $this->renderFurtherInformation('Endtime', ($row['endtime'] ? strftime($timeformat, $row['endtime']) : ''))
				. $this->renderFurtherInformation('FE Group', $row['fe_group'])
				. $this->renderFurtherInformation('Target Page', $row['targetpid'])
				. $this->renderFurtherInformation('URL Params', $row['params'])
				. $this->renderFurtherInformation('Original PID', $row['orig_pid'])
				. $this->renderFurtherInformation('Original UID', $row['orig_uid'])
				. $this->renderFurtherInformation('Path', $row['directory'])
				. '<div class="clearer">&nbsp;</div>'
				. '<div class="box"><div class="headline">Abstract</div><div class="content">' . nl2br($row['abstract']) .'</div></div>'
				. '<div class="box"><div class="headline">Content</div><div class="content">' . nl2br($row['content']) .'</div></div>'
				.  '<div class="box"><div class="headline">Tags</div><div class="content">'.$tagTable.'</div></div>'
				. '</div>';

		}

		return $content;

	}

	/**
	 *
	 * @param string $label
	 * @param string $content
	 * @return string
	 */
	function renderFurtherInformation($label, $content) {
		return '<div class="info"><span class="label">' . $label . ': </span><span class="value">' . $content . '</span></div>';
	}

	/**
	 *
	 * @param integer $pageUid
	 * @param integer $days
	 * @return string
	 */
	function getSearchwordStatistics($pageUid, $days) {
		if (!$pageUid) {
			$content = '<div class="error">Select page first!</div>';
			return $content;
		}

		// calculate statistic start
		$timestampStart = time() - ($days*60*60*24);

		// get data from sysfolder or from single page?
		$isSysFolder = $this->checkSysfolder();
		$pidWhere = $isSysFolder ? ' AND pid=' . intval($pageUid) . ' ' : ' AND pageid=' . intval($pageUid) . ' ';

		// get languages
		$fields = 'language';
		$table = 'tx_kesearch_stat_word';
		$where = 'tstamp > ' . $timestampStart . ' ' . $pidWhere;
		$groupBy = 'language';
		$languageResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, $groupBy);

		if (!$GLOBALS['TYPO3_DB']->sql_num_rows($languageResult)) {
			$content .= '<div class="error">No statistic data found! Please select the sysfolder where your index is stored or the page where your search plugin is placed.</div>';
			return $content;
		}

		while ( ($languageRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($languageResult)) ) {
			$content .= '<h1 style="clear:left; padding-top:1em;">Language ' . $languageRow['language'] . '</h1>';
			if ($isSysFolder) {
				$content .= $this->getAndRenderStatisticTable('tx_kesearch_stat_search', $languageRow['language'], $timestampStart, $pidWhere, 'searchphrase');
			} else {
				$content .= '<i>Please select the sysfolder where your index is stored for a list of search phrases</i>';
			}
			$content .= $this->getAndRenderStatisticTable('tx_kesearch_stat_word', $languageRow['language'], $timestampStart, $pidWhere, 'word');
		}
		$content .= '<br style="clear:left;" />';
		return $content;

	}

	/**
	 *
	 * @param string $table
	 * @param integer $language
	 * @param integer $timestampStart
	 * @param string $pidWhere
	 * @param string $tableCol
	 * @return string
	 */
	public function getAndRenderStatisticTable($table, $language, $timestampStart, $pidWhere, $tableCol) {
		$content = '<div style="width=50%; float:left; margin-right:1em;">';
		$content .= '<h2 style="margin:0em;">' . $tableCol . 's</h2>';

		$rows = '';

		// get statistic data from db
		$fields = 'count('. $tableCol . ') as num, ' . $tableCol;
		$where = 'tstamp > ' . $timestampStart . ' AND language=' . $language . ' ' . $pidWhere;
		$groupBy = $tableCol . ' HAVING count(' . $tableCol . ')>0';
		$orderBy = 'num desc';
		$limit = '';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, $groupBy, $orderBy, $limit);
		$numResults = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		// get statistic
		$i=1;
		if ($numResults) {
			while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$cssClass = ($i%2==0) ?  'even' : 'odd';
				$rows .= '<tr>';
				$rows .= '	<td class="'.$cssClass.'">'.$row[$tableCol].'</td>';
				$rows .= '	<td class="times '.$cssClass.'">'.$row['num'].'</td>';
				$rows .= '</tr>';
				$i++;
			}

			$content .=
				'<table class="statistics">
					<tr>
					<th>' . $tableCol . '</th>
					<th>counter</th>
					</tr>'
				.$rows.
				'</table>';
		}

		$content .= '</div>';

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

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ke_search/mod1/index.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ke_search/mod1/index.php']);
}

// Make instance:
if (TYPO3_VERSION_INTEGER >= 6002000) {
	$SOBE = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_kesearch_module1');
} else {
	$SOBE = t3lib_div::makeInstance('tx_kesearch_module1');
}
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>