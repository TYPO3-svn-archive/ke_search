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
require_once(t3lib_extMgm::extPath('ke_search').'pi1/class.tx_kesearch_div.php');

/**
 * Plugin 'Faceted search - searchbox and filters' for the 'ke_search' extension.
 *
 * @author	Andreas Kiefer (kennziffer.com) <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kesearch
 */
class tx_kesearch_pi1 extends tslib_pibase {
	var $prefixId           = 'tx_kesearch_pi1';		// Same as class name
	var $scriptRelPath      = 'pi1/class.tx_kesearch_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey             = 'ke_search';	// The extension key.

	var $ms                 = 0;
	var $startingPoints     = 0; // comma seperated list of startingPoints
	var $firstStartingPoint = 0; // comma seperated list of startingPoints
	var $ffdata             = array(); // FlexForm-Configuration
	var $countTagsResult    = 0; // precount the results of table content
	var $countContentResult = 0; // precount the results of table tags
	var $indexToUse         = ''; // it's for 'USE INDEX ($indexToUse)' to speed up queries
	var $tagsInSearchResult = false; // contains all tags of current search result

 	/**
	* @var tx_xajax
	*/
	var $xajax;

	/**
	* @var tx_kesearch_div
	*/
	var $div;


	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {

		$this->ms = t3lib_div::milliseconds();
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!

		// GET FLEXFORM DATA
		$this->initFlexforms();
		
		// clean piVars
		$this->piVars = $this->div->cleanPiVars($this->piVars);

		// debug db errors?
		// $GLOBALS['TYPO3_DB']->debugOutput = true;

		// get extension configuration array
		$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		$this->UTF8QuirksMode = $confArr['useUTF8QuirksMode'];

		// get html template
		$this->templateFile = $this->conf['templateFile'] ? $this->conf['templateFile'] : t3lib_extMgm::siteRelPath($this->extKey).'res/template_pi1.tpl';
		$this->templateCode = $this->cObj->fileResource($this->templateFile);

		// get startingpoint
		$this->firstStartingPoint = $this->div->getFirstStartingPoint($this->startingPoints);

		// init XAJAX?
		if ($this->ffdata['renderMethod'] != 'static') $this->initXajax();

		// get css file (include only in searchbox -> avoid duplicate css)
		if ($this->ffdata['mode'] == 0) {
			$cssFile = $this->conf['cssFile'] ? $this->conf['cssFile'] : t3lib_extMgm::siteRelPath($this->extKey).'res/ke_search_pi1.css';
			$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId.'_css'] = '<link rel="stylesheet" type="text/css" href="'.$cssFile.'" / >';
		}

		// get preselected filter from rootline
		$this->getFilterPreselect();

		// include javascript in searchbox mode only
		if ($this->ffdata['mode'] == 0) $this->getJSContent();

		// Spinner Image
		if ($this->conf['spinnerImageFile']) {
			$spinnerSrc = $this->conf['spinnerImageFile'];
		} else {
			$spinnerSrc = t3lib_extMgm::siteRelPath($this->extKey).'res/img/spinner.gif';
		}
		$this->spinnerImageFilters = '<img id="kesearch_spinner_filters" src="'.$spinnerSrc.'" alt="'.$this->pi_getLL('loading').'" />';
		$this->spinnerImageResults = '<img id="kesearch_spinner_results" src="'.$spinnerSrc.'" alt="'.$this->pi_getLL('loading').'" />';

		// get javascript onclick actions
		$this->initOnclickActions();

		// init onclick image
		$this->initOnloadImage();

		// hook for initials
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['initials'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['initials'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->addInitials($this);
			}
		}


		// get content
		switch ($this->ffdata['mode']) {

			// SEARCHBOX AND FILTERS
			case 0:
				$content = $this->getSearchboxContent();
				$content = $this->cObj->substituteMarker($content,'###SPINNER###',$this->spinnerImageFilters);
				$content = $this->cObj->substituteMarker($content,'###LOADING###',$this->pi_getLL('loading'));

				break;

			// SEARCH RESULTS
			case 1:

				$content = $this->cObj->getSubpart($this->templateCode,'###RESULT_LIST###');

				// get number of results
				$this->numberOfResults = $this->getSearchResults(true);
				$content = $this->cObj->substituteMarker($content,'###NUMBER_OF_RESULTS###', $this->numberOfResults);

				if($this->isEmptySearch() && $this->ffdata['showTextInsteadOfResults']) {
					$content = $this->cObj->substituteMarker($content,'###MESSAGE###', $this->pi_RTEcssText($this->ffdata['textForResults']));
					$content = $this->cObj->substituteMarker($content,'###QUERY_TIME###', '');
					$content = $this->cObj->substituteMarker($content,'###PAGEBROWSER_TOP###', '');
					$content = $this->cObj->substituteMarker($content,'###PAGEBROWSER_BOTTOM###', '');
					$content = $this->cObj->substituteMarker($content,'###ORDERING###', '');
					$content = $this->cObj->substituteMarker($content,'###NUMBER_OF_RESULTS###', '');
				} else {
					if ($this->ffdata['renderMethod'] == 'ajax_after_reload') {
						$content = $this->cObj->substituteMarker($content,'###MESSAGE###', '');
						$content = $this->cObj->substituteMarker($content,'###ORDERING###', $this->renderOrdering());
						$content = $this->cObj->substituteMarker($content,'###SPINNER###', $this->spinnerImageResults);
						$content = $this->cObj->substituteMarker($content,'###LOADING###',$this->pi_getLL('loading'));
						$content = $this->cObj->substituteMarker($content,'###QUERY_TIME###', '');
						$content = $this->cObj->substituteMarker($content,'###PAGEBROWSER_TOP###', '');
						$content = $this->cObj->substituteMarker($content,'###PAGEBROWSER_BOTTOM###', '');
						$content = $this->cObj->substituteMarker($content,'###NUMBER_OF_RESULTS###', '');
						return $this->pi_wrapInBaseClass($content);
					}

					// get number of results
					$this->numberOfResults = $this->getSearchResults(true);

					// render pagebrowser
					if ($GLOBALS['TSFE']->id == $this->ffdata['resultPage']) {
						if ($this->ffdata['pagebrowserOnTop'] || $this->ffdata['pagebrowserAtBottom']) {
							$pagebrowserContent = $this->renderPagebrowser();
						}
						if ($this->ffdata['pagebrowserOnTop']) {
							$content = $this->cObj->substituteMarker($content,'###PAGEBROWSER_TOP###', $pagebrowserContent);
						} else {
							$content = $this->cObj->substituteMarker($content,'###PAGEBROWSER_TOP###', '');
						}
						if ($this->ffdata['pagebrowserAtBottom']) {
							$content = $this->cObj->substituteMarker($content,'###PAGEBROWSER_BOTTOM###',$pagebrowserContent);
						} else {
							$content = $this->cObj->substituteMarker($content,'###PAGEBROWSER_BOTTOM###','');
						}
					}

					// get max score
					if ($this->ffdata['showPercentalScore'] || $this->ffdata['showScoreScale']) {
						$this->maxScore = $this->getSearchResults(false, true);
					}
					$content = $this->cObj->substituteMarker($content, '###MESSAGE###', $this->getSearchResults());
					$content = $this->cObj->substituteMarker($content, '###ORDERING###', $this->renderOrdering());
					$content = $this->cObj->substituteMarker($content, '###SPINNER###', $this->spinnerImageResults);
					$content = $this->cObj->substituteMarker($content, '###LOADING###', $this->pi_getLL('loading'));
					$content = $this->cObj->substituteMarker($content, '###QUERY_TIME###', '');
					$content = $this->cObj->substituteMarker($content, '###NUMBER_OF_RESULTS###', $this->pi_getLL('num_results') . $this->numberOfResults);
				}

				break;
		}

		return $this->pi_wrapInBaseClass($content);
	}


	/*
	 * function initOnclickActions
	 */
	function initOnclickActions() {

		switch ($this->ffdata['renderMethod']) {


			// AJAX version
			case 'ajax':

				// set submit onclick
				if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
					 // results page is  not the current page
					 $this->onclickSubmit =  'redirectToResultPage()';
				} else {
					// results page is the current page
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
					$this->onclickFilter = 'submitAction(); '.$this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\'));  ';
				}

				// set pagebrowser onclick
				$this->onclickPagebrowser = $this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); pagebrowserAction(); ';
				break;

			// AJAX after reload version
			case 'ajax_after_reload':

				// set pagebrowser onclick
				$this->onclickPagebrowser = 'pagebrowserAction(); ';


				// $this->onclickFilter = 'this.form.submit();';
				$this->onclickFilter = 'document.getElementById(\'xajax_form_kesearch_pi1\').submit();';

				break;

			// STATIC version
			case 'static':
				return;
				break;

		}

	}



	/*
	 * function getSearchboxContent
	 */
	function getSearchboxContent() {

		// get main template code
		if ($this->ffdata['renderMethod'] != 'ajax') {
			$content = $this->cObj->getSubpart($this->templateCode,'###SEARCHBOX_STATIC###');
		} else {
			$content = $this->cObj->getSubpart($this->templateCode,'###SEARCHBOX_AJAX###');
			$content = $this->cObj->substituteMarker($content,'###ONCLICK###',$this->onclickSubmit);
		}

		// set page = 1 if not set yet
		if (!$this->piVars['page']) $this->piVars['page'] = 1;
		$content = $this->cObj->substituteMarker($content,'###HIDDEN_PAGE_VALUE###',$this->piVars['page']);

		// submit
		$content = $this->cObj->substituteMarker($content,'###SUBMIT_VALUE###',$this->pi_getLL('submit'));

		// searchword input value
		$searchWordValue = trim($this->piVars['sword']);

		if (!empty($searchWordValue) && $searchWordValue != $this->pi_getLL('searchbox_default_value')) {
			// no searchword entered
			$swordValue = $this->piVars['sword'] ? $this->div->removeXSS($this->piVars['sword']) : '';
			$searchboxFocusJS = '';
		// } else if ($this->ffdata['renderMethod'] != 'static') {
		} else {
			// do not use when static mode is called

			// get default value from LL
			$swordValue = $this->pi_getLL('searchbox_default_value');

			// set javascript for resetting searchbox value
			$searchboxFocusJS = ' searchboxFocus(this);  ';

		}
		$content = $this->cObj->substituteMarker($content,'###SWORD_VALUE###', $swordValue);
		$content = $this->cObj->substituteMarker($content,'###SWORD_ONFOCUS###', $searchboxFocusJS);



		// get filters
		if ($this->ffdata['renderMethod'] == 'ajax') $content = $this->cObj->substituteMarker($content,'###FILTER###',$this->renderFilters());
		else {
			$content = $this->cObj->substituteMarker($content,'###FILTER###', $hiddenFilterContent);
		}

		// set form action for static mode
		if ($this->ffdata['renderMethod'] != 'ajax') {

			// set form action pid
			$content = $this->cObj->substituteMarker($content,'###FORM_TARGET_PID###', $this->ffdata['resultPage']);
			// set form action
			$content = $this->cObj->substituteMarker($content,'###FORM_ACTION###', t3lib_div::getIndpEnv('TYPO3_SITE_URL'));

			// set other hidden fields
			$hiddenFieldsContent = '';
			// language parameter
			$lParam = t3lib_div::_GET('L');
			if (isset($lParam)) {
				$hiddenFieldValue = intval($lParam);
				$hiddenFieldsContent .= '<input type="hidden" name="L" value="'.$hiddenFieldValue.'" />';
			}
			// mountpoint parameter
			$mpParam = t3lib_div::_GET('MP');
			if (isset($mpParam)) {
				$hiddenFieldValue = htmlentities($mpParam);
				$hiddenFieldValue = $this->div->removeXSS($hiddenFieldValue);
				$hiddenFieldsContent .= '<input type="hidden" name="MP" value="'.$hiddenFieldValue.'" />';
			}
			$content = $this->cObj->substituteMarker($content,'###HIDDENFIELDS###', $hiddenFieldsContent);

			// set reset link
			unset($linkconf);
			$linkconf['parameter'] = $this->ffdata['resultPage'];
			$resetUrl = $this->cObj->typoLink_URL($linkconf);
			$resetLink = '<a href="'.$resetUrl.'" class="resetButton"><span>'.$this->pi_getLL('reset_button').'</span></a>';

		} else {
			// set reset link
			$resetLink = '<div onclick="resetSearchboxAndFilters();" class="resetButton"><span>'.$this->pi_getLL('reset_button').'</span>'.$resetButton.'</div>';
		}

		$this->initOnloadImage;

		$content = $this->cObj->substituteMarker($content,'###ONLOAD_IMAGE###', $this->onloadImage);

		$content = $this->cObj->substituteMarker($content,'###RESET###',$resetLink);

		// type param
		$typeParam = t3lib_div::_GP('type');
		if ($typeParam) {
			$hiddenFieldValue = intval($typeParam);
			$typeContent = $this->cObj->getSubpart($this->templateCode,'###SUB_PAGETYPE###');
			$typeContent = $this->cObj->substituteMarker($typeContent,'###PAGETYPE###',$typeParam);
		} else $typeContent = '';
		$content = $this->cObj->substituteSubpart ($content, '###SUB_PAGETYPE###', $typeContent, $recursive=1);

		return $content;
	}




	/*
	 * function renderFilters()
	 */
	function renderFilters() {

		// get filters from db
		$this->filters = $this->getFilters();

		if (!empty($this->ffdata['filters'])) {
			$filterList = explode(',', $this->ffdata['filters']);

			foreach ($filterList as $key => $filterUid) {

				unset($options);

				// current filter has options
				if (!empty($this->filters[$filterUid]['options'])) {

					// get filter options
					$fields = '*';
					$table = 'tx_kesearch_filteroptions';
					$where = 'uid in ('.$GLOBALS['TYPO3_DB']->quoteStr($this->filters[$filterUid]['options'],'tx_kesearch_index').')';
					$where .= ' AND pid in ('.$GLOBALS['TYPO3_DB']->quoteStr($this->startingPoints,'tx_kesearch_index').')';
					$where .= $this->cObj->enableFields($table);
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');

					// loop through filteroptions
					while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

						// reset options count
						$optionsCount = 0;

						// check filter availability?
						if ($this->ffdata['checkFilterCondition'] != 'none') {
							if ($this->checkIfTagMatchesRecords($row['tag'],$this->ffdata['checkFilterCondition'], $filterUid)) {
								// process check in condition to other filters or without condition

								// selected / preselected?
								$selected = 0;

								if ($this->piVars['filter'][$filterUid] == $row['tag']) {
									$selected = 1;
								} else if (is_array($this->piVars['filter'][$filterUid])) {
									if(t3lib_div::inArray($this->piVars['filter'][$filterUid], $row['tag'])) {
										$selected = 1;
									}
								} else if (!isset($this->piVars['filter'][$filterUid]) && !is_array($this->piVars['filter'][$filterUid])) {
									if (is_array($this->preselectedFilter) && in_array($row['tag'], $this->preselectedFilter)) {
										$selected = 1;
										$this->piVars['filter'][$filterUid] = $row['tag'];
									}
								}
								$options[$row['uid']] = array(
									'title' => $row['title'],
									'value' => $row['tag'],
									'selected' => $selected,
								);
								$optionsCount++;
							}
						} else {
							// do not process check; show all filters
							$options[$row['uid']] = array(
								'title' => $row['title'],
								'value' => $row['tag'],
								'selected' => $selected,
							);
							$optionsCount++;
						}
					}
				}

				// sorting of options as set in filter record by IRRE
				$sorting = t3lib_div::trimExplode(',', $this->filters[$filterUid]['options'], true);
				foreach ($sorting as $key => $uid) {
					if (is_array($options[$uid])) {
						$sortedOptions[] = $options[$uid];
					}
				}
				$options = $sortedOptions;
				unset($sortedOptions);

				// hook for modifying filter options
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptionsArray'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptionsArray'] as $_classRef) {
						$_procObj = & t3lib_div::getUserObj($_classRef);
						$options = $_procObj->modifyFilterOptionsArray($filterUid, $options, $this);
					}
				}

					// render "wrap"
				if ($this->filters[$filterUid]['wrap']) {
					$wrap = t3lib_div::trimExplode('|', $this->filters[$filterUid]['wrap']);
				} else {
					$wrap = array(
						0 => '',
						1 => ''
					);
				}

					// get subparts corresponding to render type
				switch ($this->filters[$filterUid]['rendertype']) {

					case 'select':
					default:
						$filterContent .= $wrap[0] . $this->renderSelect($filterUid, $options) . $wrap[1];
						break;

					case 'list':
						$filterContent .= $wrap[0] . $this->renderList($filterUid, $options) . $wrap[1];
						break;

					case 'checkbox':
						$filterContent .= $wrap[0] . $this->renderCheckbox($filterUid, $options) . $wrap[1];
						break;

						// use custom render code
					default:
							// hook for custom filter renderer
						$customFilterContent = '';
						if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['customFilterRenderer'])) {
							foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['customFilterRenderer'] as $_classRef) {
								$_procObj = & t3lib_div::getUserObj($_classRef);
								$customFilterContent .= $_procObj->customFilterRenderer($filterUid, $options, $this);
							}
						}
						if ($customFilterContent) {
							$filterContent .= $wrap[0] . $customFilterContent . $wrap[1];
						}
						break;
				}
			}
		}
		return $filterContent;
	}



	/*
	 * function renderSelect
	 * @param $arg
	 */
	function renderSelect($filterUid, $options) {

		$filterSubpart = '###SUB_FILTER_SELECT###';
		$optionSubpart = '###SUB_FILTER_SELECT_OPTION###';

		// add standard option "all"
		$optionsContent .= $this->cObj->getSubpart($this->templateCode,$optionSubpart);
		$optionsContent = $this->cObj->substituteMarker($optionsContent,'###TITLE###', $this->filters[$filterUid]['title']);
		$optionsContent = $this->cObj->substituteMarker($optionsContent,'###VALUE###', '');
		$optionsContent = $this->cObj->substituteMarker($optionsContent,'###SELECTED###','');
		$optionsContent = $this->cObj->substituteMarker($optionsContent,'###CSS_CLASS###', 'class="label" ' );

		// loop through options
		if (is_array($options)) {
			foreach ($options as $key => $data) {
				$optionsContent .= $this->cObj->getSubpart($this->templateCode, $optionSubpart);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###ONCLICK###', $this->onclickFilter);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###TITLE###', $data['title']);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###VALUE###', $data['value']);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###SELECTED###', $data['selected'] ? ' selected="selected" ' : '');
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###CSS_CLASS###', ' ' );
				$optionsCount++;
			}
		}

		// modify filter options by hook
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptions'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptions'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$optionsContent .= $_procObj->modifyFilterOptions(
					$filterUid,
					$optionsContent,
					$optionsCount,
					$this
				);
			}
		}


		// fill markers
		$filterContent = $this->cObj->getSubpart($this->templateCode, $filterSubpart);
		$filterContent = $this->cObj->substituteSubpart ($filterContent, $optionSubpart, $optionsContent, $recursive=1);
		$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERTITLE###', $this->filters[$filterUid]['title']);
		$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERNAME###', 'tx_kesearch_pi1[filter]['.$filterUid.']');
		$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERID###', 'filter['.$filterUid.']');
		$filterContent = $this->cObj->substituteMarker($filterContent,'###DISABLED###', $optionsCount > 0 ? '' : ' disabled="disabled" ');

		// set onclick actions for different rendering methods
		if ($this->ffdata['renderMethod'] == 'static') {
			$filterContent = $this->cObj->substituteMarker($filterContent,'###ONCHANGE###', '');
		} else {
			$filterContent = $this->cObj->substituteMarker($filterContent,'###ONCHANGE###', $this->onclickFilter);
		}


		return $filterContent;


	}


	/*
	 * function renderList
	 * @param $arg
	 */
	function renderList($filterUid, $options) {

		if ($this->ffdata['renderMethod'] == 'ajax') {
			return $this->renderSelect($filterUid, $options);
		}

		$filterSubpart = '###SUB_FILTER_LIST###';
		$optionSubpart = '###SUB_FILTER_LIST_OPTION###';

		$optionsCount = 0;

		// loop through options
		if (is_array($options)) {
			foreach ($options as $key => $data) {

				$onclick = '';
				$tempField = strtolower(t3lib_div::removeXSS($this->piVars['orderByField']));
				$tempDir = strtolower(t3lib_div::removeXSS($this->piVars['orderByDir']));
				if($tempField != '' && $tempDir != '') {
					$onclick = 'setOrderBy(' . $tempField . ', ' . $tempDir . ');';
				}
				$onclick = $onclick . ' document.getElementById(\'filter['.$filterUid.']\').value=\''.$data['value'].'\'; ';
				$onclick .= $this->onclickFilter;

				$optionsContent .= $this->cObj->getSubpart($this->templateCode, $optionSubpart);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###ONCLICK###', $onclick);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###TITLE###', $data['title']);
				$cssClass = 'option ';
				$cssClass .= $data['selected'] ? 'selected' : '';
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###OPTIONCSSCLASS###', $cssClass);

				$optionsCount++;

			}
		}

		// modify filter options by hook
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptions'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptions'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$optionsContent .= $_procObj->modifyFilterOptions(
					$filterUid,
					$optionsContent,
					$optionsCount,
					$this
				);
			}
		}

		// fill markers
		$filterContent = $this->cObj->getSubpart($this->templateCode, $filterSubpart);
		$filterContent = $this->cObj->substituteSubpart ($filterContent, $optionSubpart, $optionsContent);

		if ($this->UTF8QuirksMode) $filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERTITLE###', utf8_encode($this->filters[$filterUid]['title']));
		else $filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERTITLE###', $this->filters[$filterUid]['title']);

		$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERTITLE###', $this->filters[$filterUid]['title']);
		$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERNAME###', 'tx_kesearch_pi1[filter]['.$filterUid.']');
		$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERID###', 'filter['.$filterUid.']');
		$filterContent = $this->cObj->substituteMarker($filterContent,'###ONCHANGE###', $this->onclickFilter);
		$filterContent = $this->cObj->substituteMarker($filterContent,'###ONCLICK_RESET###', $this->onclickFilter);
		$filterContent = $this->cObj->substituteMarker($filterContent,'###DISABLED###', $optionsCount > 0 ? '' : ' disabled="disabled" ');
		$filterContent = $this->cObj->substituteMarker($filterContent,'###VALUE###', $this->piVars['filter'][$filterUid]);

		// bullet
		unset($imageConf);
		$bulletSrc = $this->filters[$filterUid]['expandbydefault'] ? 'list-head-expanded.gif' : 'list-head-closed.gif';
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/'.$bulletSrc;
		$imageConf['params'] = 'class="bullet" id="bullet_filter['.$filterUid.']" ';
		$filterContent = $this->cObj->substituteMarker($filterContent,'###BULLET###', $this->cObj->IMAGE($imageConf));

		// expand by default ?
		$class = $this->filters[$filterUid]['expandbydefault'] || !empty($this->piVars['filter'][$filterUid]) ? 'expanded' : 'closed';
		$filterContent = $this->cObj->substituteMarker($filterContent,'###LISTCSSCLASS###', $class);

		// special css class (outer options list for scrollbox)
		$filterContent = $this->cObj->substituteMarker($filterContent,'###SPECIAL_CSS_CLASS###', $this->filters[$filterUid]['cssclass'] ? $this->filters[$filterUid]['cssclass'] : '');

		return $filterContent;

	}




	/*
	 * function renderCheckbox
	 * @param $arg
	 */
	function renderCheckbox($filterUid, $options) {

		/*if ($this->ffdata['renderMethod'] == 'ajax') {
			return $this->renderSelect($filterUid, $options);
		}*/

		$filters = $this->getFilters();
		$allOptionsOfCurrentFilter = $this->getFilterOptions($filters[$filterUid]['options']);

		$filterSubpart = '###SUB_FILTER_CHECKBOX###';
		$optionSubpart = '###SUB_FILTER_CHECKBOX_OPTION###';

		$optionsCount = 0;

		// loop through options
		if(is_array($allOptionsOfCurrentFilter)) {
			foreach($allOptionsOfCurrentFilter as $key => $data) {
				$checkBoxParams['selected'] = '';
				$checkBoxParams['disabled'] = '';

				// check if current option (of searchresults) is in array of all possible options
				$isOptionInOptionArray = 0;
				foreach($options as $optionKey => $optionValue) {
					if(is_array($options[$optionKey]) && t3lib_div::inArray($options[$optionKey], $data['title'])) {
						$isOptionInOptionArray = 1;
						break;
					}
				}

				// if option is in optionArray, we have to mark the checkboxes
				// but only if customer has searched for filters
				if($isOptionInOptionArray) {
					$checkBoxParams['selected'] = (implode($this->piVars['filter'][$filterUid])) ? 'checked="checked"' : '';
					if(!is_array($this->piVars['filter'][$filterUid]) && $this->filters[$filterUid]['markAllCheckboxes']) {
						$checkBoxParams['selected'] = 'checked="checked"';
					}
					$checkBoxParams['disabled'] = '';
				} else {
					$checkBoxParams['disabled'] = 'disabled="disabled"';
				}

				$optionsContent .= $this->cObj->getSubpart($this->templateCode, $optionSubpart);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###TITLE###', $data['title']);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###VALUE###', $data['tag']);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###OPTIONKEY###', $key);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###OPTIONID###', 'filter[' . $filterUid . '][' . $key . ']');
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###OPTIONCSSCLASS###', 'optionCheckBox optionCheckBox' . $key);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###OPTIONNAME###', 'optionCheckBox' . $filterUid);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###OPTIONSELECT###', $checkBoxParams['selected']);
				$optionsContent = $this->cObj->substituteMarker($optionsContent,'###OPTIONDISABLED###', $checkBoxParams['disabled']);

				$optionsCount++;
			}
		}

		// modify filter options by hook
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptions'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptions'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$optionsContent .= $_procObj->modifyFilterOptions(
					$filterUid,
					$optionsContent,
					$optionsCount,
					$this
				);
			}
		}

		// fill markers
		$filterContent = $this->cObj->getSubpart($this->templateCode, $filterSubpart);
		$filterContent = $this->cObj->substituteSubpart ($filterContent, $optionSubpart, $optionsContent);

		if ($this->UTF8QuirksMode) $filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERTITLE###', utf8_encode($this->filters[$filterUid]['title']));
		else $filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERTITLE###', $this->filters[$filterUid]['title']);

		$filterContent = $this->cObj->substituteMarker($filterContent,'###LABEL_ALL###', $this->pi_getLL('label_all'));
		$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERTITLE###', $this->filters[$filterUid]['title']);
		$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERNAME###', 'tx_kesearch_pi1[filter]['.$filterUid.']');
		$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTERID###', 'filter['.$filterUid.']');
		$filterContent = $this->cObj->substituteMarker($filterContent,'###FILTER_UID###', $filterUid);
		$filterContent = $this->cObj->substituteMarker($filterContent,'###ONCHANGE###', $this->onclickFilter);
		$filterContent = $this->cObj->substituteMarker($filterContent,'###ONCLICK_RESET###', $this->onclickFilter);
		$filterContent = $this->cObj->substituteMarker($filterContent,'###DISABLED###', $optionsCount > 0 ? '' : ' disabled="disabled" ');

		// bullet
		unset($imageConf);
		$bulletSrc = $this->filters[$filterUid]['expandbydefault'] ? 'list-head-expanded.gif' : 'list-head-closed.gif';
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/'.$bulletSrc;
		$imageConf['params'] = 'class="bullet" id="bullet_filter['.$filterUid.']" ';
		$filterContent = $this->cObj->substituteMarker($filterContent,'###BULLET###', $this->cObj->IMAGE($imageConf));

		// expand by default ?
		$class = $this->filters[$filterUid]['expandbydefault'] || !empty($this->piVars['filter'][$filterUid]) ? 'expanded' : 'closed';
		$filterContent = $this->cObj->substituteMarker($filterContent,'###LISTCSSCLASS###', $class);

		// special css class (outer options list for scrollbox)
		$filterContent = $this->cObj->substituteMarker($filterContent,'###SPECIAL_CSS_CLASS###', $this->filters[$filterUid]['cssclass'] ? $this->filters[$filterUid]['cssclass'] : '');

		return $filterContent;
	}




	/*
	 * function checkIfFilterMatchesRecords
	 */
	function checkIfTagMatchesRecords($tag, $mode='multi', $filterId) {

		// get all tags of current searchresult
		if(!is_array($this->tagsInSearchResult)) {

			// build words search phrase
			$searchWordInformation = $this->buildWordSearchphrase();
			$sword = $searchWordInformation['sword'];
			$swords = $searchWordInformation['swords'];
			$wordsAgainst = $searchWordInformation['wordsAgainst'];

			$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

			// get filter list
			$filterList = explode(',', $this->ffdata['filters']);

			// extend against-clause for multi check (in condition with other selected filters)
			if ($mode == 'multi' && is_array($filterList)) {
				// get all filteroptions from URL
				foreach ($filterList as $key => $foreignFilterId) {
					if(is_array($this->piVars['filter'][$foreignFilterId])) {
						foreach($this->piVars['filter'][$foreignFilterId] as $optionKey => $optionValue) {
							if(!empty($this->piVars['filter'][$foreignFilterId][$optionKey])) {
								// Don't add a "+", because we are here in checkbox mode
								$tagsAgainst .= ' "#'.$GLOBALS['TYPO3_DB']->quoteStr($this->piVars['filter'][$foreignFilterId][$optionKey], 'tx_kesearch_index').'#" ';
							}
						}
					} else {
						if(!empty($this->piVars['filter'][$foreignFilterId])) {
							$tagsAgainst .= ' +"#'.$GLOBALS['TYPO3_DB']->quoteStr($this->piVars['filter'][$foreignFilterId], 'tx_kesearch_index').'#" ';
						}
					}
				}
			}
			$tagsAgainst = $this->div->removeXSS($tagsAgainst);

			$this->setCountResults($wordsAgainst, $tagsAgainst);

			$fields = 'uid';
			$table = 'tx_kesearch_index';
			$where = '1=1';
			$countMatches = 0;
			if($tagsAgainst) {
				$where .= ' AND MATCH (tags) AGAINST (\''.$tagsAgainst.'\' IN BOOLEAN MODE) ';
				$countMatches++;
			}
			if(count($swords)) {
				// MATCH-AGAINST is really fast if there is only one searchword
				if(count($swords) == 1 OR !intval($confArr['activateSubStringSearch'])) {
					$where .= ' AND MATCH (content) AGAINST (\''.$wordsAgainst.'\' IN BOOLEAN MODE) ';
				} else {
					// LIKE is much faster if MATCH-AGAINST returns a mass of results
					foreach($swords as $value) {
						$where .= ' AND content LIKE "% ' . $value . '%" ';
					}
				}
				$countMatches++;
			}
			$where .= $this->cObj->enableFields($table);
			// which index to use
			if($countMatches == 2) {
				$index = ' USE INDEX (' . $this->indexToUse . ')';
			} else $index = '';

			$query = $GLOBALS['TYPO3_DB']->SELECTquery(
				'uid, REPLACE(tags, "##", "#~~~#") as tags',
				'tx_kesearch_index' . $index,
				$where,
				'','',''
			);
			$res = $GLOBALS['TYPO3_DB']->sql_query($query);

			while($tags = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				foreach(explode('~~~', $tags['tags']) as $value) {
					$tagTempArray[] = $value;
				}
			}

			// the following is much faster than array_unique()
			$tagArray = array();
			foreach($tagTempArray as $key => $val) {
				$tagArray[$val] = true;
			}
			$this->tagsInSearchResult = array_keys($tagArray);
		}

		if(array_search('#' . $tag . '#', $this->tagsInSearchResult) === false) {
			return false;
		} else {
			return true;
		}
	}


	/**
	 * This function is useful to decide which index to use
	 *
	 * @param string $searchString
	 * @param string $tagsString
	 */
	protected function setCountResults($searchString, $tagsString = '') {
		if(!$this->countContentResults) {
			// generate Query for counting searchresults in table content
			$query = $GLOBALS['TYPO3_DB']->SELECTquery(
				'COUNT(*) as content',
				'tx_kesearch_index',
				'MATCH (content) AGAINST (\'' . $GLOBALS['TYPO3_DB']->quoteStr($searchString, 'tx_kesearch_index') . '\' IN BOOLEAN MODE)'
			);
			$res = $GLOBALS['TYPO3_DB']->sql_query($query);
			$count = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$this->countContentResult = $count['content'];
		}

		if(!$this->countTagsResults && $tagsString) {
			$where = $this->div->createQueryForTags($tagsString);

			// generate Query for counting searchresults in table tags
			$queryTags = $GLOBALS['TYPO3_DB']->SELECTquery(
				'COUNT(*) as tags',
				'tx_kesearch_index',
				$where
			);
			$res = $GLOBALS['TYPO3_DB']->sql_query($query);
			$count = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$this->countTagsResult = $count['tags'];
		}

		//decide which index to use
		if($this->countContentResult > $this->countTagsResult) {
			$this->indexToUse = 'tag';
		} else {
			$this->indexToUse = 'content';
		}
	}


	/*
	 * function getFilters
	 */
	function getFilters() {
		if (!empty($this->ffdata['filters'])) {
			$fields = '*';
			$table = 'tx_kesearch_filters';
			$where = 'pid in ('.$GLOBALS['TYPO3_DB']->quoteStr($this->startingPoints, $table).')';
			$where .= 'AND uid in ('.$GLOBALS['TYPO3_DB']->quoteStr($this->ffdata['filters'], 'tx_kesearch_filters').')';			$where .= $this->cObj->enableFields($table);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
			while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$results[$row['uid']] = $row;
			}
		}
		return $results;
	}

	/**
	 * get optionrecords of given list of option-IDs
	 *
	 * @param string $optionList
	 * @return array Filteroptions
	 */
	protected function getFilterOptions($optionList) {
		// check/convert if list contains only integers
		$optionIdArray = t3lib_div::intExplode(',', $optionList, true);
		$optionList = implode(',', $optionIdArray);

		// search for filteroptions
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_kesearch_filteroptions',
			'pid in ('.$this->startingPoints.') ' .
			'AND uid in (' . $GLOBALS['TYPO3_DB']->quoteStr($optionList, 'tx_kesearch_filteroptions') . ') ' .
			$this->cObj->enableFields('tx_kesearch_filteroptions'),
			'', '', ''
		);
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$optionArray[] = $row;
		}

		return $optionArray;
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
		// $this->xajax->setCharEncoding('utf-8');
		$this->xajax->setCharEncoding('iso-8859-1');
		// To prevent conflicts, prepend the extension prefix.
		$this->xajax->setWrapperPrefix($this->prefixId);
		// Do you want messages in the status bar?
		// $this->xajax->statusMessagesOn();
		// Turn only on during testing
		// $this->xajax->debugOn();

		// Register the names of the PHP functions you want to be able to call through xajax
		$this->xajax->registerFunction(array('refresh', &$this, 'refresh'));
		if ($this->ffdata['renderMethod'] != 'static') {
			$this->xajax->registerFunction(array('refreshResultsOnLoad', &$this, 'refreshResultsOnLoad'));
			$this->xajax->registerFunction(array('refreshFiltersOnLoad', &$this, 'refreshFiltersOnLoad'));
		}
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
		if ($this->ffdata['renderMethod'] != 'static') {
			$GLOBALS['TSFE']->additionalHeaderData['xajax_search_onload'] = '<script type="text/javascript">function tx_kesearch_pi1refreshResultsOnLoad(){ return xajax.call("refreshResultsOnLoad", arguments, 1);}</script>';
			$GLOBALS['TSFE']->additionalHeaderData['xajax_search_onload'] .= '<script type="text/javascript">function tx_kesearch_pi1refreshFiltersOnLoad(){ return xajax.call("refreshFiltersOnLoad", arguments, 1);}</script>';
		}
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

		// set pivars
		$this->piVars = $data[$this->prefixId];
		$this->piVars = $this->div->cleanPiVars($this->piVars);

		// make xajax response object
		$objResponse = new tx_xajax_response();

		if($this->isEmptySearch() && $this->ffdata['showTextInsteadOfResults']) {
			$objResponse->addAssign("kesearch_results", "innerHTML", $this->pi_RTEcssText($this->ffdata['textForResults']));
			$objResponse->addAssign("kesearch_query_time", "innerHTML", '');
			$objResponse->addAssign("kesearch_ordering", "innerHTML", '');
			$objResponse->addAssign("kesearch_pagebrowser_top", "innerHTML", '');
			$objResponse->addAssign("kesearch_pagebrowser_bottom", "innerHTML", '');
			$objResponse->addAssign("kesearch_updating_results", "innerHTML", '');
			$objResponse->addAssign("kesearch_filters", "innerHTML", $this->renderFilters().$this->onloadImage);
		} else {
			// get number of results
			$this->numberOfResults = $this->getSearchResults(true);

			// set start milliseconds for query time calculation
			if ($this->ffdata['showQueryTime']) $startMS = t3lib_div::milliseconds();

			// get max score for all hits
			if ($this->ffdata['showPercentalScore'] || $this->ffdata['showScoreScale']) {
				$this->maxScore = $this->getSearchResults(false, true);
			}

			// get onclick action
			$this->initOnclickActions();

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
		}
		// return response xml
		return $objResponse->getXML();
	}

	/*
	 * function refresh
	 * @param $arg
	 */
	function refresh($data) {

		// set pivars
		foreach($data[$this->prefixId] as $key => $value) {
			if(is_array($data[$this->prefixId][$key])) {
				foreach($data[$this->prefixId][$key] as $subkey => $subtag)  {
					$this->piVars[$key][$subkey] = $this->div->removeXSS($subtag);
				}
			} else {
				$this->piVars[$key] = $this->div->removeXSS($value);
			}
		}

		// create a list of all filters in piVars
		foreach($this->piVars['filter'] as $key => $value) {
			if(is_array($this->piVars['filter'][$key])) {
				$filterString .= implode($this->piVars['filter'][$key]);
			} else {
				$filterString .= $this->piVars['filter'][$key];
			}
		}

		// clean piVars
		$this->piVars = $this->div->cleanPiVars($this->piVars);

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

		// init javascript onclick actions
		$this->initOnclickActions();

		// reset filters?
		if ($this->piVars['resetFilters'] && is_array($this->piVars['filter'])) {
			foreach ($this->piVars['filter'] as $key => $value) {
				// do not reset the preselected filters
				if ($this->preselectedFilter[$key]) {
					$this->piVars['filter'][$key] = $this->preselectedFilter[$key];
				}
			}
		}

		// make xajax response object
		$objResponse = new tx_xajax_response();

		if($this->isEmptySearch() && $this->ffdata['showTextInsteadOfResults']) {
			// get number of results
			$this->numberOfResults = $this->getSearchResults(true);
			$this->ffdata['textForResults'] = $this->cObj->substituteMarker($this->ffdata['textForResults'], '###NUMBER_OF_RECORDS###', $this->numberOfResults);

			$objResponse->addAssign('kesearch_results', 'innerHTML', $this->pi_RTEcssText($this->ffdata['textForResults']));
			$objResponse->addAssign('kesearch_query_time', 'innerHTML', '');
			$objResponse->addAssign('kesearch_ordering', 'innerHTML', '');
			$objResponse->addAssign('kesearch_pagebrowser_top', 'innerHTML', '');
			$objResponse->addAssign('kesearch_pagebrowser_bottom', 'innerHTML', '');
			$objResponse->addAssign('kesearch_updating_results', 'innerHTML', '');
			$objResponse->addAssign('kesearch_num_results', 'innerHTML', '');
			$objResponse->addAssign('kesearch_filters', 'innerHTML', $this->renderFilters().$this->onloadImage);
		} else {
			// get number of results
			$this->numberOfResults = $this->getSearchResults(true);

			// set pagebrowser
			if ($GLOBALS['TSFE']->id == $this->ffdata['resultPage']) {
				if ($this->ffdata['pagebrowserOnTop'] || $this->ffdata['pagebrowserAtBottom']) {
					$pagebrowserContent = $this->renderPagebrowser();
				}
				if ($this->ffdata['pagebrowserOnTop']) {
					$objResponse->addAssign("kesearch_pagebrowser_top", "innerHTML", $pagebrowserContent);
				} else {
					$objResponse->addAssign("kesearch_pagebrowser_top", "innerHTML", '');
				}
				if ($this->ffdata['pagebrowserAtBottom']) {
					$objResponse->addAssign("kesearch_pagebrowser_bottom", "innerHTML", $pagebrowserContent);
				} else {
					$objResponse->addAssign("kesearch_pagebrowser_bottom", "innerHTML", '');
				}
			}

			// set start milliseconds for query time calculation
			if ($this->ffdata['showQueryTime']) $startMS = t3lib_div::milliseconds();

			// set filters
			$objResponse->addAssign("kesearch_filters", "innerHTML", $this->renderFilters().$this->onloadImage);

			// get max score for all hits
			if ($this->ffdata['showPercentalScore'] || $this->ffdata['showScoreScale']) {
				$this->maxScore = $this->getSearchResults(false, true);
			}

			// set search results
			// process if on result page
			if ($GLOBALS['TSFE']->id == $this->ffdata['resultPage']) {
				$objResponse->addAssign('kesearch_results', 'innerHTML', $this->getSearchResults());
				$objResponse->addAssign('kesearch_num_results', 'innerHTML', $this->pi_getLL('num_results') . $this->numberOfResults);
				$objResponse->addAssign('kesearch_ordering', 'innerHTML', $this->renderOrdering());
			}

			// set end milliseconds for query time calculation
			if ($this->ffdata['showQueryTime']) {
				$endMS = t3lib_div::milliseconds();
				// calculate query time
				$queryTime = $endMS - $startMS;
				$objResponse->addAssign("kesearch_query_time", "innerHTML", sprintf($this->pi_getLL('query_time'), $queryTime));
			}

			// Show error message
			if ($this->showShortMessage) {
				$errorMessage = $this->cObj->getSubpart($this->templateCode,'###GENERAL_MESSAGE###');
				// attention icon
				unset($imageConf);
				$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/attention.gif';
				$imageConf['altText'] = $this->pi_getLL('searchword_length_error');
				$errorMessage = $this->cObj->substituteMarker($errorMessage,'###IMAGE###', $this->cObj->IMAGE($imageConf));
				$errorMessage = $this->cObj->substituteMarker($errorMessage,'###MESSAGE###', $this->pi_getLL('searchword_length_error'));

				if ($this->UTF8QuirksMode) $objResponse->addAssign("kesearch_error", "innerHTML", utf8_encode($errorMessage));
				else $objResponse->addAssign("kesearch_error", "innerHTML", $errorMessage);

			} else {
				$objResponse->addAssign("kesearch_error", "innerHTML", '');
			}
		}
		// return response xml
		return $objResponse->getXML();
	}

	/*
	 * function refresh
	 * @param $arg
	 */
	function refreshFiltersOnload($data) {

		// set pivars
		$this->piVars = $data[$this->prefixId];
		$this->piVars = $this->div->cleanPiVars($this->piVars);

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

		// init javascript onclick actions
		$this->initOnclickActions();

		// reset filters?
		if ($this->piVars['resetFilters'] && is_array($this->piVars['filter'])) {
			foreach ($this->piVars['filter'] as $key => $value) {
				// do not reset the preselected filters
				if ($this->preselectedFilter[$key]) {
					$this->piVars['filter'][$key] = $this->preselectedFilter[$key];
				}
				else {
					$this->piVars['filter'][$key] = '';
				}
			}
		}

		// make xajax response object
		$objResponse = new tx_xajax_response();

		// set filters
		$objResponse->addAssign("kesearch_filters", "innerHTML", $this->renderFilters().$this->onloadImage);

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

		// generate onload image
		$onloadSrc = t3lib_extMgm::siteRelPath($this->extKey).'res/img/blank.gif';
		$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onload="hideSpinner();" alt="" /> ';
		if ($GLOBALS['TSFE']->id != $this->ffdata['resultPage']) {
			$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onload="hideSpinnerFiltersOnly();" alt="" /> ';
		}

		// set pivars
		$this->piVars = $data[$this->prefixId];
		$this->piVars = $this->div->cleanPiVars($this->piVars);

		// onclick javascript actions
		$this->initOnclickActions();

		// reset filters?
		if ($this->piVars['resetFilters'] && is_array($this->piVars['filter'])) {
			foreach ($this->piVars['filter'] as $key => $value) {
				//$testcontent .= '<p>'.$key.': '.$value;
				// do not reset the preselected filters

				if ($this->preselectedFilter[$key]) {
					//$testcontent .= ' : '.$this->preselectedFilter[$key].'</p>';
					$this->piVars['filter'][$key] = $this->preselectedFilter[$key];
				}
				else {
					// reset filter value to 'all'
					$this->piVars['filter'][$key] = '';
				}
			}
		}

		// make xajax response object
		$objResponse = new tx_xajax_response();

		// set filters
		$objResponse->addAssign("kesearch_filters", "innerHTML", $this->renderFilters().$this->onloadImage);

		// return response xml
		return $objResponse->getXML();

	}


	/*
	 * function buildTagSearchphrase
	 */
	function buildTagSearchphrase() {
		foreach($this->preselectedFilter as $value) {
			$against[0] .= ' +"#' . $value . '#"';
		}
		// build tag searchphrase
		$against = array();
		if (is_array($this->piVars['filter'])) {
			foreach ($this->piVars['filter'] as $key => $tag)  {
				if (is_array($this->piVars['filter'][$key])) {
					foreach ($this->piVars['filter'][$key] as $subkey => $subtag)  {
						// Don't add a "+", because we are here in checkbox mode
						if (!empty($subtag)) $against[($key + 1)] .= ' "#'.$GLOBALS['TYPO3_DB']->quoteStr($subtag, 'tx_kesearch_index').'#" ';
					}
				} else {
					if (!empty($tag)) $against[0] .= ' +"#'.$GLOBALS['TYPO3_DB']->quoteStr($tag, 'tx_kesearch_index').'#" ';
				}
			}
		}
		return $against;
	}


	/**
 	* Build search word string for SQL Query from piVars['sword']
 	*
 	* @return  array
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Wed Mar 16 2011 15:03:26 GMT+0100
 	*/
	public function buildWordSearchphrase() {
		// prepare searchword for query
		$sword = $this->div->removeXSS($this->piVars['sword']);
		$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

		// ignore default search box content
		if (strtolower(trim($sword)) == strtolower($this->pi_getLL('searchbox_default_value'))) {
			$sword = '';
		}

		// replace plus and minus chars
		$sword = str_replace('-', ' ', $sword);
		$sword = str_replace('+', ' ', $sword);

		// split several words
		$swords = t3lib_div::trimExplode(' ', $sword, true);

		// build words searchphrase
		$wordsAgainst = '';
		$scoreAgainst = '';

		// build against clause for all searchwords
		if (count($swords)) {
			foreach ($swords as $key => $word) {
				// ignore words under length of 4 chars
				if (strlen($word) > 3) {
					if(intval($confArr['activateSubStringSearch'])) $subString = '*';

					if ($this->UTF8QuirksMode) {
						$scoreAgainst .= utf8_decode($GLOBALS['TYPO3_DB']->quoteStr($word, 'tx_kesearch_index')).' ';
						$wordsAgainst .= '+'.utf8_decode($GLOBALS['TYPO3_DB']->quoteStr($word, 'tx_kesearch_index')).'* ';
					}
					else {
						$scoreAgainst .= $GLOBALS['TYPO3_DB']->quoteStr($word, 'tx_kesearch_index').' ';
						$wordsAgainst .= '+'.$GLOBALS['TYPO3_DB']->quoteStr($word, 'tx_kesearch_index').'* ';
					}

				} else {
					unset ($swords[$key]);

					// if any of the search words is below 3 characters
					$this->showShortMessage = true;
				}
			}
		}

		$wordsArray = array(
			'sword' => $sword,
			'swords' => $swords,
			'wordsAgainst' => $wordsAgainst,
			'scoreAgainst' => $scoreAgainst
		);

		// Hook: modifySearchWords
		if(isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['modifySearchWords'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['modifySearchWords'] as $classRef) {
				$hookObj = t3lib_div::getUserObj($classRef);
				if(method_exists($hookObj, 'modifySearchWords')) {
					$hookObj->modifySearchWords($wordsArray, $this->cObj, $this->piVars);
			    }
			}
		}

		return $wordsArray;
	}

	/*
	 * function getSearchResults
	 */
	function getSearchResults($numOnly=false, $maxScore=false) {
		if($this->ms == 0) $this->ms = t3lib_div::milliseconds();

		// build words searchphrase
		$searchWordInformation = $this->buildWordSearchphrase();
		$sword = $searchWordInformation['sword'];
		$swords = $searchWordInformation['swords'];
		$wordsAgainst = $searchWordInformation['wordsAgainst'];
		$scoreAgainst = $searchWordInformation['scoreAgainst'];

		$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

		// build "tagged content only" searchphrase
		if ($this->ffdata['showTaggedContentOnly']) {
			$taggedOnlyWhere = ' AND tags<>"" ';
		}

		// build tag searchphrase
		$tagsAgainst = $this->buildTagSearchphrase();

		// calculate limit (not if num or max score is requested)
		if ($numOnly || $maxScore) {
			$limit = '';
		} else {
			$start = ($this->piVars['page'] * $this->ffdata['resultsPerPage']) - $this->ffdata['resultsPerPage'];
			if ($start < 0) $start = 0;
			$limit = $start . ', ' . $this->ffdata['resultsPerPage'];
		}
		if (empty($this->ffdata['resultsPerPage'])) {
			$limit .= '10';
		}

		// precount results to find the best index
		$this->setCountResults($wordsAgainst, $tagsAgainst);

		// get max score only (searchword entered)
		if ($maxScore && !$this->isEmptySearch()) {

			// Generate query for determing the max score
			// ----------------------------------------------------------
			// EXAMPLE:
			// SELECT *, MAX(MATCH (content) AGAINST ('+major')) as score FROM tx_kesearch_index
			// WHERE MATCH (content,tags) AGAINST ('+major +"#category_117#" +"#country_97#"' IN BOOLEAN MODE)

			$fields = 'MAX(MATCH (content) AGAINST (\''.$scoreAgainst.'\')) AS maxscore';
			$table = 'tx_kesearch_index';
			$where = '1=1 ';
			$countMatches = 0;
			if(!empty($wordsAgainst)) {
				// MATCH-AGAINST is really fast if there is only one searchword
				if(count($swords) == 1 OR !intval($confArr['activateSubStringSearch'])) {
					$where .= ' AND MATCH (content) AGAINST (\''.$wordsAgainst.'\' IN BOOLEAN MODE) ';
				} else {
					// LIKE is much faster if MATCH-AGAINST returns a mass of results
					foreach($swords as $value) {
						$where .= ' AND content LIKE "% ' . $value . '%" ';
					}
				}
				$countMatches++;
			}
			if(($tagWhere = $this->div->createQueryForTags($tagsAgainst))) {
				$where .= $tagWhere;
				$countMatches++;
			}
			$where .= ' AND pid in ('.$this->startingPoints.') ';

			// add "tagged content only" searchphrase
			if ($this->ffdata['showTaggedContentOnly']) $where .= $taggedOnlyWhere;

			// add enable fields
			$where .= $this->cObj->enableFields($table);

			if($countMatches == 2) {
				$index = ' USE INDEX (' . $this->indexToUse . ')';
			}

			// process query
			$query = $GLOBALS['TYPO3_DB']->SELECTquery(
				$fields,
				$table . $index,
				$where, '', '', $limit
			);
			$res = $GLOBALS['TYPO3_DB']->sql_query($query);
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);

			// return maximum score
			return $row['maxscore'];

		} else if ($maxScore) {

			// if there is no sword: set max score to 0
			return 0;
		}

		// Generate query for matching content
		// ----------------------------------------------------------
		// EXAMPLE:
		// SELECT * , MATCH (content) AGAINST ('major') AS score FROM tx_kesearch_index
		// WHERE MATCH (content, tags) AGAINST ('+major +"#category_117#" +"#country_97#" 'IN BOOLEAN MODE)
		// AND pid IN ( 1139 ) order by score desc

		$fields = '*';

		// add score if searchword was entered
		if (count($swords)) $fields .= ',MATCH (content) AGAINST (\''.$scoreAgainst.'\') AS score ';

		$table = 'tx_kesearch_index';

		// add boolean where clause for all searchwords and/or tags
		$where = '1=1 ';
		$countMatches = 0;
		if(!empty($wordsAgainst)) {
			// MATCH-AGAINST is really fast if there is only one searchword
			if(count($swords) == 1 OR !intval($confArr['activateSubStringSearch'])) {
				$where .= ' AND MATCH (content) AGAINST (\''.$wordsAgainst.'\' IN BOOLEAN MODE) ';
			} else {
				// LIKE is much faster if MATCH-AGAINST returns a mass of results
				foreach($swords as $value) {
					$where .= ' AND content LIKE "% ' . $value . '%" ';
				}
			}
			$countMatches++;
		}
		if(($tagWhere = $this->div->createQueryForTags($tagsAgainst))) {
			$where .= $tagWhere;
			$countMatches++;
		}

		// restrict to storage page
		$where .= ' AND pid in (' . $this->startingPoints . ') ';

		// add "tagged content only" searchphrase
		if ($this->ffdata['showTaggedContentOnly']) $where .= $taggedOnlyWhere;

		// add enable fields
		$where .= $this->cObj->enableFields($table);

		// add ordering
		$orderBy = $this->ffdata['sortWithoutSearchword'];

		if(count($swords)) {
			// if sorting in FE is allowed
			if($this->ffdata['showSortInFrontend']) {
				$tempField = strtolower(t3lib_div::removeXSS($this->pObj->piVars['orderByField']));
				$tempDir = strtolower(t3lib_div::removeXSS($this->pObj->piVars['orderByDir']));
				if($tempField != '' && $tempDir != '') {
					// overwrite ordering with piVars when allowed
					if($this->conf['sortByVisitor'] != '' && t3lib_div::inList($this->ffdata['sortByVisitor'], $tempField)) {
						$orderBy = $tempField . ' ' . $tempDir;
					}
				}
			} else { // if sorting is predefined by admin
				$orderBy = $this->ffdata['sortByAdmin'];
			}
		}

		// get number of results with COUNT(*)
		if ($numOnly) {
			$query = $GLOBALS['TYPO3_DB']->SELECTquery('COUNT(*) as numResults', $table, $where);
			$res = $GLOBALS['TYPO3_DB']->sql_query($query);
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			return $row['numResults'];
		}

		// process query
		if(count($swords)) {
			$query = $GLOBALS['TYPO3_DB']->SELECTquery(
				$fields,
				$table . $index,
				$where, '', '', $limit
			);
			$query = $GLOBALS['TYPO3_DB']->SELECTquery('*', '(' . $query . ') as results', '', '', 'results.' . $orderBy, '');
		} else {
			$query = $GLOBALS['TYPO3_DB']->SELECTquery($fields, $table, $where, '', $orderBy, $limit);
		}
		$res = $GLOBALS['TYPO3_DB']->sql_query($query);
		$numResults = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		// searchword statistic
		if (!$numOnly && !$maxScore) {
			// count searchword with ke_stats
			$this->countSearchWordWithKeStats($sword);

			// count search phrase in ke_search statistic tables
			if ($this->ffdata['countSearchPhrases']) {
				$this->countSearchPhrase($sword, $swords, $numResults, $tagsAgainst);
			}
		}

		if ($numOnly) {
			// get number of records only?
			return $numResults;
		}
		else if ($numResults == 0) {

			// get subpart for general message
			$content = $this->cObj->getSubpart($this->templateCode,'###GENERAL_MESSAGE###');

			// check if searchwords were too short
			if (!empty($this->piVars['sword']) && !count($swords)) {
				if ($this->UTF8QuirksMode) $content = $this->cObj->substituteMarker($content,'###MESSAGE###', utf8_encode($this->pi_getLL('searchword_length_error')));
				else $content = $this->cObj->substituteMarker($content,'###MESSAGE###', $this->pi_getLL('searchword_length_error'));
			}

			// no results found
			if ($this->ffdata['showNoResultsText']) {
				// use individual text set in flexform
				$noResultsText = $this->pi_RTEcssText($this->ffdata['noResultsText']);
				$attentionImage = '';
			} else {
				// use general text
				$noResultsText = $this->pi_getLL('no_results_found');
				// attention icon
				unset($imageConf);
				$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/attention.gif';
				$imageConf['altText'] = $this->pi_getLL('no_results_found');
				$attentionImage=$this->cObj->IMAGE($imageConf);
			}
			// set text for "no results found"
			$content = $this->cObj->substituteMarker($content,'###MESSAGE###', $noResultsText);
			// set attention icon?
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
			$linktext = $this->div->removeXSS($linktext);
			//$linktext = htmlentities($linktext);

			// highlight hits in result title?
			if ($this->ffdata['highlightSword'] && count($swords)) {
				foreach ($swords as $word) {
					$word = str_replace('/', '\/', $word);
					$linktextReplaced = preg_replace('/('.$word.')/iu','<span class="hit">\0</span>',$linktext);
					if (!empty($linktextReplaced)) $linktext = $linktextReplaced;
				}
			}


			$resultLink = $this->cObj->typoLink($linktext,$linkconf);
			$resultUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL').$this->cObj->typoLink_URL($linkconf);
			$this->resultUrl = $resultUrl;
			$resultUrlLink = $this->cObj->typoLink($resultUrl,$linkconf);

			// generate row content
			$tempContent = $this->cObj->getSubpart($this->templateCode,'###RESULT_ROW###');


			// result preview - as set in pi config
			if ($this->ffdata['previewMode'] == 'abstract') {

				// always show abstract
				if (!empty($row['abstract'])) {
					$teaserContent = nl2br($row['abstract']);
					$teaserContent = $this->buildTeaserContent($teaserContent, $swords);
					// highlight hits?
					/*
					if ($this->ffdata['highlightSword'] && count($swords)) {
						foreach ($swords as $word) {
							$teaserContent = preg_replace('/('.$word.')/iu','<span class="hit">\0</span>',$teaserContent);
						}
					}
					*/
				} else  {
					// build teaser from content
					$teaserContent = $this->buildTeaserContent($row['content'], $swords);
				}

			} else if ($this->ffdata['previewMode'] == 'hit' || $this->ffdata['previewMode'] == '') {
				if (!empty($row['abstract'])) {
					// show abstract if it contains sword, otherwise show content
					$abstractHit = false;
					foreach($swords as $word) {
						if (preg_match('/('.$word.')/iu', $row['abstract'])) {
							$abstractHit = true;
						}
					}
					if ($abstractHit) {
						$teaserContent = nl2br($row['abstract']);
						$teaserContent = $this->buildTeaserContent($teaserContent, $swords);
						// highlight hits?
						/*
						if ($this->ffdata['highlightSword'] && count($swords)) {
							foreach ($swords as $word) {
								$teaserContent = preg_replace('/('.$word.')/iu','<span class="hit">\0</span>',$teaserContent);
							}
						}
						*/
					} else {
						// sword was not found in abstract
						$teaserContent = $this->buildTeaserContent($row['content'], $swords);
					}
				} else {
					// sword was not found in abstract
					$teaserContent = $this->buildTeaserContent($row['content'], $swords);
				}
			}

			$tempMarkerArray = array(
				'title' => $resultLink,
				'teaser' => $teaserContent,
			);

			// hook for additional markers in result
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['additionalResultMarker'])) {
					// make curent row number available to hook
				$this->currentRowNumber = $resultCount;
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['additionalResultMarker'] as $_classRef) {
					$_procObj = & t3lib_div::getUserObj($_classRef);
					$_procObj->additionalResultMarker(
						$tempMarkerArray,
						$row,
						$this
					);
				}
				unset($this->currentRowNumber);
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

			// show date?
			if ($this->ffdata['showDate'] && $row['sortdate']) {
				$subContent = $this->cObj->getSubpart($this->templateCode,'###SUB_DATE###');
				$subContent = $this->cObj->substituteMarker($subContent,'###LABEL_DATE###', $this->pi_getLL('label_date'));
				$subContent = $this->cObj->substituteMarker($subContent,'###DATE###', date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $row['sortdate']));
			} else {
				$subContent = '';
			}
			$tempContent = $this->cObj->substituteSubpart ($tempContent, '###SUB_DATE###', $subContent, $recursive=1);

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
				$tags = str_replace('#', ' ', $tags);
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

			// increase result counter
			$resultCount++;
		}

		// hook for additional content AFTER the result list
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['additionalContentAfterResultList'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['additionalContentAfterResultList'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$content .= $_procObj->additionalContentAfterResultList($this);
			}
		}

		// add onload image
		$content .= $this->onloadImage;

		// Show a text (if defined in FF) if searchbox was opened without any piVars
		if($this->isEmptySearch() && $this->ffdata['showTextInsteadOfResults']) {
			$content = $this->pi_RTEcssText($this->ffdata['textForResults']);
		}

		if ($this->UTF8QuirksMode) return utf8_encode($content);
		else return $content;
	}


	/**
 	* Counts searchword and -phrase if ke_stats is installed
 	*
 	* @param   string $searchphrase
 	* @return  void
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Tue Mar 01 2011 12:34:25 GMT+0100
 	*/
	function countSearchWordWithKeStats($searchphrase='') {

		$searchphrase = trim($searchphrase);
		if (t3lib_extMgm::isLoaded('ke_stats') && !empty($searchphrase)) {
			$keStatsObj = t3lib_div::getUserObj('EXT:ke_stats/pi1/class.tx_kestats_pi1.php:tx_kestats_pi1');
			$keStatsObj->initApi();

				// count words
			$wordlist = t3lib_div::trimExplode(' ', $searchphrase, true);
			foreach ($wordlist as $singleword) {
				$keStatsObj->increaseCounter(
					'ke_search Words',
					'element_title,year,month',
					$singleword,
					0,
					$this->firstStartingPoint,
					$GLOBALS['TSFE']->sys_page->sys_language_uid,
					0,
					'extension'
				);
			}

				// count phrase
			$keStatsObj->increaseCounter(
				'ke_search Phrases',
				'element_title,year,month',
				$searchphrase,
				0,
				$this->firstStartingPoint,
				$GLOBALS['TSFE']->sys_page->sys_language_uid,
				0,
				'extension'
			);

			unset($wordlist);
			unset($singleword);
			unset($keStatsObj);
		}
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
		// switch through all swords and use first word found for calculating
		$resultPos = 0;
		if (count($swords)) {
			for ($i=0; $i<count($swords); $i++) {
				$newResultPos = intval(stripos($resultText, $swords[$i]));
				if ($resultPos == 0) {
					$resultPos = $newResultPos;
				}
			}
		}

		$startPos = $resultPos - (ceil($this->ffdata['resultChars'] / 2));
		if ($startPos < 0) $startPos = 0;
		$teaser = substr($resultText, $startPos);

		// crop til whitespace reached
		$cropped = false;
		if ($startPos != 0 && $teaser[0] != " " ) {
			$pos = strpos($teaser, ' ');
			if ($pos === false) {
				$teaser = ' ' . $teaser;
			} else {
				$teaser = substr($teaser, $pos);
				$cropped = true;
			}
		}

		// append dots when cropped
		if ($startPos > 0) $teaser = '...'.$teaser;
		$teaser = $this->betterSubstr($teaser, $this->ffdata['resultChars']);

		// highlight hits?
		if ($this->ffdata['highlightSword'] && count($swords)) {
			foreach ($swords as $word) {
				$word = str_replace('/', '\/', $word);
				$teaser = preg_replace('/('.$word.')/iu','<span class="hit">\0</span>',$teaser);
			}
		}

		return $teaser;
	}


	/*
	 * init flexforms
	 */
	function initFlexforms() {
		// get some helper functions
		$this->div = t3lib_div::makeInstance('tx_kesearch_div');
		$this->div->init($this);

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

		// set startingPoints
		$this->startingPoints = $this->div->getStartingPoint();

		// set resultPage to value from conf if set or current PID if not set in ff or conf
		 if (!$this->ffdata['resultPage']) {
			/*
			 if ($this->conf['resultPage']) $this->ffdata['resultPage'] = $this->conf['resultPage'];
			else $this->ffdata['resultPage'] = $GLOBALS['TSFE']->id;
			*/
		}

		if ($this->ffdata['mode'] == 1 && !empty($this->ffdata['loadFlexformsFromOtherCE'])) {
			// load flexform config from other ce
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

		// set "countSearchPhrase" to 1 if not set yet (default)
		if (!isset($this->ffdata['countSearchPhrases'])) {
			$this->ffdata['countSearchPhrases'] = 1;
		}

		// Hook: modify_flexFormData
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['modifyFlexFormClass'])) 
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['modifyFlexFormClass'] as $hookObject) {
			    if(method_exists($hookObject, 'modify_flexFormData')) {
			        $hookObject->modify_flexFormData($this->ffdata, $this->cObj, $this->piVars);
			    }
			}
		}
	}


	/*
	 * function betterSubstr
	 *
	 * better substring function
	 *
	 * @param $str
	 * @param $length
	 * @param $minword
	 */
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

		$this->initOnclickActions();

		$numberOfResults = $this->numberOfResults;
		$resultsPerPage = $this->ffdata['resultsPerPage'];
		$maxPages = $this->ffdata['maxPagesInPagebrowser'];

		// set first page if not set
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

				if ($this->ffdata['renderMethod'] == 'static' || $this->ffdata['renderMethod'] == 'ajax_after_reload') {

					// render static version
					unset($linkconf);
					$linkconf['parameter'] = $GLOBALS['TSFE']->id;
					$linkconf['addQueryString'] = 1;
					$linkconf['additionalParams'] = '&tx_kesearch_pi1[sword]='.$this->piVars['sword'];
					$linkconf['additionalParams'] .= '&tx_kesearch_pi1[page]='.intval($i);
					$filterArray = $this->getFilters();

					if (is_array($this->piVars['filter'])) {
						foreach($this->piVars['filter'] as $filterId => $data) {
							$linkconf['additionalParams'] .= '&tx_kesearch_pi1[filter]['.$filterId.']='.$this->piVars['filter'][$filterId];
						}
					}

					if ($this->piVars['page'] == $i) $linkconf['ATagParams'] = 'class="current" ';
					$tempContent .= $this->cObj->typoLink($i, $linkconf);

				} else {

					// render ajax version
					$tempContent .= '<a onclick="document.getElementById(\'pagenumber\').value=\''.$i.'\'; '.$this->onclickPagebrowser.'"';
					if ($this->piVars['page'] == $i) $tempContent .= ' class="current" ';
					$tempContent .= '>'.$i.'</a>';

				}
			}
		}

		// end
		$end = ($start+$resultsPerPage > $numberOfResults) ? $numberOfResults : ($start+$resultsPerPage);

		// previous image with link
		if ($this->piVars['page'] > 1) {

			$previousPage = $this->piVars['page']-1;
			if ($this->ffdata['renderMethod'] == 'static' || $this->ffdata['renderMethod'] == 'ajax_after_reload') {
				// get static version
				unset($linkconf);
				$linkconf['parameter'] = $GLOBALS['TSFE']->id;
				$linkconf['addQueryString'] = 1;
				$linkconf['additionalParams'] = '&tx_kesearch_pi1[sword]='.$this->piVars['sword'];
				$linkconf['additionalParams'] .= '&tx_kesearch_pi1[page]='.intval($previousPage);
				$filterArray = $this->getFilters();

				if (is_array($this->piVars['filter'])) {
					foreach($this->piVars['filter'] as $filterId => $data) {
						$linkconf['additionalParams'] .= '&tx_kesearch_pi1[filter]['.$filterId.']='.$this->piVars['filter'][$filterId];
					}
				}

				$linkconf['ATagParams'] = 'class="prev" ';
				$previous = $this->cObj->typoLink(' ', $linkconf);
			} else {
				// get ajax version
				$onclickPrevious = 'document.getElementById(\'pagenumber\').value=\''.$previousPage.'\'; ';
				$previous = '<a class="prev" onclick="'.$onclickPrevious.$this->onclickPagebrowser.'">&nbsp;</a>';
			}
		} else {
			$previous = '';
		}

		// next image with link
		if ($this->piVars['page'] < $pagesTotal) {
			$nextPage = $this->piVars['page']+1;
			if ($this->ffdata['renderMethod'] == 'static' || $this->ffdata['renderMethod'] == 'ajax_after_reload') {
				// get static version
				unset($linkconf);
				$linkconf['parameter'] = $GLOBALS['TSFE']->id;
				$linkconf['addQueryString'] = 1;
				$linkconf['additionalParams'] = '&tx_kesearch_pi1[sword]='.$this->piVars['sword'];
				$linkconf['additionalParams'] .= '&tx_kesearch_pi1[page]='.intval($nextPage);
				$filterArray = $this->getFilters();

				if (is_array($this->piVars['filter'])) {
					foreach($this->piVars['filter'] as $filterId => $data) {
						$linkconf['additionalParams'] .= '&tx_kesearch_pi1[filter]['.$filterId.']='.$this->piVars['filter'][$filterId];
					}
				}

				$linkconf['ATagParams'] = 'class="next" ';
				$next = $this->cObj->typoLink(' ', $linkconf);
			} else {
				// get ajax version
				$onclickNext = 'document.getElementById(\'pagenumber\').value=\''.$nextPage.'\'; ';
				$next  = '<a class="next" onclick="'.$onclickNext.$this->onclickPagebrowser.'">&nbsp;</a>';
			}

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


	function renderOrdering() {
		// show ordering only if is set in FlexForm
		if($this->ffdata['showSortInFrontend']) {
			$subpartArray['###ORDERNAVIGATION###'] = $this->cObj->getSubpart($this->templateCode, '###ORDERNAVIGATION###');
			$subpartArray['###SORT_LINK###'] = $this->cObj->getSubpart($subpartArray['###ORDERNAVIGATION###'], '###SORT_LINK###');

			if($this->ffdata['showSortInFrontend']) {
				$orderByDir = strtolower($this->div->removeXSS($this->piVars['orderByDir']));
				$orderByField = strtolower($this->div->removeXSS($this->piVars['orderByField']));
				if(!$orderByField) $orderByField = 'score';
				if($orderByDir != 'desc' && $orderByDir != 'asc') $orderByDir = 'desc';
				if($orderByDir == 'desc') {
					$orderByDir = 'asc';
				} else {
					$orderByDir = 'desc';
				}
			} else {
				$orderByDir = 'desc';
			}

			$orderBy = t3lib_div::trimExplode(',', $this->ffdata['sortByVisitor']);

			// loop all allowed orderings
			foreach($orderBy as $value) {
				// we can't sort by score if there is no sword given
				if($this->piVars['sword'] != '' || $value != 'score') {
					$markerArray['###FIELDNAME###'] = $value;
					if($this->ffdata['renderMethod'] == 'ajax') {
						// generate link for ajax mode
						$markerArray['###URL###'] = '<span onclick="setOrderBy(\'' . $value . '\', \'' . $orderByDir . '\')">' . $value . '</span>';
					} else {
						// generate link for static and after reload mode
						$markerArray['###URL###'] = $this->cObj->typoLink(
							$this->pi_getLL('orderlink_' . $value, $value),
							array(
								'parameter' => $GLOBALS['TSFE']->id,
								'addQueryString' => 1,
								'additionalParams' => '&' . $this->prefixId . '[orderByField]=' . $value . '&' . $this->prefixId . '[orderByDir]=' . $orderByDir,
								'section' => 'kesearch_ordering'
							)
						);
					}

					// add classname for sorting arrow
					if($value == $orderByField) {
						if($orderByDir == 'asc') {
							$markerArray['###CLASS###'] = 'down';
						} else {
							$markerArray['###CLASS###'] = 'up';
						}
					} else {
						$markerArray['###CLASS###'] = '';
					}

					$links .= $this->cObj->substituteMarkerArray($subpartArray['###SORT_LINK###'], $markerArray);
				}
			}

			$content = $this->cObj->substituteSubpart($subpartArray['###ORDERNAVIGATION###'], '###SORT_LINK###', $links);
			$content = $this->cObj->substituteMarker($content, '###LABEL_SORT###', $this->pi_getLL('label_sort'));

			return $content;
		} else {
			return '';
		}
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
							$where = $GLOBALS['TYPO3_DB']->listQuery('options', intval($tagData['uid']), 'tx_kesearch_filters');
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
				$where = $GLOBALS['TYPO3_DB']->listQuery('options', intval($option), 'tx_kesearch_filters');
				$where .= ' AND tx_kesearch_filteroptions.uid = '.intval($option);
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
		$type = $this->div->removeXSS($type);
		unset($imageConf);
		$imageConf['file'] = t3lib_extMgm::siteRelPath($this->extKey).'res/img/types/'.$type.'.gif';
		$image=$this->cObj->IMAGE($imageConf);
		return $image;
	}


	/*
	 * function initOnloadImage
	 */
	function initOnloadImage() {

		$onloadSrc = t3lib_extMgm::siteRelPath($this->extKey).'res/img/blank.gif';

		// is current page the result page?
		$resultPage = ($GLOBALS['TSFE']->id == $this->ffdata['resultPage']) ? TRUE : FALSE;

		switch ($this->ffdata['renderMethod']) {
			case 'ajax':
				if ($resultPage) {
					$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onLoad="onloadFiltersAndResults();" alt="" /> ';
				} else {
					$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onLoad="onloadFilters();" alt="" /> ';
				}
				break;

			case 'ajax_after_reload':
				if ($resultPage) {
					$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onLoad="setTimeout(\'onloadFiltersAndResults()\',200 );" alt="" /> ';
				} else {
					$this->onloadImage = '<img src="'.$onloadSrc.'?ts='.time().'" onLoad="onloadFilters();" alt="" /> ';
				}
				break;

			case 'static':
			default:
				$this->onloadImage = '';
				break;

		}

	}



	/*
	 * count searchwords and phrases in statistic tables
	 *
	 * @param $searchPhrase string
	 * @param $searchWordsArray array
	 * @param $hits int
	 * @param $tagsAgainst string
	 * @return void
	 *
	 */
	function countSearchPhrase($searchPhrase, $searchWordsArray, $hits, $tagsAgainst) {

		// prepare "tagsAgainst"
		$search = array('"', ' ', '+');
		$replace = array('', '', '');
		$tagsAgainst = str_replace($search, $replace, implode(' ', $tagsAgainst));

		// count search phrase
		if (!empty($searchPhrase)) {
			$table = 'tx_kesearch_stat_search';
			$fields_values = array(
				'pid' => $this->firstStartingPoint,
				'searchphrase' => strtolower($searchPhrase),
				'tstamp' => time(),
				'hits' => $hits,
				'tagsagainst' => $tagsAgainst,
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery($table,$fields_values,$no_quote_fields=FALSE);
		}

		// count single words
		foreach ($searchWordsArray as $searchWord) {
			$table = 'tx_kesearch_stat_word';
			$timestamp = time();
			if (!empty($searchWord)) {
				$fields_values = array(
					'pid' => $this->firstStartingPoint,
					'word' => strtolower($searchWord),
					'tstamp' => $timestamp,
					'pageid' => $GLOBALS['TSFE']->id,
					'resultsfound' => $hits ? 1 : 0,
				);
				$GLOBALS['TYPO3_DB']->exec_INSERTquery($table,$fields_values,$no_quote_fields=FALSE);
			}
		}

	}


	/**
	 * function isEmptySearch
	 * checks if an empty search was loaded / submitted
	 *
	 * @return boolean true if no searchparams given; otherwise false
	 */
	protected function isEmptySearch() {
		// build words searchphrase
		$searchWordInformation = $this->buildWordSearchphrase();
		$sword = $searchWordInformation['sword'];
		$swords = $searchWordInformation['swords'];
		$wordsAgainst = $searchWordInformation['wordsAgainst'];
		$scoreAgainst = $searchWordInformation['scoreAgainst'];

		// check if searchword is emtpy or equal with default searchbox value
		$emptySearchword = (empty($sword) || $sword == $this->pi_getLL('searchbox_default_value')) ? true : false;

		// check if filters are set
		$this->filters = $this->getFilters();
		$filterSet = false;
		if(is_array($this->filters))  {
			//TODO: piVars filter is a multidimensional array
			foreach($this->filters as $uid => $data)  {
				if(!empty($this->piVars['filter'][$uid])) $filterSet = true;
			}
		}
		// check for preselected filters
		if(count($this->preselectedFilter)) $filterSet = true;

		if($emptySearchword && !$filterSet) return true;
		else return false;
	}


	/*
	 * function includeJavascript
	 */
	function getJSContent() {

		$jsContent = '';

		// build target URL if not result page
		unset($linkconf);
		$linkconf['parameter'] = $this->ffdata['resultPage'];
		$linkconf['additionalParams'] = '';
		$linkconf['useCacheHash'] = false;
		$targetUrl = t3lib_div::locationHeaderUrl($this->cObj->typoLink_URL($linkconf));

		// include js for all render methods
		$jsContent .= '
			function searchboxFocus(searchbox) {
				if (searchbox.value == \''.$this->pi_getLL('searchbox_default_value').'\' ) {
					searchbox.value = \'\';
				}
			}

			function enableCheckboxes(filter) {
				allCb = document.getElementById(\'options_filter[\'+filter+\']\').getElementsByTagName(\'input\');
				allCbChecked = true;
				for(i = 0; i < allCb.length; i++) {
					if(!allCb[i].checked) {
						allCbChecked = false;
					}
				}
				if(allCbChecked) {
					for(i = 0; i < allCb.length; i++) {
						allCb[i].checked = false;
					}
				} else {
					for(i = 0; i < allCb.length; i++) {
						allCb[i].checked = true;
					}
				}
			}

		';

		// include js for non-static modes
		if ($this->ffdata['renderMethod'] != 'static' ) {
			$jsContent .= '
				function switchArea(objid) {
					if (document.getElementById(\'options_\' + objid).className == \'expanded\') {
						document.getElementById(\'options_\' + objid).className = \'closed\';
						document.getElementById(\'bullet_\' + objid).src=\''.t3lib_extMgm::siteRelPath($this->extKey).'res/img/list-head-closed.gif\';
					} else {
						document.getElementById(\'options_\' + objid).className = \'expanded\';
						document.getElementById(\'bullet_\' + objid).src=\''.t3lib_extMgm::siteRelPath($this->extKey).'res/img/list-head-expanded.gif\';
					}
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

				// refresh result list onload
				function onloadFilters() {
					document.getElementById(\'kesearch_filters\').style.display=\'none\';
					document.getElementById(\'kesearch_updating_filters\').style.display=\'block\';
					tx_kesearch_pi1refreshFiltersOnLoad(xajax.getFormValues(\'xajax_form_kesearch_pi1\'));
				}

			';

		}

		// include js for full ajax mode
		if ($this->ffdata['renderMethod'] == 'ajax') {
			$jsContent .= '

				function submitAction() {
					document.getElementById(\'kesearch_filters\').style.display=\'none\';
					document.getElementById(\'kesearch_updating_filters\').style.display=\'block\';
					document.getElementById(\'kesearch_results\').style.display=\'none\';
					document.getElementById(\'kesearch_updating_results\').style.display=\'block\';
					document.getElementById(\'kesearch_pagebrowser_top\').style.display=\'none\';
					document.getElementById(\'kesearch_pagebrowser_bottom\').style.display=\'none\';
					document.getElementById(\'kesearch_query_time\').style.display=\'none\';
					document.getElementById(\'pagenumber\').value="1";
				}

				function refreshFiltersOnly() {
					document.getElementById(\'kesearch_filters\').style.display=\'none\';
					document.getElementById(\'kesearch_updating_filters\').style.display=\'block\';
				}

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

				function onloadFiltersAndResults() {
					document.getElementById(\'kesearch_filters\').style.display=\'none\';
					document.getElementById(\'kesearch_updating_filters\').style.display=\'block\';
					document.getElementById(\'kesearch_results\').style.display=\'none\';
					document.getElementById(\'kesearch_updating_results\').style.display=\'block\';
					document.getElementById(\'kesearch_pagebrowser_top\').style.display=\'none\';
					document.getElementById(\'kesearch_pagebrowser_bottom\').style.display=\'none\';
					document.getElementById(\'kesearch_query_time\').style.display=\'none\';
					tx_kesearch_pi1refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\'));
				}

				// reset both searchbox and filters
				function resetSearchboxAndFilters() {
					document.getElementById(\'kesearch_filters\').style.display=\'none\';
					document.getElementById(\'kesearch_updating_filters\').style.display=\'block\';
					document.getElementById(\'resetFilters\').value=1;
					document.getElementById(\'ke_search_sword\').value="";
					document.getElementById(\'pagenumber\').value="1";
					tx_kesearch_pi1resetSearchbox(xajax.getFormValues(\'xajax_form_kesearch_pi1\'));
				}

				// set form action so that redirect to result page is processed
				function redirectToResultPage() {
					formEl = document.getElementById(\'xajax_form_kesearch_pi1\');
					formEl.action=\''.$targetUrl.'\';
					formEl.submit();
				}

				// orderBy
				function setOrderBy(field, direction) {
					document.getElementById(\'orderByField\').value = field;
					document.getElementById(\'orderByDir\').value = direction;
					document.getElementById(\'pagenumber\').value=1;
					//document.getElementById(\'resetFilters\').value=1;
					'.$this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\'));
					submitAction();
				}
			';

			$jsContent .= '
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
				$jsContent .= 'redirectToResultPage(); }';
			} else {
				// refresh results and searchbox if current page is result page
				$jsContent .=  ' '.$this->prefixId . 'refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\')); submitAction();}';
			}
			$jsContent .= '}';

			$jsContent .= '
				function hideSpinner() {
					document.getElementById(\'kesearch_filters\').style.display=\'block\';
					document.getElementById(\'kesearch_updating_filters\').style.display=\'none\';
					document.getElementById(\'kesearch_results\').style.display=\'block\';
					document.getElementById(\'kesearch_updating_results\').style.display=\'none\';
					document.getElementById(\'kesearch_pagebrowser_top\').style.display=\'block\';
					document.getElementById(\'kesearch_pagebrowser_bottom\').style.display=\'block\';
					document.getElementById(\'kesearch_query_time\').style.display=\'block\';';

			// add reset filters?
			if ($this->ffdata['resetFiltersOnSubmit']) $jsContent .= 'document.getElementById(\'resetFilters\').value=0; ';

			$jsContent .= '
				}';

		}

		// include js for "ajax after page reload" mode
		if ($this->ffdata['renderMethod'] == 'ajax_after_reload') {
			$jsContent .= '
				// refresh result list onload
				function onloadResults() {
					document.getElementById(\'kesearch_pagebrowser_top\').style.display=\'none\';
					document.getElementById(\'kesearch_pagebrowser_bottom\').style.display=\'none\';
					document.getElementById(\'kesearch_results\').style.display=\'none\';
					document.getElementById(\'kesearch_updating_results\').style.display=\'block\';
					document.getElementById(\'kesearch_query_time\').style.display=\'none\';
					tx_kesearch_pi1refreshResultsOnLoad(xajax.getFormValues(\'xajax_form_kesearch_pi1\'));
				}

				function onloadFiltersAndResults() {
					document.getElementById(\'kesearch_filters\').style.display=\'none\';
					document.getElementById(\'kesearch_updating_filters\').style.display=\'block\';
					document.getElementById(\'kesearch_results\').style.display=\'none\';
					document.getElementById(\'kesearch_updating_results\').style.display=\'block\';
					document.getElementById(\'kesearch_pagebrowser_top\').style.display=\'none\';
					document.getElementById(\'kesearch_pagebrowser_bottom\').style.display=\'none\';
					document.getElementById(\'kesearch_query_time\').style.display=\'none\';
					tx_kesearch_pi1refresh(xajax.getFormValues(\'xajax_form_kesearch_pi1\'));
				}

				function hideSpinner() {
					document.getElementById(\'kesearch_filters\').style.display=\'block\';
					document.getElementById(\'kesearch_updating_filters\').style.display=\'none\';
					document.getElementById(\'kesearch_results\').style.display=\'block\';
					document.getElementById(\'kesearch_updating_results\').style.display=\'none\';
					document.getElementById(\'kesearch_pagebrowser_top\').style.display=\'block\';
					document.getElementById(\'kesearch_pagebrowser_bottom\').style.display=\'block\';
					document.getElementById(\'kesearch_query_time\').style.display=\'block\';
				}
			';
		}

		$jsContent = '<script type="text/javascript">'.$jsContent.'</script>';

		// minify JS?
		if (version_compare(TYPO3_version, '4.2.0', '>=' )) $jsContent = t3lib_div::minifyJavaScript($jsContent);
		// add JS to page header
		$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId.'_jsContent'] = $jsContent;

	}



}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_search/pi1/class.tx_kesearch_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_search/pi1/class.tx_kesearch_pi1.php']);
}

?>
