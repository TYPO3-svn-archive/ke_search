<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_kesearch_filters'] = array (
	'ctrl' => $TCA['tx_kesearch_filters']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'hidden,title,options,rendertype'
	),
	'feInterface' => $TCA['tx_kesearch_filters']['feInterface'],
	'columns' => array (
		'hidden' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'title' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.title',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'rendertype' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.rendertype',
			'config' => array (
				'type' => 'select',
				'items' => array (
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.rendertype.I.0', 'select'),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.rendertype.I.1', 'list'),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.rendertype.I.2', 'checkbox'),
				),
				'size' => 1,
				'maxitems' => 1,
				'default' => 'select',
			)
		),

		'cssclass' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.cssclass',
			'config' => array (
				'type' => 'select',
				'items' => array (
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.cssclass.I.0', ''),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.cssclass.I.1', 'small'),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.cssclass.I.2', 'larger'),
				),
				'size' => 1,
				'maxitems' => 1,
			)
		),

		'expandbydefault' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.expandbydefault',
			'config' => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'markAllCheckboxes' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.markAllCheckboxes',
			'config' => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'options' => array (
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.options',
			'config' => Array(
				'type' => 'inline',
				'foreign_table' => 'tx_kesearch_filteroptions',
				'foreign_sortby' => 'sorting',
				'maxitems' => 500,
				'appearance' => Array(
					'collapseAll' => 0,
					'expandSingle' => 0,
					'useSortable' => 1,
				),
			),
		),
		'wrap' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filters.wrap',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
	),
	'types' => array (
		'select' => array('showitem' => 'hidden;;1;;1-1-1, title;;;;2-2-2, rendertype;;;;3-3-3, options, wrap;;;;4-4-4'),
		'list' => array('showitem' => 'hidden;;1;;1-1-1, title;;;;2-2-2, rendertype;;;;3-3-3, expandbydefault, cssclass, options, wrap;;;;4-4-4'),
		'checkbox' => array('showitem' => 'hidden;;1;;1-1-1, title;;;;2-2-2, rendertype;;;;3-3-3, expandbydefault, markAllCheckboxes, cssclass, options, wrap;;;;4-4-4')
	),
	'palettes' => array (
		'1' => array('showitem' => '')
	)
);



$TCA['tx_kesearch_filteroptions'] = array (
	'ctrl' => $TCA['tx_kesearch_filteroptions']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'hidden,title,tag'
	),
	'feInterface' => $TCA['tx_kesearch_filteroptions']['feInterface'],
	'columns' => array (
		'hidden' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'title' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filteroptions.title',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'tag' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filteroptions.tag',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'automated_tagging' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_filteroptions.automated_tagging',
			'config' => array (
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => 1,
				'minitems' => 0,
				'maxitems' => 1,
			)
		),

	),
	'types' => array (
		'0' => array('showitem' => 'hidden;;1;;1-1-1, title;;;;2-2-2, tag;;;;3-3-3, automated_tagging;;;;4-4-4')
	),
	'palettes' => array (
		'1' => array('showitem' => '')
	)
);



$TCA['tx_kesearch_index'] = array (
	'ctrl' => $TCA['tx_kesearch_index']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'targetpid,content,params,type,tags,abstract,title,language'
	),
	//'hideTable' => 1,
	'feInterface' => $TCA['tx_kesearch_index']['feInterface'],
	'columns' => array (
		'starttime' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.starttime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'default'  => '0',
				'checkbox' => '0'
			)
		),
		'endtime' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.endtime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'checkbox' => '0',
				'default'  => '0',
				'range'    => array (
					'upper' => mktime(3, 14, 7, 1, 19, 2038),
					'lower' => mktime(0, 0, 0, date('m')-1, date('d'), date('Y'))
				)
			)
		),
		'fe_group' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.fe_group',
			'config'  => array (
				'type'  => 'select',
				'items' => array (
					array('', 0),
					array('LLL:EXT:lang/locallang_general.xml:LGL.hide_at_login', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.any_login', -2),
					array('LLL:EXT:lang/locallang_general.xml:LGL.usergroups', '--div--')
				),
				'foreign_table' => 'fe_groups'
			)
		),
		'targetpid' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_index.targetpid',
			'config' => array (
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => 1,
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'content' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_index.content',
			'config' => array (
				'type' => 'text',
				'wrap' => 'OFF',
				'cols' => '30',
				'rows' => '5',
			)
		),
		'params' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_index.params',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'type' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_index.type',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'tags' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_index.tags',
			'config' => array (
				'type' => 'text',
				'wrap' => 'OFF',
				'cols' => '30',
				'rows' => '5',
			)
		),
		'abstract' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_index.abstract',
			'config' => array (
				'type' => 'text',
				'cols' => '30',
				'rows' => '5',
			)
		),
		'title' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_index.title',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'language' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_index.language',
			'config' => array (
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.uid',
				'size' => 1,
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'sortdate' => Array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_index.sortdate',
			'config' => Array (
				'type' => 'input',
				'size' => '10',
				'max' => '20',
				'eval' => 'datetime',
				'checkbox' => '0',
				'default' => '0'
			)
		),
	),
	'types' => array (
		'0' => array('showitem' => 'starttime;;;;1-1-1, endtime, fe_group, targetpid, content, params, type, tags, abstract, title;;;;2-2-2, language;;;;3-3-3')
	),
	'palettes' => array (
		'1' => array('showitem' => '')
	)
);




$TCA['tx_kesearch_indexerconfig']['ctrl']['requestUpdate'] = 'type';
$TCA['tx_kesearch_indexerconfig'] = array (
	'ctrl' => $TCA['tx_kesearch_indexerconfig']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'hidden,title,storagepid,startingpoints_recursive,single_pages,sysfolder,type,index_content_with_restrictions, index_passed_events'
	),
	'feInterface' => $TCA['tx_kesearch_indexerconfig']['feInterface'],
	'columns' => array (
		'hidden' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'title' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.title',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'storagepid' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.storagepid',
			'config' => array (
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => 1,
				'minitems' => 1,
				'maxitems' => 1,
			)
		),
		'targetpid' => array (
			'displayCond' => 'FIELD:type:IN:ke_yac,ttnews,dam,xtypocommerce,tt_address',
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.targetpid',
			'config' => array (
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => 1,
				'minitems' => 1,
				'maxitems' => 1,
			)
		),
		'type' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.type',
			'config' => array (
				'type' => 'select',
				'items' => array (
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.type.I.0', 'page', t3lib_extMgm::extRelPath('ke_search').'selicon_tx_kesearch_indexerconfig_type_0.gif'),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.type.I.1', 'ke_yac', t3lib_extMgm::extRelPath('ke_search').'selicon_tx_kesearch_indexerconfig_type_1.gif'),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.type.I.2', 'ttnews', t3lib_extMgm::extRelPath('ke_search').'selicon_tx_kesearch_indexerconfig_type_2.gif'),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.type.I.3', 'dam', t3lib_extMgm::extRelPath('ke_search').'selicon_tx_kesearch_indexerconfig_type_3.gif'),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.type.I.4', 'xtypocommerce', t3lib_extMgm::extRelPath('ke_search').'selicon_tx_kesearch_indexerconfig_type_4.gif'),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.type.I.5', 'tt_address', t3lib_extMgm::extRelPath('ke_search').'selicon_tx_kesearch_indexerconfig_type_5.gif'),
				),
				'size' => 1,
				'maxitems' => 1,
			)
		),
		'startingpoints_recursive' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.startingpoints_recursive',
			'displayCond' => 'FIELD:type:=:page',
			'config' => array (
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => 10,
				'minitems' => 0,
				'maxitems' => 99,
			)
		),
		'single_pages' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.single_pages',
			'displayCond' => 'FIELD:type:=:page',
			'config' => array (
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => 10,
				'minitems' => 0,
				'maxitems' => 99,
			)
		),
		'sysfolder' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.sysfolder',
			'displayCond' => 'FIELD:type:IN:ke_yac,ttnews,dam,tt_address',
			'config' => array (
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => 10,
				'minitems' => 0,
				'maxitems' => 99,
			)
		),
		'index_content_with_restrictions' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.index_content_with_restrictions',
			'displayCond' => 'FIELD:type:=:page',
			'config' => array (
				'type' => 'select',
				'items' => array (
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.index_content_with_restrictions.I.0', 'yes'),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.index_content_with_restrictions.I.1', 'no'),
				),
				'size' => 1,
				'maxitems' => 1,
			)
		),
		'index_passed_events' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.index_passed_events',
			'displayCond' => 'FIELD:type:=:ke_yac',
			'config' => array (
				'type' => 'select',
				'items' => array (
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.index_passed_events.I.0', 'yes'),
					array('LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.index_passed_events.I.1', 'no'),
				),
				'size' => 1,
				'maxitems' => 1,
			)
		),
		'index_dam_categories' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.index_dam_categories',
			'displayCond' => 'FIELD:type:=:dam',
			'config' => array (
				'type' => 'select',
				'form_type' => 'user',
				'userFunc' => 'EXT:dam/lib/class.tx_dam_tcefunc.php:&tx_dam_tceFunc->getSingleField_selectTree',
				'treeViewBrowseable' =>  0,
				'treeViewClass' => 'EXT:dam/components/class.tx_dam_selectionCategory.php:&tx_dam_selectionCategory',
				'foreign_table' => 'tx_dam_cat',
				'size' => 10,
				'autoSizeMax' => 10,
				'minitems' => 0,
				'maxitems' => 100,
			)
		),
		'index_dam_categories_recursive' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:ke_search/locallang_db.xml:tx_kesearch_indexerconfig.index_dam_categories_recursive',
			'displayCond' => 'FIELD:type:=:dam',
			'config' => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
	),
	'types' => array (
		'0' => array('showitem' => 'hidden;;1;;1-1-1, title;;;;2-2-2, storagepid,targetpid;;;;3-3-3, type, startingpoints_recursive, single_pages, sysfolder, index_content_with_restrictions, index_passed_events, index_dam_categories, index_dam_categories_recursive')
	),
	'palettes' => array (
		'1' => array('showitem' => '')
	)
);


?>
