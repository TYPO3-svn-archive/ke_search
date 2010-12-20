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

require_once(PATH_tslib.'class.tslib_pibase.php');


/**
 * Plugin 'Faceted search - searchbox and filters' for the 'ke_search' extension.
 *
 * @author	Andreas Kiefer (kennziffer.com) <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kesearch
 */
class tx_kesearch_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_kesearch_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_kesearch_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'ke_search';	// The extension key.

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {
		
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!

		// get html template
		$this->templateFile = $this->conf['templateFile'] ? $this->conf['templateFile'] : t3lib_extMgm::siteRelPath($this->extKey).'res/template_pi1.tpl';
		$this->templateCode = $this->cObj->fileResource($this->templateFile);

		// get startingpoint
		$pages = $this->cObj->data['pages'];
		$this->pids = $this->pi_getPidList($pages, $this->cObj->data['recursive']);

		// init XAJAX
		$this->initXajax();
		
		// $GLOBALS['TYPO3_DB']->debugOutput = true;
		
		// GET FLEXFORM DATA
		$this->initFlexforms();

		// get css file (include only in searchbox -> avoid duplicate css)
		if ($this->ffdata['mode'] == 0) {
			$cssFile = $this->conf['cssFile'] ? $this->conf['cssFile'] : t3lib_extMgm::siteRelPath($this->extKey).'res/ke_search_pi1.css';
			$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId.'_css'] = '<link rel="stylesheet" type="text/css" href="'.$cssFile.'" />';
		}

		// get preselected filter from rootline
		$this->getFilterPreselect();

		// set javascript if searchbox is loaded (=load once only )
		if ($this->ffdata['mode'] == 0) {
			$jsContent = '
				<script type="text/javascript">
					function submitAction() {
						document.getElementById(\'kesearch_filters\').style.display=\'none\';
						document.getElementById(\'kesearch_updating_filters\').style.display=\'block\';
						document.getElementById(\'kesearch_results\').style.display=\'none\';
						document.getElementById(\'kesearch_updating_results\').style.display=\'block\';
						document.getElementById(\'kesearch_pagebrowser_top\').style.display=\'none\';
						document.getElementById(\'kesearch_pagebrowser_bottom\').style.display=\'none\';
						document.getElementById(\'kesearch_query_time\').style.display=\'none\';
					}
					
					function refreshFiltersOnly() {
						document.getElementById(\'kesearch_filters\').style.display=\'none\';
						document.getElementById(\'kesearch_updating_filters\').style.display=\'block\';
					}
					
					function hideSpinnerFiltersOnly() {
						document.getElementById(\'kesearch_filters\').style.display=\'block\';
						document.getElementById(\'kesearch_updating_filters\').style.display=\'none\';
						document.getElementById(\'resetFilters\').value=0;
					}
					
					function pagebrowserAction() {
						document.getElementById(\'kesearch_results\').style.display=\'none\';
						document.getElementById(\'kesearch_updating_results\').style.display=\'block\';
						document.getElementById(\'kesearch_pagebrowser_top\').style.display=\'none\';
						document.getElementById(\'kesearch_pagebrowser_bottom\').style.display=\'none\';
						document.getElementById(\'kesearch_query_time\').style.display=\'none\';
					}

					function hideSpinner() {
						document.getElementById(\'kesearch_filters\').style.display=\'block\';
						document.getElementById(\'kesearch_updating_filters\').style.display=\'none\';
						document.getElementById(\'kesearch_results\').style.display=\'block\';
						document.getElementById(\'kesearch_updating_results\').style.display=\'none\';
						document.getElementById(\'kesearch_pagebrowser_top\').style.display=\'block\';
						document.getElementById(\'kesearch_pagebrowser_bottom\').style.display=\'block\';
						document.getElementById(\'kesearch_query_time\').style.display=\'block\';';
			
			// add reset filters?
			if ($this->ffdata['resetFiltersOnSubmit']) {
				$jsContent .= '
						document.getElementById(\'resetFilters\').value=0; ';
			}
			$jsContent .= '
					}';
			
			// build target URL if not result page
			unset($linkconf);
			$linkconf['parameter'] = $this->ffdata['resultPage'];
			$linkconf['additionalParams'] = '';
			$linkconf['useCacheHash'] = false;
			$targetUrl = t3lib_div::locationHeaderUrl($this->cObj->typoLink_URL($linkconf));
			
			
			$jsContent .= '
					
					// refresh result list onload
					function onloadResults() {
						document.getElementById(\'kesearch_results\').style.display=\'none\';
						document.getElementById(\'kesearch_updating_results\').style.display=\'block\';
						document.getElementById(\'kesearch_pagebrowser_top\').style.display=\'none\';
						document.getElementById(\'kesearch_pagebrowser_bottom\').style.display=\'none\';
						document.getElementById(\'kesearch_query_time\').style.display=\'none\';
						tx_kesearch_pi1refreshResultsOnLoad(xajax.getFormValues(\'xajax_form_kesearch_pi1\'));
						// document.getElementById(\'resetFilters\').value=0;
					}
					
					// reset both searchbox and filters
					function resetSearchboxAndFilters() {
						document.getElementById(\'kesearch_filters\').style.display=\'none\';
						document.getElementById(\'kesearch_updating_filters\').style.display=\'block\';
						document.getElementById(\'resetFilters\').value=1;
						document.getElementById(\'ke_search_sword\').value="";
						document.getElementById(\'pagenumber\').value="1";
						tx_kesearch_pi1resetSearchbox(xajax.getFormValues(\'xajax_form_kesearch_pi1\'));
						// document.getElementById(\'resetFilters\').value=0;
					}
					
					// set form action so that redirect to result page is processed
					function redirectToResultPage() {
						formEl = document.getElementById(\'xajax_form_kesearch_pi1\');
						formEl.action=\''.$targetUrl.'\';
						formEl.submit();
					}
					
					// add event listener for key press
					function keyPressAction(e) {
						e = e || window.event;
						var code = e.keyCode || e.which;
						// (submit search when pressing RETURN)
						if(code == 13) {
					';
			
			
			if ($this->ffdata['resetFiltersOnSubmit']) {
				$jsContent .= '
							document.getElementById(\'resetFilters\').value=1;';
			}
			
			if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
				// redirect to result page if current page is not the result page and option is activated
				$jsContent .= '
							redirectToResultPage();
						}';
			} else {
				// refresh results and searchbox if current page is result page
				$jsContent .= ' 
							'.$this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\'));
							submitAction();}';
			}
			$jsContent .= '
					}
				</script>';
			
			// minify JS?
			if (version_compare(TYPO3_version, '4.2.0', '>=' )) $jsContent = t3lib_div::minifyJavaScript($jsContent);
			
			// add JS to page header
			$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId.'_jsContent'] .= $jsContent;
		}

		// onclick javascript actions
		if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
			 $this->onclickSubmit =  'redirectToResultPage()';
		} else {
			// process ajax if result page
			$this->onclickSubmit =  $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); submitAction(); ';
			// add resetting of filters?
			if ($this->ffdata['resetFiltersOnSubmit']) $this->onclickSubmit = 'document.getElementById(\'resetFilters\').value=1; '. $this->onclickSubmit;
		}
		
		// onclick filter
		if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
			if ($this->ffdata['redirectOnFilterChange']) {
				$this->onclickFilter = 'redirectToResultPage();';
			} else {
				$this->onclickFilter = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); refreshFiltersOnly(); ';
			}
		} else {
			$this->onclickFilter = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); submitAction(); ';
		}
		
		$this->onclickPagebrowser = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); pagebrowserAction(); ';

		// Spinner Image
		$spinnerSrc = t3lib_extMgm::siteRelPath($this->extKey).'res/img/spinner.gif';
		$this->spinnerImageFilters = '<img id="kesearch_spinner_filters" src="'.$spinnerSrc.'" alt="'.$this->pi_getLL('loading').'" />';
		$this->spinnerImageResults = '<img id="kesearch_spinner_results" src="'.$spinnerSrc.'" alt="'.$this->pi_getLL('loading').'" />';

		switch ($this->ffdata['mode']) {

			// SEARCHBOX AND FILTERS
			case 0:
				$content = $this->getSearchboxContent();
				$content = $this->cObj->substituteMarker($content,'###SPINNER###',$this->spinnerImageFilters);
				$content = $this->cObj->substituteMarker($content,'###LOADING###',$this->pi_getLL('loading'));

				// refresh result list
				if ($GLOBALS['TSFE']->id == $this->ffdata['resultPage']) $this->refreshResultsOnLoad($_POST);

				break;

			// SEARCH RESULTS
			case 1:
				$content = $this->cObj->getSubpart($this->templateCode,'###RESULT_LIST###');
				$content = $this->cObj->substituteMarker($content,'###PAGEBROWSER_TOP###','');
				$content = $this->cObj->substituteMarker($content,'###PAGEBROWSER_BOTTOM###','');
				$content = $this->cObj->substituteMarker($content,'###ONLOAD_IMAGE###', $this->onloadImage);
				$content = $this->cObj->substituteMarker($content,'###MESSAGE###', $this->pi_getLL('message_before_search'));
				$content = $this->cObj->substituteMarker($content,'###MESSAGE###', $this->getSearchResults());
				$content = $this->cObj->substituteMarker($content,'###SPINNER###',$this->spinnerImageResults);
				$content = $this->cObj->substituteMarker($content,'###LOADING###',$this->pi_getLL('loading'));
				$content = $this->cObj->substituteMarker($content,'###QUERY_TIME###', '');
				// onload image for reloading results by ajax
				$onloadSrc = t3lib_extMgm::siteRelPath($this->extKey).'res/img/blank.gif';
				$onloadImageResults = '<img src="'.$onloadSrc.'?ts='.time().'" onload="onloadResults();" alt="" /> ';
				$content = $this->cObj->substituteMarker($content,'###ONLOAD_IMAGE_RESULTS###', $onloadImageResults);
				
				break;
				
		}

		return $this->pi_wrapInBaseClass($content);
	}





	/*
	 * function getSearchboxContent
	 */
	function getSearchboxContent() {

		// get main template code
		$content = $this->cObj->getSubpart($this->templateCode,'###SEARCHBOX###');
		$content = $this->cObj->substituteMarker($content,'###ONCLICK###',$this->onclickSubmit);

		// search word value
		if (!$this->piVars['page']) $this->piVars['page'] = 1;
		$content = $this->cObj->substituteMarker($content,'###HIDDEN_PAGE_VALUE###',$this->piVars['page']);
		$content = $this->cObj->substituteMarker($content,'###SUBMIT_VALUE###',$this->pi_getLL('submit'));
		$content = $this->cObj->substituteMarker($content,'###SWORD_VALUE###',$this->piVars['sword'] ? $this->piVars['sword'] : '');
		
		// get filters
		$content = $this->cObj->substituteMarker($content,'###FILTER###',$this->renderFilters());
		
		// get reset button
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/reset.gif';
		$resetButton=$this->cObj->IMAGE($imageConf);
		// $resetLink = '<div onclick="resetSearchboxAndFilters();" class="resetButton"><span>&nbsp;</span>'.$resetButton.'</div>';
		$resetLink = '<div onclick="resetSearchboxAndFilters();" class="resetButton"><span>'.$this->pi_getLL('reset_button').'</span>'.$resetButton.'</div>';
		
		$content = $this->cObj->substituteMarker($content,'###RESET###',$resetLink);

		return $content;
	}

	


	/*
	 * function renderFilters()
	 */
	function renderFilters() {

		// get filters from db
		$this->filters = $this->getFilters();

		if (!empty($this->ffdata['filters'])) {
			$filterList = explode(',',$this->ffdata['filters']);

			foreach ($filterList as $key => $uid) {

				// reset options content
				$optionsContent = '';

				// add standard option "all"
				$optionsContent .= $this->cObj->getSubpart($this->templateCode,'###SUB_FILTER_SELECT_OPTION###');
				// $optionsContent = $this->cObj->substituteMarker($optionsContent,'###TITLE###', $this->pi_getLL('all_options'));
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###TITLE###', $this->filters[$uid]['title']);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###VALUE###', '');
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###SELECTED###','');
				
				if (!empty($this->filters[$uid]['options'])) {
					// get filter options
					$fields = '*';
					$table = 'tx_kesearch_filteroptions';
					$where = 'uid in ('.$this->filters[$uid]['options'].')';
					$where .= ' AND pid in ('.$this->pids.')';
					$where .= $this->cObj->enableFields($table);
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='title',$limit='');
					// loop through filteroptions
					$optionsCount = 0;
					
					while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
	
						// check filter availability?
						if ($this->ffdata['checkFilterCondition'] != 'none') {
							if ($this->checkIfTagMatchesRecords($row['tag'],$this->ffdata['checkFilterCondition'], $uid)) {
								// process check in condition to other filters or without condition
								
								// build filter option
								$optionsContent .= $this->cObj->getSubpart($this->templateCode,'###SUB_FILTER_SELECT_OPTION###');
								$optionsContent = $this->cObj->substituteMarker($optionsContent,'###TITLE###', $row['title']);
								$optionsContent = $this->cObj->substituteMarker($optionsContent,'###VALUE###', $row['tag']);
	
								// selected / preselected?
								$selected = '';
								if ($this->piVars['filter'][$uid] == $row['tag']) {
									$selected = 'selected="selected" ';
								}
								// else if (!isset($this->piVars['filter'][$uid]) && $this->ffdata['resetFiltersOnSubmit']) {
								else if (!isset($this->piVars['filter'][$uid])) {
									if (is_array($this->preselectedFilter) && in_array($row['tag'], $this->preselectedFilter)) {
										$selected = 'selected="selected" ';
										$this->piVars['filter'][$uid] = $row['tag'];
									}
								}
								else $selected = '';
	
								$optionsContent = $this->cObj->substituteMarker($optionsContent,'###SELECTED###',$selected);
								$optionsCount++;
							}
						} else {
							// do not process check; show all filters
							$optionsContent .= $this->cObj->getSubpart($this->templateCode,'###SUB_FILTER_SELECT_OPTION###');
							$optionsContent = $this->cObj->substituteMarker($optionsContent,'###TITLE###', $row['title']);
							$optionsContent = $this->cObj->substituteMarker($optionsContent,'###VALUE###', $row['tag']);
							$selected = $this->piVars['filter'][$uid] == $row['tag'] ? 'selected="selected"' : '';
							$optionsContent = $this->cObj->substituteMarker($optionsContent,'###SELECTED###',$selected);
							$optionsCount++;
						}
					}	
				}
				
				// modify filter options by hook
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptions'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptions'] as $_classRef) {
						$_procObj = & t3lib_div::getUserObj($_classRef);
						$optionsContent .= $_procObj->modifyFilterOptions(
							$uid,
							$optionsContent,
							$optionsCount,
							$this
						);
					}
				}

				// get subpart
				$filterContent .= $this->cObj->getSubpart($this->templateCode,'###SUB_FILTER_SELECT###');

				// set disabled if no options but "all"
				if (!$optionsCount) {
					// add empty element with special text and "disabled" mode if no options but "all"
					$optionsContent = $this->cObj->getSubpart($this->templateCode,'###SUB_FILTER_SELECT_OPTION###');
					$optionsContent = $this->cObj->substituteMarker($optionsContent,'###TITLE###', $this->pi_getLL('no_options'));
					$optionsContent = $this->cObj->substituteMarker($optionsContent,'###VALUE###', '');
					$optionsContent = $this->cObj->substituteMarker($optionsContent,'###SELECTED###','');
				}
				
				// fill markers
				$filterContent = $this->cObj->substituteSubpart ($filterContent, '###SUB_FILTER_SELECT_OPTION###', $optionsContent, $recursive=1);
				$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERTITLE###', $this->filters[$uid]['title']);
				$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERNAME###', 'tx_kesearch_pi1[filter]['.$uid.']');
				$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERID###', 'filter['.$uid.']');
				$filterContent = $this->cObj->substituteMarker($filterContent,'###ONCHANGE###', $this->onclickFilter);
				$filterContent = $this->cObj->substituteMarker($filterContent,'###DISABLED###', $optionsCount > 0 ? '' : ' disabled="disabled" ');
			}

		}
		return $filterContent;
		//.'<br /><br /><br />'.t3lib_div::view_array($this->piVars);
	}




	/*
	 * function checkIfFilterMatchesRecords
	 */
	function checkIfTagMatchesRecords($tag, $mode='multi', $filterId) {

		// prepare searchword for query
		$sword = $this->removeXSS($this->piVars['sword']);
		// split several words
		$swords = t3lib_div::trimExplode(' ', $sword, true);

		// build words searchphrase
		$against = '';
		$againstBoolean = '';
		// build against clause for all searchwords
		if (count($swords)) {
			foreach ($swords as $word) {
				$against .=  $word.' ';
				$againstBoolean .= '+*'.$word.'* ';
			}
			$against = trim($against);
			$againstBoolean = trim($againstBoolean);
		}

		$filterList = explode(',', $this->ffdata['filters']);

		// against-clause for single check (not in condition with other selected filters)
		$againstClause = ' +"#'.$tag.'#" ';

		// extend against-clause for multi check (in condition with other selected filters)
		if ($mode == 'multi' && is_array($filterList)) {
			// andere filter aufrufen
			foreach ($filterList as $key => $foreignFilterId) {
				if ($foreignFilterId != $filterId) {
					// filter wurde gewählt
					if (!empty($this->piVars['filter'][$foreignFilterId])) {
						$againstClause .= ' +"#'.$this->piVars['filter'][$foreignFilterId].'#" ';
					}
				}
			}
		}

		// build query
		$fields = 'uid';
		$table = 'tx_kesearch_index';
		$where = ' MATCH(tags) AGAINST (\''.$againstClause.'\' IN BOOLEAN MODE) ';
		if (count($swords)) $where .= ' AND MATCH(content) AGAINST (\''.$againstBoolean.'\' IN BOOLEAN MODE)';
		$where .= $this->cObj->enableFields($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,'','',1);
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		return $anz;
	}


	/*
	 * function getFilters
	 */
	function getFilters() {
		if (!empty($this->ffdata['filters'])) {
			$fields = 'uid,title,options';
			$table = 'tx_kesearch_filters';
			$where = 'pid in ('.$this->pids.')';
			$where .= 'AND uid in ('.$this->ffdata['filters'].')';
			$where .= $this->cObj->enableFields($table);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
			while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$results[$row['uid']] = $row;
			}
		}
		return $results;

	}





	/**
	 * Init XAJAX
	 */
	function initXajax()	{
		// Include xaJax
		if(!class_exists('xajax')) {
			require_once(t3lib_extMgm::extPath('xajax') . 'class.tx_xajax.php');
		}
		// Make the instance
		$this->xajax = t3lib_div::makeInstance('tx_xajax');
		// Decode form vars from utf8
		#$this->xajax->decodeUTF8InputOn();
		// Encoding of the response to utf-8.
		$this->xajax->setCharEncoding('utf-8');
		#$this->xajax->setCharEncoding('iso-8859-1');
		// To prevent conflicts, prepend the extension prefix.
		$this->xajax->setWrapperPrefix($this->prefixId);
		// Do you want messages in the status bar?
		// $this->xajax->statusMessagesOn();
		// Turn only on during testing
		// $this->xajax->debugOn();

		// Register the names of the PHP functions you want to be able to call through xajax
		$this->xajax->registerFunction(array('refresh', &$this, 'refresh'));
		$this->xajax->registerFunction(array('refreshResultsOnLoad', &$this, 'refreshResultsOnLoad'));
		$this->xajax->registerFunction(array('resetSearchbox', &$this, 'resetSearchbox'));

		// If this is an xajax request call our registered function, send output and exit
		$this->xajax->processRequests();

		// Create javacript and add it to the normal output
		$found = false;
		foreach ($GLOBALS['TSFE']->additionalHeaderData as $key=>$val) {
			if (stristr($key,'xajax_')) $found = true;
		}
		$GLOBALS['TSFE']->additionalHeaderData['xajax_search_filters'] = $this->xajax->getJavascript( "typo3conf/ext/xajax/");
		$GLOBALS['TSFE']->additionalHeaderData['xajax_search_filters'] .= '<script type="text/javascript">function tx_kesearch_pi1refresh(){ return xajax.call("refresh", arguments, 1);}</script>';
		$GLOBALS['TSFE']->additionalHeaderData['xajax_search_onload'] = '<script type="text/javascript">function tx_kesearch_pi1refreshResultsOnLoad(){ return xajax.call("refreshResultsOnLoad", arguments, 1);}</script>';
		$GLOBALS['TSFE']->additionalHeaderData['xajax_search_reset'] = '<script type="text/javascript">function tx_kesearch_pi1resetSearchbox(){ return xajax.call("resetSearchbox", arguments, 1);}</script>';
	}


	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function refreshResultsOnLoad($data) {

		// init Flexforms
		$this->initFlexforms();

		// get preselected filter from rootline
		$this->getFilterPreselect();

		// set pagebrowser onclick
		$this->onclickPagebrowser = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); pagebrowserAction(); ';

		// set pivars
		$this->piVars = $data[$this->prefixId];

		// make xajax response object
		$objResponse = new tx_xajax_response();

		// get number of results
		$this->numberOfResults = $this->getSearchResults(true);

		// set start milliseconds for query time calculation
		if ($this->ffdata['showQueryTime']) $startMS = t3lib_div::milliseconds();

		// get max score for all hits
		$this->maxScore = $this->getSearchResults(false, true);
		
		// onclick filter
		if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
			if ($this->ffdata['redirectOnFilterChange']) {
				$this->onclickFilter = 'redirectToResultPage();';
			} else {
				$this->onclickFilter = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); refreshFiltersOnly(); ';
			}
		} else {
			$this->onclickFilter = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); submitAction(); ';
		}
		
		/*
		// reset filters?
		if ($this->piVars['resetFilters'] && is_array($this->piVars['filter'])) {
			foreach ($this->piVars['filter'] as $key => $value)  {
				// do not reset the preselected filter
				 if ($this->preselectedFilter != $value) $this->piVars['filter'][$key] = '';
				if (!is_array($this->preselectedFilter) || !in_array($value, $this->preselectedFilter)) {
				$this->piVars['filter'][$key] = '';
				}
			}
		}
		*/
		
		// generate onload image
		$onloadSrc = t3lib_extMgm::siteRelPath($this->extKey).'res/img/blank.gif';
		$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onload="hideSpinner();" alt="" /> ';
		if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
			$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onload="hideSpinnerFiltersOnly();" alt="" /> ';
		}
		
		// set filters
		$objResponse->addAssign("kesearch_filters", "innerHTML", $this->renderFilters().$this->onloadImage);
		
		// set search results
		$searchResults = $this->getSearchResults();
		$objResponse->addAssign("kesearch_results", "innerHTML", $this->getSearchResults().$this->onloadImage);

		// set end milliseconds for query time calculation
		if ($this->ffdata['showQueryTime']) {
			$endMS = t3lib_div::milliseconds();
			// calculate query time
			$queryTime = $endMS - $startMS;
			$objResponse->addAssign("kesearch_query_time", "innerHTML", sprintf($this->pi_getLL('query_time'), $queryTime));
		}

		// return response xml
		return $objResponse->getXML();
	}


	/*
	 * function refresh
	 * @param $arg
	 */
	function refresh($data) {

		// init Flexforms
		$this->initFlexforms();

		// get preselected filter from rootline
		$this->getFilterPreselect();
		
		// generate onload image
		$onloadSrc = t3lib_extMgm::siteRelPath($this->extKey).'res/img/blank.gif';
		$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onload="hideSpinner();" alt="" />';
		if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
			$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onload="hideSpinnerFiltersOnly();" alt="" /> ';
		}

		// onclick javascript actions
		$this->onclickSubmit =  $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); submitAction(); ';

		// add resetting of filters?
		if ($this->ffdata['resetFiltersOnSubmit']) $this->onclickSubmit = 'document.getElementById(\'resetFilters\').value=1; '. $this->onclickSubmit;
		
		
		// onclick filter
		if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
			if ($this->ffdata['redirectOnFilterChange']) {
				$this->onclickFilter = 'redirectToResultPage();';
			} else {
				$this->onclickFilter = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); refreshFiltersOnly(); ';
			}
		} else {
			$this->onclickFilter = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); submitAction(); ';
		}
		
		$this->onclickPagebrowser = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); pagebrowserAction(); ';

		// set pivars
		$this->piVars = $data[$this->prefixId];

		// reset filters?
		if ($this->piVars['resetFilters'] && is_array($this->piVars['filter'])) {
			foreach ($this->piVars['filter'] as $key => $value) {
				//$testcontent .= '<p>'.$key.': '.$value; 
				// do not reset the preselected filters
				
				if ($this->preselectedFilter[$key]) {
					//$testcontent .= ' : '.$this->preselectedFilter[$key].'</p>';
					$this->piVars['filter'][$key] = $this->preselectedFilter[$key];
				}
				/*
				if ((is_array($this->preselectedFilter) && in_array($value, $this->preselectedFilter))) {
					// do not reset filter
					$this->piVars['filter'][$key] = $value;
				}
				*/
				else {
					// reset filter value to 'all'
					$this->piVars['filter'][$key] = '';
				}
			}
			//$testcontent .= t3lib_div::view_array($this->preselectedFilter);
		}

		// make xajax response object
		$objResponse = new tx_xajax_response();

		// get number of results
		$this->numberOfResults = $this->getSearchResults(true);

		// set pagebrowser
		
		if ($GLOBALS['TSFE']->id == $this->ffdata['resultPage']) {
			if ($this->ffdata['pagebrowserOnTop'] || $this->ffdata['pagebrowserAtBottom']) {
				$pagebrowserContent = $this->renderPagebrowser();
				if ($this->ffdata['pagebrowserOnTop']) {
					$objResponse->addAssign("kesearch_pagebrowser_top", "innerHTML", $pagebrowserContent);
				}
				if ($this->ffdata['pagebrowserAtBottom']) {
					$objResponse->addAssign("kesearch_pagebrowser_bottom", "innerHTML", $pagebrowserContent);
				}
			}
		}

		// set start milliseconds for query time calculation
		if ($this->ffdata['showQueryTime']) $startMS = t3lib_div::milliseconds();

		// set filters
		$objResponse->addAssign("kesearch_filters", "innerHTML", $this->renderFilters().$this->onloadImage);

		// get max score for all hits
		$this->maxScore = $this->getSearchResults(false, true);

		// set search results
		// process if on result page
		if ($GLOBALS['TSFE']->id == $this->ffdata['resultPage']) {
			$objResponse->addAssign("kesearch_results", "innerHTML", $this->getSearchResults());
		}

		// set end milliseconds for query time calculation
		if ($this->ffdata['showQueryTime']) {
			$endMS = t3lib_div::milliseconds();
			// calculate query time
			$queryTime = $endMS - $startMS;
			$objResponse->addAssign("kesearch_query_time", "innerHTML", sprintf($this->pi_getLL('query_time'), $queryTime));
		}

		// fill testbox
		$objResponse->addAssign(
			"testbox",
			"innerHTML",
			t3lib_div::view_array($this->piVars).'<br /><br />'
			.'<b>preselect</b><br />'.t3lib_div::view_array($this->preselectedFilter)
		);

		// return response xml
		return $objResponse->getXML();

	}


	/*
	 * function refresh
	 * @param $arg
	 */
	function resetSearchbox($data) {

		// init Flexforms
		$this->initFlexforms();

		// get preselected filter from rootline
		$this->getFilterPreselect();

		// generate onload image
		$onloadSrc = t3lib_extMgm::siteRelPath($this->extKey).'res/img/blank.gif';
		$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onload="hideSpinner();" alt="" /> ';
		if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
			$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onload="hideSpinnerFiltersOnly();" alt="" /> ';
		}
		
		// set pivars
		$this->piVars = $data[$this->prefixId];
		
		// onclick javascript actions
		$this->onclickSubmit =  $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); submitAction(); ';
		
		$this->onclickPagebrowser = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); pagebrowserAction(); ';
		
		// add resetting of filters?
		if ($this->ffdata['resetFiltersOnSubmit']) $this->onclickSubmit = 'document.getElementById(\'resetFilters\').value=1; '. $this->onclickSubmit;
		
		// onclick filter
		if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
			if ($this->ffdata['redirectOnFilterChange']) {
				$this->onclickFilter = 'redirectToResultPage();';
			} else {
				$this->onclickFilter = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); refreshFiltersOnly(); ';
			}
		} else {
			$this->onclickFilter = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); submitAction(); ';
		}

		

		// reset filters?
		if ($this->piVars['resetFilters'] && is_array($this->piVars['filter'])) {
			foreach ($this->piVars['filter'] as $key => $value) {
				//$testcontent .= '<p>'.$key.': '.$value; 
				// do not reset the preselected filters
				
				if ($this->preselectedFilter[$key]) {
					//$testcontent .= ' : '.$this->preselectedFilter[$key].'</p>';
					$this->piVars['filter'][$key] = $this->preselectedFilter[$key];
				}
				/*
				if ((is_array($this->preselectedFilter) && in_array($value, $this->preselectedFilter))) {
					// do not reset filter
					$this->piVars['filter'][$key] = $value;
				}
				*/
				else {
					// reset filter value to 'all'
					$this->piVars['filter'][$key] = '';
				}
			}
			//$testcontent .= t3lib_div::view_array($this->preselectedFilter);
		}

		// make xajax response object
		$objResponse = new tx_xajax_response();

		// set filters
		$objResponse->addAssign("kesearch_filters", "innerHTML", $this->renderFilters().$this->onloadImage);
		/*
		// fill testbox
		
		$objResponse->addAssign(
			"testbox",
			"innerHTML",
			$testcontent.'<p>'.t3lib_div::view_array($this->piVars).'</p>'
		);
		*/
		
		// return response xml
		return $objResponse->getXML();

	}


	/*
	 * function buildTagSearchphrase
	 */
	function buildTagSearchphrase() {
		// build tag searchphrase
		if (is_array($this->piVars['filter'])) {
			$against = '';
			foreach ($this->piVars['filter'] as $key => $tag)  {
				if (!empty($tag)) 	$against .= ' +"#'.$tag.'#" ';
			}
			if (!empty($against)) $tagWhere = ' AND MATCH (tags) AGAINST (\''.$against.'\' IN BOOLEAN MODE)';
			return $tagWhere;
		}
	}



	/*
	 * function getSearchResults
	 */
	function getSearchResults($numOnly=false, $maxScore=false) {

		// prepare searchword for query
		$sword = $this->removeXSS($this->piVars['sword']);
		// split several words
		$swords = t3lib_div::trimExplode(' ', $sword, true);
		
		// build "tagged content only" searchphrase
		if ($this->ffdata['showTaggedContentOnly']) {
			$taggedOnlyWhere = ' AND tags<>"" ';
		}

		// build tag searchphrase
		$tagWhere = $this->buildTagSearchphrase();
		
		
		// calculate limit (not if num or max score is requested)
		if ($numOnly || $maxScore) {
			$limit = '';
		} else {
			$start = ($this->piVars['page'] * $this->ffdata['resultsPerPage']) - $this->ffdata['resultsPerPage'];
			if ($start < 0) $start = 0;
			$limit = $start.', '.$this->ffdata['resultsPerPage'];
		}
		if (empty($this->ffdata['resultsPerPage'])) {
			$limit .= '10';
		}

		// build words searchphrase
		$against = '';
		$againstBoolean = '';
		// build against clause for all searchwords
		if (count($swords)) {
			foreach ($swords as $word) {
				$against .=  $word.' ';
				$againstBoolean .= '+*'.$word.'* ';
			}
			$against = trim($against);
			$againstBoolean = trim($againstBoolean);
		}


		// get max score only
		if ($maxScore && count($swords)) {
			
			// Generate query for determing the max score
			// ----------------------------------------------------------
			// EXAMPLE:
			// SELECT MAX(MATCH (content) AGAINST ('kind hygiene')) AS maxscore FROM tx_kesearch_index
			// WHERE MATCH (content) against ('+kind +hygiene' IN BOOLEAN MODE)
			
			$fields = 'MAX(MATCH (content) AGAINST (\''.$against.'\')) AS maxscore';
			$table = 'tx_kesearch_index';
			$where .= 'MATCH (content) AGAINST (\''.$againstBoolean.'\' IN BOOLEAN MODE)';
			$where .= ' AND pid in ('.$this->pids.') ';
			
			// add filter searchphrase
			if (!empty($tagWhere)) $where .= $tagWhere;
			
			// add "tagged content only" searchphrase
			if ($this->ffdata['showTaggedContentOnly']) $where .= $taggedOnlyWhere;
			
			$where .= $this->cObj->enableFields($table);
			$query = $GLOBALS['TYPO3_DB']->SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit);
			
			// return $GLOBALS['TYPO3_DB']->SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit);
			$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			
			return $row['maxscore'];
		
		} else if ($maxScore) {
			
			// if there is no sword: set max score to 0
			return 0;
		
		}

		// Generate query for matching content
		// ----------------------------------------------------------
		// EXAMPLE:
		// SELECT title,content,tags,MATCH (content) AGAINST ('kind hygiene') AS score FROM tx_kesearch_index
		// WHERE MATCH (content) against ('+kind +hygiene' IN BOOLEAN MODE) ORDER BY score DESC
		
		$fields = 'targetpid,title,content,tags,params,type,abstract';
		
		// add score if searchword was entered
		if (count($swords)) $fields .= ',MATCH (content) AGAINST (\''.$against.'\') AS score ';
		
		$table = 'tx_kesearch_index';
		
		// add boolean where clause for all searchwords
		if (count($swords)) $where = 'MATCH (content) AGAINST (\''.$againstBoolean.'\' IN BOOLEAN MODE) ';
		else $where = '1=1 ';
		
		$where .= ' AND pid in ('.$this->pids.') ';
		
		// add filter searchphrase
		if (!empty($tagWhere)) $where .= $tagWhere;
		
		// add "tagged content only" searchphrase
		if ($this->ffdata['showTaggedContentOnly']) $where .= $taggedOnlyWhere;
		
		$where .= $this->cObj->enableFields($table);
		
		if (count($swords)) $orderBy = 'score DESC';
		else $orderBy = 'uid ASC';
		
		$query = $GLOBALS['TYPO3_DB']->SELECTquery($fields,$table,$where,$groupBy='',$orderBy,$limit);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy,$limit);
		$numResults = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		if ($numOnly) {
			// get number of records only?
			return $numResults;
		}
		else if ($numResults == 0) {
			
			$content = $this->cObj->getSubpart($this->templateCode,'###GENERAL_MESSAGE###');
			
			// check if searchword lengts are less then 3 chars
			/*
			foreach ($swords as $word) {
				if (strlen($word) < 4) {
					// too short searchword
					$content = $this->cObj->substituteMarker($content,'###MESSAGE###', $this->pi_getLL('searchword_length_error','wort zu kurz'));
					return $content;
				}
			}
			*/

			// check if empty result list is caused by 50%-rule of MySQL MATCH() AGAINST() query
			if (!count($swords) || $this->checkIfFiftyPercentRuleFits($swords) == false) {
				// 50% rule does not fit
				$content = $this->cObj->substituteMarker($content,'###MESSAGE###', $this->pi_getLL('no_results_found'));
			 } else {
				// 50% rule fits
				$content = $this->cObj->substituteMarker($content,'###MESSAGE###', $this->pi_getLL('no_results_refine'));
			}

			// attention icon
			unset($imageConf);
			$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/attention.gif';
			$imageConf['altText'] = $this->pi_getLL('no_results_found');
			$attentionImage=$this->cObj->IMAGE($imageConf);
			$content = $this->cObj->substituteMarker($content,'###IMAGE###', $attentionImage);

			// add query
			if ($this->ffdata['showQuery']) {
				$content .= '<br />'.$query.'<br />';
			}

			// add onload image
			$content .= $this->onloadImage;
			return $content;
		}

		// loop through results
		// init results counter
		$resultCount = 1;
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			
			// build link and url
			unset($linkconf);
			$linkconf['parameter'] = $row['targetpid'];
			// add params to result link
			if (!empty($row['params'])) $linkconf['additionalParams'] = $row['params'];
			// add chash
			$linkconf['useCacheHash'] = true;
			$linkconf['target'] = $this->ffdata['resultLinkTarget'];
			
			// set result title
			$linktext = $row['title'];
			$linktext = strip_tags($linktext);
			$linktext = $this->removeXSS($linktext);
			
			// highlight hits in result title?
			if ($this->ffdata['highlightSword'] && count($swords)) {
				foreach ($swords as $word) {
					$linktext = preg_replace('/('.$word.')/iu','<span class="hit">\0</span>',$linktext);
				}
			}
			
			$resultLink = $this->cObj->typoLink($linktext,$linkconf);
			$resultUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL').$this->cObj->typoLink_URL($linkconf);
			$this->resultUrl = $resultUrl;
			$resultUrlLink = $this->cObj->typoLink($resultUrl,$linkconf);
			
			// generate row content
			$tempContent = $this->cObj->getSubpart($this->templateCode,'###RESULT_ROW###');
			if (!empty($row['abstract'])) {
				$teaserContent = nl2br($row['abstract']);
				// highlight hits?
				if ($this->ffdata['highlightSword'] && count($swords)) {
					foreach ($swords as $word) {
						$teaserContent = preg_replace('/('.$word.')/iu','<span class="hit">\0</span>',$teaserContent);
					}
				}
			} else  {
				// build teaser from content
				$teaserContent = $this->buildTeaserContent($row['content'], $swords);
			}
			
			$tempMarkerArray = array(
				'title' => $resultLink,
				'teaser' => $teaserContent,
			);
			
			// hook for additional markers in result 
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['additionalResultMarker'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['additionalResultMarker'] as $_classRef) {
					$_procObj = & t3lib_div::getUserObj($_classRef);
					$_procObj->additionalResultMarker(
						$tempMarkerArray,
						$row,
						$this
					);
				}
			}
			
			
			$tempContent = $this->cObj->substituteMarkerArray($tempContent,$tempMarkerArray,$wrap='###|###',$uppercase=1);

			// show result url?
			if ($this->ffdata['showResultUrl']) {
				$subContent = $this->cObj->getSubpart($this->templateCode,'###SUB_RESULTURL###');
				$subContent = $this->cObj->substituteMarker($subContent,'###LABEL_RESULTURL###', $this->pi_getLL('label_resulturl'));
				$subContent = $this->cObj->substituteMarker($subContent,'###RESULTURL###', $resultUrlLink);
			} else {
				$subContent = '';
			}
			$tempContent = $this->cObj->substituteSubpart ($tempContent, '###SUB_RESULTURL###', $subContent, $recursive=1);

			// show result url?
			if ($this->ffdata['resultsNumeration']) {
				$subContent = $this->cObj->getSubpart($this->templateCode,'###SUB_NUMERATION###');
				$subContent = $this->cObj->substituteMarker($subContent,'###NUMBER###', $resultCount+$start);
			} else {
				$subContent = '';
			}
			$tempContent = $this->cObj->substituteSubpart ($tempContent, '###SUB_NUMERATION###', $subContent, $recursive=1);

			// show score?
			if ($this->ffdata['showScore'] && $row['score']) {
				$subContent = $this->cObj->getSubpart($this->templateCode,'###SUB_SCORE###');
				$subContent = $this->cObj->substituteMarker($subContent,'###LABEL_SCORE###', $this->pi_getLL('label_score'));
				$subContent = $this->cObj->substituteMarker($subContent,'###SCORE###', number_format($row['score'],2,',',''));
			} else {
				$subContent = '';
			}
			$tempContent = $this->cObj->substituteSubpart ($tempContent, '###SUB_SCORE###', $subContent, $recursive=1);

			// show percental score?
			if ($this->ffdata['showPercentalScore'] && $row['score']) {
				$subContent = $this->cObj->getSubpart($this->templateCode,'###SUB_SCORE_PERCENT###');
				$subContent = $this->cObj->substituteMarker($subContent,'###LABEL_SCORE_PERCENT###', $this->pi_getLL('label_score_percent'));
				$subContent = $this->cObj->substituteMarker($subContent,'###SCORE_PERCENT###', $this->calculateScorePercent($row['score']));
			} else {
				$subContent = '';
			}
			$tempContent = $this->cObj->substituteSubpart ($tempContent, '###SUB_SCORE_PERCENT###', $subContent, $recursive=1);

			// show score scale?
			if ($this->ffdata['showScoreScale'] && $row['score']) {
				$subContent = $this->cObj->getSubpart($this->templateCode,'###SUB_SCORE_SCALE###');
				$subContent = $this->cObj->substituteMarker($subContent,'###LABEL_SCORE_SCALE###', $this->pi_getLL('label_score_scale'));
				$scoreScale = $this->renderSVGScale($this->calculateScorePercent($row['score']));
				$subContent = $this->cObj->substituteMarker($subContent,'###SCORE_SCALE###', $scoreScale);
			} else {
				$subContent = '';
			}
			$tempContent = $this->cObj->substituteSubpart ($tempContent, '###SUB_SCORE_SCALE###', $subContent, $recursive=1);

			// show tags?
			if ($this->ffdata['showTags']) {
				$tags = $row['tags'];
				$subContent = $this->cObj->getSubpart($this->templateCode,'###SUB_TAGS###');
				$subContent = $this->cObj->substituteMarker($subContent,'###LABEL_TAGS###', $this->pi_getLL('label_tags'));
				$subContent = $this->cObj->substituteMarker($subContent,'###TAGS###', $tags);
			} else {
				$subContent = '';
			}
			$tempContent = $this->cObj->substituteSubpart ($tempContent, '###SUB_TAGS###', $subContent, $recursive=1);

			// show query?
			if ($this->ffdata['showQuery']) {
				$subContent = $this->cObj->getSubpart($this->templateCode,'###SUB_QUERY###');
				$subContent = $this->cObj->substituteMarker($subContent,'###LABEL_QUERY###', $this->pi_getLL('label_query'));
				$subContent = $this->cObj->substituteMarker($subContent,'###QUERY###', $query);
			} else {
				$subContent = '';
			}
			$tempContent = $this->cObj->substituteSubpart ($tempContent, '###SUB_QUERY###', $subContent, $recursive=1);

			// type icon
			if ($this->ffdata['showTypeIcon']) {
				$subContent = $this->cObj->getSubpart($this->templateCode,'###SUB_TYPE_ICON###');
				$subContent = $this->cObj->substituteMarker($subContent,'###TYPE_ICON###', $this->renderTypeIcon($row['type']));
			} else {
				$subContent = '';
			}
			$tempContent = $this->cObj->substituteSubpart ($tempContent, '###SUB_TYPE_ICON###', $subContent, $recursive=1);



			// add temp content to result list
			$content .= $tempContent;
			// $content .= t3lib_div::view_array($row);

			// increase result counter
			$resultCount++;
		}

		// add onload image
		$content .= $this->onloadImage;

		return $content;
	}



	/*
	 * function checkIfFiftyPercentRuleFits
	 * @param $sword
	 */
	function checkIfFiftyPercentRuleFits($swords) {

		$fields = '*';
		$table = 'tx_kesearch_index';
		$where = '';
		$i=0;
		foreach ($swords as $word) {
			if ($i>0) $where .= ' AND ';
			$where .= 'content like "%'.$word.'%"';
			$i++;
		}

		$where .= $this->cObj->enableFields($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
		$query = $GLOBALS['TYPO3_DB']->SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
		$num = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		return $num ? true : false;
	}



	/*
	 * function calculateScore
	 */
	function calculateScorePercent($currentScore) {
		if ($this->maxScore) return ceil(($currentScore * 100) / $this->maxScore);
		else return 0;
	}



	/*
	 * function buildTeaserContent
	 */
	function buildTeaserContent($resultText, $swords) {
		
		// get position of first linebreak
		$breakPos = strpos($resultText, "\n");
		
		// get substring from break position
		$resultText = substr($resultText, $breakPos);
		
		// calculate substring params
		if ($swords[0]) {
			$resultPos = stripos($resultText, $swords[0]);
		} else {
			$resultPos = 0;
		}
		$startPos = $resultPos - (ceil($this->ffdata['resultChars'] / 2));
		if ($startPos < 0) $startPos = 0;
		$teaser = substr($resultText, $startPos);

		// crop til whitespace reached
		$cropped = false;
		if ($startPos != 0 && $teaser[0] != " " ) {
			while ($teaser[0] != " ") {
				$teaser = substr($teaser, 1);
				$cropped = true;
			}
		}

		// append dots when cropped
		if ($startPos > 0) $teaser = '...'.$teaser;
		$teaser = $this->betterSubstr($teaser, $this->ffdata['resultChars']);

		// highlight hits?
		if ($this->ffdata['highlightSword'] && count($swords)) {
			foreach ($swords as $word) {
				$teaser = preg_replace('/('.$word.')/iu','<span class="hit">\0</span>',$teaser);
			}
		}
		return $teaser;
	}


	/*
	 * init flexforms
	 */
	function initFlexforms() {
		// GET FLEXFORM DATA
		$this->pi_initPIflexForm();
		$piFlexForm = $this->cObj->data['pi_flexform'];
		if (is_array($piFlexForm['data'])) {
			foreach ( $piFlexForm['data'] as $sheet => $data ) {
				foreach ( $data as $lang => $value ) {
					foreach ( $value as $key => $val ) {
						$this->ffdata[$key] = $this->pi_getFFvalue($piFlexForm, $key, $sheet);
					}
				}
			}
		}
		
		// load flexform config from other ce
		if ($this->ffdata['mode'] == 1 && !empty($this->ffdata['loadFlexformsFromOtherCE'])) {
			$fields = 'pi_flexform';
			$table = 'tt_content';
			$where = 'uid="'.intval($this->ffdata['loadFlexformsFromOtherCE']).'"  ';
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
			while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$ffXMLData = t3lib_div::xml2array($row['pi_flexform']);
				if (is_array($ffXMLData['data'])) {
					foreach ( $ffXMLData['data'] as $sheet => $data ) {
						foreach ( $data as $lang => $value ) {
							foreach ( $value as $key => $val ) {
								
								if ($key != 'mode') $this->ffdata[$key] = $this->pi_getFFvalue($ffXMLData, $key, $sheet);
							}
						}
					}
				}
			}
		}
		
	}

	/**
	* Use removeXSS function from t3lib_div if exists
	* otherwise use removeXSS class included in this extension
	* (e.g. for older TYPO3 versions)
	*
	* @param	string		value
	* @return	string 		XSS safe value
	*/
	function removeXSS($value) {
		if (method_exists(t3lib_div,'removeXSS')) {
			return t3lib_div::removeXSS($value);
		} else {
			require_once(t3lib_extMgm::extPath($this->extKey).'res/scripts/RemoveXSS.php');
			return  RemoveXSS::process($value);
		}
	}


	function betterSubstr($str, $length, $minword = 3) {
		$sub = '';
		$len = 0;
		foreach (explode(' ', $str) as $word) {
			$part = (($sub != '') ? ' ' : '') . $word;
			$sub .= $part;
			$len += strlen($part);
			if (strlen($word) > $minword && strlen($sub) >= $length) {
				break;
			}
		}
		return $sub . (($len < strlen($str)) ? '...' : '');
	}



	/*
	 * function renderPagebrowser
	 * @param $arg
	 */
	function renderPagebrowser() {
		
		$this->onclickPagebrowser = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); pagebrowserAction(); ';
		
		//$numberOfResults = $this->getSearchResults(true);
		$numberOfResults = $this->numberOfResults;
		// return $numberOfResults;

		$resultsPerPage = $this->ffdata['resultsPerPage'];
		// return $resultsPerPage;
		$maxPages = $this->ffdata['maxPagesInPagebrowser'];

		// set first page if no other is set
		if (!isset($this->piVars['page'])) $this->piVars['page'] = 1;

		// get total number of items to show
		if ($numberOfResults > $resultsPerPage) {
			// show pagebrowser if there are more entries that are
			// shown on one page
			$this->limit = $resultsPerPage;
		} else {
			// do not show pagebrowser
			return '';
		}

		// set db limit
		$start = ($this->piVars['page'] * $resultsPerPage) - $resultsPerPage;
		$this->dbLimit = $start.','.$resultsPerPage;

		// number of pages
		$pagesTotal = ceil($numberOfResults/ $resultsPerPage);

		$interval = ceil($maxPages/2);

		$startPage = $this->piVars['page'] - ceil(($maxPages/2));
		$endPage = $startPage + $maxPages - 1;
		if ($startPage < 1) {
			$startPage = 1;
			$endPage = $startPage + $maxPages -1;
		}
		if ($startPage > $pagesTotal) {
			$startPage = $pagesTotal - $maxPages + 1;
			$endPage = $pagesTotal;
		}
		if ($endPage > $pagesTotal) {
			$startPage = $pagesTotal - $maxPages + 1;
			$endPage = $pagesTotal;
		}

		// render pages list
		for ($i=1; $i<=$pagesTotal; $i++) {
			if ($i >= $startPage && $i <= $endPage) {
				$tempContent .= '<a onclick="document.getElementById(\'pagenumber\').value=\''.$i.'\'; '.$this->onclickPagebrowser.'"';
				if ($this->piVars['page'] == $i) $tempContent .= ' class="current" ';
				$tempContent .= '>'.$i.'</a>';
			}
		}

		// end
		$end = ($start+$resultsPerPage > $numberOfResults) ? $numberOfResults : ($start+$resultsPerPage);

		// previous image with link
		if ($this->piVars['page'] > 1) {
			// link
			$previousPage = $this->piVars['page']-1;
			$onclickPrevious = 'document.getElementById(\'pagenumber\').value=\''.$previousPage.'\'; ';
			$previous = '<a class="prev" onclick="'.$onclickPrevious.$this->onclickPagebrowser.'">&nbsp;</a>';
		} else {
			$previous = '';
		}

		// next image with link
		// previous image with link
		if ($this->piVars['page'] < $pagesTotal) {
			// link
			$nextPage = $this->piVars['page']+1;
			$onclickNext = 'document.getElementById(\'pagenumber\').value=\''.$nextPage.'\'; ';
			$next  = '<a class="next" onclick="'.$onclickNext.$this->onclickPagebrowser.'">&nbsp;</a>';
		} else {
			$next = '';
		}


		// render pagebrowser content
		$content = $this->cObj->getSubpart($this->templateCode,'###PAGEBROWSER###');
		$markerArray = array(
			'current' => $this->piVars['page'],
			'pages_total' => $pagesTotal,
			'pages_list' => $tempContent,
			'start' => $start+1,
			'end' => $end,
			'total' => $numberOfResults,
			'previous' => $previous,
			'next' => $next,
			'results' => $this->pi_getLL('results'),
			'until' => $this->pi_getLL('until'),
			'of' => $this->pi_getLL('of'),
		);
		$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);

		return $content;

	}


	/*
	 * function renderSVGScale
	 * @param $arg
	 */
	function renderSVGScale($percent) {
		$svgScriptPath = t3lib_extMgm::siteRelPath($this->extKey).'res/scripts/SVG.php?p='.$percent;
		return '<embed src="'.$svgScriptPath.'" style="margin-top: 5px;" width="50" height="12" type="image/svg+xml"	pluginspage="http://www.adobe.com/svg/viewer/install/" />';
	}



	/*
	 * function getFilterPreselect
	 */
	function getFilterPreselect () {
		
		// get definitions from filter records (for pages only)
		$rootlineArray = $GLOBALS['TSFE']->sys_page->getRootLine($GLOBALS['TSFE']->id);
		$rootlineTags = $this->getRootlineTags();
		
		if (count($rootlineArray)) {
			foreach ($rootlineArray as $level => $data) {
				if (count($rootlineTags)) {
					foreach ($rootlineTags as $tagKey => $tagData) {
						if ($data['pid'] == $tagData['foreign_pid']) {
							$fields = '*';
							$table = 'tx_kesearch_filters';
							$where = $GLOBALS['TYPO3_DB']->listQuery('options', $tagData['uid'], 'tx_kesearch_filters');
							$where .= $this->cObj->enableFields('tx_kesearch_filters');
							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
							while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
								$this->preselectedFilter[$row['uid']] = $tagData['tag'];
							}
						}
					}
				}
			}
		}
		
		// get definitions from plugin settings
		if ($this->ffdata['mode'] == 0 && $this->ffdata['preselected_filters']) {
			$preselectedArray = t3lib_div::trimExplode(',', $this->ffdata['preselected_filters'], true);
			foreach ($preselectedArray as $key => $option) {
				$fields = '*, tx_kesearch_filters.uid as filteruid';
				$table = 'tx_kesearch_filters, tx_kesearch_filteroptions';
				$where = $GLOBALS['TYPO3_DB']->listQuery('options', $option, 'tx_kesearch_filters');
				$where .= ' AND tx_kesearch_filteroptions.uid = '.$option;
				$where .= $this->cObj->enableFields('tx_kesearch_filters');
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
				while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$this->preselectedFilter[$row['filteruid']] = $row['tag'];
				}
			}
		}
	}

	/*
	 * function getRootlineTags
	 */
	function getRootlineTags() {
		$fields = '*, automated_tagging as foreign_pid';
		$table = 'tx_kesearch_filteroptions';
		$where = 'automated_tagging <> "" ';
		// $where = 'AND pid in () "" '; TODO
		$where .= $this->cObj->enableFields($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$results[] = $row;
		}
		return $results;
	}
	
	
	/*
	 * function renderTypeIcon
	 * @param $type string
	 */
	function renderTypeIcon($type) {
		$type = $this->removeXSS($type);
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/types/'.$type.'.gif';
		$image=$this->cObj->IMAGE($imageConf);
		return $image;
	}




}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_search/pi1/class.tx_kesearch_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_search/pi1/class.tx_kesearch_pi1.php']);
}

?>
