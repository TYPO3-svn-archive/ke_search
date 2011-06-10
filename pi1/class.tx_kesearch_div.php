<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Stefan Froemken (kennziffer.com) <froemken@kennziffer.com>
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
 * Plugin 'Faceted search - searchbox and filters' for the 'ke_search' extension.
 *
 * @author	Stefan Froemken (kennziffer.com) <froemken@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kesearch
 */
class tx_kesearch_div {
	var $showShortMessage = false;
	
	/**
	 * Contains the parent object
	 * @var tx_kesearch_pi1
	 */
	var $pObj;

	public function __construct($pObj) {
		$this->pObj = $pObj;
	}

	function getStartingPoint() {
		// if loadFlexformsFromOtherCE is set
		// try to get startingPoint of given page
		if($uid = intval($this->pObj->conf['loadFlexformsFromOtherCE'])) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'pages, recursive',
				'tt_content',
				'uid = ' . $uid,
				'', '', ''
			);
			if($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				return $this->pObj->pi_getPidList($row['pages'], $row['recursive']);
			}
		}
		// if loadFlexformsFromOtherCE is NOT set
		// get startingPoints of current page
		return $this->pObj->pi_getPidList(
			$this->pObj->cObj->data['pages'],
			$this->pObj->cObj->data['recursive']
		);
	}

	/**
	 * Get the first page of starting points
	 * @param string comma seperated list of page-uids
	 * @return int first page uid
	 */
	function getFirstStartingPoint($pages = 0) {
		$pageArray = explode(',', $pages);
		return intval($pageArray[0]);
	}

	function getSearchString() {
		$searchString = $this->removeXSS($this->piVars['sword']);

		// replace plus and minus chars
		$searchString = str_replace('-', ' ', $searchString);
		$searchString = str_replace('+', ' ', $searchString);

		// split several words
		$searchWordArray = t3lib_div::trimExplode(' ', $searchString, true);

		// build against clause for all searchwords
		if(count($searchWordArray)) {
			foreach ($searchWordArray as $key => $searchWord) {
				// ignore words under length of 4 chars
				if (strlen($searchWord) > 3) {
					if($this->UTF8QuirksMode) {
						$newSearchString .= '+'.utf8_encode($searchWord).'* ';
					}
					else {
						$newSearchString .= '+'.$searchWord.'* ';
					}
				} else {
					unset ($searchWordArray[$key]);
				}
			}
			return $newSearchString;
		} else {
			return '';
		}
	}

	
	/**
 	* Build search strings for SQL Query from piVars
 	*
 	* @return  array
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Wed Mar 16 2011 15:03:26 GMT+0100
 	*/
	public function buildSearchphrase() {
		// prepare searchword for query
		$sword = $this->removeXSS($this->pObj->piVars['sword']);

		// ignore default search box content
		if (strtolower(trim($sword)) == strtolower($this->pObj->pi_getLL('searchbox_default_value'))) {
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
		if(count($swords)) {
			foreach ($swords as $key => $word) {
				// ignore words under length of 4 chars
				if (strlen($word) > 3) {

					if ($this->pObj->UTF8QuirksMode) {
						$scoreAgainst .= utf8_decode($word).' ';
						$wordsAgainst .= '+'.utf8_decode($word).'* ';
					}
					else {
						$scoreAgainst .= $word.' ';
						$wordsAgainst .= '+'.$word.'* ';
					}

				} else {
					unset ($swords[$key]);

					// if any of the search words is below 3 characters
					$this->showShortMessage = true;
				}
			}
		}

		// build tag searchphrase
		$tagsAgainst = array();
		if(is_array($this->pObj->piVars['filter'])) {
			foreach($this->pObj->piVars['filter'] as $key => $tag)  {
				if(is_array($this->pObj->piVars['filter'][$key])) {
					foreach($this->pObj->piVars['filter'][$key] as $subkey => $subtag)  {
						// Don't add a "+", because we are here in checkbox mode. It's a OR.
						if(!empty($subtag)) $tagsAgainst[($key + 1)] .= ' "#'.$subtag.'#" ';
					}
				} else {
					if(!empty($tag)) $tagsAgainst[0] .= ' +"#'.$tag.'#" ';
				}
			}
		}
		
		return array(
			'sword' => $sword, // f.e. hello karl-heinz +mueller
			'swords' => $swords, // f.e. Array: hello|karl|heinz|mueller
			'wordsAgainst' => $wordsAgainst, // f.e. +hello* +karl* +heinz* +mueller* 
			'tagsAgainst' => $tagsAgainst, // f.e. Array: +#category_213# +#color_123# +#city_42# 
			'scoreAgainst' => $scoreAgainst // f.e. hello karl heinz mueller
		);
	}

	
	/**
	* Use removeXSS function from t3lib_div if exists
	* otherwise use removeXSS class included in this extension
	*
	* @param string value
	* @return string XSS safe value
	*/
	function removeXSS($value) {
		if(method_exists(t3lib_div, 'removeXSS')) {
			return t3lib_div::removeXSS($value);
		} else {
			require_once(t3lib_extMgm::extPath($this->extKey).'res/scripts/RemoveXSS.php');
			return RemoveXSS::process($value);
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_search/pi1/class.tx_kesearch_div.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_search/pi1/class.tx_kesearch_div.php']);
}
?>
