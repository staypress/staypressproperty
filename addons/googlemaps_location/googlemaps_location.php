<?php
/*
Plugin Name: Google maps location selection
Version: 0.1
Plugin URI:
Description: A StayPress addon that enables the google maps locations selection facility - this is a free addon.
Author: Barry
Author URI: http://mapinated.com

Copyright 2010  (email: barry@mapinated.com)

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

class sp_googlemaps_location {

	var $build = 1;

	var $property;	// The property model

	function __construct() {

		global $wpdb, $user;

		$installed_build = SPPCommon::get_option('staypress_property_build', false);
		$this->property = new property_model($wpdb, $installed_build);

		$tz = get_option('gmt_offset');
		$this->property->set_timezone($tz);

		// Add in the actions
		add_action( 'staypress_propertyedit_form_extras', array(&$this, 'show_property_location' ), 5, 3 );

		add_action( 'staypress_property_admin_header_core', array(&$this, 'enqueue_map_styles') );
		add_action( 'staypress_property_admin_header_edit', array(&$this, 'enqueue_map_script') );
		add_action( 'staypress_property_admin_header_add', array(&$this, 'enqueue_map_script') );

		add_filter( 'staypress_property_preupdate_details', array(&$this, 'update_location_fields') );
		add_filter( 'staypress_property_preadd_details', array(&$this, 'update_location_fields') );

		add_action( 'staypress_property_options_form', array(&$this, 'show_property_map_options') );
		add_action( 'staypress_property_postoptions_update', array(&$this, 'update_property_map_options') );

		// Options page integration
		add_action( 'staypress_property_admin_header_options', array(&$this, 'enqueue_mapoptions_script') );

	}

	function sp_googlemaps_location() {
		$this->__construct();
	}

	function enqueue_map_styles() {
		wp_enqueue_style('propertyadminmapcss', SPPCommon::propertyaddons_url('googlemaps_location/css/mapadministration.css'), array(), $this->build);
	}

	function enqueue_map_script() {

		$defaults = array( 	'latitude' 	=> 	'37.94199',
							'longitude' => 	'-0.74363',
							'zoom'		=>	'12'
							);

		$propertyoptions = SPPCommon::get_option('property_location_defaults', array());
		$propertyoptions = array_merge($defaults, $propertyoptions);

		SPPCommon::enqueue_data( 'staypressmaps', 'latitude', esc_attr($propertyoptions['latitude']));
		SPPCommon::enqueue_data( 'staypressmaps', 'longitude', esc_attr($propertyoptions['longitude']));
		SPPCommon::enqueue_data( 'staypressmaps', 'zoom', esc_attr($propertyoptions['zoom']));

		wp_enqueue_script('googlemaps', "http://maps.google.com/maps/api/js?sensor=true", array(), $this->build);
		wp_enqueue_script('propertymapeditaddjs', SPPCommon::propertyaddons_url('googlemaps_location/js/editaddmap.js'), array('jquery'), $this->build);

		add_action( 'admin_head', array( 'SPPCommon', 'print_data' ) );
	}

	function enqueue_mapoptions_script() {

		$defaults = array( 	'latitude' 	=> 	'37.94199',
							'longitude' => 	'-0.74363',
							'zoom'		=>	'12'
							);

		$propertyoptions = SPPCommon::get_option('property_location_defaults', array());
		$propertyoptions = array_merge($defaults, $propertyoptions);

		SPPCommon::enqueue_data( 'staypressmaps', 'latitude', esc_attr($propertyoptions['latitude']));
		SPPCommon::enqueue_data( 'staypressmaps', 'longitude', esc_attr($propertyoptions['longitude']));
		SPPCommon::enqueue_data( 'staypressmaps', 'zoom', esc_attr($propertyoptions['zoom']));

		wp_enqueue_script('googlemaps', "http://maps.google.com/maps/api/js?sensor=true", array(), $this->build);
		wp_enqueue_script('propertymapoptionsjs', SPPCommon::propertyaddons_url('googlemaps_location/js/optionsmap.js'), array('jquery'), $this->build);

		//add_action( 'admin_head', array( &$this, 'output_admin_header' ) );
		add_action( 'admin_head', array( 'SPPCommon', 'print_data' ) );
	}

	function output_admin_header() {
		echo '<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=true"></script>' . "\n";
	}

	function update_location_fields($property) {

		// Location details
		$property['latitude'] = $_POST['propertylat'];
		$property['longitude'] = $_POST['propertylng'];
		$property['town'] = $_POST['propertytown'];
		$property['region'] = $_POST['propertyregion'];
		$property['country'] = $_POST['propertycountry'];

		return $property;

	}

	function show_property_location($id = false, $property = false, $error = false) {

		if($id) {
			echo "<h3>" . __('Edit Location','property') . "</h3>\n";
		} else {
			echo "<h3>" . __('Add Location','property') . "</h3>\n";
		}

		echo "<input type='text' class='' name='mapquickfind' id='mapquickfind' value='' />";
		echo "<input type='button' class='button' name='mapquickfindbutton' id='mapquickfindbutton' value='" . __('Find','property') . "' />";

		echo "<div id='locationmap'></div>";

		echo "<div id='locationdetails'>";

		if(!isset($property->latitude) || empty($property->latitude)) {
			$style = 'display: none;';

			echo "<h4 style='$style'><span>" . __('Location details','property') . "</span><a id='editlocationlink' href='#editlocation'>" . __('edit','property') . "</a></h4>";

			echo "<div id='innerlocationmessage'>";
			echo "<p class='location'>";
			echo __('Select the location of this property on the map on the left to auto-populate the location details.','property');
			echo "</p>";
			echo "</div>";

			echo "<div id='innerlocationdetails' style='$style'>";
			echo "<label class='main'>" . __('Town','property') . "</label>";
			echo "<p id='propertytowntext' class='location'>" . __('Not set','property') . "</p>";
			echo "<input type='text' name='propertytown' id='propertytown' value='' class='locationinput locale' />";

			echo "<label class='main'>" . __('Region','property') . "</label>";
			echo "<p id='propertyregiontext' class='location'>" . __('Not set','property') . "</p>";
			echo "<input type='text' name='propertyregion' id='propertyregion' value='' class='locationinput locale' />";

			echo "<label class='main'>" . __('Country','property') . "</label>";
			echo "<p id='propertycountrytext' class='location'>" . __('Not set','property') . "</p>";
			echo "<input type='text' name='propertycountry' id='propertycountry' value='' class='locationinput locale' />";

			echo "<label class='main locationsup'>" . __('Latitude','property') . "</label>";
			echo "<input type='text' name='propertylat' id='propertylat' value='' class='locationinput coord' />";

			echo "<label class='main locationsup'>" . __('Longitude','property') . "</label>";
			echo "<input type='text' name='propertylng' id='propertylng' value='' class='locationinput coord' />";
		} else {
			$style = 'display: block';

			echo "<h4 style='$style'><span>" . __('Location details','property') . "</span><a id='editlocationlink' href='#editlocation'>" . __('edit','property') . "</a></h4>";

			echo "<div id='innerlocationdetails' style='$style'>";
			echo "<label class='main'>" . __('Town','property') . "</label>";
			echo "<p id='propertytowntext' class='location'>" . $property->town . "</p>";
			echo "<input type='text' name='propertytown' id='propertytown' value='" . htmlentities(stripslashes($property->town),ENT_QUOTES, 'UTF-8') . "' class='locationinput locale' />";

			echo "<label class='main'>" . __('Region','property') . "</label>";
			echo "<p id='propertyregiontext' class='location'>" . $property->region . "</p>";
			echo "<input type='text' name='propertyregion' id='propertyregion' value='" . htmlentities(stripslashes($property->region),ENT_QUOTES, 'UTF-8') . "' class='locationinput locale' />";

			echo "<label class='main'>" . __('Country','property') . "</label>";
			echo "<p id='propertycountrytext' class='location'>" . $property->country . "</p>";
			echo "<input type='text' name='propertycountry' id='propertycountry' value='" . htmlentities(stripslashes($property->country),ENT_QUOTES, 'UTF-8') . "' class='locationinput locale' />";

			echo "<label class='main locationsup'>" . __('Latitude','property') . "</label>";
			echo "<input type='text' name='propertylat' id='propertylat' value='" . $property->latitude . "' class='locationinput coord' />";

			echo "<label class='main locationsup'>" . __('Longitude','property') . "</label>";
			echo "<input type='text' name='propertylng' id='propertylng' value='" . $property->longitude . "' class='locationinput coord' />";
		}

		echo "</div>";


		echo "</div>";

		echo "<div class='clear'></div>";

	}

	function update_property_map_options( $ignoreproperty ) {

		$property = array();
		// Location details
		$property['latitude'] = $_POST['latitude'];
		$property['longitude'] = $_POST['longitude'];
		$property['zoom'] = $_POST['zoom'];

		SPPCommon::update_option('property_location_defaults', $property);

	}

	function show_property_map_options( $propertyoptions ) {

		$defaults = array( 	'latitude' 	=> 	'37.94199',
							'longitude' => 	'-0.74363',
							'zoom'		=>	'12'
							);

		$propertyoptions = SPPCommon::get_option('property_location_defaults', array());
		$propertyoptions = array_merge($defaults, $propertyoptions);

		echo "<h3>" . __('Default Map Co-ordinates','property') . "</h3>";
		echo "<p>" . __('Move and/or zoom the map to the default location you want to show on the property edit/add pages.','property') . "</p>";

		echo "<div id='locationmap'></div>";

		echo "<input type='hidden' name='latitude' id='latitude' value='" . esc_attr($propertyoptions['latitude'])  . "' />";
		echo "<input type='hidden' name='longitude' id='longitude' value='" . esc_attr($propertyoptions['longitude'])  . "' />";
		echo "<input type='hidden' name='zoom' id='zoom' value='" . esc_attr($propertyoptions['zoom'])  . "' />";



	}


}

$sp_googlemaps_location = new sp_googlemaps_location();

?>