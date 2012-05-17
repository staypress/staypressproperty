<?php
/*
Plugin Name: Staypress Property Plugin Widgets
Version: 1.2
Plugin URI: http://staypress.com
Description: Core widgets for the StayPress property plugins
Author:
Author URI: http://staypress.com

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
require_once('includes/config.php');
require_once('classes/common.php');
// Set up my location
SPPCommon::set_property_url(__FILE__);
SPPCommon::set_property_dir(__FILE__);

// Add in the database model
require_once('classes/postmodel.php');

/*
*	The property listing map
*/
class sp_propertylistingmap extends WP_Widget {

	var $build = 1;

	function sp_propertylistingmap() {

		// Load the text-domain
		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPPCommon::property_dir( "includes/lang/property-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'property', $mofile );

		$widget_ops = array( 'classname' => 'sp_propertylistingmap', 'description' => __('StayPress Property Map', 'property') );
		$control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'sp_propertylistingmap');
		$this->WP_Widget( 'sp_propertylistingmap', __('StayPress Property Map', 'property'), $widget_ops, $control_ops );

		add_action('init', array(&$this, 'enqueue_page_headings'));
	}

	function enqueue_page_headings() {

		// Only need these on the public side of the site
		if(is_admin()) {
			return;
		}
		// Check if we are actually being used anywhere
		if(is_active_widget(false, false, 'sp_propertylistingmap')) {
			// we are an active widget so add in our styles and js
			wp_enqueue_script('googlemaps', "http://maps.google.com/maps/api/js?sensor=true", array(), $this->build);
			wp_enqueue_script("markerclustererjs", SPPCommon::property_url('js/markerclusterer_compiled.js'), array('jquery', 'googlemaps'));
			wp_enqueue_script('mapwidgetjs', SPPCommon::property_url('js/property.mapwidget.js'), array('jquery'), $this->build);
			wp_enqueue_style('mapwidgetcss', SPPCommon::property_url('css/property.mapwidget.css'), array());

		}

		// enqueue the map icon locations
		SPPCommon::enqueue_data('staypress_data', 'mapicon', SPPCommon::property_url('images/mapicons/black%%.png'));
		SPPCommon::enqueue_data('staypress_data', 'nearicon', SPPCommon::property_url('images/mapicons/gray%%.png'));
		SPPCommon::enqueue_data('staypress_data', 'mapshadow', SPPCommon::property_url('images/mapicons/shadow.png'));
		SPPCommon::enqueue_data('staypress_data', 'nearshadow', SPPCommon::property_url('images/mapicons/shadow.png'));

	}

	function widget( $args, $instance ) {

		extract( $args );

		// build the check array
		$defaults = array(
			'jslinkto' 			=> 	'',
			'title'				=>	'',
			'width' 			=> 	'100%',
			'height'			=>	'300px',
			'maptype'			=>	'ROADMAP',
			'clustermarkers'	=> 	'no',
			'minimumzoom'		=> 	'18'
		);

		foreach($defaults as $key => $value) {
			if(isset($instance[$key])) {
				$defaults[$key] = $instance[$key];
			}
		}

		extract($defaults);

		echo $before_widget;
		$title = apply_filters('widget_title', $title );

		if ( !empty($title) ) {
			echo $before_title . $title . $after_title;
		}

		if(empty($jslinkto)) {
			$jslinkto = 'propertylist';
		}

		echo "<div id='spmap_" . esc_attr($jslinkto) . "' class='spmap";
		if($clustermarkers == 'yes') echo " clustermarkers";
		echo " " . $maptype;
		echo " minimumzoom minimumzoom-" . $minimumzoom;
		echo "' style='width:" . $width . "; height:" . $height . ";'>";
		echo "</div>";

		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$defaults = array(
			'jslinkto' 			=> 	'',
			'title'				=>	'',
			'width' 			=> 	'100%',
			'height'			=>	'300px',
			'maptype'			=>	'ROADMAP',
			'clustermarkers'	=> 	'no',
			'minimumzoom'		=> 	'18'
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {

		$defaults = array(
			'jslinkto' 			=> 	'',
			'title'				=>	'',
			'width' 			=> 	'100%',
			'height'			=>	'300px',
			'maptype'			=>	'ROADMAP',
			'clustermarkers'	=> 	'no',
			'minimumzoom'		=> 	'18'
		);

		$instance = wp_parse_args( (array) $instance, $defaults );

		?>
			<p>
				<?php _e('The only advanced setting for this widget is to link it to (one of) the listing pages javascript variables.','property'); ?>
			</p>
			<p>
				<?php _e('If you do not know what these are, then leave that setting blank and the widget will attempt to locate one itself.','property'); ?>
			</p>
			<p>
				<?php _e('Title:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'title' ); ?>' id='<?php echo $this->get_field_id( 'title' ); ?>' value='<?php echo esc_attr(stripslashes($instance['title'])); ?>' />
			</p>
			<p>
				<?php _e('Link to:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'jslinkto' ); ?>' id='<?php echo $this->get_field_id( 'jslinkto' ); ?>' value='<?php echo esc_attr(stripslashes($instance['jslinkto'])); ?>' />
			</p>
			<p>
				<?php _e('Map width:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'width' ); ?>' id='<?php echo $this->get_field_id( 'width' ); ?>' value='<?php echo esc_attr(stripslashes($instance['width'])); ?>' />
			</p>
			<p>
				<?php _e('Map height:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'height' ); ?>' id='<?php echo $this->get_field_id( 'height' ); ?>' value='<?php echo esc_attr(stripslashes($instance['height'])); ?>' />
			</p>
			<p>
				<?php _e('Cluster markers at higher zoom levels:','property'); ?><br/>
				<select name='<?php echo $this->get_field_name( 'clustermarkers' ); ?>' id='<?php echo $this->get_field_id( 'clustermarkers' ); ?>'>
					<option value='no' <?php if(esc_attr($instance['clustermarkers']) == 'no') echo "selected='selected'"; ?>><?php _e('No','property'); ?></option>
					<option value='yes' <?php if(esc_attr($instance['clustermarkers']) == 'yes') echo "selected='selected'"; ?>><?php _e('Yes','property'); ?></option>
				</select>
			</p>
			<p>
				<?php _e('Default Map Type:','property'); ?><br/>
				<select name='<?php echo $this->get_field_name( 'maptype' ); ?>' id='<?php echo $this->get_field_id( 'maptype' ); ?>'>
					<option value='ROADMAP' <?php if(esc_attr($instance['maptype']) == 'ROADMAP') echo "selected='selected'"; ?>><?php _e('Road Map','property'); ?></option>
					<option value='SATELLITE' <?php if(esc_attr($instance['maptype']) == 'SATELLITE') echo "selected='selected'"; ?>><?php _e('Satellite Map','property'); ?></option>
					<option value='HYBRID' <?php if(esc_attr($instance['maptype']) == 'HYBRID') echo "selected='selected'"; ?>><?php _e('Hybrid Map','property'); ?></option>
					<option value='TERRAIN' <?php if(esc_attr($instance['maptype']) == 'TERRAIN') echo "selected='selected'"; ?>><?php _e('Terrain Map','property'); ?></option>
				</select>
			</p>
			<p>
				<?php _e('Minimum zoom level:','property'); ?><br/>
				<select name='<?php echo $this->get_field_name( 'minimumzoom' ); ?>' id='<?php echo $this->get_field_id( 'minimumzoom' ); ?>'>
					<?php
						for($n=0; $n <= 18; $n++) {
							?>
							<option value='<?php echo $n; ?>' <?php if(esc_attr($instance['minimumzoom']) == $n) echo "selected='selected'"; ?>><?php echo $n; ?></option>
							<?php
						}
					?>
				</select>
			</p>
			<p>&nbsp;</p>
	<?php
	}
}

/*
*	The area information drill down
*/
class sp_drilldownwidget extends WP_Widget {

	var $build = 1;

	function sp_drilldownwidget() {

		// Load the text-domain
		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPPCommon::property_dir( "includes/lang/property-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'property', $mofile );

		$widget_ops = array( 'classname' => 'sp_drilldownwidget', 'description' => __('StayPress Destination Drilldown', 'property') );
		$control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'sp_drilldownwidget');
		$this->WP_Widget( 'sp_drilldownwidget', __('StayPress Destination Drilldown', 'property'), $widget_ops, $control_ops );

	}

	function widget( $args, $instance ) {

		extract( $args );

		// build the check array
		$defaults = array(
			'key'			=>	'_staypress_destination_sidebar_information',
			'title'			=>	'About ',
			'showmore'		=>	'More...'
		);

		foreach($defaults as $key => $value) {
			if(isset($instance[$key])) {
				$defaults[$key] = $instance[$key];
			}
		}

		extract($defaults);

		$infopostid = sp_use_drilldown_post( $key );
		if(!empty($infopostid)) {
			// get the post here and output the content at the top of the page
			// this will generally be a description of the area.
			$infopost =& new WP_Query('post_type=destination&post_status=publish&p=' . $infopostid);

			if($infopost->have_posts()) {
				$infopost->the_post();

				echo $before_widget;
				$thetitle = apply_filters('widget_title', trim($title) . ' ' . get_the_title() );

				if ( !empty($thetitle) ) {
					echo $before_title . $thetitle . $after_title;
				}
				echo "<p>";
				the_excerpt();
				echo "</p>";
				echo "<br/>";
				if(!empty($showmore)) {
					// Put a show more link here
					echo "<p><a href='";
					echo get_permalink( get_the_id() );
					echo "'>";
					echo $showmore;
					echo "</a></p>";
				}

				echo $after_widget;
			}
		}

	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$defaults = array(
			'key'			=>	'_staypress_destination_sidebar_information',
			'title'			=>	'About ',
			'showmore'		=>	'More...'
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {

		$defaults = array(
			'key'			=>	'_staypress_destination_sidebar_information',
			'title'			=>	'About ',
			'showmore'		=>	'More...'
		);

$instance = wp_parse_args( (array) $instance, $defaults );

		?>
			<p>
				<?php _e('The only setting for this widget is the title prefix.','property'); ?>
			</p>
			<p>
				<?php _e('The main content displayed by this widget is grabbed from the excerpt of a destination page relating to destination URL the visitor is on.','property'); ?>
			</p>
			<p>
				<?php _e('The title prefix is pre-pended to the destination pages title. E.G. "About " is prepended to "Spain" to make "About Spain" as the widget title.','property'); ?>
			</p>
			<p>
				<?php _e('Title:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'title' ); ?>' id='<?php echo $this->get_field_id( 'title' ); ?>' value='<?php echo esc_attr(stripslashes($instance['title'])); ?>' />
			</p>
			<p>
				<?php _e('More text:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'showmore' ); ?>' id='<?php echo $this->get_field_id( 'showmore' ); ?>' value='<?php echo esc_attr(stripslashes($instance['showmore'])); ?>' />
			</p>
			<p>&nbsp;</p>
	<?php
	}
}

/*
*	Tag links.
*/
class sp_taglist extends WP_Widget {

	var $build = 1;

	function sp_taglist() {

		// Load the text-domain
		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPPCommon::property_dir( "includes/lang/property-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'property', $mofile );

		$widget_ops = array( 'classname' => 'sp_taglist', 'description' => __('StayPress Tags List', 'property') );
		$control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'sp_taglist');
		$this->WP_Widget( 'sp_taglist', __('StayPress Tags List', 'property'), $widget_ops, $control_ops );

		add_action('init', array(&$this, 'enqueue_page_headings'));
	}

	function enqueue_page_headings() {

		// Only need these on the public side of the site
		if(is_admin()) {
			return;
		}
		// Check if we are actually being used anywhere
		if(is_active_widget(false, false, 'sp_taglist')) {
			// we are an active widget so add in our styles and js
			wp_enqueue_script('taglistwidgetjs', SPPCommon::property_url('js/property.tagwidget.js'), array('jquery'), $this->build);
			wp_enqueue_style('taglistwidgetcss', SPPCommon::property_url('css/property.tagwidget.css'), array(), $this->build);
		}

	}

	function get_term_by_slug( $slug ) {

		global $wpdb;

		$sql = $wpdb->prepare( "SELECT * FROM $wpdb->terms as t WHERE slug = %s LIMIT 0, 1", $slug );

		return $wpdb->get_row( $sql );
	}

	function widget( $args, $instance ) {

		global $sp_tags;

		extract( $args );

		$defaults = array(
			'title'				=>	'',
			'linkbase'			=>	'',
			'taglist'			=>	''
		);

		foreach($defaults as $key => $value) {
			if(isset($instance[$key])) {
				$defaults[$key] = $instance[$key];
			}
		}

		extract($defaults);

		echo $before_widget;
		$title = apply_filters('widget_title', $title );
		if ( !empty($title) ) {
			echo $before_title . $title . $after_title;
		}

		if(!empty($taglist)) {
			// Add a "currently selected" are to the top of any list so it's easy to remove criteria
			if(!empty($sp_tags)) {
				echo "<ul class='widget_termgroup'><li class='widget_termgrouplabel'>";
				echo __('Current filter', 'property');
				echo "</li>";
				echo "<li class='widget_termitems'><ul>";
				foreach( (array) $sp_tags as $tag ) {
					$term = $this->get_term_by_slug($tag);
					if(!empty($term)) {
						echo "<li class='widget_termitem currentlist'>";
						echo "<div>";
						echo "<a title='" . __('Show properties with this tag.','property') . "' href='";
						if(!empty($linkbase)) {
							$url = trailingslashit($linkbase) . $term->slug . '?' .$_SERVER['QUERY_STRING'];
							echo remove_query_arg('q', $url);
						} else {
							$url = sp_get_permalink('with', false, $term->slug) . '?' . $_SERVER['QUERY_STRING'];
							echo remove_query_arg('q', $url);
						}
						echo "'>";
						echo $term->name;
						echo "</a>";
						echo "<a title='" . __('Remove this tag from your list criteria.','property') . "' href='";
						if(!empty($sp_tags)) {
							$tags = $sp_tags;
							$key = array_search($term->slug, $tags);
							if( $key !== false ) {
								unset($tags[$key]);
							}
							$tags = array_unique($tags);
						} else {
							$tags = array();
						}
						if(!empty($linkbase)) {
							echo trailingslashit($linkbase);
							echo implode('/', $tags);
						} else {
							if(empty($tags)) {
								$url = sp_get_permalink('properties') . '?' .$_SERVER['QUERY_STRING'];
								echo remove_query_arg('q', $url);
							} else {
								$url = sp_get_permalink('with', false, implode('/', $tags)) . '?' .$_SERVER['QUERY_STRING'];
								echo remove_query_arg('q', $url);
							}
						}
						echo "'>";
						echo "<span class='minus'>-</span>";
						echo "</a>";
						echo "</div>";
						echo "<div class='end'>&nbsp;</div>";
						echo "</li>";
					}
				}
				echo "</ul></li></ul>";
			}
			// Carry on with the rest of the tags list now
			foreach( (array) $taglist as $tag ) {
				$tax = get_taxonomy($tag);
				$terms = get_terms($tag);
				if(!empty($terms)) {
					echo "<ul class='widget_termgroup'><li class='widget_termgrouplabel'>";
					echo $tax->label;
					echo "</li>";
					echo "<li class='widget_termitems'><ul>";
					foreach( (array) $terms as $term ) {

						if(!empty($sp_tags) && in_array($term->slug, (array) $sp_tags)) {
							echo "<li class='widget_termitem selected'>";
							echo "<div>";
							echo "<a title='" . __('Show properties with this tag.','property') . "' href='";
							if(!empty($linkbase)) {
								$url = trailingslashit($linkbase) . $term->slug . '?' .$_SERVER['QUERY_STRING'];
								echo remove_query_arg('q', $url);
							} else {
								$url = sp_get_permalink('with', false, $term->slug) . '?' .$_SERVER['QUERY_STRING'];
								echo remove_query_arg('q', $url);
							}
							echo "'>";
							echo $term->name;
							echo "</a>";
							echo "<a title='" . __('Remove this tag from your list criteria.','property') . "' href='";
							if(!empty($sp_tags)) {
								$tags = $sp_tags;
								$key = array_search($term->slug, $tags);
								if( $key !== false ) {
									unset($tags[$key]);
								}
								$tags = array_unique($tags);
							} else {
								$tags = array($term->slug);
							}
							if(!empty($linkbase)) {
								echo trailingslashit($linkbase);
								echo implode('/', $tags);
							} else {
								if(empty($tags)) {
									$url = sp_get_permalink('properties') . '?' .$_SERVER['QUERY_STRING'];
									echo remove_query_arg('q', $url);
								} else {
									$url = sp_get_permalink('with', false, implode('/', $tags)) . '?' .$_SERVER['QUERY_STRING'];
									echo remove_query_arg('q', $url);
								}
							}
							echo "'>";
							echo "<span class='minus'>-</span>";
							echo "</a>";
							echo "</div>";
							echo "<div class='end'>&nbsp;</div>";
							echo "</li>";
						} else {
							echo "<li class='widget_termitem'>";
							echo "<div>";
							echo "<a title='" . __('Show properties with this tag.','property') . "' href='";
							if(!empty($linkbase)) {
								$url = trailingslashit($linkbase) . $term->slug . '?' .$_SERVER['QUERY_STRING'];
								echo remove_query_arg('q', $url);
							} else {
								$url = sp_get_permalink('with', false, $term->slug) . '?' .$_SERVER['QUERY_STRING'];
								echo remove_query_arg('q', $url);
							}
							echo "'>";
							echo $term->name;
							echo "</a>&nbsp;";
							echo "<a title='" . __('Add this tag to your list criteria.','property') . "' href='";
							if(!empty($sp_tags)) {
								$tags = $sp_tags;
								$tags[] = $term->slug;
								$tags = array_unique($tags);
							} else {
								$tags = array($term->slug);
							}
							if(!empty($linkbase)) {
								$url = trailingslashit($linkbase) . implode('/', $tags) . '?' .$_SERVER['QUERY_STRING'];
								echo remove_query_arg('q', $url);
							} else {
								$url = sp_get_permalink('with', false, implode('/', $tags)) . '?' .$_SERVER['QUERY_STRING'];
								echo remove_query_arg('q', $url);
							}
							echo "'>";
							echo "<span class='plus'>+</span>";
							echo "</a>";
							echo "</div>";
							echo "<div class='end'>&nbsp;</div>";
							echo "</li>";
						}


					}
					echo "</ul></li></ul>";
				}
			}
		}

		echo $after_widget;

	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$defaults = array(
			'title'				=>	'',
			'linkbase'			=>	'',
			'taglist'			=>	''
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {

		$defaults = array(
			'title'				=>	'',
			'linkbase'			=>	'',
			'taglist'			=>	''
		);

		$instance = wp_parse_args( (array) $instance, $defaults );
		?>
			<p>
				<?php _e('Select the tags lists to show in this widget below.','property'); ?>
			</p>
			<p>
				<?php _e('Title:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'title' ); ?>' id='<?php echo $this->get_field_id( 'title' ); ?>' value='<?php echo esc_attr(stripslashes($instance['title'])); ?>' />
			</p>
			<p>
				<?php _e('Tag lists:','property'); ?><br/>
				<?php
				$taxes = get_object_taxonomies(STAYPRESS_PROPERTY_POST_TYPE);
				if($taxes) {
					echo '<ul>';
					$list = array();
					foreach($taxes as $key => $value) {
						if(!is_taxonomy_hierarchical($value)) {
							$mtax = get_taxonomy($value);
							$list[$key] = "<li><input type='checkbox' name='" . $this->get_field_name( 'taglist' ) . "[]' value='" . esc_attr($value) . "'";
							if(in_array($value, (array) $instance['taglist'])) $list[$key] .= " checked='checked'";
							$list[$key] .= " />&nbsp;";
							//if($value == $taxonomy) $list[$key] .= 'current';
							$list[$key] .= esc_html($mtax->label);
							$list[$key] .= '</li>';
						}
					}
					echo implode("\n", $list);
					echo '</ul>';
				}
				?>
				<br/>
			</p>
			<p>
				<?php _e('Link base:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'linkbase' ); ?>' id='<?php echo $this->get_field_id( 'linkbase' ); ?>' value='<?php echo esc_attr(stripslashes($instance['linkbase'])); ?>' />
				<br/><em><?php _e('Leave blank for default','property'); ?></em>
			</p>
			<p>&nbsp;</p>
	<?php
	}
}

class sp_propertiesnear extends WP_Widget {

	var $build = 1;
	var $property;
	var $propertyoptions;

	function sp_propertiesnear() {

		// Load the text-domain
		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPPCommon::property_dir( "includes/lang/property-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'property', $mofile );

		$widget_ops = array( 'classname' => 'sp_propertiesnear', 'description' => __('StayPress Nearby List', 'property') );
		$control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'sp_propertiesnear');
		$this->WP_Widget( 'sp_propertiesnear', __('StayPress Nearby List', 'property'), $widget_ops, $control_ops );

		add_action('init', array(&$this, 'enqueue_page_headings'));
	}

	function enqueue_page_headings() {

		// Only need these on the public side of the site
		if(is_admin()) {
			return;
		}
		// Check if we are actually being used anywhere
		if(is_active_widget(false, false, 'sp_propertiesnear')) {
			// we are an active widget so add in our styles and js
			wp_enqueue_style('nearbywidgetcss', SPPCommon::property_url('css/property.nearbywidget.css'), array(), $this->build);
		}

	}

	function enqueue_propertynear_data( $posts ) {

		global $wp_query;
		//print_r($wp_query);

		static $alphabet = array("-","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		static $propertydata = array();
		static $count = 1;

		$defaultoptions = array( 	'propertytext'			=> 	'property',
									'propertiestext'		=>	'properties',
									'permalinkhasid'		=>	'yes',
									'firstelement'			=>	'reference',
									'propertytitlelayout'	=>	'%title%',
									'propertytitlemarker'	=>	'numeric'
								);

		$this->propertyoptions = SPPCommon::get_option('sp_property_options', $defaultoptions);

		foreach(array_slice( (array) $posts, 0, 19) as $postkey => $post) {
			if(!empty($post->latitude)) {
				$key = $count++;

				$propertydata[$key] = array(		'ID'			=> 	$post->ID,
													'post_title' 	=> 	$post->post_title,
													'post_excerpt' 	=> 	$post->post_excerpt,
													'latitude'		=> 	$post->latitude,
													'longitude'		=>	$post->longitude,
													'permalink'		=>	sp_get_permalink( 'property', $post )
												);


				if($this->enhancetitle) {
					$title = $post->post_title;
					if(empty($this->propertyoptions['propertytitlelayout'])) $this->propertyoptions['propertytitlelayout'] = '%title%';
					$posts[$postkey]->post_title = str_replace('%title%', $title, $this->propertyoptions['propertytitlelayout']);
					$posts[$postkey]->post_title = str_replace('%listmarker%', $key, $posts[$postkey]->post_title);
				}


			}
		}

		if(!empty($propertydata)) {
			SPPCommon::enqueue_data('staypress_data', 'propertylist_near', $propertydata);
		}

		return $posts;
	}

	function get_properties_near($lat, $lng, $radiuskm, $number = STAYPRESS_PROPERTY_PER_PAGE ) {

		$point = array(	'lat' => $lat,
						'lng' => $lng
						);

		$bounds = $this->property->getBoundingBox($point, $radiuskm + 10, 'km');
		list($lat1, $lat2, $lon1, $lon2) = $bounds;
		$tright = array( 'lat' => $lat2, 'lng' => $lon2);
		$bleft = array( 'lat' => $lat1, 'lng' => $lon1);

		return $this->property->get_withinrangeandbounds( $point, $tright, $bleft, $radiuskm, 'km', 0, $number, true, false );

	}

	function get_properties_near_property($id, $radiuskm, $number = STAYPRESS_PROPERTY_PER_PAGE ) {

		$property = $this->property->get_property($id, true, false);

		if(!empty($property)) {
			$lat = $property->latitude;
			$lng = $property->longitude;

			$point = array(	'lat' => $lat,
							'lng' => $lng
							);

			$bounds = $this->property->getBoundingBox($point, $radiuskm + 10, 'km');

			list($lat1, $lat2, $lon1, $lon2) = $bounds;
			$tright = array( 'lat' => $lat2, 'lng' => $lon2);
			$bleft = array( 'lat' => $lat1, 'lng' => $lon1);

			return $this->property->get_withinrangeandbounds( $point, $tright, $bleft,$radiuskm, 'km', 0, $number, true, false );

		} else {
			return false;
		}

	}

	function widget( $args, $instance ) {

		global $wpdb;

		extract( $args );

		$defaults = array(
			'title'			=>	'',
			'neartype'		=>	'define',
			'location'		=> 	'',
			'distancekm'	=>	'50',
			'shownumber'	=>	'25'
		);

		foreach($defaults as $key => $value) {
			if(isset($instance[$key])) {
				$defaults[$key] = $instance[$key];
			}
		}

		extract($defaults);

		$this->property =& new property_model($wpdb, false);

		switch($neartype) {
			case 'define':		if(defined('STAYPRESS_ON_PROPERTY_PAGE')) $property_id = (int) STAYPRESS_ON_PROPERTY_PAGE;
								$properties = $this->get_properties_near_property($property_id, $distancekm, $shownumber );
								break;

			case 'propertyid':	$property_id = (int) $location;
								$properties = $this->get_properties_near_property($property_id, $distancekm, $shownumber );
								break;

			case 'latlng':		if(!empty($location)) {
									$property_id = false;
									$coords = explode(',', $location);
									if(count($coords) == 2) {
										$properties = $this->get_properties_near($coords[0], $coords[1], $distancekm, $shownumber );
									}
								}
								break;
		}

		if(!empty($properties)) {

			echo $before_widget;
			$title = apply_filters('widget_title', $title );
			if ( !empty($title) ) {
				echo $before_title . $title . $after_title;
			}

			$count = 1;

			echo "<ul class='propertythumbnaillist'>";
			foreach( (array) $properties as $key => $property ) {
				if($property_id !== false && $property_id == $property->ID) {
					unset($properties[$key]);
					continue;
				}

				if(has_post_thumbnail( $property->ID )) {

					echo "<li class='propertythumbnail'>";
					echo "<div class='nearcount'>" . $count . "</div>";
					echo "<a href='" . sp_get_permalink( 'property', $property ) . "'>";
					// The excerpt
					echo "<div class='theexcerpt'>";
					echo esc_html($property->post_excerpt);
					echo "</div>";
					// The image
					echo get_the_post_thumbnail( $property->ID );
					echo "<h4>" . esc_html($property->post_title) . "</h4>";
					echo "</a>";
					echo "</li>";

					$count++;

				} else {
					unset($properties[$key]);
				}

			}
			echo "</ul> <!-- propertythumbnaillist -->";

			echo $after_widget;

			$this->enqueue_propertynear_data($properties);

		}

	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$defaults = array(
			'title'			=>	'',
			'neartype'		=>	'define',
			'location'		=> 	'',
			'distancekm'	=>	'50',
			'shownumber'	=>	'25'
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {

		$defaults = array(
			'title'			=>	'',
			'neartype'		=>	'define',
			'location'		=> 	'',
			'distancekm'	=>	'50',
			'shownumber'	=>	'25'
		);

		$instance = wp_parse_args( (array) $instance, $defaults );

		?>
			<p>
				<?php _e('Title:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'title' ); ?>' id='<?php echo $this->get_field_id( 'title' ); ?>' value='<?php echo esc_attr(stripslashes($instance['title'])); ?>' />
			</p>
			<p>
				<?php _e('Location to use:','property'); ?><br/>
				<select name='<?php echo $this->get_field_name( 'neartype' ); ?>' id='<?php echo $this->get_field_id( 'neartype' ); ?>'>
					<option value='define' <?php if(esc_attr(stripslashes($instance['neartype'])) == 'define') echo "selected='selected'"; ?>><?php _e('Automatic','property'); ?></option>
					<option value='propertyid' <?php if(esc_attr(stripslashes($instance['neartype'])) == 'propertyid') echo "selected='selected'"; ?>><?php _e('Property ID','property'); ?></option>
					<option value='latlng' <?php if(esc_attr(stripslashes($instance['neartype'])) == 'latlng') echo "selected='selected'"; ?>><?php _e('Co-ordinates','property'); ?></option>
				</select>
			</p>
			<p>
				<?php _e('ID / Location:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'location' ); ?>' id='<?php echo $this->get_field_id( 'location' ); ?>' value='<?php echo esc_attr(stripslashes($instance['location'])); ?>' />
				<br/><em><?php _e('enter the property id or location in the format lat,lng','property'); ?></em>
			</p>
			<p>
				<?php _e('Radius (km):','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'distancekm' ); ?>' id='<?php echo $this->get_field_id( 'distancekm' ); ?>' value='<?php echo esc_attr(stripslashes($instance['distancekm'])); ?>' />
				<br/><em><?php _e('enter the radius in km that you want the list to cover.','property'); ?></em>
			</p>
			<p>
				<?php _e('Number to show:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'shownumber' ); ?>' id='<?php echo $this->get_field_id( 'shownumber' ); ?>' value='<?php echo esc_attr(stripslashes($instance['shownumber'])); ?>' />
			</p>

			<p>&nbsp;</p>
	<?php
	}
}

class sp_propertiesbyowner extends WP_Widget {

	var $build = 1;
	var $property;
	var $propertyoptions;

	function sp_propertiesbyowner() {

		// Load the text-domain
		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPPCommon::property_dir( "includes/lang/property-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'property', $mofile );

		$widget_ops = array( 'classname' => 'sp_propertiesbyowner', 'description' => __('StayPress By Owner List', 'property') );
		$control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'sp_propertiesbyowner');
		$this->WP_Widget( 'sp_propertiesbyowner', __('StayPress By Owner List', 'property'), $widget_ops, $control_ops );

		add_action('init', array(&$this, 'enqueue_page_headings'));
	}

	function enqueue_page_headings() {

		// Only need these on the public side of the site
		if(is_admin()) {
			return;
		}
		// Check if we are actually being used anywhere
		if(is_active_widget(false, false, 'sp_propertiesbyowner')) {
			// we are an active widget so add in our styles and js
			wp_enqueue_style('nearbywidgetcss', SPPCommon::property_url('css/property.nearbywidget.css'), array(), $this->build);
		}

	}

	function enqueue_propertynear_data( $posts ) {

		global $wp_query;
		//print_r($wp_query);

		static $alphabet = array("-","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		static $propertydata = array();
		static $count = 1;

		$defaultoptions = array( 	'propertytext'			=> 	'property',
									'propertiestext'		=>	'properties',
									'permalinkhasid'		=>	'yes',
									'firstelement'			=>	'reference',
									'propertytitlelayout'	=>	'%title%',
									'propertytitlemarker'	=>	'numeric'
								);

		$this->propertyoptions = SPPCommon::get_option('sp_property_options', $defaultoptions);

		foreach(array_slice( (array) $posts, 0, 19) as $postkey => $post) {
			if(!empty($post->latitude)) {
				$key = $count++;

				$propertydata[$key] = array(		'ID'			=> 	$post->ID,
													'post_title' 	=> 	$post->post_title,
													'post_excerpt' 	=> 	$post->post_excerpt,
													'latitude'		=> 	$post->latitude,
													'longitude'		=>	$post->longitude,
													'permalink'		=>	sp_get_permalink( 'property', $post )
												);


				if($this->enhancetitle) {
					$title = $post->post_title;
					if(empty($this->propertyoptions['propertytitlelayout'])) $this->propertyoptions['propertytitlelayout'] = '%title%';
					$posts[$postkey]->post_title = str_replace('%title%', $title, $this->propertyoptions['propertytitlelayout']);
					$posts[$postkey]->post_title = str_replace('%listmarker%', $key, $posts[$postkey]->post_title);
				}


			}
		}

		if(!empty($propertydata)) {
			SPPCommon::enqueue_data('staypress_data', 'propertylist_near', $propertydata);
		}

		return $posts;
	}

	function get_properties_owned_by($property_id, $shownumber ) {

		$property = get_post($property_id);

		if(!empty($property)) {
			$author_id = $property->post_author;
			if(!empty($author_id)) {
				return $this->get_properties_with_owner_id($author_id, $shownumber);
			}
		} else {
			return false;
		}

	}

	function get_properties_with_owner_id($owner_id, $shownumber ) {

		global $wpdb;

		$sql = $wpdb->prepare( "SELECT p.ID, p.post_title, p.post_excerpt, p.post_name, sp.latitude, sp.longitude FROM {$wpdb->posts} AS p LEFT JOIN {$this->property->property} AS sp ON p.ID = sp.post_id WHERE post_author = %d AND post_type = %s AND post_status = %s ORDER BY post_modified DESC LIMIT 0, %d", $owner_id, 'property', 'publish', $shownumber );

		$results = $wpdb->get_results( $sql );

		if(!empty($results)) {
			return $results;
		} else {
			return false;
		}

	}

	function get_properties_with_owner_name($ownername, $shownumber ) {

		if(!empty($ownername)) {
			$theuser = new WP_User( $ownername );
			if(!empty($theuser) && !is_wp_error($theuser)) {
				return $this->get_properties_with_owner_id($theuser->ID, $shownumber);
			}
		} else {
			return false;
		}


	}

	function widget( $args, $instance ) {

		global $wpdb;

		extract( $args );

		$defaults = array(
			'title'			=>	'',
			'ownertype'		=>	'define',
			'owner'		=> 	'',
			'shownumber'	=>	'25'
		);

		foreach($defaults as $key => $value) {
			if(isset($instance[$key])) {
				$defaults[$key] = $instance[$key];
			}
		}

		extract($defaults);

		$this->property =& new property_model($wpdb, false);

		switch($ownertype) {
			case 'define':		if(defined('STAYPRESS_ON_PROPERTY_PAGE')) $property_id = (int) STAYPRESS_ON_PROPERTY_PAGE;
								$properties = $this->get_properties_owned_by($property_id, $shownumber );
								break;

			case 'ownerid':		$owner_id = (int) $owner;
								$properties = $this->get_properties_with_owner_id($owner_id, $shownumber );
								break;

			case 'ownername':	$ownername = $owner;
								$properties = $this->get_properties_with_owner_name($ownername, $shownumber );
								break;
		}

		if(!empty($properties)) {

			echo $before_widget;
			$title = apply_filters('widget_title', $title );
			if ( !empty($title) ) {
				echo $before_title . $title . $after_title;
			}

			$count = 1;

			echo "<ul class='propertythumbnaillist'>";
			foreach( (array) $properties as $key => $property ) {
				if($property_id !== false && $property_id == $property->ID) {
					unset($properties[$key]);
					continue;
				}

				if(has_post_thumbnail( $property->ID )) {

					echo "<li class='propertythumbnail'>";
					echo "<div class='nearcount'>" . $count . "</div>";
					echo "<a href='" . sp_get_permalink( 'property', $property ) . "'>";
					// The excerpt
					echo "<div class='theexcerpt'>";
					echo esc_html($property->post_excerpt);
					echo "</div>";
					// The image
					echo get_the_post_thumbnail( $property->ID );
					echo "<h4>" . esc_html($property->post_title) . "</h4>";
					echo "</a>";
					echo "</li>";

					$count++;

				} else {
					unset($properties[$key]);
				}

			}
			echo "</ul> <!-- propertythumbnaillist -->";

			echo $after_widget;

			$this->enqueue_propertynear_data($properties);

		}

	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$defaults = array(
			'title'			=>	'',
			'ownertype'		=>	'define',
			'owner'		=> 	'',
			'shownumber'	=>	'25'
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {

		$defaults = array(
			'title'			=>	'',
			'ownertype'		=>	'define',
			'owner'		=> 	'',
			'shownumber'	=>	'25'
		);

		$instance = wp_parse_args( (array) $instance, $defaults );

		?>
			<p>
				<?php _e('Title:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'title' ); ?>' id='<?php echo $this->get_field_id( 'title' ); ?>' value='<?php echo esc_attr(stripslashes($instance['title'])); ?>' />
			</p>
			<p>
				<?php _e('Owner to list:','property'); ?><br/>
				<select name='<?php echo $this->get_field_name( 'ownertype' ); ?>' id='<?php echo $this->get_field_id( 'ownertype' ); ?>'>
					<option value='define' <?php if(esc_attr(stripslashes($instance['ownertype'])) == 'define') echo "selected='selected'"; ?>><?php _e('Automatic','property'); ?></option>
					<option value='ownerid' <?php if(esc_attr(stripslashes($instance['ownertype'])) == 'ownerid') echo "selected='selected'"; ?>><?php _e('User ID','property'); ?></option>
					<option value='ownername' <?php if(esc_attr(stripslashes($instance['ownertype'])) == 'ownername') echo "selected='selected'"; ?>><?php _e('Username','property'); ?></option>
				</select>
			</p>
			<p>
				<?php _e('Owner:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'owner' ); ?>' id='<?php echo $this->get_field_id( 'owner' ); ?>' value='<?php echo esc_attr(stripslashes($instance['owner'])); ?>' />
				<br/><em><?php _e('enter the user id or username if you have not chosen automatic above','property'); ?></em>
			</p>
			<p>
				<?php _e('Number to show:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'shownumber' ); ?>' id='<?php echo $this->get_field_id( 'shownumber' ); ?>' value='<?php echo esc_attr(stripslashes($instance['shownumber'])); ?>' />
			</p>

			<p>&nbsp;</p>
	<?php
	}
}

class sp_propertquicksearch extends WP_Widget {

	var $build = 1;
	var $property;
	var $propertyoptions;

	function sp_propertquicksearch() {

		// Load the text-domain
		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPPCommon::property_dir( "includes/lang/property-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'property', $mofile );

		$widget_ops = array( 'classname' => 'sp_propertquicksearch', 'description' => __('StayPress Quick Search', 'property') );
		$control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'sp_propertquicksearch');
		$this->WP_Widget( 'sp_propertquicksearch', __('StayPress Quick Search', 'property'), $widget_ops, $control_ops );

		add_action('init', array(&$this, 'enqueue_page_headings'));

	}

	function enqueue_page_headings() {

		// Only need these on the public side of the site
		if(is_admin()) {
			return;
		}

		// Load default options
		$defaultoptions = array( 	'propertytext'			=> 	'property',
									'propertiestext'		=>	'properties',
									'permalinkhasid'		=>	'yes',
									'firstelement'			=>	'reference',
									'propertytitlelayout'	=>	'%title%',
									'propertytitlemarker'	=>	'numeric',
									'propertysearchtext'	=>	'Search for...'
								);

		$this->propertyoptions = SPPCommon::get_option('sp_property_options', $defaultoptions);

		// Check if we are actually being used anywhere
		if(is_active_widget(false, false, 'sp_propertquicksearch')) {
			// we are an active widget so add in our styles and js
			wp_enqueue_style('quicksearchcss', SPPCommon::property_url('css/property.quicksearchwidget.css'), array(), $this->build);
			wp_enqueue_script('quicksearchjs', SPPCommon::property_url('js/property.quicksearchwidget.js'), array(), $this->build);
			wp_localize_script('quicksearchjs', 'quicksearchwidget', array(	"searchtext"	=>	__($this->propertyoptions['propertysearchtext'],'property')) );
		}

	}

	function make_url($thestring) {

		return str_replace(' ','-', strtolower($thestring));

	}

	function widget( $args, $instance ) {

		extract( $args );

		$defaults = array(
			'title'			=>	'',
			'extended'		=>	'yes',
			'buttontext'	=>	'Go'
		);

		foreach($defaults as $key => $value) {
			if(isset($instance[$key])) {
				$defaults[$key] = $instance[$key];
			}
		}

		extract($defaults);

		echo $before_widget;
		$title = apply_filters('widget_title', $title );
		if ( !empty($title) ) {
			echo $before_title . $title . $after_title;
		}

		echo "<form action='" . sp_get_permalink('search') . "' method='get' class='sp_quicksearchwidgetform'>";
		echo "<input type='hidden' name='searchmadeby' value='quicksearchwidget' />";
		echo "<input type='text' name='sp_quicksearchfor' class='sp_quicksearchfor' value='" . __($this->propertyoptions['propertysearchtext'],'property') . "' />";
		echo "<input type='submit' name='sp_quicksearch' class='sp_quicksearch' value='" . __($buttontext,'property') . "' />";

		if($extended == 'yes') {
			echo apply_filters( 'staypress_preextend_widget_search_form', $html, 'sp_quickavail_');
			echo apply_filters( 'staypress_extend_widget_search_form', $html, 'sp_quickavail_');
		}

		echo "</form>";

		echo $after_widget;

	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$defaults = array(
			'title'			=>	'',
			'extended'		=>	'yes',
			'buttontext'	=>	'Go'
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {

		$defaults = array(
			'title'			=>	'',
			'extended'		=>	'yes',
			'buttontext'	=>	'Go'
		);

		$instance = wp_parse_args( (array) $instance, $defaults );

		?>
			<p>
				<?php _e('Title:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'title' ); ?>' id='<?php echo $this->get_field_id( 'title' ); ?>' value='<?php echo esc_attr(stripslashes($instance['title'])); ?>' />
			</p>
			<p>
				<?php _e('Extended form:','property'); ?><br/>
				<?php
				echo '<ul>';
				echo "<li><input type='radio' name='" . $this->get_field_name( 'extended' ) . "' value='yes' ";
				if($instance['extended'] == 'yes') echo " checked='checked'";
				echo " />&nbsp;";
				echo __('Search can be extended by plugins','property');
				echo '</li>';
				echo "<li><input type='radio' name='" . $this->get_field_name( 'extended' ) . "' value='no' ";
				if($instance['extended'] == 'no') echo " checked='checked'";
				echo " />&nbsp;";
				echo __('Search can not be extended','property');
				echo '</li>';
				echo '</ul>';
				?>
			</p>
			<p>
				<?php _e('Button text:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'buttontext' ); ?>' id='<?php echo $this->get_field_id( 'buttontext' ); ?>' value='<?php echo esc_attr(stripslashes($instance['buttontext'])); ?>' />
			</p>
			<p>&nbsp;</p>
	<?php
	}
}

class sp_propertyownerdetails extends WP_Widget {

	var $build = 1;
	var $property;

	function sp_propertyownerdetails() {

		// Load the text-domain
		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPPCommon::property_dir( "includes/lang/property-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'property', $mofile );

		$widget_ops = array( 'classname' => 'sp_propertyownerdetails', 'description' => __('StayPress Contact Details', 'property') );
		$control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'sp_propertyownerdetails');
		$this->WP_Widget( 'sp_propertyownerdetails', __('StayPress Contact Details', 'property'), $widget_ops, $control_ops );

		add_action('init', array(&$this, 'enqueue_page_headings'));

	}

	function enqueue_page_headings() {

		// Only need these on the public side of the site
		if(is_admin()) {
			return;
		}

		// Check if we are actually being used anywhere
		if(is_active_widget(false, false, 'sp_propertyownerdetails')) {
			// we are an active widget so add in our styles and js
			wp_enqueue_style('ownerdetailscss', SPPCommon::property_url('css/property.ownerdetails.css'), array(), $this->build);
		}

	}

	function widget( $args, $instance ) {

		global $wpdb;

		extract( $args );

		$defaults = array(
			'title'			=>	'',
			'showname'		=>	'no',
			'showtelephone'	=>	'no',
			'showemail'		=>	'no',
			'shownotes'		=>	'no',
			'linktoenquiry'	=>	'no',
			'uselink'		=>	'',
			'parsetype'		=>	'define',
			'property_id'	=>	'',
			'buttontext'	=>	__('Send Enquiry', 'property'),
			'showgravatar'	=>	'no'
		);

		foreach($defaults as $key => $value) {
			if(isset($instance[$key])) {
				$defaults[$key] = $instance[$key];
			}
		}

		extract($defaults);

		$this->property =& new property_model($wpdb, false);

		// get the property id
		switch( $instance['parsetype'] ) {
			case 'define':	if(defined('STAYPRESS_ON_PROPERTY_PAGE')) $property_id = (int) STAYPRESS_ON_PROPERTY_PAGE;
							break;
			case 'below':	$property_id = (int) $instance['property_id'];
							break;

			case 'url':		$uri = $_SERVER['REQUEST_URI'];
							$number = (int) $instance['property_id'];
							//
							$urisplit = explode("/",$uri);
							if( is_numeric($number) && count($urisplit) > (int) $number ) {
								$property_id = $urisplit[$number];
							} else {
								$property_id = false;
							}
							break;

			case 'querystring':
							$property_id = (int) $_GET[$instance['property_id']];
							break;
		}

		$contacts = $this->property->public_get_propertycontacts( $property_id );

		if(!empty($contacts)) {
			echo $before_widget;
			$title = apply_filters('widget_title', $title );
			if ( !empty($title) ) {
				echo $before_title . $title . $after_title;
			}

			$contact = array_shift($contacts);
			$contactmetadata = get_post_custom($contact->ID);
			$tel = array_shift($contactmetadata['contact_tel']);
			$email = array_shift($contactmetadata['contact_email']);


			echo "<ul class='contactdetails'>";

			if($showgravatar == 'yes' && !empty($email)) {
				echo "<li>";
				echo "<img class='contactgravatar' src='";
				echo "http://www.gravatar.com/avatar/";
				echo md5( strtolower( trim( $email ) ) );
				echo "?s=80&d=mm&r=g";
				echo "' alt='' />";
				echo "</li>";
			}

			if($showname == 'yes') {
				echo "<li class='contactname'>" . esc_html($contact->post_title) . "</li>";
			}
			if($showtelephone == 'yes') {
				echo "<li class='contacttel'>" . esc_html($tel) . "</li>";
			}
			if($showemail == 'yes') {
				echo "<li class='contactemail'><a href='mailto:" . esc_attr($email) . "'>" . esc_html($email) . "</a></li>";
			}
			if($shownotes == 'yes') {
				echo "<li class='contactemail'>" . esc_html($contact->post_content) . "</li>";
			}

			if($linktoenquiry == 'yes') {
				echo "<li class='contactenquirylink'>";
				echo "<a href='";
				if(!empty($uselink)) {
					echo esc_attr($uselink);
				} else {
					$post = get_post($property_id);
					echo sp_get_permalink( 'property', $post, '#' . __('propertyenquiry','property'));
				}
				echo "' class='button'>";
				echo esc_html($buttontext);
				echo "</a>";
				echo "</li>";
			}

			echo "</ul>";

			echo $after_widget;
		}
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$defaults = array(
			'title'			=>	'',
			'showname'		=>	'no',
			'showtelephone'	=>	'no',
			'showemail'		=>	'no',
			'shownotes'		=>	'no',
			'linktoenquiry'	=>	'no',
			'uselink'		=>	'',
			'parsetype'		=>	'define',
			'property_id'	=>	'',
			'buttontext'	=>	__('Send Enquiry', 'property'),
			'showgravatar'	=>	'no'
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {

		$defaults = array(
			'title'			=>	'',
			'showname'		=>	'no',
			'showtelephone'	=>	'no',
			'showemail'		=>	'no',
			'shownotes'		=>	'no',
			'linktoenquiry'	=>	'no',
			'uselink'		=>	'',
			'parsetype'		=>	'define',
			'property_id'	=>	'',
			'buttontext'	=>	__('Send Enquiry', 'property'),
			'showgravatar'	=>	'no'
		);

		$instance = wp_parse_args( (array) $instance, $defaults );

		?>
			<p>
				<?php _e('Title:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'title' ); ?>' id='<?php echo $this->get_field_id( 'title' ); ?>' value='<?php echo esc_attr(stripslashes($instance['title'])); ?>' />
			</p>
			<p>
				<?php _e('Get property details from:','booking'); ?><br/>
				<select name='<?php echo $this->get_field_name( 'parsetype' ); ?>' id='<?php echo $this->get_field_id( 'parsetype' ); ?>'>
					<option value='define' <?php if($instance['parsetype'] == 'define') echo "selected='selected'"; ?>><?php echo __('Automatic','booking'); ?></option>
					<option value='below' <?php if($instance['parsetype'] == 'below') echo "selected='selected'"; ?>><?php echo __('Setting below','booking'); ?></option>
					<option value='url' <?php if($instance['parsetype'] == 'url') echo "selected='selected'"; ?>><?php echo __('Parse URL','booking'); ?></option>
					<option value='querystring' <?php if($instance['parsetype'] == 'querystring') echo "selected='selected'"; ?>><?php echo __('Querystring','booking'); ?></option>
				</select>
			</p>
			<p>
				<?php _e('Property ID:','booking'); ?><br/><small><?php _e('or url segment / querystring','booking'); ?></small><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'property_id' ); ?>' id='<?php echo $this->get_field_id( 'property_id' ); ?>' value='<?php echo esc_attr(stripslashes($instance['property_id'])); ?>' />
			</p>
			<p>
				<?php _e('Show information:','property'); ?><br/>
				<input type='checkbox' name='<?php echo $this->get_field_name( 'showname' ); ?>' id='<?php echo $this->get_field_id( 'showname' ); ?>' value='yes' <?php if($instance['showname'] == 'yes') echo " checked='checked'"; ?> />&nbsp;<?php _e('Contact Name','property'); ?>
				<br/>
				<input type='checkbox' name='<?php echo $this->get_field_name( 'showtelephone' ); ?>' id='<?php echo $this->get_field_id( 'showtelephone' ); ?>' value='yes' <?php if($instance['showtelephone'] == 'yes') echo " checked='checked'"; ?> />&nbsp;<?php _e('Contact Telephone','property'); ?>
				<br/>
				<input type='checkbox' name='<?php echo $this->get_field_name( 'showemail' ); ?>' id='<?php echo $this->get_field_id( 'showemail' ); ?>' value='yes' <?php if($instance['showemail'] == 'yes') echo " checked='checked'"; ?> />&nbsp;<?php _e('Contact Email','property'); ?>
				<br/>
				<input type='checkbox' name='<?php echo $this->get_field_name( 'shownotes' ); ?>' id='<?php echo $this->get_field_id( 'shownotes' ); ?>' value='yes' <?php if($instance['shownotes'] == 'yes') echo " checked='checked'"; ?> />&nbsp;<?php _e('Contact Notes','property'); ?>
				<br/>
				<input type='checkbox' name='<?php echo $this->get_field_name( 'showgravatar' ); ?>' id='<?php echo $this->get_field_id( 'showgravatar' ); ?>' value='yes' <?php if($instance['showgravatar'] == 'yes') echo " checked='checked'"; ?> />&nbsp;<?php _e('Show Gravatar','property'); ?>
				<br/>
				<input type='checkbox' name='<?php echo $this->get_field_name( 'linktoenquiry' ); ?>' id='<?php echo $this->get_field_id( 'linktoenquiry' ); ?>' value='yes' <?php if($instance['linktoenquiry'] == 'yes') echo " checked='checked'"; ?> />&nbsp;<?php _e('Link to Contact Form','property'); ?>
				<br/>

			</p>
			<p>
				<?php _e('Custom Enquiry Link:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'uselink' ); ?>' id='<?php echo $this->get_field_id( 'uselink' ); ?>' value='<?php echo esc_attr(stripslashes($instance['uselink'])); ?>' /><br/>
				<em><?php _e('Leave blank to use the default link.','property'); ?></em>
			</p>
			<p>
				<?php _e('Button Text:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'buttontext' ); ?>' id='<?php echo $this->get_field_id( 'buttontext' ); ?>' value='<?php echo esc_attr(stripslashes($instance['buttontext'])); ?>' />
			</p>
			<p>&nbsp;</p>
	<?php
	}
}

class sp_propertadvsearch extends WP_Widget {

	var $build = 1;
	var $property;
	var $propertyoptions;

	function sp_propertadvsearch() {

		// Load the text-domain
		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPPCommon::property_dir( "includes/lang/property-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'property', $mofile );

		$widget_ops = array( 'classname' => 'sp_propertadvsearch', 'description' => __('StayPress Advanced Search', 'property') );
		$control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'sp_propertadvsearch');
		$this->WP_Widget( 'sp_propertadvsearch', __('StayPress Advanced Search', 'property'), $widget_ops, $control_ops );

		add_action('init', array(&$this, 'enqueue_page_headings'));

	}

	function enqueue_page_headings() {

		// Only need these on the public side of the site
		if(is_admin()) {
			return;
		}

		// Load default options
		$defaultoptions = array( 	'propertytext'			=> 	'property',
									'propertiestext'		=>	'properties',
									'permalinkhasid'		=>	'yes',
									'firstelement'			=>	'reference',
									'propertytitlelayout'	=>	'%title%',
									'propertytitlemarker'	=>	'numeric',
									'propertysearchtext'	=>	'Search for...'
								);

		$this->propertyoptions = SPPCommon::get_option('sp_property_options', $defaultoptions);

		// Check if we are actually being used anywhere
		if(is_active_widget(false, false, 'sp_propertadvsearch')) {
			// we are an active widget so add in our styles and js
			wp_enqueue_style('advsearchcss', SPPCommon::property_url('css/property.advsearchwidget.css'), array(), $this->build);
			wp_enqueue_script('advsearchjs', SPPCommon::property_url('js/property.advsearchwidget.js'), array(), $this->build);
			wp_localize_script('advsearchjs', 'advsearchwidget', array(	"searchtext"	=>	__($this->propertyoptions['propertysearchtext'],'property')) );
		}

	}

	function make_url($thestring) {

		return str_replace(' ','-', strtolower($thestring));

	}

	function show_meta_searchform( $passedmeta = array(), $showheadings = 'no' ) {

		$metas = $this->property->get_metadesc();

		if(isset($_GET['meta'])) {
			$searched = (array) $_GET['meta'];
		} else {
			$searched = array();
		}

		$groupitems = array();
		$groupheading = array();

		if($metas) {
			$groupid = false;
			foreach($metas as $meta) {

				if(!in_array($meta->id, $passedmeta)) {
					continue;
				}

				$ihtml = '';
				if($groupid != $meta->metagroup_id) {
					$groupid = $meta->metagroup_id;
					$groupitems[$groupid] = array();
					$groupheading[$groupid] = "<li><div class='metagroupname'>" . __($meta->groupname,'property') . "</div></li>";
				}
				$ihtml .= "<li>";
				$ihtml .= "<div class='metaname'>" . __($meta->metaname,'property') . "</div>";

				switch($meta->metatype) {
					case '1':	// Numeric
								$ihtml .= "<input type='text' name='meta[" . $meta->id . "]' id='' class='metavalue numeric' value='";
								if(!empty($searched[$meta->id])) $ihtml .= esc_attr(stripslashes($searched[$meta->id]));
								$ihtml .= "' />";
								break;
					case '2':	// Text
								$ihtml .= "<input type='text' name='meta[" . $meta->id . "]' id='' class='metavalue text' value='";
								if(!empty($searched[$meta->id])) $ihtml .= esc_attr(stripslashes($searched[$meta->id]));
								$ihtml .= "' />";
								break;
					case '3':	// Yes / No
								$ihtml .= "<select name='meta[" . $meta->id . "]' id='' class='metavalue'>";
								$ihtml .= "<option value=''";
								if(!empty($searched[$meta->id]) && $searched[$meta->id] == '') $ihtml .= " selected='selected'";
								$ihtml .= "></option>";
								$ihtml .= "<option value='yes'";
								if(!empty($searched[$meta->id]) && $searched[$meta->id] == 'yes') $ihtml .= " selected='selected'";
								$ihtml .= ">" . __('Yes');
								$ihtml .= "</option>";
								$ihtml .= "<option value='no'";
								if(!empty($searched[$meta->id]) && $searched[$meta->id] == 'no') $ihtml .= " selected='selected'";
								$ihtml .= ">" . __('No');
								$ihtml .= "</option>";
								$ihtml .= "</select>";
								break;
					case '4':	// Option
								$options = explode("\n", $meta->metaoptions);
								if(!empty($options)) {
									$ihtml .= "<select name='meta[" . $meta->id . "]' id='' class='metavalue'>";
									$ihtml .= "<option value=''";
									if(!empty($searched[$meta->id]) && $searched[$meta->id] == '') $ihtml .= " selected='selected'";
									$ihtml .= "></option>";
									foreach($options as $opt) {
										if(!empty($opt)) {
											$ihtml .= "<option value='" . esc_attr(trim($opt)) . "'";
											if(!empty($searched[$meta->id]) && $searched[$meta->id] == esc_attr(trim($opt))) $ihtml .= " selected='selected'";
											$ihtml .= ">" . esc_html(trim($opt));
											$ihtml .= "</option>";
										}
									}
									$ihtml .= "</select>";
								} else {
									$ihtml .= "<input type='text' name='meta[" . $meta->id . "]' id='' class='metavalue text' value='";
									if(!empty($searched[$meta->id])) $ihtml .= esc_attr(stripslashes($searched[$meta->id]));
									$ihtml .= "' />";
								}
								break;
				}
				$ihtml .= "</li>";

				if(!empty($ihtml)) {
					$groupitems[$groupid][] = $ihtml;
				}

			}

			$html = '';
			foreach($groupitems as $key => $group) {
				if(!empty($group)) {
					if($showheadings == 'yes') {
						$html .= $groupheading[$key];
					}
					$html .= implode("\n", $group);
				}
			}

			if(!empty($html)) {
				$html = "<ul>" . $html . "</ul>";
			}
		}

		return $html;

	}

	function widget( $args, $instance ) {

		global $wpdb;

		$this->property =& new property_model($wpdb, false);

		extract( $args );

		$defaults = array(
			'title'			=>	'',
			'extended'		=>	'yes',
			'buttontext'	=>	'Go',
			'showsearchbox'	=>	'yes',
			'showheadings'	=>	'no',
			'meta'			=>	array()
		);

		foreach($defaults as $key => $value) {
			if(isset($instance[$key])) {
				$defaults[$key] = $instance[$key];
			}
		}

		extract($defaults);

		echo $before_widget;
		$title = apply_filters('widget_title', $title );
		if ( !empty($title) ) {
			echo $before_title . $title . $after_title;
		}

		echo "<form action='" . sp_get_permalink('search') . "' method='get' class='sp_advsearchwidgetform'>";
		echo "<input type='hidden' name='searchmadeby' value='advsearchwidget' />";
		echo "<input type='text' name='sp_advsearchfor' class='sp_advsearchfor' value='" . __($this->propertyoptions['propertysearchtext'],'property') . "' />";
		echo "<input type='submit' name='sp_advsearch' class='sp_advsearch' value='" . __($buttontext,'property') . "' />";

		if($extended == 'yes') {
			echo apply_filters( 'staypress_preextend_widget_advsearch_form', $html, 'sp_adv');
			echo $this->show_meta_searchform($meta, $showheadings);
			echo apply_filters( 'staypress_extend_widget_advsearch_form', $html, 'sp_adv');
		}

		echo "</form>";

		echo $after_widget;

	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$defaults = array(
			'title'			=>	'',
			'extended'		=>	'yes',
			'buttontext'	=>	'Go',
			'showsearchbox'	=>	'yes',
			'showheadings'	=>	'no',
			'meta'			=>	array()
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {

		global $wpdb;

		$this->property =& new property_model($wpdb, false);

		$defaults = array(
			'title'			=>	'',
			'extended'		=>	'yes',
			'buttontext'	=>	'Go',
			'showsearchbox'	=>	'yes',
			'showheadings'	=>	'no',
			'meta'			=>	array()
		);

		$instance = wp_parse_args( (array) $instance, $defaults );

		?>
			<p>
				<?php _e('Title:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'title' ); ?>' id='<?php echo $this->get_field_id( 'title' ); ?>' value='<?php echo esc_attr(stripslashes($instance['title'])); ?>' />
			</p>
			<p>
				<?php _e('Include facilities:','property'); ?><br/>
				<?php
					$metas = $this->property->get_metadesc();
					//print_r($metas);
					$lastgroupid = false;
					foreach($metas as $meta) {
						if($lastgroupid != $meta->metagroup_id) {
							if($lastgroupid !== false) {
								echo "</ul><br/>";
							}
							echo "<strong>" . __($meta->groupname,'property') . "</strong>";
							echo '<ul>';
							$lastgroupid = $meta->metagroup_id;
						}
						echo "<li><input type='checkbox' name='" . $this->get_field_name( 'meta' ) . "[]' value='" . $meta->id . "' ";
						if(in_array($meta->id, (array) $instance['meta'])) echo " checked='checked'";
						echo " />&nbsp;";
						echo __($meta->metaname,'property');
						echo '</li>';
					}
					if($lastgroupid !== false) {
						echo "</ul><br/>";
					}
				?>
			</p>
			<p>
				<?php
				echo "<input type='checkbox' name='" . $this->get_field_name( 'showheadings' ) . "' value='yes' ";
				if($instance['showheadings'] == 'yes') echo " checked='checked'";
				echo " />&nbsp;";
				echo __('Show Meta Group Headings','property');
				?>
			</p>
			<p>
				<?php _e('Extended form:','property'); ?><br/>
				<?php
				echo '<ul>';
				echo "<li><input type='radio' name='" . $this->get_field_name( 'extended' ) . "' value='yes' ";
				if($instance['extended'] == 'yes') echo " checked='checked'";
				echo " />&nbsp;";
				echo __('Search can be extended by plugins','property');
				echo '</li>';
				echo "<li><input type='radio' name='" . $this->get_field_name( 'extended' ) . "' value='no' ";
				if($instance['extended'] == 'no') echo " checked='checked'";
				echo " />&nbsp;";
				echo __('Search can not be extended','property');
				echo '</li>';
				echo '</ul>';
				?>
			</p>
			<p>
				<?php _e('Button text:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'buttontext' ); ?>' id='<?php echo $this->get_field_id( 'buttontext' ); ?>' value='<?php echo esc_attr(stripslashes($instance['buttontext'])); ?>' />
			</p>
			<p>&nbsp;</p>
	<?php
	}
}

class sp_recentproperties extends WP_Widget {

	var $build = 1;
	var $property;
	var $propertyoptions;

	function sp_recentproperties() {

		// Load the text-domain
		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPPCommon::property_dir( "includes/lang/property-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'property', $mofile );

		$widget_ops = array( 'classname' => 'sp_recentproperties', 'description' => __('StayPress Recent Properties', 'property') );
		$control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'sp_recentproperties');
		$this->WP_Widget( 'sp_recentproperties', __('StayPress Recent Properties', 'property'), $widget_ops, $control_ops );

		add_action('init', array(&$this, 'enqueue_page_headings'));
	}

	function enqueue_page_headings() {

		// Only need these on the public side of the site
		if(is_admin()) {
			return;
		}
		// Check if we are actually being used anywhere
		if(is_active_widget(false, false, 'sp_propertiesbyowner')) {
			// we are an active widget so add in our styles and js
			wp_enqueue_style('nearbywidgetcss', SPPCommon::property_url('css/property.nearbywidget.css'), array(), $this->build);
		}

	}

	function enqueue_propertynear_data( $posts ) {

		global $wp_query;
		//print_r($wp_query);

		static $alphabet = array("-","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		static $propertydata = array();
		static $count = 1;

		$defaultoptions = array( 	'propertytext'			=> 	'property',
									'propertiestext'		=>	'properties',
									'permalinkhasid'		=>	'yes',
									'firstelement'			=>	'reference',
									'propertytitlelayout'	=>	'%title%',
									'propertytitlemarker'	=>	'numeric'
								);

		$this->propertyoptions = SPPCommon::get_option('sp_property_options', $defaultoptions);

		foreach(array_slice( (array) $posts, 0, 19) as $postkey => $post) {
			if(!empty($post->latitude)) {
				$key = $count++;

				$propertydata[$key] = array(		'ID'			=> 	$post->ID,
													'post_title' 	=> 	$post->post_title,
													'post_excerpt' 	=> 	$post->post_excerpt,
													'latitude'		=> 	$post->latitude,
													'longitude'		=>	$post->longitude,
													'permalink'		=>	sp_get_permalink( 'property', $post )
												);


				if($this->enhancetitle) {
					$title = $post->post_title;
					if(empty($this->propertyoptions['propertytitlelayout'])) $this->propertyoptions['propertytitlelayout'] = '%title%';
					$posts[$postkey]->post_title = str_replace('%title%', $title, $this->propertyoptions['propertytitlelayout']);
					$posts[$postkey]->post_title = str_replace('%listmarker%', $key, $posts[$postkey]->post_title);
				}


			}
		}

		if(!empty($propertydata)) {
			SPPCommon::enqueue_data('staypress_data', 'propertylist_near', $propertydata);
		}

		return $posts;
	}

	function get_properties_owned_by($property_id, $shownumber ) {

		$property = get_post($property_id);

		if(!empty($property)) {
			$author_id = $property->post_author;
			if(!empty($author_id)) {
				return $this->get_properties_with_owner_id($author_id, $shownumber);
			}
		} else {
			return false;
		}

	}

	function get_properties_with_owner_id($owner_id, $shownumber ) {

		global $wpdb;

		$sql = $wpdb->prepare( "SELECT p.ID, p.post_title, p.post_excerpt, p.post_name, sp.latitude, sp.longitude FROM {$wpdb->posts} AS p LEFT JOIN {$this->property->property} AS sp ON p.ID = sp.post_id WHERE post_author = %d AND post_type = %s AND post_status = %s ORDER BY post_modified DESC", $owner_id, 'property', 'publish' );

		$results = $wpdb->get_results( $sql );

		if(!empty($results)) {
			return $results;
		} else {
			return false;
		}

	}

	function get_properties_with_owner_name($ownername, $shownumber ) {

		if(!empty($ownername)) {
			$theuser = new WP_User( $ownername );
			if(!empty($theuser) && !is_wp_error($theuser)) {
				return $this->get_properties_with_owner_id($theuser->ID, $shownumber);
			}
		} else {
			return false;
		}


	}

	function widget( $args, $instance ) {

		global $wpdb;

		extract( $args );

		$defaults = array(
			'title'			=>	'',
			'ownertype'		=>	'define',
			'owner'		=> 	'',
			'shownumber'	=>	'25'
		);

		foreach($defaults as $key => $value) {
			if(isset($instance[$key])) {
				$defaults[$key] = $instance[$key];
			}
		}

		extract($defaults);

		$this->property =& new property_model($wpdb, false);

		switch($ownertype) {
			case 'define':		if(defined('STAYPRESS_ON_PROPERTY_PAGE')) $property_id = (int) STAYPRESS_ON_PROPERTY_PAGE;
								$properties = $this->get_properties_owned_by($property_id, $shownumber );
								break;

			case 'ownerid':		$owner_id = (int) $owner;
								$properties = $this->get_properties_with_owner_id($owner_id, $shownumber );
								break;

			case 'ownername':	$ownername = $owner;
								$properties = $this->get_properties_with_owner_name($ownername, $shownumber );
								break;
		}

		if(!empty($properties)) {

			echo $before_widget;
			$title = apply_filters('widget_title', $title );
			if ( !empty($title) ) {
				echo $before_title . $title . $after_title;
			}

			$count = 1;

			echo "<ul class='propertythumbnaillist'>";
			foreach( (array) $properties as $key => $property ) {
				if($property_id !== false && $property_id == $property->ID) {
					unset($properties[$key]);
					continue;
				}

				if(has_post_thumbnail( $property->ID )) {

					echo "<li class='propertythumbnail'>";
					echo "<div class='nearcount'>" . $count . "</div>";
					echo "<a href='" . sp_get_permalink( 'property', $property ) . "'>";
					// The excerpt
					echo "<div class='theexcerpt'>";
					echo esc_html($property->post_excerpt);
					echo "</div>";
					// The image
					echo get_the_post_thumbnail( $property->ID );
					echo "<h4>" . esc_html($property->post_title) . "</h4>";
					echo "</a>";
					echo "</li>";

					$count++;

				} else {
					unset($properties[$key]);
				}

			}
			echo "</ul> <!-- propertythumbnaillist -->";

			echo $after_widget;

			$this->enqueue_propertynear_data($properties);

		}

	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$defaults = array(
			'title'			=>	'',
			'ownertype'		=>	'define',
			'owner'		=> 	'',
			'shownumber'	=>	'25'
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {

		$defaults = array(
			'title'			=>	'',
			'ownertype'		=>	'define',
			'owner'		=> 	'',
			'shownumber'	=>	'25'
		);

		$instance = wp_parse_args( (array) $instance, $defaults );

		?>
			<p>
				<?php _e('Title:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'title' ); ?>' id='<?php echo $this->get_field_id( 'title' ); ?>' value='<?php echo esc_attr(stripslashes($instance['title'])); ?>' />
			</p>
			<p>
				<?php _e('Owner to list:','property'); ?><br/>
				<select name='<?php echo $this->get_field_name( 'ownertype' ); ?>' id='<?php echo $this->get_field_id( 'ownertype' ); ?>'>
					<option value='define' <?php if(esc_attr(stripslashes($instance['ownertype'])) == 'define') echo "selected='selected'"; ?>><?php _e('Automatic','property'); ?></option>
					<option value='ownerid' <?php if(esc_attr(stripslashes($instance['ownertype'])) == 'ownerid') echo "selected='selected'"; ?>><?php _e('User ID','property'); ?></option>
					<option value='ownername' <?php if(esc_attr(stripslashes($instance['ownertype'])) == 'ownername') echo "selected='selected'"; ?>><?php _e('Username','property'); ?></option>
				</select>
			</p>
			<p>
				<?php _e('Owner:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'owner' ); ?>' id='<?php echo $this->get_field_id( 'owner' ); ?>' value='<?php echo esc_attr(stripslashes($instance['location'])); ?>' />
				<br/><em><?php _e('enter the user id or username if you have not chosen automatic above','property'); ?></em>
			</p>
			<p>
				<?php _e('Number to show:','property'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'shownumber' ); ?>' id='<?php echo $this->get_field_id( 'shownumber' ); ?>' value='<?php echo esc_attr(stripslashes($instance['shownumber'])); ?>' />
			</p>

			<p>&nbsp;</p>
	<?php
	}
}


function sp_propertywidgets_register() {
	register_widget( 'sp_propertylistingmap' );
	register_widget( 'sp_drilldownwidget' );
	register_widget( 'sp_taglist' );
	register_widget( 'sp_propertiesnear' );
	register_widget( 'sp_propertiesbyowner' );
	register_widget( 'sp_propertquicksearch' );
	register_widget( 'sp_propertyownerdetails' );
	register_widget( 'sp_propertadvsearch' );
}

add_action( 'widgets_init', 'sp_propertywidgets_register' );

?>