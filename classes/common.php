<?php
// Common functions and plugin updates classes - primarily to prevent any function name clashes.
class SPPCommon {

	private static $SP_property_url;
	private static $SP_property_dir;

	private static $SP_data_queue = array();

	public static function set_property_url($base) {

		if(defined('WPMU_PLUGIN_URL') && defined('WPMU_PLUGIN_DIR') && file_exists(WPMU_PLUGIN_DIR . '/' . basename($base))) {
			self::$SP_property_url = trailingslashit(WPMU_PLUGIN_URL);
		} elseif(defined('WP_PLUGIN_URL') && defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/staypressproperty/' . basename($base))) {
			self::$SP_property_url = trailingslashit(WP_PLUGIN_URL . '/staypressproperty');
		} else {
			self::$SP_property_url = trailingslashit(WP_PLUGIN_URL . '/staypressproperty');
		}

	}

	// Sets the property plugin directory
	public static function set_property_dir($base) {

		if(defined('WPMU_PLUGIN_DIR') && file_exists(WPMU_PLUGIN_DIR . '/' . basename($base))) {
			self::$SP_property_dir = trailingslashit(WPMU_PLUGIN_URL);
		} elseif(defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/staypressproperty/' . basename($base))) {
			self::$SP_property_dir = trailingslashit(WP_PLUGIN_DIR . '/staypressproperty');
		} else {
			self::$SP_property_dir = trailingslashit(WP_PLUGIN_DIR . '/staypressproperty');
		}
	}

	// Returns the property plugin url
	public static function property_url($extended) {
		return self::$SP_property_url . $extended;
	}

	public static function propertyaddons_url($extended) {
		return self::property_url( 'addons/' . $extended);
	}

	// Returns the property plugin directory
	public static function property_dir($extended) {
		return self::$SP_property_dir . $extended;
	}

	public static function propertyaddons_dir($extended) {
		return self::property_dir( 'addons/' . $extended);
	}

	// Gets the options for the plugin depending on whether we are running in enterprise or local mode.
	public static function get_option($option, $default = false) {

		if(defined('SP_ONMS')) {
			return get_site_option($option, $default);
		} else {
			return get_option($option, $default);
		}
	}

	// Updates the options for the plugin depending on whether we are running in enterprise or local mode.
	public static function update_option($option, $newvalue) {
		if(defined('SP_ONMS')) {
			return update_site_option($option, $newvalue);
		} else {
			return update_option($option, $newvalue);
		}
	}

	public static function delete_option($option) {
		if(defined('SP_ONMS')) {
			return delete_site_option($option);
		} else {
			return delete_option($option);
		}
	}

	public static function load_property_addons() {

		$plugins =  self::_get_property_plugins( );

		if(!empty($plugins)) {
			foreach($plugins as $file => $plugin) {
				include_once( self::propertyaddons_dir( $file ) );
			}
		}

	}

	private static function _get_property_plugins($plugin_folder = 'addons') {

		if ( ! $cache_plugins = wp_cache_get('property_plugins', 'property_plugins') )
			$cache_plugins = array();

		if ( isset($cache_plugins[ $plugin_folder ]) )
			return $cache_plugins[ $plugin_folder ];

		$sp_plugins = array ();
		$plugin_root = self::property_dir( $plugin_folder );

		$plugins_dir = @ opendir( $plugin_root);
		$plugin_files = array();
		if ( $plugins_dir ) {
			while (($file = readdir( $plugins_dir ) ) !== false ) {
				if ( substr($file, 0, 1) == '.' )
					continue;
				if ( is_dir( $plugin_root.'/'.$file ) ) {
					$plugins_subdir = @ opendir( $plugin_root.'/'.$file );
					if ( $plugins_subdir ) {
						while (($subfile = readdir( $plugins_subdir ) ) !== false ) {
							if ( substr($subfile, 0, 1) == '.' )
								continue;
							if ( substr($subfile, -4) == '.php' )
								$plugin_files[] = "$file/$subfile";
						}
					}
				} else {
					if ( substr($file, -4) == '.php' )
						$plugin_files[] = $file;
				}
			}
		} else {
			return $sp_plugins;
		}

		@closedir( $plugins_dir );
		@closedir( $plugins_subdir );

		if ( empty($plugin_files) )
			return $sp_plugins;

		foreach ( $plugin_files as $plugin_file ) {
			if ( !is_readable( "$plugin_root/$plugin_file" ) )
				continue;

			$plugin_data = self::_get_property_plugin_data( "$plugin_root/$plugin_file", false, false ); //Do not apply markup/translate as it'll be cached.

			if ( empty ( $plugin_data['Name'] ) )
				continue;

			$sp_plugins[plugin_basename( $plugin_file )] = $plugin_data;
		}

		uasort( $sp_plugins, create_function( '$a, $b', 'return strnatcasecmp( $a["Name"], $b["Name"] );' ));

		$cache_plugins[ $plugin_folder ] = $sp_plugins;
		wp_cache_set('property_plugins', $cache_plugins, 'property_plugins');

		return $sp_plugins;
	}

	private static function _get_property_plugin_data( $plugin_file, $markup = true, $translate = true ) {

		$default_headers = array(
			'Name' => 'Plugin Name',
			'PluginURI' => 'Plugin URI',
			'Version' => 'Version',
			'Description' => 'Description',
			'Author' => 'Author',
			'AuthorURI' => 'Author URI',
			'TextDomain' => 'Text Domain',
			'DomainPath' => 'Domain Path',
			'Network' => 'Network',
			// Site Wide Only is deprecated in favor of Network.
			'_sitewide' => 'Site Wide Only',
		);

		$plugin_data = get_file_data( $plugin_file, $default_headers, 'plugin' );

		return $plugin_data;
	}

	// JSON and data based functions
	public static function enqueue_data( $namespace, $key, $data ) {

		self::$SP_data_queue[$namespace][$key] = self::_build_data($data);

	}

	private static function _encode_json($results) {
		if(function_exists('json_encode')) {
			return json_encode($results);
		} else {
			// PHP4 version
			require_once(ABSPATH."wp-includes/js/tinymce/plugins/spellchecker/classes/utils/JSON.php");
			$json_obj = new Moxiecode_JSON();
			return $json_obj->encode($results);
		}
	}

	private static function _build_data($results) {

		$data = array();

		if(is_array($results)) {
			return self::_encode_json($results);
		} else {
			if(!is_numeric($results)) {
				$results = '"' . mysql_real_escape_string($results) . '"';
			}
			return $results;
		}

	}

	public static function print_data() {

		if(!empty(self::$SP_data_queue)) {
			echo "\n" . '<script type="text/javascript">';
			echo "\n" . '/* <![CDATA[ */ ' . "\n";

			foreach(self::$SP_data_queue as $key => $data) {

				if(!empty($data)) {
						echo "var " . esc_attr($key) . " = {\n";
						$firstone = true;


						foreach($data as $vkey => $vdata) {
							if(!$firstone) {
								echo ",\n";
							}
							echo esc_attr($vkey) . " : " . $vdata;
							$firstone = false;
						}

						echo "\n};\n";
				}

			}

			echo "\n" . '/* ]]> */ ';
			echo '</script>';
		}

	}

}

?>