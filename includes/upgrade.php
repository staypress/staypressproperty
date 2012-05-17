<?php

function sp_upgradeproperty($from = false) {

	switch($from) {
		case false:	sp_createpropertytables();
					break;

		default:	sp_createpropertytables();
					break;
	}

}

function sp_createpropertytables() {

	global $wpdb;

	if( !empty($wpdb->base_prefix) && defined('STAYPRESS_GLOBAL_TABLES') && STAYPRESS_GLOBAL_TABLES == true ) {
		$prefix = $wpdb->base_prefix;
	} else {
		$prefix = $wpdb->prefix;
	}

	//{$wpdb->prefix}

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_property` (
	  `id` bigint(20) NOT NULL auto_increment,
	  `blog_id` bigint(20) NOT NULL default '0',
	  `post_id` bigint(20) NOT NULL default '0',
	  `status` varchar(20) default '0',
	  `reference` varchar(150) default NULL,
	  `title` varchar(250) default NULL,
	  `extract` text,
	  `description` text,
	  `latitude` double default NULL,
	  `longitude` double default NULL,
	  `country` varchar(150) default NULL,
	  `town` varchar(150) default NULL,
	  `region` varchar(150) default NULL,
	  `mainimageid` bigint(20) default NULL,
	  `listimageid` bigint(20) default NULL,
	  `property_modified` datetime default '0000-00-00 00:00:00',
	  `property_modified_gmt` datetime default '0000-00-00 00:00:00',
	  `contact_id` bigint(20) default '0',
	  PRIMARY KEY  (`id`),
	  KEY `blog_id` (`blog_id`),
	  KEY `parent_id` (`post_id`),
	  KEY `longitude` (`longitude`),
	  KEY `latitude` (`latitude`)
	);";
	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_metadesc` (
	  `id` bigint(20) NOT NULL auto_increment,
	  `metagroup_id` bigint(20) default '0',
	  `metaname` varchar(250) default NULL,
	  `metatype` int(11) default NULL,
	  `metaoptions` text,
	  `blog_id` bigint(20) default '0',
	  `showorder` bigint(20) default '0',
	  PRIMARY KEY  (`id`),
	  KEY `metaname` (`metaname`),
	  KEY `blog_id` (`blog_id`)
	)";
	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_metagroupdesc` (
	  `id` bigint(20) NOT NULL auto_increment,
	  `groupname` varchar(150) default NULL,
	  `grouporder` bigint(20) default '0',
	  `blog_id` bigint(20) default '0',
	  PRIMARY KEY  (`id`),
	  KEY `blog_id` (`blog_id`)
	);";
	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_property_meta` (
	  `property_id` bigint(20) default NULL,
	  `meta_id` bigint(20) default NULL,
	  `meta_value` text,
	  `blog_id` bigint(20) default '0',
	  KEY `property_id` (`property_id`),
	  KEY `meta_id` (`meta_id`),
	  KEY `blog_id` (`blog_id`)
	);";
	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_property_price` (
	  `property_id` bigint(20) default NULL,
	  `price_row` int(11) default '0',
	  `price_day` int(11) default '0',
	  `price_month` int(11) default '0',
	  `blog_id` bigint(20) default '0',
	  KEY `property_id` (`property_id`),
	  KEY `blog_id` (`blog_id`),
	  KEY `price_month` (`price_month`,`price_day`)
	);";
	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_property_price_line` (
	  `property_id` bigint(20) default NULL,
	  `price_row` int(11) default NULL,
	  `price_amount` decimal(10,2) default NULL,
	  `price_period` int(11) default NULL,
	  `price_period_type` varchar(10) default NULL,
	  `price_currency` varchar(5) default NULL,
	  KEY `property_id` (`property_id`,`price_row`),
	  KEY `price_period_type` (`price_period_type`)
	);";
	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_queue` (
	  `blog_id` bigint(20) NOT NULL default '0',
	  `object_id` bigint(20) NOT NULL default '0',
	  `object_area` varchar(100) NOT NULL default '',
	  `object_operation` varchar(20) NOT NULL default '',
	  `object_timestamp` int(11) default NULL,
	  PRIMARY KEY  (`object_id`,`object_area`,`object_operation`,`blog_id`),
	  KEY `object_timestamp` (`object_timestamp`)
	);";
	$wpdb->query($sql);

}

?>