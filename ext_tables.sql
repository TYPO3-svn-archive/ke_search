#
# Table structure for table 'pages'
#
CREATE TABLE pages (
	tx_kesearch_tags text
);



#
# Table structure for table 'tx_kesearch_filters'
#
CREATE TABLE tx_kesearch_filters (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	title tinytext,
	options text,
	rendertype varchar(90) DEFAULT '' NOT NULL,
	expandbydefault tinyint(1) DEFAULT '0' NOT NULL,
	markAllCheckboxes tinyint(1) DEFAULT '0' NOT NULL,
	cssclass varchar(90) DEFAULT '' NOT NULL,
	wrap tinytext,

	PRIMARY KEY (uid),
	KEY parent (pid)
) ENGINE=MyISAM;



#
# Table structure for table 'tx_kesearch_filteroptions'
#
CREATE TABLE tx_kesearch_filteroptions (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	title tinytext,
	tag tinytext,
	automated_tagging text,
	sorting int(11) DEFAULT '0' NOT NULL,
	PRIMARY KEY (uid),
	KEY parent (pid)
) ENGINE=MyISAM;



#
# Table structure for table 'tx_kesearch_index'
#
CREATE TABLE tx_kesearch_index (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	starttime int(11) DEFAULT '0' NOT NULL,
	endtime int(11) DEFAULT '0' NOT NULL,
	fe_group varchar(100) DEFAULT '0' NOT NULL,
	targetpid text,
	content text,
	params tinytext,
	type tinytext,
	tags text,
	abstract text,
	sortdate int(11) DEFAULT '0' NOT NULL,
	title tinytext,
	language int(11) DEFAULT '0' NOT NULL,

	FULLTEXT INDEX tags (tags),
	FULLTEXT INDEX content (content),

	PRIMARY KEY (uid),
	KEY parent (pid)
) ENGINE=MyISAM;



#
# Table structure for table 'tx_kesearch_indexerconfig'
#
CREATE TABLE tx_kesearch_indexerconfig (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	title tinytext,
	storagepid text,
	targetpid text,
	startingpoints_recursive text,
	single_pages text,
	sysfolder text,
	index_content_with_restrictions text,
	index_passed_events text,
	type varchar(90) DEFAULT '' NOT NULL,
	index_dam_categories text,
	index_dam_categories_recursive tinyint(3) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid)
) ENGINE=MyISAM;

#
# Table structure for table 'tx_kesearch_stat_search'
#
CREATE TABLE tx_kesearch_stat_search (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  searchphrase text,
  tstamp int(11) DEFAULT '0' NOT NULL,
  hits int(11) DEFAULT '0' NOT NULL,
  tagsagainst text,
  PRIMARY KEY (uid)
) ENGINE=MyISAM;

#
# Table structure for table 'tx_kesearch_stat_word'
#
CREATE TABLE tx_kesearch_stat_word (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  word text,
  tstamp int(11) DEFAULT '0' NOT NULL,
  pageid int(11) DEFAULT '0' NOT NULL,
  resultsfound int(1) DEFAULT '0' NOT NULL,
  PRIMARY KEY (uid),
) ENGINE=MyISAM;
