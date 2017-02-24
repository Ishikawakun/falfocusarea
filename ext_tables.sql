#
# Table structure for table 'sys_file_metadata'
#
CREATE TABLE sys_file_metadata (
	focal_x_min INT(11)  DEFAULT '0' NOT NULL,
	focal_y_min INT(11)  DEFAULT '0' NOT NULL,

	focal_x_max INT(11)  DEFAULT '0' NOT NULL,
	focal_y_max INT(11)  DEFAULT '0' NOT NULL,
);

CREATE TABLE sys_file_reference (
  falfocusarea varchar(4000) DEFAULT '' NOT NULL,
);