<?php

/* ***************************************************************
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
 * *************************************************************
 * 
 * @author Andreas Kiefer <kiefer@kennziffer.com>
 * @author Christian Bülter <buelter@kennziffer.com>
 */

define('DONOTINDEX', -3);
/**
 * Plugin 'Faceted search' for the 'ke_search' extension.
 *
 * @author	Andreas Kiefer (kennziffer.com) <kiefer@kennziffer.com>
 * @author	Stefan Froemken 
 * @author	Christian Bülter (kennziffer.com) <buelter@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kesearch
 */
class tx_kesearch_indexer_types_page extends tx_kesearch_indexer_types {

	/**
	 * this array contains all data of all pages in the default language
	 *
	 * @var array
	 */
	var $pageRecords = array();

	/**
	 * this array contains all data of all pages, but additionally with all available languages
	 *
	 * @var array
	 */
	var $cachedPageRecords = array(); //

	/**
	 * this array contains the system languages
	 *
	 * @var type
	 */
	var $sysLanguages = array();

	/**
	 * this array contains the definition of which content element types should be indexed
	 *
	 * @var array
	 */
	var $indexCTypes = array(
	    'text',
	    'textpic',
	    'bullets',
	    'table',
	    'html',
	    'header',
	    'uploads'
	);

	/**
	 * this array contains the definition of which file content element types should be indexed
	 *
	 * @var type
	 */
	var $fileCTypes = array('uploads');

	/**
	 *
	 * this array contains the definition of which page
	 * types (field doktype in pages table) should be indexed.
	 *
	 * Standard = 1
	 * Advanced = 2
	 * External URL = 3
	 * Shortcut = 4
	 * Not in menu = 5
	 * Backend User Section = 6
	 * Mountpoint = 7
	 * Spacer = 199
	 * SysFolder = 254
	 * Recycler = 255
	 *
	 * @var array
	 */
	var $indexDokTypes = array(1,2,5);

	/*
	 * Name of indexed elements. Will be overwritten in content element indexer.
	 */
	var $indexedElementsName = 'pages';

	/**
	 * @var \TYPO3\CMS\Core\Resource\FileRepository
	 */
	var $fileRepository;

	/**
	 * counter for how many pages we have indexed
	 *
	 * @var integer
	 */
	var $counter = 0;

	/**
	 * counter for how many pages without content we found
	 *
	 * @var integer
	 */
	var $counterWithoutContent = 0;

	/**
	 * counter for how many files we have indexed
	 *
	 * @var integer
	 */
	var $fileCounter = 0;

	/**
	 * sql query for content types
	 *
	 * @var string
	 */
	var $whereClauseForCType = '';

	/**
	 * Constructor of this object
	 */
	public function __construct($pObj) {
		parent::__construct($pObj);

		foreach ($this->indexCTypes as $value) {
			$cTypes[] = 'CType="' . $value . '"';
		}
		$this->whereClauseForCType = implode(' OR ', $cTypes);

		// get all available sys_language_uid records
		$this->sysLanguages = t3lib_BEfunc::getSystemLanguages();

		// make file repository instance only if TYPO3 version is >= 6.0
		if (TYPO3_VERSION_INTEGER >= 6000000) {
			if (TYPO3_VERSION_INTEGER >= 6002000) {
				$this->fileRepository = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\FileRepository');
			} else {
				$this->fileRepository = t3lib_div::makeInstance('TYPO3\\CMS\\Core\\Resource\\FileRepository');
			}
		}
	}

	/**
	 * This function was called from indexer object and saves content to index table
	 *
	 * @return string content which will be displayed in backend
	 */
	public function startIndexing() {
		// get all pages. Regardeless if they are shortcut, sysfolder or external link
		$indexPids = $this->getPagelist($this->indexerConfig['startingpoints_recursive'], $this->indexerConfig['single_pages']);

		// add complete page record to list of pids in $indexPids
		$this->pageRecords = $this->getPageRecords($indexPids);

		// create an array of cached page records which contains pages in
		// default and all other languages registered in the system
		foreach ($this->pageRecords as $pageRecord) {
			$this->addLocalizedPagesToCache($pageRecord);
		}

		// create a new list of allowed pids
		$indexPids = array_keys($this->pageRecords);

		// add tags to pages of doktype standard, advanced, shortcut and "not in menu"
		// add tags also to subpages of sysfolders (254), since we don't want them to be excluded (see: http://forge.typo3.org/issues/49435)
		$where = ' (doktype = 1 OR doktype = 2 OR doktype = 4 OR doktype = 5 OR doktype = 254) ';

		// add the tags of each page to the global page array
		$this->addTagsToRecords($indexPids, $where);

		// loop through pids and collect page content and tags
		foreach ($indexPids as $uid) {
			if ($uid) {
				$this->getPageContent($uid);
			}
		}

		// show indexer content
		$content .= '<p><b>Indexer "' . $this->indexerConfig['title'] . '": </b><br />'
			. count($indexPids) . ' pages in ' . count($this->sysLanguages) . ' languages have been found for indexing .<br />' . "\n"
			. $this->counter . ' ' . $this->indexedElementsName . ' have been indexed (' . $this->counterWithoutContent .' more had no content).<br />' . "\n"
			. $this->fileCounter . ' files have been indexed.<br />' . "\n"
			. '</p>' . "\n";

		if (TYPO3_VERSION_INTEGER < 6000000) {
			$content .= '<p><i>For file indexing from content elements you need at least TYPO3 6.0.0!</i></p>';
		}

		$content .= $this->showErrors();
		$content .= $this->showTime();

		return $content;
	}

	/**
	 * get array with all pages which should be indexed
	 *
	 * @param array Array with all page cols
	 */
	public function getPageRecords($uids) {
		$fields = '*';
		$table = 'pages';
		$where = 'uid IN (' . implode(',', $uids) . ')';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
		while ($pageRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$pages[$pageRow['uid']] = $pageRow;
		}
		return $pages;
	}

	/**
	 * add localized page records to a cache/globalArray
	 * This is much faster than requesting the DB for each tt_content-record
	 *
	 * @param array $pageRow
	 * @return void
	 */
	public function addLocalizedPagesToCache($pageRow) {
		// create entry in cachedPageRecods for default language
		$this->cachedPageRecords[0][$pageRow['uid']] = $pageRow;

		// create entry in cachedPageRecods for additional languages, skip default language 0
		foreach ($this->sysLanguages as $sysLang) {
			if ($sysLang[1] > 0) {
				list($pageOverlay) = t3lib_BEfunc::getRecordsByField(
						'pages_language_overlay', 'pid', $pageRow['uid'], 'AND sys_language_uid=' . intval($sysLang[1])
				);
				if ($pageOverlay) {
					$this->cachedPageRecords[$sysLang[1]][$pageRow['uid']] = t3lib_div::array_merge(
							$pageRow, $pageOverlay
					);
				}
			}
		}
	}

	/**
	 * creates a rootline and searches for valid feGroups
	 * returns the fe_group restrictions for the given page
	 *
	 * @param integer $currentPageUid
	 * @return array
	 */
	public function getRecursiveFeGroups($currentPageUid) {

		// get the rootline, start with the current page and go up
		$pageUid = $currentPageUid;
		$tempRootline = array(intval($this->cachedPageRecords[0][$currentPageUid]['pageUid']));
		while ($this->cachedPageRecords[0][$pageUid]['pid'] > 0) {
			$pageUid = intval($this->cachedPageRecords[0][$pageUid]['pid']);
			if (is_array($this->cachedPageRecords[0][$pageUid])) {
				$tempRootline[] = $pageUid;
			}
		}

		// revert the ordering of the rootline so it starts with the
		// page at the top of the tree
		krsort($tempRootline);
		$rootline = array();
		foreach ($tempRootline as $pageUid) {
			$rootline[] = $pageUid;
		}

		// since now we have a full rootline of the current page. 0 = level 0, 1 = level 1 and so on,
		// we can fetch the inherited groups from pages above
		$inheritedFeGroups = array();
		foreach ($rootline as $pageUid) {
			if ($this->cachedPageRecords[0][$pageUid]['extendToSubpages'] && !empty($this->cachedPageRecords[0][$pageUid]['fe_group'])) {
				$inheritedFeGroups = explode(',', $this->cachedPageRecords[0][$pageUid]['fe_group']);
			}
		}

		// use the fe_groups restriction of the current page OR use the inherited groups, do not combine them
		if ($this->cachedPageRecords[0][$currentPageUid]['fe_group']) {
			$feGroups = explode(',', $this->cachedPageRecords[0][$currentPageUid]['fe_group']);
		} else {
			if ($inheritedFeGroups) {
				foreach ($inheritedFeGroups as $group) {
					$feGroups = $this->addGroup($inheritedFeGroups, $group);
				}
			} else {
				$feGroups = array();
			}
		}

		return $feGroups;
	}

	/**
	 * adds a frontend group to an array of groups. If the new group is a real
	 * group, remove "hide at login" (-1) and "show for all" (-2) groups from the list.
	 *
	 * @param array $groupList
	 * @param string $group
	 * @return type
	 * @author Christian Bülter <buelter@kennziffer.com>
	 * @since 07.10.13
	 */
	public function addGroup($groupList, $group) {
		$groupList[] = $group;

		if (intval($group) > 0) {
			foreach ($groupList as $key => $value) {
				if ($value < 0) {
					unset($groupList[$key]);
				}
			}
		}

		return $groupList;
	}

	/**
	 * get content of current page and save data to db
	 * @param $uid page-UID that has to be indexed
	 */
	public function getPageContent($uid) {
		// get content elements for this page
		$fields = 'uid, header, bodytext, CType, sys_language_uid, header_layout, fe_group';
		$table = 'tt_content';
		$where = 'pid = ' . intval($uid);
		$where .= ' AND (' . $this->whereClauseForCType . ')';
		$where .= t3lib_BEfunc::BEenableFields($table);
		$where .= t3lib_BEfunc::deleteClause($table);

		// Get frontend groups for this page, this group applies to all
		// content elements of this pages. Individuel frontent groups
		// set for the content elements will be ignored. Use the content
		// element indexer if you need that feature!
		$feGroupsPages = t3lib_div::uniqueList(implode(',', $this->getRecursiveFeGroups($uid)));

		// get Tags for current page
		$tags = $this->pageRecords[intval($uid)]['tags'];

		$ttContentRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where);
		$pageContent = array();
		if (count($ttContentRows)) {
			foreach ($ttContentRows as $ttContentRow) {
				$content = '';

				// index header
				// add header only if not set to "hidden"
				if ($ttContentRow['header_layout'] != 100) {
					$content .= strip_tags($ttContentRow['header']) . "\n";
				}

				// index content of this content element and find attached or linked files.
				// Attached files are saved as file references, the RTE links directly to
				// a file, thus we get file objects.
				// Files go into the index no matter if "index_content_with_restrictions" is set
				// or not, that means even if protected content elements do not go into the index,
				// files do. Since each file gets it's own index entry with correct access
				// restrictons, that's no problem from a access permission perspective (in fact, it's a feature).
				if (in_array($ttContentRow['CType'], $this->fileCTypes)) {
					$fileObjects = $this->findAttachedFiles($ttContentRow);
				} else {
					$fileObjects = $this->findLinkedFilesInRte($ttContentRow);
					$content .= $this->getContentFromContentElement($ttContentRow) . "\n";
				}

				// index the files fond
				$this->indexFiles($fileObjects, $ttContentRow, $feGroupsPages, $tags) . "\n";

				// add content from this content element to page content
				// ONLY if this content element is not access protected
				// or protected content elements should go into the index
				// by configuration.
				if ($this->indexerConfig['index_content_with_restrictions'] == 'yes' || $ttContentRow['fe_group'] == '' || $ttContentRow['fe_group'] == '0') {
					$pageContent[$ttContentRow['sys_language_uid']] .= $content;
				}

			}
		} else {
			$this->counterWithoutContent++;
			return;
		}

		// make it possible to modify the indexerConfig via hook
		$indexerConfig = $this->indexerConfig;

		// make it possible to modify the default values via hook
		$indexEntryDefaultValues = array(
			'type' => 'page',
			'uid' => $uid,
			'params' => '',
			'feGroupsPages' => $feGroupsPages,
			'debugOnly' => FALSE
		);

		// hook for custom modifications of the indexed data, e. g. the tags
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPagesIndexEntry'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPagesIndexEntry'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->modifyPagesIndexEntry(
					$uid, $pageContent, $tags, $this->cachedPageRecords, $additionalFields, $indexerConfig, $indexEntryDefaultValues
				);
			}
		}

		// store record in index table
		if (count($pageContent)) {
			foreach ($pageContent as $language_uid => $content) {
				if ($this->checkIfpageShouldBeIndexed($uid, $language_uid)) {
					$this->pObj->storeInIndex(
						$indexerConfig['storagepid'],                               // storage PID
						$this->cachedPageRecords[$language_uid][$uid]['title'],     // page title
						$indexEntryDefaultValues['type'],                           // content type
						$indexEntryDefaultValues['uid'],                            // target PID: where is the single view?
						$content,                                                   // indexed content, includes the title (linebreak after title)
						$tags,                                                      // tags
						$indexEntryDefaultValues['params'],                         // typolink params for singleview
						$this->cachedPageRecords[$language_uid][$uid]['abstract'],  // abstract
						$language_uid,                                              // language uid
						$this->cachedPageRecords[$language_uid][$uid]['starttime'], // starttime
						$this->cachedPageRecords[$language_uid][$uid]['endtime'],   // endtime
						$indexEntryDefaultValues['feGroupsPages'],                  // fe_group
						$indexEntryDefaultValues['debugOnly'],                      // debug only?
						$additionalFields                                           // additional fields added by hooks
					);
					$this->counter++;
				}
			}
		}

		return;
	}

	/**
	 * checks wether the given page should go to the index.
	 * Checks the doktype and wethe the "hidden" or "no_index" flags
	 * are set.
	 *
	 * @param integer $pageUid
	 * @param integer $language_uid
	 * @return boolean
	 */
	public function checkIfpageShouldBeIndexed($uid, $language_uid) {
		$index = true;

		if ($this->cachedPageRecords[$language_uid][$uid]['hidden']) {
			$index = false;
		}

		if ($this->cachedPageRecords[$language_uid][$uid]['no_search']) {
			$index = false;
		}

		if (!in_array($this->cachedPageRecords[$language_uid][$uid]['doktype'], $this->indexDokTypes)) {
			$index = false;
		}

		return $index;
	}

	/**
	 * combine group access restrictons from page(s) and content element
	 * 
	 * @param string $feGroupsPages comma list
	 * @param string $feGroupsContentElement comma list
	 * @return type
	 * @author Christian Bülter <buelter@kennziffer.com>
	 * @since 26.09.13
	 */
	public function getCombinedFeGroupsForContentElement($feGroupsPages, $feGroupsContentElement) {

		// combine frontend groups from page(s) and content elemenet as follows
		// 1. if page has no groups, but ce has groups, use ce groups
		// 2. if ce has no groups, but page has grooups, use page groups
		// 3. if page has "show at any login" (-2) and ce has groups, use ce groups
		// 4. if ce has "show at any login" (-2) and page has groups, use page groups
		// 5. if page and ce have explicit groups (not "hide at login" (-1), merge them (use only groups both have)
		// 6. if page or ce has "hide at login" and the other
		// has an expclicit group the element will never be shown and we must not index it.
		// So which group do we set here? Let's use a constant for that and check in the calling function for that.

		if (!$feGroupsPages && $feGroupsContentElement) {
			$feGroups = $feGroupsContentElement;
		}

		if ($feGroupsPages && !$feGroupsContentElement) {
			$feGroups = $feGroupsPages;
		}

		if ($feGroupsPages == '-2' && $feGroupsContentElement) {
			$feGroups = $feGroupsContentElement;
		}

		if ($feGroupsPages && $feGroupsContentElement == '-2') {
			$feGroups = $feGroupsPages;
		}

		if ($feGroupsPages && $feGroupsContentElement && $feGroupsPages != '-1' && $feGroupsContentElement != '-1') {
			$feGroupsContentElementArray = t3lib_div::intExplode(',', $feGroupsContentElement);
			$feGroupsPagesArray = t3lib_div::intExplode(',', $feGroupsPages);
			$feGroups = implode(',', array_intersect($feGroupsContentElementArray,$feGroupsContentElementArray));
		}

		if (
			($feGroupsContentElement && $feGroupsContentElement != '-1' && $feGroupsContentElement != -2 && $feGroupsPages == '-1')
			||
			($feGroupsPages && $feGroupsPages != '-1' && $feGroupsPages != -2 && $feGroupsContentElement == '-1')
			) {
			$feGroups = DONOTINDEX;
		}

		return $feGroups;
	}

	/**
	 *
	 * Extracts content from files given (as array of file objects or file reference objects)
	 * and writes the content to the index
	 *
	 * @param array $fileObjects
	 * @param array $ttContentRow
	 * @param string $feGroupsPages comma list
	 * @param string $tags string
	 * @author Christian Bülter <buelter@kennziffer.com>
	 * @since 25.09.13
	 */
	public function indexFiles($fileObjects, $ttContentRow, $feGroupsPages, $tags) {
		// combine group access restrictons from page(s) and content element
		$feGroups = $this->getCombinedFeGroupsForContentElement($feGroupsPages, $ttContentRow['fe_group']);

		if (count($fileObjects) && $feGroups != DONOTINDEX) {

			// loop through files
			foreach ($fileObjects as $fileObject) {

				// check if the file extension fits in the list of extensions
				// to index defined in the indexer configuration
				if (t3lib_div::inList($this->indexerConfig['fileext'], $fileObject->getExtension())) {

					// get file path and URI
					$fileUri = $fileObject->getStorage()->getPublicUrl($fileObject);
					$filePath = $fileObject->getForLocalProcessing(FALSE);

					if (TYPO3_VERSION_INTEGER >= 6002000) {
						/* @var $fileIndexerObject tx_kesearch_indexer_types_file  */
						$fileIndexerObject = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_kesearch_indexer_types_file', $this->pObj);
					} else {
						/* @var $fileIndexerObject tx_kesearch_indexer_types_file  */
						$fileIndexerObject = t3lib_div::makeInstance('tx_kesearch_indexer_types_file', $this->pObj);
					}

					// add tags from linking page to this index record?
					if (!$this->indexerConfig['index_use_page_tags_for_files']) {
						$tags = '';
					}

					// add tag to identify this index record as file
					tx_kesearch_helper::makeTags($tags, array('file'));

					// get file information and  file content (using external tools)
					// write file data to the index as a seperate index entry
					// count indexed files, add it to the indexer output
					if ($fileIndexerObject->fileInfo->setFile($filePath)) {
						if (($content = $fileIndexerObject->getFileContent($filePath))) {
							$this->storeFileContentToIndex($fileObject, $content, $fileIndexerObject, $feGroups, $tags, $ttContentRow);
							$this->fileCounter++;
						} else {
							$this->addError($fileIndexerObject->getErrors());
							$this->addError('Could not index file ' . $filePath);
						}
					}

				}
			}
		}
	}

	/**
	 * Finds files attached to "uploads" content elements
	 * returns them as file reference objects array
	 *
	 * @author Christian Bülter <buelter@kennziffer.com>
	 * @since 24.09.13
	 * @param array $ttContentRow content element
	 * @return array
	 */
	public function findAttachedFiles($ttContentRow) {
		if (TYPO3_VERSION_INTEGER >= 6000000) {
			// get files attached to the content element
			$fileReferenceObjects = $this->fileRepository->findByRelation('tt_content', 'media', $ttContentRow['uid']);
		} else {
			$fileReferenceObjects = array();
		}
		return $fileReferenceObjects;
	}


	/**
	 * Finds files linked in rte text
	 * returns them as array of file objects
	 *
	 * @param array $ttContentRow content element
	 * @return array
	 * @author Christian Bülter <buelter@kennziffer.com>
	 * @since 24.09.13
	 */
	public function findLinkedFilesInRte($ttContentRow) {
		$fileObjects = array();
		// check if there are links to files in the rte text
		if (TYPO3_VERSION_INTEGER >= 6000000) {
			if (TYPO3_VERSION_INTEGER >= 6002000) {
				/* @var $rteHtmlParser \TYPO3\CMS\Core\Html\RteHtmlParser */
				$rteHtmlParser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Html\\RteHtmlParser');
			} else {
				/* @var $rteHtmlParser \TYPO3\CMS\Core\Html\RteHtmlParser */
				$rteHtmlParser = t3lib_div::makeInstance('TYPO3\\CMS\\Core\\Html\\RteHtmlParser');
			}
			$blockSplit = $rteHtmlParser->splitIntoBlock('link', $ttContentRow['bodytext'], 1);
			foreach ($blockSplit as $k => $v) {
				if ($k % 2) {
					$tagCode = \TYPO3\CMS\Core\Utility\GeneralUtility::unQuoteFilenames(trim(substr($rteHtmlParser->getFirstTag($v), 0, -1)), TRUE);
					$link_param = $tagCode[1];
					list($linkHandlerKeyword, $linkHandlerValue) = explode(':', trim($link_param), 2);
					if ($linkHandlerKeyword === 'file' && is_numeric($linkHandlerValue)) {
						$fileObjects[] = $this->fileRepository->findByUid($linkHandlerValue);
					}
				}
			}
		}
		return $fileObjects;
	}


	/**
	 *
	 * Store the file content and additional information to the index
	 *
	 * @param $fileObject file reference object or file object
	 * @param string $content file text content
	 * @param tx_kesearch_indexer_types_file $fileIndexerObject
	 * @param string $feGroups comma list of groups to assign
	 * @param array $ttContentRow tt_content element the file was assigned to
	 * @author Christian Bülter <buelter@kennziffer.com>
	 * @since 25.09.13
	 */
	public function storeFileContentToIndex($fileObject, $content, $fileIndexerObject, $feGroups, $tags, $ttContentRow) {

		// get metadata
		if ($fileObject instanceof TYPO3\CMS\Core\Resource\FileReference) {
			$orig_uid = $fileObject->getOriginalFile()->getUid();
			if (TYPO3_VERSION_INTEGER >= 6002000) {
				$metadata = $fileObject->getOriginalFile()->_getMetaData();
			} else {
				$metadata = array();
			}
		} else {
			$orig_uid = $fileObject->getUid();
			if (TYPO3_VERSION_INTEGER >= 6002000) {
				$metadata = $fileObject->_getMetaData();
			} else {
				$metadata = array();
			}
		}

		// assign categories as tags
		$categories = tx_kesearch_helper::getCategories($metadata['uid'], 'sys_file_metadata');
		tx_kesearch_helper::makeTags($tags, $categories['title_list']);

		if ($metadata['title']) {
			$content = $metadata['title'] . "\n" . $content;
		}

		if ($metadata['description']) {
			$abstract = $metadata['description'];
			$content = $metadata['description'] . "\n" . $content;
		}

		if ($metadata['alternative']) {
			$content .= "\n" . $metadata['alternative'];
		}

		$title = $fileIndexerObject->fileInfo->getName();
		$storagePid = $this->indexerConfig['storagepid'];
		$type = 'file:' . $fileObject->getExtension();

		$additionalFields = array(
			'sortdate' => $fileIndexerObject->fileInfo->getModificationTime(),
			'orig_uid' => $orig_uid,
			'orig_pid' => 0,
			'directory' => $fileIndexerObject->fileInfo->getRelativePath(),
			'hash' => $fileIndexerObject->getUniqueHashForFile()
		);

		//hook for custom modifications of the indexed data, e. g. the tags
		if(is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFileIndexEntryFromContentIndexer'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFileIndexEntryFromContentIndexer'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->modifyFileIndexEntryFromContentIndexer($fileObject, $content, $fileIndexerObject, $feGroups, $ttContentRow, $storagePid, $title, $tags, $abstract, $additionalFields);
			}
		}

		// Store record in index table:
		// Add usergroup restrictions of the page and the
		// content element to the index data.
		// Add time restrictions to the index data.
		$this->pObj->storeInIndex(
			$storagePid,                             // storage PID
			$title,                                  // file name
			$type,                                   // content type
			1,                                       // target PID: where is the single view?
			$content,                                // indexed content
			$tags,                                   // tags
			'',                                      // typolink params for singleview
			$abstract,                               // abstract
			$ttContentRow['sys_language_uid'],       // language uid
			$ttContentRow['starttime'],              // starttime
			$ttContentRow['endtime'],                // endtime
			$feGroups,                               // fe_group
			false,                                   // debug only?
			$additionalFields                        // additional fields added by hooks
		);
	}

	/**
	 *
	 * Extracts content from content element and returns it as plain text
	 * for writing it directly to the index
	 *
	 * @author Christian Bülter <buelter@kennziffer.com>
	 * @since 24.09.13
	 * @param array $ttContentRow content element
	 * @return string
	 */
	public function getContentFromContentElement($ttContentRow) {
		// bodytext
		$bodytext = $ttContentRow['bodytext'];

		// following lines prevents having words one after the other like: HelloAllTogether
		$bodytext = str_replace('<td', ' <td', $bodytext);
		$bodytext = str_replace('<br', ' <br', $bodytext);
		$bodytext = str_replace('<p', ' <p', $bodytext);
		$bodytext = str_replace('<li', ' <li', $bodytext);

		if ($ttContentRow['CType'] == 'table') {
			// replace table dividers with whitespace
			$bodytext = str_replace('|', ' ', $bodytext);
		}
		$bodytext = strip_tags($bodytext);
		return $bodytext;
	}

}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ke_search/Classes/indexer/types/class.tx_kesearch_indexer_types_page.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ke_search/Classes/indexer/types/class.tx_kesearch_indexer_types_page.php']);
}
?>
