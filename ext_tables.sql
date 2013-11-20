#
# Table structure for table 'sys_file'
#
CREATE TABLE sys_file (
	_migrateddamuid int(11) unsigned DEFAULT '0' NOT NULL
);

#
# Table structure for table 'sys_file_collection'
#
CREATE TABLE sys_file_collection (
	_migrateddamcatuid int(11) unsigned DEFAULT '0' NOT NULL,
	_migrateddamselectionuid int(11) unsigned DEFAULT '0' NOT NULL
);

#
# Table structure for table 'sys_category'
#
CREATE TABLE sys_category (
	_migrateddamcatuid int(11) unsigned DEFAULT '0' NOT NULL
);
