<?php
/*
Plugin Name: StayPress Property Mangement plugin
Plugin URI: http://staypress.com/
Description: The StayPress property management plugin
Author: StayPress team
Version: 1.3
Author URI: http://staypress.org/

Copyright 2012  (email: support@staypress.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// This is a holder plugin file and only loads the administration classes
// If we are in the administration panel - thus saving memory and processing
// on the public side of the site

//define('SP_ONMS', true);

require_once('includes/config.php');
require_once('classes/common.php');
// Set up my location
SPPCommon::set_property_url(__FILE__);
SPPCommon::set_property_dir(__FILE__);

if(is_admin()) {
	require_once('classes/postmodel.php');	// custom post types model
	require_once('classes/queue.php');
	require_once('classes/administration.php');
	// Adminstration interface
	$sp_propertyadmin = new sp_propertyadmin();
} else {
	require_once('classes/postmodel.php'); // custom post types model
	require_once('classes/public.php');
	require_once('includes/images.php');
	//  Public interface
	$sp_property = new sp_propertypublic();
}

// Load secondary plugins
SPPCommon::load_property_addons();

?>