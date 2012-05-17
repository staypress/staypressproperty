CREATE TABLE `wp_sp_property_site_1` (
  `id` bigint(20) NOT NULL auto_increment,
  `blog_id` bigint(20) NOT NULL default '0',
  `parent_id` bigint(20) NOT NULL default '0',
  `live` int(11) default '0',
  `reference` varchar(150) default NULL,
  `title` varchar(250) default NULL,
  `extract` text,
  `description` text,
  `latitude` double default NULL,
  `longitude` double default NULL,
  `country` varchar(150) default NULL,
  `town` varchar(150) default NULL,
  `region` varchar(150) default NULL,
  `mainsmallimageid` bigint(20) default NULL,
  `mainimageid` bigint(20) default NULL,
  `listsmallimageid` bigint(20) default NULL,
  `listimageid` bigint(20) default NULL,
  `property_modified` datetime default '0000-00-00 00:00:00',
  `property_modified_gmt` datetime default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `blog_id` (`blog_id`),
  KEY `parent_id` (`parent_id`),
  KEY `longitude` (`longitude`),
  KEY `latitude` (`latitude`)
) ENGINE=MyISAM AUTO_INCREMENT=287 DEFAULT CHARSET=utf8

CREATE TABLE `wp_sp_property_image_site_1` (
  `id` bigint(20) NOT NULL auto_increment,
  `property_id` bigint(20) default NULL,
  `imagename` varchar(250) default NULL,
  `imagetype` varchar(10) default 'thumbnail',
  `imagewidth` int(11) default '0',
  `imageheight` int(11) default '0',
  `showorder` int(11) default '999',
  `host` varchar(100) default NULL,
  `prefix` varchar(10) default NULL,
  `thumbnails` text,
  PRIMARY KEY  (`id`),
  KEY `property_id` (`property_id`),
  KEY `imagetype` (`imagetype`),
  KEY `showorder` (`showorder`)
) ENGINE=MyISAM AUTO_INCREMENT=5743 DEFAULT CHARSET=utf8

CREATE TABLE `wp_sp_property_account_site_1` (
  `property_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8

CREATE TABLE `wp_sp_property_tag_site_1` (
  `property_id` bigint(20) default NULL,
  `tag_id` bigint(20) default NULL,
  KEY `property_id` (`property_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8

CREATE TABLE `wp_sp_tag_groups_site_1` (
  `id` bigint(20) NOT NULL auto_increment,
  `group` varchar(150) default NULL,
  `live` int(11) default '1',
  `fixed` int(11) default '0',
  `display` int(11) default '0',
  `public` int(11) default '1',
  `blog_id` bigint(20) default '0',
  PRIMARY KEY  (`id`),
  KEY `live` (`live`),
  KEY `blog_id` (`blog_id`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=latin1

CREATE TABLE `wp_sp_tags_site_1` (
  `id` bigint(20) NOT NULL auto_increment,
  `group_id` bigint(20) default '0',
  `tag` varchar(250) default NULL,
  `live` int(11) default '1',
  `taguri` varchar(250) default NULL,
  `fixed` int(11) default '0',
  `meta` text,
  `public` int(11) default '1',
  `blog_id` bigint(11) default '0',
  PRIMARY KEY  (`id`),
  KEY `category_id` (`group_id`),
  KEY `taguri` (`taguri`),
  KEY `live` (`live`)
) ENGINE=MyISAM AUTO_INCREMENT=69 DEFAULT CHARSET=latin1

CREATE TABLE `wp_sp_metadesc_site_1` (
  `id` bigint(20) default NULL,
  `metaname` varchar(250) default NULL,
  `metatype` int(11) default NULL,
  `metaoptions` text,
  `blog_id` bigint(20) default '0',
  `showorder` bigint(20) default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin1

CREATE TABLE `wp_sp_property_meta_site_1` (
  `property_id` bigint(20) default NULL,
  `meta_id` bigint(20) default NULL,
  `meta_value` text
) ENGINE=MyISAM DEFAULT CHARSET=latin1