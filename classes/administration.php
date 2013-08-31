<?php

class sp_propertyadmin {

	var $build = 1;

	var $property;

	//
	var $propertypages = array();
	var $showpropertynumber = STAYPRESS_ADMIN_PROPERTY_PER_PAGE;

	var $mylocation = "";
	var $plugindir = "";
	var $base_uri = '';

	var $get_option = '';
	var $update_option = '';

	var $urloptions = array();

	var $defaultpricecolumns = array();

	// If there is an error, then we need to override the normal add or edit form with our error correcting one
	// Might be a bit of a hack, but it's the only thing I can think of at the moment.
	var $error_override_edit_form = false;

	function __construct() {

		global $wpdb, $user;

		$installed_build = SPPCommon::get_option('staypress_property_build', false);

		if($installed_build === false) {
			$installed_build = $this->build;
			// Create the property class and force table creation
			$this->property = new property_model($wpdb, false);
			SPPCommon::update_option('staypress_property_build', $installed_build);
		} else {
			// Create the property class and send through installed build version
			$this->property = new property_model($wpdb, $installed_build);
		}

		$tz = get_option('gmt_offset');
		$this->property->set_timezone($tz);

		add_action( 'plugins_loaded', array(&$this, 'load_textdomain'));

		add_action( 'init', array( &$this, 'initialise_property' ) );
		add_action( 'init', array( &$this, 'initialise_propertyadmin_ajax' ) );

		// Add admin menus
		add_action( 'admin_menu', array( &$this, 'add_admin_menu' ), 1 );

		// Header actions
		add_action('load-toplevel_page_property', array(&$this, 'add_admin_header_property'));
		add_action('load-properties_page_property-add', array(&$this, 'add_admin_header_property_add'));
		add_action('load-properties_page_property-facilities', array(&$this, 'add_admin_header_property_facilities'));
		add_action('load-properties_page_property-tags', array(&$this, 'add_admin_header_property_tags'));
		add_action('load-properties_page_property-pricing', array(&$this, 'add_admin_header_property_pricing'));
		add_action('load-properties_page_property-options', array(&$this, 'add_admin_header_property_options'));

		add_action('load-properties_page_property-permalinks', array(&$this, 'add_admin_header_property_permalinks'));
		add_action('load-properties_page_property-pages', array(&$this, 'add_admin_header_property_pages'));

		// Right hand menus
		add_action( 'staypress_rightpanel_page_property', array( &$this, 'handle_property_rightpanel' ) );
		add_action( 'staypress_rightpanel_page_property-add', array( &$this, 'show_propertyadd_rightpanel' ) );

		// Edit and Add property forms items
		add_action( 'staypress_propertyedit_form_extras', array(&$this, 'show_property_facilities' ), 2, 3 );
		add_action( 'staypress_propertyedit_form_extras', array(&$this, 'show_property_prices' ), 3, 3 );
		add_action( 'staypress_propertyedit_form_extras', array(&$this, 'show_property_contact' ), 6, 3 );

		// Favourite actions
		add_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_standard'));
		add_action( 'admin_bar_menu', array(&$this, 'add_wp_admin_menu_actions'), 45 );

		do_action ( 'staypress_modify_property_plugin_actions' );

		//Translations
		add_filter( 'gettext', array(&$this, 'replace_text'), 10, 3);

		// Helper filters
		add_filter( 'property_get_details', array(&$this, 'filter_property_get_details') );
		add_filter( 'property_get_list', array(&$this, 'filter_property_get_list') );

		// Rewrites
		add_action('generate_rewrite_rules', array(&$this, 'add_rewrite'));
		add_filter('query_vars', array(&$this, 'add_queryvars'));

		// Queue actions
		add_action( 'staypress_property_added', array('SPQueue', 'queue_operation'), 10, 3 );
		add_action( 'staypress_property_updated', array('SPQueue', 'queue_operation'), 10, 3 );
		add_action( 'staypress_property_deleted', array('SPQueue', 'queue_operation'), 10, 3 );

	}

	function __destruct() {

	}

	function sp_propertyadmin() {
		$this->__construct();
	}

	function load_textdomain() {

		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPPCommon::property_dir( "includes/lang/property-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'property', $mofile );

	}

	function add_queryvars($vars) {
		// This function add the namespace (if it hasn't already been added) and the
		// eventperiod queryvars to the list that WordPress is looking for.
		// Note: Namespace provides a means to do a quick check to see if we should be doing anything

		if(!in_array('namespace',$vars)) $vars[] = 'namespace';

		$vars[] = 'reference';
		$vars[] = 'search';
		$vars[] = 'with';
		$vars[] = 'paged';
		$vars[] = 'dest';
		$vars[] = 'near';
		$vars[] = 'type';
		$vars[] = 'area';
		$vars[] = 'fromstamp';
		$vars[] = 'tostamp';
		$vars[]	= 'propertyid';

		return $vars;
	}

	function add_rewrite($wp_rewrite ) {
	  	// This function adds in the api rewrite rules
		// Note the addition of the namespace variable so that we know these are vent based
		// calls

		$page_prefix = SPPCommon::get_option('property_rewrite_prefix', '');
		if(!empty($page_prefix)) $page_prefix = trailingslashit($page_prefix);

		$defaultoptions = array( 	'propertytext'		=> 	'property',
									'propertiestext'	=>	'properties',
									'listingmethod'		=>	'permalink',
									'permalinkhasid'	=>	'yes',
									'firstelement'		=>	'reference'
								);

		$propertyoptions = SPPCommon::get_option('sp_property_options', $defaultoptions);

		if($propertyoptions['listingmethod'] == 'permalink') {

			$defaultoptions = array( 	'permalinkhasid'		=>	'no',
										'firstelement'			=>	'slug',
										'propertyurl'			=>	__('property', 'property'),
										'propertylisturl'		=>	__('properties', 'property'),
										'propertytagurl'		=>	__('with','property'),
										'propertydesturl'		=>	__('in','property'),
										'propertynearurl'		=>	__('near','property'),
										'propertyavailurl'		=>	__('available','property'),
										'propertysearchurl'		=>	__('search','property'),
										'propertymapurl'		=>	__('map','property')
									);

			$permaoptions = SPPCommon::get_option('sp_property_permalink_options', $defaultoptions);

			$new_rules = array( $permaoptions['propertylisturl'] . '/page/?([0-9]{1,})/?$' => 'index.php?namespace=staypress&paged=' . $wp_rewrite->preg_index(1) . '&type=list',
								$permaoptions['propertylisturl'] . '$' => 'index.php?namespace=staypress&type=list', 	// plugin list

								$page_prefix . $permaoptions['propertysearchurl'] . '/(.+)/page/?([0-9]{1,})' => 'index.php?namespace=staypress&search=' . $wp_rewrite->preg_index(1) . '&paged=' . $wp_rewrite->preg_index(2) . '&type=search',
								$page_prefix . $permaoptions['propertysearchurl'] . '/(.+)' => 'index.php?namespace=staypress&search=' . $wp_rewrite->preg_index(1) . '&type=search',
								$page_prefix . $permaoptions['propertysearchurl'] . '' => 'index.php?namespace=staypress&type=search',

								$page_prefix . $permaoptions['propertytagurl'] . '/(.+)/page/?([0-9]{1,})' => 'index.php?namespace=staypress&with=' . $wp_rewrite->preg_index(1) . '&paged=' . $wp_rewrite->preg_index(2) . '&type=tag',
								$page_prefix . $permaoptions['propertytagurl'] . '/(.+)' => 'index.php?namespace=staypress&with=' . $wp_rewrite->preg_index(1) . '&type=tag',

								$page_prefix . $permaoptions['propertydesturl'] . '/(.+)/page/?([0-9]{1,})' => 'index.php?namespace=staypress&dest=' . $wp_rewrite->preg_index(1) . '&paged=' . $wp_rewrite->preg_index(2) . '&type=dest',
								$page_prefix . $permaoptions['propertydesturl'] . '/(.+)' => 'index.php?namespace=staypress&dest=' . $wp_rewrite->preg_index(1) . '&type=dest',

								$page_prefix . $permaoptions['propertynearurl'] . '/(.+)/page/?([0-9]{1,})' => 'index.php?namespace=staypress&near=' . $wp_rewrite->preg_index(1) . '&paged=' . $wp_rewrite->preg_index(2) . '&type=near',
								$page_prefix . $permaoptions['propertynearurl'] . '/(.+)' => 'index.php?namespace=staypress&near=' . $wp_rewrite->preg_index(1) . '&type=near',

								$page_prefix . $permaoptions['propertyavailurl'] . '/(.+)/(.+)/page/?([0-9]{1,})' => 'index.php?namespace=staypress&fromstamp=' . $wp_rewrite->preg_index(1) . '&tostamp=' . $wp_rewrite->preg_index(2) . '&paged=' . $wp_rewrite->preg_index(3) . '&type=available',
								$page_prefix . $permaoptions['propertyavailurl'] . '/(.+)/(.+)' => 'index.php?namespace=staypress&fromstamp=' . $wp_rewrite->preg_index(1) . '&tostamp=' . $wp_rewrite->preg_index(2) . '&type=available',

								$page_prefix . $permaoptions['propertymapurl'] . '/(.+)' => 'index.php?namespace=staypress&near=' . $wp_rewrite->preg_index(1) . '&type=map'

							);

			if(empty($propertyoptions['permalinkhasid']) || $propertyoptions['permalinkhasid'] == 'yes') {
				$new_rules[$permaoptions['propertyurl'] . '/([0-9]{1,})/(.+)'] = 'index.php?namespace=staypress&p=' . $wp_rewrite->preg_index(1) . '&type=property';
			} else {
				if(empty($propertyoptions['firstelement']) || $propertyoptions['firstelement'] == 'reference') {
					$new_rules[$permaoptions['propertyurl'] . '/(.+)'] = 'index.php?namespace=staypress&reference=' . $wp_rewrite->preg_index(1) . '&type=property';
				} else {
					$new_rules[$permaoptions['propertyurl'] . '/(.+)'] = 'index.php?namespace=staypress&name=' . $wp_rewrite->preg_index(1) . '&type=property';
				}
			}

		} else {

			$defaultoptions = array( 	'permalinkhasid'		=>	'no',
										'firstelement'			=>	'slug',
										'propertylistpage'		=>	'',
										'propertytagpage'		=>	'',
										'propertydestpage'		=>	'',
										'propertynearpage'		=>	'',
										'propertyavailpage'		=>	'',
										'propertysearchpage'	=>	'',
										'propertymappage'		=>	'',
										'propertypage'			=>	''
									);

			$pageoptions = SPPCommon::get_option('sp_property_page_options', $defaultoptions);

			$new_rules = array();

			foreach( $pageoptions as $key => $value ) {

				if(empty($value)) continue;

				switch( $key ) {

					case 'propertypage':			$propertypage = untrailingslashit( get_permalink( $value ) );
													$propertypage = str_replace( trailingslashit( get_option('home') ), '', $propertypage );

													if(empty($pageoptions['permalinkhasid']) || $pageoptions['permalinkhasid'] == 'yes') {
														$new_rules[$propertypage . '/([0-9]{1,})/(.+)'] = 'index.php?namespace=staypress&propertyid=' . $wp_rewrite->preg_index(1) . '&type=property&pagename=' . $propertypage;
													} else {
														if(empty($pageoptions['firstelement']) || $pageoptions['firstelement'] == 'reference') {
															$new_rules[$propertypage . '/(.+)'] = 'index.php?namespace=staypress&reference=' . $wp_rewrite->preg_index(1) . '&type=property&pagename=' . $propertypage;
														} else {
															$new_rules[$propertypage . '/(.+)'] = 'index.php?namespace=staypress&title=' . $wp_rewrite->preg_index(1) . '&type=property&pagename=' . $propertypage;
														}
													}
													break;

					case 'propertylistpage':		$propertylistpage = untrailingslashit( get_permalink( $value ) );
													$propertylistpage = str_replace( trailingslashit( get_option('home') ), '', $propertylistpage );

													$new_rules[$propertylistpage . '/page/?([0-9]{1,})/?$'] = 'index.php?namespace=staypress&paged=' . $wp_rewrite->preg_index(1) . '&type=list&pagename=' . $propertylistpage;
													$new_rules[$propertylistpage . '$'] = 'index.php?namespace=staypress&type=list&pagename=' . $propertylistpage;
													break;

					case 'propertytagpage':			$propertytagpage = untrailingslashit( get_permalink( $value ) );
													$propertytagpage = str_replace( trailingslashit( get_option('home') ), '', $propertytagpage );

													$new_rules[$propertytagpage . '/(.+)/page/?([0-9]{1,})'] = 'index.php?namespace=staypress&with=' . $wp_rewrite->preg_index(1) . '&paged=' . $wp_rewrite->preg_index(2) . '&type=tag&pagename=' . $propertytagpage;
													$new_rules[$propertytagpage . '/(.+)'] = 'index.php?namespace=staypress&with=' . $wp_rewrite->preg_index(1) . '&type=tag&pagename=' . $propertytagpage;
													break;

					case 'propertydestpage':		$propertydestpage = untrailingslashit( get_permalink( $value ) );
													$propertydestpage = str_replace( trailingslashit( get_option('home') ), '', $propertydestpage );

													$new_rules[$propertydestpage . '/(.+)/page/?([0-9]{1,})'] = 'index.php?namespace=staypress&dest=' . $wp_rewrite->preg_index(1) . '&paged=' . $wp_rewrite->preg_index(2) . '&type=dest&pagename=' . $propertydestpage;
													$new_rules[$propertydestpage . '/(.+)'] = 'index.php?namespace=staypress&dest=' . $wp_rewrite->preg_index(1) . '&type=dest&pagename=' . $propertydestpage;
													break;

					case 'propertynearpage':		$propertynearpage = untrailingslashit( get_permalink( $value ) );
													$propertynearpage = str_replace( trailingslashit( get_option('home') ), '', $propertynearpage );

													$new_rules[$propertynearpage . '/(.+)/page/?([0-9]{1,})'] = 'index.php?namespace=staypress&near=' . $wp_rewrite->preg_index(1) . '&paged=' . $wp_rewrite->preg_index(2) . '&type=near&pagename=' . $propertynearpagea;
													$new_rules[$propertynearpage . '/(.+)'] = 'index.php?namespace=staypress&near=' . $wp_rewrite->preg_index(1) . '&type=near&pagename=' . $propertynearpage;
													break;

					case 'propertyavailpage':		$propertyavailpage = untrailingslashit( get_permalink( $value ) );
													$propertyavailpage = str_replace( trailingslashit( get_option('home') ), '', $propertyavailpage );

													$new_rules[$a . '/(.+)/(.+)/page/?([0-9]{1,})'] = 'index.php?namespace=staypress&fromstamp=' . $wp_rewrite->preg_index(1) . '&tostamp=' . $wp_rewrite->preg_index(2) . '&paged=' . $wp_rewrite->preg_index(3) . '&type=available&pagename=' . $propertyavailpage;
													$new_rules[$propertyavailpage . '/(.+)/(.+)'] = 'index.php?namespace=staypress&fromstamp=' . $wp_rewrite->preg_index(1) . '&tostamp=' . $wp_rewrite->preg_index(2) . '&type=available&pagename=' . $propertyavailpage;
													break;

					case 'propertysearchpage':		$propertysearchpage = untrailingslashit( get_permalink( $value ) );
													$propertysearchpage = str_replace( trailingslashit( get_option('home') ), '', $propertysearchpage );

													$new_rules[$propertysearchpage . '/(.+)/page/?([0-9]{1,})'] = 'index.php?namespace=staypress&search=' . $wp_rewrite->preg_index(1) . '&paged=' . $wp_rewrite->preg_index(2) . '&type=search&pagename=' . $propertysearchpage;
													$new_rules[$propertysearchpage . '/(.+)'] = 'index.php?namespace=staypress&search=' . $wp_rewrite->preg_index(1) . '&type=search&pagename=' . $propertysearchpage;
													$new_rules[$propertysearchpage . ''] = 'index.php?namespace=staypress&type=search&pagename=' . $propertysearchpage;
													break;

					case 'propertymappage':			$propertymappage = untrailingslashit( get_permalink( $value ) );
													$propertymappage = str_replace( trailingslashit( get_option('home') ), '', $propertymappage );

													$new_rules[$propertymappage . '/(.+)'] = 'index.php?namespace=staypress&near=' . $wp_rewrite->preg_index(1) . '&type=map&pagename=' . $propertymappage;
													break;

				}

			}

		}

		$new_rules = apply_filters('staypress_api_rules', $new_rules);

	  	$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;

		return $wp_rewrite;

	}

	// Headers
	function remove_header_notices() {
		if(has_action('admin_notices')) {
			remove_all_actions( 'admin_notices' );
		}
	}


	function add_admin_header_core() {

		global $action, $page;

		// Grab any action or page variables
		wp_reset_vars(array('action','page'));

		// Set up the models user
		$user = wp_get_current_user();
		$user_ID = $user->ID;

		$this->property->set_userid($user_ID);

		// Needed for all pages
		wp_enqueue_style('propertyadmincss', SPPCommon::property_url('css/defaultadministration.css'), array(), $this->build);

		// remove admin_notices
		$this->remove_header_notices();

		// For extension
		do_action( 'staypress_property_admin_header_core' );

	}

	function add_admin_header_property() {

		global $action, $page;

		$this->add_admin_header_core();

		if(($action == 'edit')) {
			// For editing a property - need full scripts
			wp_enqueue_script('wp-lists');
			wp_enqueue_script('propertyeditaddjs', SPPCommon::property_url('js/editadd.js'), array('jquery', 'jquery-form', 'jquery-ui-droppable', 'jquery-ui-sortable', 'suggest'), $this->build);

			wp_localize_script( 'propertyeditaddjs', 'property', array( 'deleteimagetitle' => __('Delete this image','property'),
																		'deleteimage' => __('Are you sure you want to delete this image?','property'),
																		'copyprices' => __('Are you sure you want to copy these prices?','property'),
																		'deleteprices' => __('Are you sure you want to delete these prices?','property'),
																		'deletepriceperiod' => __('Are you sure you want to delete this price?','property'),
																		'maperror' => __('There was a problem getting your location details please try later.','property'),
																		'pricerowdeletetitle' => __('Delete this row of prices', 'property'),
																		'priceperioddeletetitle' => __('Remove this price', 'property')
																	) );

			if ( user_can_richedit()) {
				wp_enqueue_script('editor');
			}
			add_action( 'admin_head', array( &$this, 'output_admin_header' ) );

			// Reset the favourite_actions filter
			remove_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_standard'));
			add_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_propertylist'));

			// For extension
			do_action( 'staypress_property_admin_header_edit' );

		} else {
			// For property lists
			wp_enqueue_script( 'propertyadminjs', SPPCommon::property_url('js/administration.js'), array('jquery'), $this->build );
			wp_localize_script( 'propertyadminjs', 'property', array( 	'deleteproperty' => __('Are you sure you want to delete this property?','property'),
																		'deletefacilitygroup' => __('Are you sure you want to delete this group AND all linked facilities?','property'),
																		'deletefacility' => __('Are you sure you want to delete this facility?','property'),
																		'deletepropertynonce' => wp_create_nonce('ajaxdeleteproperty')
																	) );

			// Reset the favourite_actions filter
			remove_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_standard'));
			add_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_newproperty'));

			// For extension
			do_action( 'staypress_property_admin_header_property' );

		}

		$this->process_update_property();

	}

	function add_admin_header_property_add() {
		$this->add_admin_header_core();

		wp_enqueue_script('wp-lists');
		wp_enqueue_script('propertyeditaddjs', SPPCommon::property_url('js/editadd.js'), array('jquery', 'jquery-form', 'jquery-ui-droppable', 'jquery-ui-sortable', 'suggest'), $this->build);

		wp_localize_script( 'propertyeditaddjs', 'property', array( 'deleteimagetitle' => __('Delete this image','property'),
																	'deleteimage' => __('Are you sure you want to delete this image?','property'),
																	'copyprices' => __('Are you sure you want to copy these prices?','property'),
																	'deleteprices' => __('Are you sure you want to delete these prices?','property'),
																	'deletepriceperiod' => __('Are you sure you want to delete this price?','property'),
																	'maperror' => __('There was a problem getting your location details please try later.','property'),
																	'pricerowdeletetitle' => __('Delete this row of prices', 'property'),
																	'priceperioddeletetitle' => __('Remove this price', 'property')
																) );


		if ( user_can_richedit()) {
			wp_enqueue_script('editor');
		}

		add_action( 'admin_head', array( &$this, 'output_admin_header' ) );

		// Reset the favourite_actions filter
		remove_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_standard'));
		add_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_propertylist'));

		// For extension
		do_action( 'staypress_property_admin_header_add' );

		$this->process_add_property();

	}

	function add_admin_header_property_tags() {
		$this->add_admin_header_core();

		wp_enqueue_script('wp-ajax-response');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('admin-tags');
		wp_enqueue_script('jquery-form');

		$this->update_property_tags();
	}

	function add_admin_header_property_facilities() {
		$this->add_admin_header_core();

		wp_enqueue_script('propertyadminjs', SPPCommon::property_url('js/administration.js'), array('jquery'), $this->build);
		wp_localize_script( 'propertyadminjs', 'property', array( 	'deleteproperty' => __('Are you sure you want to delete this property?','property'),
																	'deletefacilitygroup' => __('Are you sure you want to delete this group AND all linked facilities?','property'),
																	'deletefacility' => __('Are you sure you want to delete this facility?','property'),
																	'deletepropertynonce' => wp_create_nonce('ajaxdeleteproperty')
																) );

		$this->update_property_facilities();
	}

	function add_admin_header_property_pricing() {
		$this->add_admin_header_core();

		$this->update_property_pricing();
	}

	function add_admin_header_property_options() {
		$this->add_admin_header_core();

		wp_enqueue_script('propertyadminjs', SPPCommon::property_url('js/administration.js'), array('jquery'), $this->build);

		// For extension
		do_action( 'staypress_property_admin_header_options' );

		$this->update_property_options();
	}

	function add_admin_header_property_permalinks() {
		$this->add_admin_header_core();

		wp_enqueue_script('propertyadminjs', SPPCommon::property_url('js/administration.js'), array('jquery'), $this->build);

		// For extension
		do_action( 'staypress_property_admin_header_permalinks' );

		$this->update_property_permalinks();
	}

	function add_admin_header_property_pages() {
		$this->add_admin_header_core();

		wp_enqueue_script('propertyadminjs', SPPCommon::property_url('js/administration.js'), array('jquery'), $this->build);

		// For extension
		do_action( 'staypress_property_admin_header_pages' );

		$this->update_property_pages();
	}

	// for tinymce and google
	function output_admin_header() {

		//if (function_exists('wp_tiny_mce')) wp_tiny_mce();

	}

	function add_reference_required_field( $rules ) {

		$rules['reference'] = 'required';

		return $rules;
	}

	function initialise_property() {

		// Assign the user id to the property model
		$user = wp_get_current_user();
		$this->property->set_userid($user->ID);

		// Set permissions if they haven't already been set
		$role = get_role( 'contributor' );
		if( !$role->has_cap( 'read_destination' ) ) {
			// Properties
			$role->add_cap( 'read_property' );
			$role->add_cap( 'edit_property' );
			$role->add_cap( 'delete_property' );
			// Needed so they can upload files - well duh
			$role->add_cap( 'upload_files' );
			// Author
			$role = get_role( 'author' );
			$role->add_cap( 'read_property' );
			$role->add_cap( 'edit_property' );
			$role->add_cap( 'delete_property' );
			$role->add_cap( 'publish_properties' );
			// Editor
			$role = get_role( 'editor' );
			$role->add_cap( 'read_property' );
			$role->add_cap( 'edit_property' );
			$role->add_cap( 'delete_property' );
			$role->add_cap( 'publish_properties' );
			$role->add_cap( 'edit_properties' );
			$role->add_cap( 'edit_others_properties' );
			// Administrator
			$role = get_role( 'administrator' );
			$role->add_cap( 'read_property' );
			$role->add_cap( 'edit_property' );
			$role->add_cap( 'delete_property' );
			$role->add_cap( 'publish_properties' );
			$role->add_cap( 'edit_properties' );
			$role->add_cap( 'edit_others_properties' );

			// Contacts
			$role = get_role( 'contributor' );
			$role->add_cap( 'read_contact' );
			$role->add_cap( 'edit_contact' );
			$role->add_cap( 'delete_contact' );
			// Author
			$role = get_role( 'author' );
			$role->add_cap( 'read_contact' );
			$role->add_cap( 'edit_contact' );
			$role->add_cap( 'delete_contact' );
			$role->add_cap( 'publish_contacts' );
			// Editor
			$role = get_role( 'editor' );
			$role->add_cap( 'read_contact' );
			$role->add_cap( 'edit_contact' );
			$role->add_cap( 'delete_contact' );
			$role->add_cap( 'publish_contacts' );
			$role->add_cap( 'edit_contacts' );
			$role->add_cap( 'edit_others_contacts' );
			// Administrator
			$role = get_role( 'administrator' );
			$role->add_cap( 'read_contact' );
			$role->add_cap( 'edit_contact' );
			$role->add_cap( 'delete_contact' );
			$role->add_cap( 'publish_contacts' );
			$role->add_cap( 'edit_contacts' );
			$role->add_cap( 'edit_others_contacts' );

			// Destinations
			$role = get_role( 'contributor' );
			$role->add_cap( 'read_destination' );
			$role->add_cap( 'edit_destination' );
			$role->add_cap( 'delete_destination' );
			// Author
			$role = get_role( 'author' );
			$role->add_cap( 'read_destination' );
			$role->add_cap( 'edit_destination' );
			$role->add_cap( 'delete_destination' );
			$role->add_cap( 'publish_destinations' );
			// Editor
			$role = get_role( 'editor' );
			$role->add_cap( 'read_destination' );
			$role->add_cap( 'edit_destination' );
			$role->add_cap( 'delete_destination' );
			$role->add_cap( 'publish_destinations' );
			$role->add_cap( 'edit_destinations' );
			$role->add_cap( 'edit_others_destinations' );
			// Administrator
			$role = get_role( 'administrator' );
			$role->add_cap( 'read_destination' );
			$role->add_cap( 'edit_destination' );
			$role->add_cap( 'delete_destination' );
			$role->add_cap( 'publish_destinations' );
			$role->add_cap( 'edit_destinations' );
			$role->add_cap( 'edit_others_destinations' );

		}

		// Register the property post type
		register_post_type(STAYPRESS_PROPERTY_POST_TYPE, array(	'labels' => array(
																					'name' => __('Properties', 'property'),
																					'singular_name' => __('Property', 'property'),
																					'add_new' => __( 'Add New' ),
																					'add_new_item' => __( 'Add New Property' ),
																					'edit' => __( 'Edit' ),
																					'edit_item' => __( 'Edit Property' ),
																					'new_item' => __( 'New Property' ),
																					'view' => __( 'View Property' ),
																					'view_item' => __( 'View Property' ),
																					'search_items' => __( 'Search Properties' ),
																					'not_found' => __( 'No Properties found' ),
																					'not_found_in_trash' => __( 'No Properties found in Trash' ),
																					'parent' => __( 'Parent Property' ),
																				),
																'public' => true,
																'show_ui' => false,
																'publicly_queryable' => false,
																'exclude_from_search' => true,
																'hierarchical' => true,
																'capability_type' => 'property',
																'edit_cap' => 'edit_property',
																'edit_type_cap' => 'edit_properties',
																'edit_others_cap' => 'edit_others_properties',
																'publish_others_cap' => 'publish_properties',
																'read_cap' => 'read_property',
																'delete_cap' => 'delete_property',
																'rewrite' => array( 'slug' => __('property','property'),
																					'with_front' => false ),
																)
											);

		// Register the destination post type
		register_post_type(STAYPRESS_DESTINATION_POST_TYPE, array(	'labels' => array(
																					'name' => __('Destinations', 'property'),
																					'singular_name' => __('Destination', 'property'),
																					'add_new' => __( 'Add New' ),
																					'add_new_item' => __( 'Add New Destination' ),
																					'edit' => __( 'Edit' ),
																					'edit_item' => __( 'Edit Destination' ),
																					'new_item' => __( 'New Destination' ),
																					'view' => __( 'View Destination' ),
																					'view_item' => __( 'View Destination' ),
																					'search_items' => __( 'Search Destinations' ),
																					'not_found' => __( 'No Destinations found' ),
																					'not_found_in_trash' => __( 'No Destinations found in Trash' ),
																					'parent' => __( 'Parent Destination' ),
																				),
																	'public' => true,
																	'show_ui' => true,
																	'publicly_queryable' => true,
																	'exclude_from_search' => true,
																	'hierarchical' => true,
																	'supports' => array( 'title', 'editor', 'excerpt', 'custom-fields', 'thumbnail', 'page-attributes' ),
																	'capability_type' => 'destination',
																	'edit_cap' => 'edit_destination',
																	'edit_type_cap' => 'edit_destinations',
																	'edit_others_cap' => 'edit_others_destinations',
																	'publish_others_cap' => 'publish_destinations',
																	'read_cap' => 'read_destination',
																	'delete_cap' => 'delete_destination',
																	'rewrite' => array( 'slug' => __('destination','property'),
																						'with_front' => false ),
																)
											);

		$locationsettings = array( 	'labels' => array(
										'name' => __( 'Location', 'property' ),
										'singular_name' => __( 'Location', 'property' ),
										'search_items' =>  __( 'Search Locations', 'property' ),
										'popular_items' => __( 'Popular Locations', 'property' ),
						    'all_items' => __( 'All Locations', 'property' ),
						    'parent_item' => __( 'Parent Location', 'property' ),
						    'parent_item_colon' => __( 'Parent Location:', 'property' ),
						    'edit_item' => __( 'Edit Location', 'property' ),
						    'update_item' => __( 'Update Location', 'property' ),
						    'add_new_item' => __( 'Add New Location', 'property' ),
						    'new_item_name' => __( 'New Location Name', 'property' ),
						    'separate_items_with_commas' => __( 'Separate Locations with commas', 'property' ),
						    'add_or_remove_items' => __( 'Add or remove Locations', 'property' ),
						    'choose_from_most_used' => __( 'Choose from the most used Locations', 'property' )
						  ),
						'public' => true,
						'show_ui' => true,
						'show_tagcloud' => true,
						'show_in_nav_menus' => true,
						'hierarchical' => true,
						'query_var' => 'location',
						'rewrite' => array( 'slug' => __('location', 'property'),
											'with_front' => false ),
						'update_count_callback' => array(&$this, 'update_propertylocation_taxonomy')
						);


		$propertyoptions = SPPCommon::get_option('sp_property_options', array());

		if(isset($propertyoptions['locationcategory']) && $propertyoptions['locationcategory'] == 'yes') {
			register_taxonomy( 'propertylocation', array( STAYPRESS_DESTINATION_POST_TYPE, STAYPRESS_PROPERTY_POST_TYPE), $locationsettings );
		} else {
			register_taxonomy( 'propertylocation', array( STAYPRESS_DESTINATION_POST_TYPE), $locationsettings );
		}

		// Register the contact post type
		if(!post_type_exists(STAYPRESS_CONTACT_POST_TYPE)) {
			register_post_type(STAYPRESS_CONTACT_POST_TYPE, array(	'labels' => array(
																						'name' => __('Contacts', 'property'),
																						'singular_name' => __('Contact', 'property'),
																						'add_new' => __( 'Add New' ),
																						'add_new_item' => __( 'Add New Contact' ),
																						'edit' => __( 'Edit' ),
																						'edit_item' => __( 'Edit Contact' ),
																						'new_item' => __( 'New Contact' ),
																						'view' => __( 'View Contact' ),
																						'view_item' => __( 'View Contact' ),
																						'search_items' => __( 'Search Contacts' ),
																						'not_found' => __( 'No Contacts found' ),
																						'not_found_in_trash' => __( 'No Contacts found in Trash' ),
																						'parent' => __( 'Parent Contact' ),
																					),
																	'public' => false,
																	'show_ui' => false,
																	'publicly_queryable' => false,
																	'exclude_from_search' => true,
																	'hierarchical' => true,
																	'capability_type' => 'contact',
																	'edit_cap' => 'edit_contact',
																	'edit_type_cap' => 'edit_contacts',
																	'edit_others_cap' => 'edit_others_contacts',
																	'publish_others_cap' => 'publish_contacts',
																	'read_cap' => 'read_contact',
																	'delete_cap' => 'delete_contact'
																	)
												);
		}

		$taxonomies = SPPCommon::get_option('property_taxonomies', false);

		$taxonomies = apply_filters('staypress_property_taxonomies', $taxonomies);

		if($taxonomies) {

			$page_prefix = SPPCommon::get_option('property_rewrite_prefix', '');
			if(!empty($page_prefix)) $page_prefix = trailingslashit($page_prefix);

			foreach($taxonomies as $key => $tax) {
				register_taxonomy( $key, STAYPRESS_PROPERTY_POST_TYPE, array( 'hierarchical' => false, 'label' => $tax['label'], 'query_var' => false, 'rewrite' => array('slug' => $page_prefix . 'with', 'with_front' => false) ) );

			}
		} else {

			$taxonomies = array(	"propertyfeature" => array( 'label' => __('Feature','property'), 'slug' => 'features'),
									"propertytype" => array( 'label' => __('Property type','property'), 'slug' => 'propertytypes'),
									"propertyrental" => array( 'label' => __('Rental type','property'), 'slug' => 'rentaltypes'),
									"propertysetting" => array( 'label' => __('Setting','property'), 'slug' => 'settings'),
									"propertyactivity" => array( 'label' => __('Activity','property'), 'slug' => 'activities'),
									"propertysuitability" => array( 'label' => __('Suitability','property'), 'slug' => 'suitability')
									);

			SPPCommon::update_option('property_taxonomies', $taxonomies);
			foreach($taxonomies as $key => $tax) {
				register_taxonomy( $key, STAYPRESS_PROPERTY_POST_TYPE, array( 'public' => true, 'show_ui' => false, 'show_tagcloud' => true, 'show_in_nav_menus' => true, 'hierarchical' => false, 'label' => $tax['label'], 'query_var' => false, 'rewrite' => array('slug' => $page_prefix . 'with', 'with_front' => false) ) );
			}
		}

		// Administrators tags
		if(current_user_can('edit_others_properties')) {
			register_taxonomy( 'propertyadministration', STAYPRESS_PROPERTY_POST_TYPE, array( 'public' => false, 'show_ui' => false, 'show_tagcloud' => false, 'show_in_nav_menus' => false, 'hierarchical' => false, 'label' => __('Adminstration','property') , 'query_var' => false, 'rewrite' => false ) );
			register_taxonomy( 'destinationadministration', STAYPRESS_DESTINATION_POST_TYPE, array( 'public' => false, 'show_ui' => true, 'show_tagcloud' => false, 'show_in_nav_menus' => false, 'hierarchical' => false, 'label' => __('Adminstration','property') , 'query_var' => false, 'rewrite' => false ) );
		}

		// Add in the query extension functions
		// Join our property table so we don't need to mess with a lot of extra look ups later on
		add_filter( 'posts_join_request',	array( &$this->property, 'add_to_property_to_join' ), 10, 2 );
		add_filter( 'posts_fields_request',	array( &$this->property, 'add_to_property_to_fields' ), 10, 2 );

		add_filter( 'the_post', array( &$this->property, 'extend_property' ) );

		// Validations
		$propertyfields = SPPCommon::get_option('sp_property_fields', array() );
		if(!isset($propertyfields['reference']) || $propertyfields['reference'] == 'yes') {
			// add the filter to add the reference required field here
			add_filter('staypress_property_validation_rules', array(&$this,'add_reference_required_field'));
		}

		// Register shortcodes
		$this->register_shortcodes();
	}

	function register_shortcodes() {
		add_shortcode('propertyfacilities', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertylowestprice', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertymeta', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertycountry', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertyregion', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertytown', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertydestination', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertydestinationexcerpt', array(&$this, 'do_adminside_shortcode') );

		add_shortcode('propertyprices', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertymonthlyprices', array(&$this, 'do_adminside_shortcode') );

		add_shortcode('propertymap', array(&$this, 'do_adminside_shortcode') );

		add_shortcode('propertyid', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertyreference', array(&$this, 'do_adminside_shortcode') );

		add_shortcode('propertytitle', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertypermalink', array(&$this, 'do_adminside_shortcode') );

		add_shortcode('propertysearchbox', array(&$this, 'do_adminside_shortcode') );

		// Contacts shortcodes
		add_shortcode('propertycontactnotes', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertycontacttel', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertycontactemail', array(&$this, 'do_adminside_shortcode') );
		add_shortcode('propertycontactname', array(&$this, 'do_adminside_shortcode') );

		add_shortcode('propertyenquiryform', array(&$this, 'do_adminside_shortcode') );
	}

	function do_adminside_shortcode($atts, $content = null, $code = "") {
		// Don't want to actually do anything at the moment on the admin side of things.
		return '';
	}

	function update_propertylocation_taxonomy() {
		// Will create a custom post type with the information entered into the propertylocation category for quicker searching

		$post_id = (int) $_POST['post_ID'];
		if(empty($post_id)) $post_id = (int) $_POST['id'];

		if(empty($post_id)) return;

		if(!empty($_POST['tax_input']['propertylocation'])) {
			foreach( (array) $_POST['tax_input']['propertylocation'] as $key => $location ) {
				if((int) $location > 0) {
					// A location has been entered
					$toplocation = $location;
					$term = get_term($location, 'propertylocation');
					if(!empty($term)) {
						$metavalue = $term->slug;
						while($term->parent > 0) {
							$term = get_term($term->parent, 'propertylocation');
							if(!empty($term)) {
								$metavalue = $term->slug . "-" . $metavalue;
							}
						}
						if(!empty($_POST['content']) || !empty($_POST['propertydescription'])) {
							update_metadata('post', $post_id, '_staypress_destination_information', $metavalue);
						}
						if(!empty($_POST['excerpt']) || !empty($_POST['propertyextract'])) {
							update_metadata('post', $post_id, '_staypress_destination_sidebar_information', $metavalue);
						}

					} else {
						continue;
					}
				}
			}
		}
	}

	// Ajax responses
	function initialise_propertyadmin_ajax() {

		// Images ajax
		add_action( 'wp_ajax__imageupload', array(&$this,'ajax__imageupload') );
		add_action( 'wp_ajax__deleteimage', array(&$this,'ajax__deleteimage') );
		add_action( 'wp_ajax__deleteproperty', array(&$this,'ajax__deleteproperty') );

		add_action( 'wp_ajax__propertymovemonth', array(&$this,'ajax__propertymovemonth') );

	}

	function ajax__propertymovemonth() {

		$year = (int) $_REQUEST['year'];
		$month = (int) $_REQUEST['month'];

		// Fudge the URI
		$_SERVER['REQUEST_URI'] = wp_get_referer();

		//$this->show_propertycalendar($year, $month, false);
		echo "boo";
		exit;
	}

	function ajax__imageupload() {

		$pid = addslashes($_GET['postid']);
		if($pid != "") {
			$result = $this->upload_image($pid);
		} else {
			$result = array('errorcode' => '500', 'message' => 'No property to upload image for.');
		}
		$this->return_json($result);

		exit; // or bad things happen
	}

	function ajax__deleteimage() {

		if($_GET['id'] != "") {
			$imageid = addslashes($_GET['id']);

			check_ajax_referer('deleteimage-' . $imageid);

			if(current_user_can('delete_post', $imageid)) {
				$result = wp_delete_attachment( $imageid, true );
				if($result) {
					$result = array('errorcode' => '200', 'message' => 'Deletion completed', 'id' => $imageid);
				} else {
					$result = array('errorcode' => '500', 'message' => 'Could not delete image', 'id' => $imageid);
				}
			} else {
				$result = array('errorcode' => '500', 'message' => 'You can not delete this image', 'id' => $imageid);
			}

			$this->return_json($result);
		}

		exit; // or bad things happen
	}

	function ajax__deleteproperty() {

		if(!empty($_GET['id'])) {
			$id = (int) $_GET['id'];

			check_ajax_referer('ajaxdeleteproperty');

			$result = $this->property->delete_property($id);
			if(!is_wp_error($result)) {
				do_action( 'staypress_property_deleted', $id, 'delete', 'property' );
				$result = array('errorcode' => '200', 'message' => __('Deletion completed','property'), 'id' => $id, 'newnonce' => wp_create_nonce('ajaxdeleteproperty'));
			} else {
				$result = array('errorcode' => '500', 'message' => $result->get_error_message(), 'id' => $id, 'newnonce' => wp_create_nonce('ajaxdeleteproperty'));
			}
			$this->return_json($result);
		}

		exit; // or bad things happen

	}

	function return_json($results) {

		// Check for callback
		if(isset($_GET['callback'])) {
			// Add the relevant header
			header('Content-type: text/javascript');
			echo addslashes($_GET['callback']) . " (";
		} else {
			if(isset($_GET['pretty'])) {
				// Will output pretty version
				header('Content-type: text/html');
			} else {
				//header('Content-type: application/json');
				//header('Content-type: text/javascript');
			}
		}

		if(function_exists('json_encode')) {
			echo json_encode($results);
		} else {
			// PHP4 version
			require_once(ABSPATH."wp-includes/js/tinymce/plugins/spellchecker/classes/utils/JSON.php");
			$json_obj = new Moxiecode_JSON();
			echo $json_obj->encode($results);
		}

		if(isset($_GET['callback'])) {
			echo ")";
		}

	}

	function add_favourite_actions_newproperty($actions) {

		$default_action = array('admin.php?page=property-add' => array(__('New Property','property'), 'edit_property'));
		// Quick links
		$actions['admin.php?page=property'] = array(__('Properties','property'), 'edit_property');

		$actions = array_merge($default_action, $actions);

		return $actions;

	}

	function add_favourite_actions_propertylist($actions) {

		$default_action = array('admin.php?page=property' => array(__('Properties','property'), 'edit_property'));
		// Quick links
		$actions['admin.php?page=property-add'] = array(__('New Property','property'), 'edit_property');

		$actions = array_merge($default_action, $actions);

		return $actions;

	}

	function add_favourite_actions_standard($actions) {

		// Quick links
		$actions['admin.php?page=property'] = array(__('Properties','property'), 'edit_property');
		$actions['admin.php?page=property-add'] = array(__('New Property','property'), 'edit_property');

		return $actions;

	}

	function add_wp_admin_menu_actions() {
		global $wp_admin_bar;

		if(current_user_can('edit_property')) {
			$wp_admin_bar->add_menu( array( 'parent' => 'new-content', 'id' => 'property', 'title' => __('Property','property'), 'href' => admin_url('admin.php?page=property-add') ) );
		}

	}

	function add_admin_menu() {

		global $menu, $_wp_last_object_menu, $submenu, $admin_page_hooks;

		// Add the menu page
		add_menu_page(__('Property Management','property'), __('Properties','property'), 'edit_property',  'property', array(&$this,'show_property_panel'), SPPCommon::property_url('images/home.png'));
		// Move things about
		$keys = array_keys($menu);
		$menuaddedat = end($keys);

		$checkfrom = $_wp_last_object_menu + 1;
		while(isset($menu[$checkfrom])) {
			$checkfrom += 1;
		}
		// If we are here then we have found a slot
		$menu[$checkfrom] = $menu[$menuaddedat];
		$_wp_last_object_menu = $checkfrom;
		// Remove the menu we originally added
		unset($menu[$menuaddedat]);

		// Fix WP translation hook issue
		if(isset($admin_page_hooks['property'])) {
			$admin_page_hooks['property'] = 'properties';
		}

		// Add the sub menu
		add_submenu_page('property', __('Add New Property','property'), __('Add New','property'), 'edit_property', "property-add", array(&$this,'show_propertyadd_panel'));

		// Add the settings pages if the user has the relevant permissions
		if(current_user_can('manage_categories')) {
			add_submenu_page('property', __('Facilities','property'), __('Edit Facilities','property'), 'manage_categories', "property-facilities", array(&$this,'show_facilities_panel'));
			add_submenu_page('property', __('Tags','property'), __('Edit Tags','property'), 'manage_categories', "property-tags", array(&$this,'show_tags_panel'));

			// Add in the edit locations editing page - maybe later

		}

		if(current_user_can('manage_options')) {

			$propertyoptions = SPPCommon::get_option('sp_property_options', array());

			add_submenu_page('property', __('Property options','property'), __('Edit Options','property'), 'manage_options', "property-options", array(&$this,'show_options_panel'));

			if(!isset($propertyoptions['listingmethod']) || $propertyoptions['listingmethod'] == 'permalink') {
				add_submenu_page('property', __('Property Permalinks','property'), __('Edit Permalinks','property'), 'manage_options', "property-permalinks", array(&$this,'show_permalinks_panel'));
			} else {
				add_submenu_page('property', __('Property Pages','property'), __('Edit Pages','property'), 'manage_options', "property-pages", array(&$this,'show_pages_panel'));
			}

		}

	}

	function build_property_filter() {

		$filter = array();

		// Filter tags
		if(!empty($_GET['tagfilter'])) {
			foreach($_GET['tagfilter'] as $key => $value) {
				$filter['tag'][] = $this->property->sanitize_int($value);
			}
		}

		// Filter meta
		if(!empty($_GET['meta'])) {
			foreach($_GET['meta'] as $key => $value) {
				if(!empty($value)) $filter['meta'][$this->property->sanitize_int($key)] = $this->property->sanitize_str($value);
			}
		}

		// Filter deleted
		if(!empty($_GET['includedeleted'])) {
			$filter['deleted'] = 'yes';
		}

		// Custom filters
		$filter = apply_filters('staypress_propertyadmin_filter', $filter);

		return $filter;

	}

	function show_property_panel_messages() {

		if(isset($_GET['error'])) {
			$this->show_property_panel_errors();
		} else {
			// Set up user messages
			$messages = array();
			$messages[1] = __('Your property details have been saved.','property');
			$messages[2] = __('Your property has been added.','property');
			$messages[3] = __('Your property has been published.','property');

			$messages[4] = __('The property has been deleted.','property');
			$messages[5] = __('The property could not be deleted.','property');

			$messages[6] = __('Your property details have not been saved.','property');
			$messages[7] = __('Your property has not been added.','property');
			$messages[8] = __('Your property has not been published.','property');

			$messages[9] = __('Your property has been sent for review.','property');

			// Message if there needs to be one
			if(isset($_GET['msg'])) {
				echo '<div id="upmessage" class="updatedmessage"><p>' . $messages[(int) $_GET['msg']];
				if((int) $_GET['msg'] <= 4 ) {
					// a positive message
					echo "&nbsp;&nbsp;<a href='admin.php?page=property'>" . __('Return to list','property') . "</a>";
				}
				echo '<a href="#close" id="closemessage">' . __('close', 'property') . '</a>';
				echo '</p></div>';
				$_SERVER['REQUEST_URI'] = remove_query_arg(array('msg'), $_SERVER['REQUEST_URI']);
			}
		}

	}

	function show_property_panel_errors() {

		// Set up user messages
		$errors = array();
		$errors[1] = __('Please ensure you have completed all of the required fields.','property');
		$errors[2] = __('You are not allowed to publish properties, sorry.','property');

		// Message if there needs to be one
		if(!empty($_GET['error'])) {
			echo '<div id="upmessage" class="updatedmessage errormessage"><p><strong>' . __('The following errors occured','property') . "</strong>";
			echo '<a href="#close" id="closemessage">' . __('close', 'popover') . '</a>';
			echo '</p>';
			echo '<p>';
			foreach( (array) explode(",", $_GET['error']) as $err ) {
				if(!empty($errors[ (int) $err ])) {
					echo $errors[ (int) $err ] . "<br/>";
				}
			}
			echo '</p>';
			echo '</div>';
		}

	}

	function show_property_panel() {

		global $parent_file, $wp_query, $action, $page;

		switch($action) {

			case 'edit':	$this->show_propertyedit_panel();
							return; // so we don't see the rest of this page.
							break;


		}

		if(isset($_GET['paged']) && is_numeric(addslashes($_GET['paged']))) {
			$paged = intval($_GET['paged']);
		} else {
			$paged = 1;
		}

		$startat = (($paged - 1) * $this->showpropertynumber);

		// Property type filters
		if(isset($_GET['type'])) {
			$type = addslashes($_GET['type']);
		} else {
			$type = 'all';
		}

		echo "<div class='wrap'>\n";

		echo "<div class='innerwrap'>\n";

		// Show property list
		if(!empty($_GET['filteronall'])) {
			// Filters search
			$filter = $this->build_property_filter();

			if(empty($filter)) {
				$properties = $this->property->get_propertylist($startat, $this->showpropertynumber, $type, true);

				echo "<h2><a href='' class='selected'>" . __('Property List','property') . "</a>";
			} else {
				$properties = $this->property->filter_propertylist($filter, $startat, $this->showpropertynumber, $type, true);

				echo "<h2><a href='' class='selected'>" . __('Filtered Results','property') . "</a>";

			}

		} elseif(!empty($_GET['propertysearchbutton']) && !empty($_GET['propertysearch'])) {
			// Property search
			$properties = $this->property->search_propertylist($_GET['propertysearch'], $startat, $this->showpropertynumber, $type, true);

			echo "<h2><a href='' class='selected'>" . __('Search Results','property') . "</a>";


		} else {
			// All properties
			$properties = $this->property->get_propertylist($startat, $this->showpropertynumber, $type, true);

			echo "<h2><a href='' class='selected'>" . __('Property List','property') . "</a>";

		}

		$found = $this->property->get_totalproperties();

		if($found > $this->showpropertynumber) {
			// Pagination required

			$list_navigation = paginate_links( array(
				'base' => add_query_arg( 'paged', '%#%' ),
				'format' => '',
				'total' => ceil($found / $this->showpropertynumber),
				'current' => $paged,
				'prev_next' => false
			));

			echo "<span id='pagination'>" . $list_navigation . "</span>";
		}

		// Show the end of the header
		echo "</h2>";

		echo "<div id='propertylist'>\n";

		// Inner sub menu
		echo "<ul id='innermenu'>";

		echo "<li class='leftmenu'>";

		echo "<ul id='inoutmenu' class='appmenu'>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('type', 'all', remove_query_arg('paged')) . "'";
			if($type == 'all') {
				echo " class='selected'";
			}
			echo " title='" . __('All properties' , 'property') . "'";
			echo ">";
			echo __('All', 'property');
			echo "</a>";
			echo "</li>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('type', 'publish', remove_query_arg('paged')) . "'";
			if($type == 'publish') {
				echo " class='selected'";
			}
			echo " title='" . __('Published' , 'booking') . "'";
			echo ">";
			echo __('Published', 'property');
			echo "</a>";
			echo "</li>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('type', 'private', remove_query_arg('paged')) . "'";
			if($type == 'private') {
				echo " class='selected'";
			}
			echo " title='" . __('Private' , 'booking') . "'";
			echo ">";
			echo __('Private', 'property');
			echo "</a>";
			echo "</li>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('type', 'pending', remove_query_arg('paged')) . "'";
			if($type == 'pending') {
				echo " class='selected'";
			}
			echo " title='" . __('Pending' , 'booking') . "'";
			echo ">";
			echo __('Pending', 'property');
			echo "</a>";
			echo "</li>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('type', 'draft', remove_query_arg('paged')) . "'";
			if($type == 'draft') {
				echo " class='selected'";
			}
			echo " title='" . __('Draft' , 'booking') . "'";
			echo ">";
			echo __('Draft', 'property');
			echo "</a>";
			echo "</li>";

		echo "</ul>\n";

		echo "</li>";

		echo "</ul> <!-- innermneu -->\n";

		if($properties) {
			$this->show_property_list($properties);
		} else {
			if($found == 0) {
				// No properties at all
				if($this->property->properties_exist()) {
					$this->show_property_alert( __('Sorry, I couldn\'t find any properties that match your criteria.','property') );
				} else {
					// Empty state
					$this->show_nodata_page();
				}
			} else {
				// Gone over the edge of the pages
				$this->show_noproperties_page();
			}
		}

		echo "</div> <!-- propertylist -->\n";
		echo "</div> <!-- innerwrap -->\n";

		// Start sidebar here
		echo "<div class='rightwrap'>";
		$this->show_property_rightpanel();
		echo "</div> <!-- rightwrap -->";

		echo "</div> <!-- wrap -->";

	}

	function process_update_common($id) {

		// Handle price changes
		if(isset($_POST['priceday'])) {

			$prices = array();
			$pricelines = array();
			foreach($_POST['priceday'] as $key => $priceday) {

				if(addslashes($key) == 'new' && empty($_POST['pricemonth'][$key])) {
					continue;
				}

				if(empty($priceday) || empty($_POST['pricemonth'][$key])) {
					continue;
				}

				if(addslashes($key) == 'new') {
					$lastpricerow = (int) $_POST['lastpricerow'];

					$prices[$lastpricerow]['day'] = addslashes($priceday);
					$prices[$lastpricerow]['month'] = addslashes($_POST['pricemonth'][$key]);
				} else {
					$prices[$key]['day'] = addslashes($priceday);
					$prices[$key]['month'] = addslashes($_POST['pricemonth'][$key]);
				}


			}

			foreach($_POST['price_row'] as $pkey => $price_row) {
				// Check if there is data
				if(empty($_POST['price_amount'][$pkey]) || empty($_POST['price_currency'][$pkey]) || empty($_POST['price_period'][$pkey]) || empty($_POST['price_period_type'][$pkey])) {
					continue;
				}
				// There is data, but there is no pricerow set, so we should set a new one for this data
				if(empty($price_row)) {
					$use_price_row = (int) $_POST['lastpricerow'];
				} else {
					$use_price_row = (int) $price_row;
				}

				$pricelines[] = array( 	'property_id' => $id,
										'price_row' => $use_price_row,
										'price_amount' => $_POST['price_amount'][$pkey],
										'price_currency' => $_POST['price_currency'][$pkey],
										'price_period' => $_POST['price_period'][$pkey],
										'price_period_type' => $_POST['price_period_type'][$pkey]
										);
			}

			$this->property->update_prices($id, $prices, $pricelines);

		}

		// handle price notes
		if(!empty($_POST['propertypricenotes'])) {
			update_post_meta($id, '_property_price_notes', $_POST['propertypricenotes']);
		}

		// Handle meta changes
		$meta = array();
		$postmeta = $_POST['meta'];

		if(!empty($postmeta)) {
			foreach($postmeta as $key => $value) {
				if(is_numeric($key)) {
					$meta[(int) $key] = mysql_real_escape_string($value);
				}
			}
		}
		$this->property->update_propertymeta($id, $meta);

		// Handle contact changes
		if(!empty($_POST['propertycontactname'])) {
			$contact = array();

			if(!empty($_POST['propertycontactname'])) {
				$contact['name'] = $_POST['propertycontactname'];
			}

			if(!empty($_POST['propertycontactemail']) && $this->is_valid_email(addslashes($_POST['propertycontactemail']))) {
				$contact['email'] = $_POST['propertycontactemail'];
			}

			if(!empty($_POST['propertycontacttel'])) {
				$contact['tel'] = $_POST['propertycontacttel'];
			}

			if(!empty($_POST['propertycontactnotes'])) {
				$contact['notes'] = $_POST['propertycontactnotes'];
			}

			$contact['property_id'] = $id;

			if(!empty($_POST['propertycontactid'])) {
				$contact_id = $this->property->update_contact( (int) $_POST['propertycontactid'], $contact);
			} else {
				$contact_id = $this->property->insert_contact($contact);
			}


		}
	}

	function process_update_property() {

		global $action;

		wp_reset_vars(array('action'));

		switch($action) {

			case 'save':	// Save an updated property
							$id = addslashes($_POST['id']);

							check_admin_referer('update-property-' . $id);

							// Existing property - update
							// Grab the main content
							$property = array();
							$property['reference'] = $_POST['propertyreference'];
							$property['title'] = $_POST['propertytitle'];
							$property['extract'] = $_POST['propertyextract'];
							$property['description'] = $_POST['propertydescription'];

							$property['mainimageid'] = $_POST['mainimage'];
							$property['listimageid'] = $_POST['listimage'];

							$property['post_id'] = $_POST['id'];

							// Add in the tags - testing
							if ( !empty($_POST['tags_input']) ) {
								$property['tags_imput'] = $_POST['tags_input'];
							}

							if ( !empty($_POST['tax_input']) ) {
								$property['tax_input'] = $_POST['tax_input'];
							}

							if( !empty( $_POST['post_parent'] ) ) {
								$property['post_parent'] = $_POST['post_parent'];
							}

							$property = apply_filters( 'staypress_property_preupdate_details', $property );

							// Check for publish button
							if(isset($_POST['publish']) || addslashes($_POST['status']) == 'publish') {
								// The publish button has been pressed
								$validate = $this->property->validate_property($property['post_id'], $property);
								if( current_user_can('publish_properties') ) {
									if( !is_wp_error($validate) ) {
										$property['status'] = 'publish';
									} else {
										$error = array('error' => 1);
										$property['status'] = 'draft';
									}
								} else {
									$error = array('error' => 2);
									$property['status'] = 'pending';
								}
								if(isset($_POST['oldstatus']) && addslashes($_POST['oldstatus']) != 'publish') {
									// Maybe run a transition - not sure yet will have to check into it
								}
							} else {
								// Check for submit button
								if(isset($_POST['submit']) || addslashes($_POST['status']) == 'pending') {
									// The publish button has been pressed
									$validate = $this->property->validate_property($property['post_id'], $property);
									if( is_wp_error($validate) ) {
										$error = array('error' => 1);
										$property['status'] = 'draft';
									} else {
										$property['status'] = 'pending';
									}

									if(isset($_POST['oldstatus']) && addslashes($_POST['oldstatus']) != 'publish') {
										// Maybe run a transition - not sure yet will have to check into it
									}
								} else {
									$property['status'] = $_POST['status'];
								}
							}

							// Save the main details
							$id = $this->property->update_property($id, $property);

							if(!is_wp_error($id)) {
								// Handle image changes
								if(addslashes($_POST['imageorder']) != 'none' || !empty($_POST['imageorder'])) {
									// The image order has been changed
									$imageorder = str_replace('imageitem[]=', '', addslashes($_POST['imageorder']));

									$imgarray = explode('&', $imageorder);

									$this->property->arrange_imageorder($id, $imgarray);

								}
							}

							$commonerror = $this->process_update_common($id);
							if(is_wp_error($commonerror)) {
								$error = array('error' => 1);
							}

							if(!is_wp_error($id) && !is_wp_error($commonerror)) {
								// we have a new id
								// Fire an update action
								do_action( 'staypress_property_updated', $id, 'update', 'property' );

								$return = array('msg' => 1);
								if(!empty($error)) {
									$return = array_merge($return, $error);
								}
								wp_safe_redirect( add_query_arg( $return, wp_get_referer() ) );
							} else {
								// Oops, something must have gone wrong
								$return = array('msg' => 6);
								if(!empty($error)) {
									$return = array_merge($return, $error);
								}
								wp_safe_redirect( add_query_arg( $return, wp_get_referer() ) );
							}

							break;

			case 'delete':	// Delete a property
							if(!empty($_GET['id'])) {
								$id = (int) $_GET['id'];

								check_ajax_referer('deleteproperty-' . $id);

								$result = $this->property->delete_property($id);
								if($result) {
									do_action( 'staypress_property_deleted', $id, 'delete', 'property' );
									wp_safe_redirect( add_query_arg('msg', 4, wp_get_referer() ) );
								} else {
									wp_safe_redirect( add_query_arg('msg', 5, wp_get_referer() ) );
								}
							}
							break;



		}

	}

	function process_add_property() {

		global $action;

		wp_reset_vars(array('action'));

		if($action == 'save') {

			$id = addslashes($_POST['id']);

			check_admin_referer('update-property-' . $id);

			// New property - create
			// Grab the main content
			$property = array();
			$property['reference'] = $_POST['propertyreference'];
			$property['title'] = $_POST['propertytitle'];
			$property['extract'] = $_POST['propertyextract'];
			$property['description'] = $_POST['propertydescription'];

			$property['mainimageid'] = $_POST['mainimage'];
			$property['listimageid'] = $_POST['listimage'];

			$property['post_id'] = $_POST['post_id'];

			// Add in the tags - testing
			if ( !empty($_POST['tags_input']) ) {
				$property['tags_imput'] = $_POST['tags_input'];
			}

			if ( !empty($_POST['tax_input']) ) {
				$property['tax_input'] = $_POST['tax_input'];
			}

			if( !empty( $_POST['post_parent'] ) ) {
				$property['post_parent'] = $_POST['post_parent'];
			}

			$property = apply_filters( 'staypress_property_preadd_details', $property );

			// Check for publish button
			if(isset($_POST['publish']) || addslashes($_POST['status']) == 'publish') {
				// The publish button has been pressed
				$validate = $this->property->validate_property($property['post_id'], $property);
				if( current_user_can('publish_properties') ) {
					if( !is_wp_error($validate) ) {
						$property['status'] = 'publish';
					} else {
						$error = array('error' => 1);
						$property['status'] = 'draft';
					}
				} else {
					$error = array('error' => 2);
					$property['status'] = 'pending';
				}
				if(isset($_POST['oldstatus']) && addslashes($_POST['oldstatus']) != 'publish') {
					// Maybe run a transition - not sure yet will have to check into it
				}
			} else {
				// Check for submit button
				if(isset($_POST['submit']) || addslashes($_POST['status']) == 'pending') {
					// The publish button has been pressed
					$validate = $this->property->validate_property($property['post_id'], $property);
					if( is_wp_error($validate) ) {
						$error = array('error' => 1);
						$property['status'] = 'draft';
					} else {
						$property['status'] = 'pending';
					}

					if(isset($_POST['oldstatus']) && addslashes($_POST['oldstatus']) != 'publish') {
						// Maybe run a transition - not sure yet will have to check into it
					}
				} else {
					$property['status'] = $_POST['status'];
				}
			}

			// Insert and grab the ID
			$newid = $this->property->insert_property($id, $property);

			// Handle image changes
			// Move images to new property id
			$this->rename_propertydirectory($id, $newid);
			$this->property->move_images($id, $newid);

			// Handle re-arrangements
			if(addslashes($_POST['imageorder']) != 'none' || !empty($_POST['imageorder'])) {
				// The image order has been changed
				$imageorder = str_replace('imageitem[]=', '', addslashes($_POST['imageorder']));

				$imgarray = explode('&', $imageorder);

				$this->property->arrange_imageorder($newid, $imgarray);
			}

			// Pass the id to the remaining updates
			$id = $newid;

			$commonerror = $this->process_update_common($id);
			if(is_wp_error($commonerror)) {
				$error = array('error' => 1);
			}

			if($id > 0) {
				// Fire an update action
				do_action( 'staypress_property_added', $id, 'add_new', 'property' );
				// we have a new id
				$return = array('msg' => 2, 'action' => 'edit', 'id' => $id);
				if(!empty($error)) {
					$return = array_merge($return, $error);
				}
				wp_safe_redirect( add_query_arg( $return, 'admin.php?page=property' ) );
			} else {
				// Oops, something must have gone wrong
				$return = array('msg' => 7);
				if(!empty($error)) {
					$return = array_merge($return, $error);
				}
				wp_safe_redirect( add_query_arg( $return, 'admin.php?page=property' ) );
			}


		}
	}

	function show_propertyadd_panel() {

		if($this->error_override_edit_form) {
			return;
		}

		$this->show_propertyedit_panel();
	}

	function show_property_alert($msg) {
		// Message if there needs to be one
		$this->show_property_panel_messages();

		echo "<div class='notfound'>" . $msg . "</div>";
	}

	function show_propertyedit_panel() {

		global $action, $page;

		if($this->error_override_edit_form) {
			return;
		}

		echo "<div class='wrap'>";

		echo "<form action='?page=" . $page . "' method='post' name='editaddpropertyform' id='editaddpropertyform' >";

		echo "<div class='innerwrap'>\n";

		if($action == 'edit') {
			echo "<h2><a href='' class='selected'>" . __('Edit Property','property') . "</a></h2>";
		} else {
			echo "<h2><a href='' class='selected'>" . __('Add Property','property') . "</a></h2>";
		}

		// New location for form tag to allow coverage of tags boxes

		if(isset($_GET['id'])) {
			$id = (int) $_GET['id'];

			$property = $this->property->get_property($id);

			if($property) {
				$this->show_property_edit_form($property);
			} else {
				$this->show_property_alert( __('You are not allowed to edit this property.', 'property') );
				echo "</div>";
				echo "</form>";
				echo "</div>";
				return;
			}

		} else {
			$property = new stdClass;

			// Set new random id
			$property->ID = time() * -1;
			$property->post_id = $property->ID;

			$this->show_property_edit_form($property);

		}

		echo "</div> <!-- innerwrap -->\n";

		// Show the right hand side bar
		echo "<div class='rightwrap'>";
		$this->show_propertystatus_rightpanel($property);
		$this->show_propertyparent_rightpanel($property);
		$this->show_property_tags_rightpanel($property);
		echo "</div> <!-- rightwrap -->";


		// New end of location for form tag - needs to be before the images form as that needs it's own.
		echo "</form>";

		if(current_user_can('upload_files')) {
			echo "<div class='rightwrap'>";
			$this->show_images_rightpanel($property);
			echo "</div> <!-- rightwrap -->";
		}

		echo "</div> <!-- wrap -->";

	}

	function error_class($field, $error) {

		if(empty($error)) {
			return '';
		}

		if(array_key_exists($field, $error)) {
			return ' errormessage';
		}

	}

	// Property editing and adding functions

	function show_property_edit_form($property, $error = false) {

		if(!$error) {
			$error = array();
		}

		// Get the visible fields
		$propertyfields = SPPCommon::get_option('sp_property_fields', array() );

		echo "<div id='editaddpropertyforminner' class='wrapcontents' >";

		// Message if there needs to be one
		$this->show_property_panel_messages();

		if(!empty($error)) {
			echo '<div id="upmessage" class="updatedmessage errormessage"><p><strong>' . __('The following errors occured','property') . "</strong>";
			echo '<a href="#close" id="closemessage">' . __('close', 'popover') . '</a>';
			echo '</p>';
			echo '<p>';
			echo implode("<br/>", (array) $error);
			echo '</p>';
			echo '</div>';
		}

		// Hidden fields
		echo "<input type='hidden' id='action' name='action' value='save' />";

		echo "<input type='hidden' id='imageorder' name='imageorder' value='none' />";
		echo "<input type='hidden' id='mainimage' name='mainimage' value='" . (isset($property->mainimageid) ? $property->mainimageid : '') . "' />";
		echo "<input type='hidden' id='listimage' name='listimage' value='" . (isset($property->listimageid) ? $property->listimageid : '') . "' />";

		echo "<input type='hidden' id='id' name='id' value='" . $property->ID . "' />";
		echo "<input type='hidden' id='status' name='status' value='" . (isset($property->status) ? $property->status : '') . "' />";
		echo "<input type='hidden' id='externalid' name='ext_id' value='" . (isset($property->ext_id) ? $property->ext_id : '') . "' />";

		wp_nonce_field('update-property-' . $property->ID);

		// Visible fields
		if(isset($propertyfields['reference']) && $propertyfields['reference'] == 'no') {} else {
			echo "<label for='propertyreference' class='main'>" . __('Reference', 'property') . "</label>";
			echo "<input type='text' name='propertyreference' id='propertyreference' value='" . esc_attr(stripslashes( (isset($property->reference) ? $property->reference : '' ))) . "' class='main narrow" . $this->error_class('propertyreference', $error) . "' />";
		}
		echo "<label for='propertytitle' class='main'>" . __('Title', 'property') . "</label>";
		echo "<input type='text' name='propertytitle' id='propertytitle' value='" . esc_attr(stripslashes( (isset($property->post_title) ? $property->post_title : '' ))) . "' class='main wide" . $this->error_class('propertytitle', $error) . "' />";

		// Permalink field
		if(isset($propertyfields['permalink']) && $propertyfields['permalink'] == 'no') {} else {
		}

		echo "<label for='propertyextract' class='main'>" . __('Short Description', 'property') . "</label>";
		echo "<textarea name='propertyextract' id='propertyextract' class='main wide short'>" . esc_attr(stripslashes( (isset($property->post_excerpt) ? $property->post_excerpt : '' ))) . "</textarea>";

		// Strip out the standard media buttons because we don't want them
		remove_all_actions( 'media_buttons' );
		// Add the description label
		add_action( 'media_buttons', array(&$this,'show_description_label') );

		wp_editor(stripslashes( (isset($property->post_content) ? $property->post_content : '' )), 'propertydescription', 'propertyextract', true);

		echo '<table id="post-status-info" cellspacing="0"><tbody><tr>';
		echo '<td id="wp-word-count">&nbsp;</td>';
		echo '</tr></tbody></table>';

		do_action( 'staypress_propertyedit_form_extras', $property->ID, $property, $error );

		echo "</div>";

	}

	function build_period_array() {

		$periods = array();

		$periods['h'] = __('hour(s)', 'property');
		$periods['d'] = __('day(s)', 'property');
		$periods['w'] = __('week(s)', 'property');
		$periods['m'] = __('month(s)', 'property');
		$periods['y'] = __('year(s)', 'property');

		return $periods;

	}

	function show_property_prices($id = false, $property = false, $error = false) {

		if($id > 0) {
			echo "<h3>" . __('Edit Prices','property') . "</h3>\n";
		} else {
			echo "<h3>" . __('Add Prices','property') . "</h3>\n";
		}

		echo "<ul class='pricetable'>";

		//Grab the prices
		$prices = $this->property->get_propertyprices($property->ID);

		$row = 1;
		$style = '';
		$tabindex = 1;
		if(!empty($prices)) {
			foreach($prices as $price) {
				echo "<li class='pricerow $style' id='pricerow-$row'>";
				echo "<ul class='pricecolumns'>";
					// drag handle
					echo "<li class='draghandle'>";
					echo "<a href='#move'>";
					echo "&nbsp;";
					echo "</a>";
					echo "</li>";

					echo "<li class='pricebandcolumn'>";
					echo "<select name='priceday[$row]' id='priceday-$row' class='priceday' tabindex='" . $tabindex++ . "'>";
					echo "<option value=''></option>";
					for($day=1; $day <= 31; $day++) {
						echo "<option value='" . $day . "'";
						if($day == $price->price_day) {
							echo " selected='selected'";
						}
						echo ">";
						echo $day;
						echo "</option>";
					}
					echo "</select>";

					echo "<select name='pricemonth[$row]' id='pricemonth-$row' class='pricemonth' tabindex='" . $tabindex++ . "'>";
					echo "<option value=''></option>";
					for($mon=1; $mon <= 12; $mon++) {
						echo "<option value='" . $mon . "'";
						if($mon == $price->price_month) {
							echo " selected='selected'";
						}
						echo ">";
						echo strftime('%b', strtotime(date("Y-$mon-01")));
						echo "</option>";
					}
					echo "</select>";

					echo "</li>";

					$pricelines = $this->property->get_propertypricelines( $property->ID, $price->price_row );

					echo "<li class='pricecolumn col1'>";

					if(!empty($pricelines)) {
						foreach($pricelines as $priceline) {
							echo "<div class='priceline'>";
							echo "<input type='hidden' name='price_row[]' value='" . $price->price_row . "' class='pricerowidentifier' />";
							echo "<input type='text' name='price_amount[]' class='pricefigure' value='" . esc_attr($priceline->price_amount) . "' tabindex='" . $tabindex++ . "' />";
							echo "&nbsp;";
							echo "<select name='price_currency[]' tabindex='" . $tabindex++ . "'>";
							echo "<option value=''></option>";
							$currencies = apply_filters('staypress_price_currencies', array("USD", "GBP", "EURO"));
							foreach($currencies as $currency) {
								echo "<option value='" . $currency . "'";
								if($priceline->price_currency == $currency) echo " selected='selected'";
								echo ">";
								echo $currency;
								echo "</option>";
							}
							echo "</select>";
							echo "&nbsp;" . __('for','property') . "&nbsp;";

							echo "<select name='price_period[]' tabindex='" . $tabindex++ . "'>";
							echo "<option value=''></option>";
							for($n=1; $n<=60; $n++) {
								echo "<option value='" . $n . "'";
								if($priceline->price_period == $n) echo " selected='selected'";
								echo ">";
								echo $n;
								echo "</option>";
							}
							echo "</select>&nbsp;";
							echo "<select name='price_period_type[]' tabindex='" . $tabindex++ . "'>";
							echo "<option value=''></option>";
							$periods = $this->build_period_array();
							$periods = apply_filters('staypress_price_periods', $periods );
							foreach($periods as $key => $period) {
								echo "<option value='" . $key . "'";
								if($priceline->price_period_type == $key) echo " selected='selected'";
								echo ">";
								echo $period;
								echo "</option>";
							}
							echo "</select>";
							echo "&nbsp;";
							echo "<a href='' class='removepriceperiodrow' title='Remove this price row'>&nbsp;</a>";
							echo "</div>";
						}
					}

					// Show a blank line box so a line can be easily added
					echo "<div class='priceline'>";
					echo "<input type='hidden' name='price_row[]' value='" . $price->price_row . "' class='pricerowidentifier' />";
					echo "<input type='text' name='price_amount[]' class='pricefigure' value='' tabindex='" . $tabindex++ . "' />";
					echo "&nbsp;";
					echo "<select name='price_currency[]' tabindex='" . $tabindex++ . "'>";
					echo "<option value=''></option>";
					$currencies = apply_filters('staypress_price_currencies', array("USD", "GBP", "EURO"));
					foreach($currencies as $currency) {
						echo "<option value='" . $currency . "'";
						echo ">";
						echo $currency;
						echo "</option>";
					}
					echo "</select>";
					echo "&nbsp;" . __('for','property') . "&nbsp;";

					echo "<select name='price_period[]' tabindex='" . $tabindex++ . "'>";
					echo "<option value=''></option>";
					for($n=1; $n<=60; $n++) {
						echo "<option value='" . $n . "'";
						echo ">";
						echo $n;
						echo "</option>";
					}
					echo "</select>&nbsp;";
					echo "<select name='price_period_type[]' tabindex='" . $tabindex++ . "'>";
					echo "<option value=''></option>";
					$periods = $this->build_period_array();
					$periods = apply_filters('staypress_price_periods', $periods );
					foreach($periods as $key => $period) {
						echo "<option value='" . $key . "'";
						echo ">";
						echo $period;
						echo "</option>";
					}
					echo "</select>";
					echo "&nbsp;";
					echo "<a href='' class='addpriceperiodrow' title='Add a new price row for this date'>&nbsp;</a>";
					echo "</div>";

					echo "</li>";

					echo "<li class='actioncolumn'>";
					echo "<a href='' class='removepricerow' title='Remove this price'>&nbsp;</a>";

					echo "</li>";
				echo "</ul>";
				echo "</li>";

				$row += 1;
				if($style == '') {
					$style = 'altstripe';
				} else {
					$style = '';
				}
			}
		}
			// Add a blank row
			echo "<li class='pricenewrow $style' id='pricerow-new'>";
			echo "<ul class='pricecolumns'>";

				echo "<li class='draghandle'>";
				echo "<a href='#move'>";
				echo "&nbsp;";
				echo "</a>";
				echo "</li>";

				echo "<li class='pricebandcolumn'>";

				echo "<select name='priceday[new]' id='priceday-new' class='priceday'>";
				echo "<option value=''></option>";
				for($day=1; $day <= 31; $day++) {
					echo "<option value='" . $day . "'";
					echo ">";
					echo $day;
					echo "</option>";
				}
				echo "</select>";

				echo "<select name='pricemonth[new]' id='pricemonth-new' class='pricemonth'>";
				echo "<option value=''></option>";
				for($mon=1; $mon <= 12; $mon++) {
					echo "<option value='" . $mon . "'";
					echo ">";
					echo strftime('%b', strtotime(date("Y-$mon-01")));
					echo "</option>";
				}
				echo "</select>";

				echo "</li>";

				echo "<li class='pricecolumn col1'>";
					// Show a blank line box so a line can be easily added
					echo "<div class='priceline'>";
					echo "<input type='hidden' name='price_row[]' class='pricerowidentifier' />";
					echo "<input type='text' name='price_amount[]' class='pricefigure' value='' />";
					echo "&nbsp;";
					echo "<select name='price_currency[]'>";
					echo "<option value=''></option>";
					$currencies = apply_filters('staypress_price_currencies', array("USD", "GBP", "EURO"));
					foreach($currencies as $currency) {
						echo "<option value='" . $currency . "'";
						echo ">";
						echo $currency;
						echo "</option>";
					}
					echo "</select>";
					echo "&nbsp;" . __('for','property') . "&nbsp;";

					echo "<select name='price_period[]'>";
					echo "<option value=''></option>";
					for($n=1; $n<=60; $n++) {
						echo "<option value='" . $n . "'";
						echo ">";
						echo $n;
						echo "</option>";
					}
					echo "</select>&nbsp;";
					echo "<select name='price_period_type[]'>";
					echo "<option value=''></option>";
					$periods = $this->build_period_array();
					$periods = apply_filters('staypress_price_periods', $periods );
					foreach($periods as $key => $period) {
						echo "<option value='" . $key . "'";
						echo ">";
						echo $period;
						echo "</option>";
					}
					echo "</select>";
					echo "&nbsp;";
					echo "<a href='' class='addpriceperiodrow' title='Add a new price row for this date'>&nbsp;</a>";
					echo "</div>";
				echo "</li>";

				echo "<li class='actioncolumn'>";
				echo "<a href='' class='addpricerow' title='Add another row of prices below'>&nbsp;</a>";

				echo "</li>";
			echo "</ul>";
			echo "</li>";


		echo "</ul>";

		echo "<input type='hidden' name='lastpricerow' id='lastpricerow' value='$row' />";

		// Price notes
		echo "<label for='propertypricenotes' class='main'>" . __('Pricing notes', 'property') . "</label>";
		echo "<textarea name='propertypricenotes' id='propertypricenotes' class='main wide short'>" . esc_html(stripslashes(get_post_meta($property->ID, '_property_price_notes', true))) . "</textarea>";



	}

	function show_property_facilities($id = false, $property = false, $error = false) {

		$metas = $this->property->get_metadesc();

		$metadata = $this->property->get_propertymeta($property->ID);

		$metasort = array();
		foreach((array) $metadata as $key => $value) {
			$metasort[$value->meta_id] = $value;
		}

		$metadata = $metasort;

		if($metas) {

			if($id) {
				echo "<h3>" . __('Edit Facilities','property') . "</h3>\n";
			} else {
				echo "<h3>" . __('Add Facilities','property') . "</h3>\n";
			}

			echo "<div class='clear'></div>";

			$groupid = false;

			foreach($metas as $meta) {
				if($groupid != $meta->metagroup_id) {
					if($groupid !== false) {
						echo "</ul>";
					}
					echo "<ul class='metagroup' id='metagroup-{$meta->metagroup_id}'>";

					echo "<li class='metagroupname'>";
					echo __($meta->groupname,'property');
					echo "</li>";
					$groupid = $meta->metagroup_id;
				}
				echo "<li class='metaitem'>";
				echo "<div class='metaname'>" . __($meta->metaname,'property') . "</div>";

				switch($meta->metatype) {
					case '1':	// Numeric
								echo "<input type='text' name='meta[" . $meta->id . "]' id='meta-" . $meta->id . "' class='metavalue numeric' value='";
								if(isset($metadata[$meta->id])) echo esc_attr(stripslashes($metadata[$meta->id]->meta_value));
								echo "' />";
								break;
					case '2':	// Text
								echo "<input type='text' name='meta[" . $meta->id . "]' id='meta-" . $meta->id . "' class='metavalue text' value='";
								if(isset($metadata[$meta->id])) echo esc_attr(stripslashes($metadata[$meta->id]->meta_value));
								echo "' />";
								break;
					case '3':	// Yes / No
								echo "<select name='meta[" . $meta->id . "]' id='meta-" . $meta->id . "' class='metavalue'>";

								echo "<option value=''";
								if(isset($metadata[$meta->id]) && $metadata[$meta->id]->meta_value == '') echo " selected='selected'";
								echo "></option>";
								echo "<option value='yes'";
								if(isset($metadata[$meta->id]) && $metadata[$meta->id]->meta_value == 'yes') echo " selected='selected'";
								echo ">" . __('Yes');
								echo "</option>";
								echo "<option value='no'";
								if(isset($metadata[$meta->id]) && $metadata[$meta->id]->meta_value == 'no') echo " selected='selected'";
								echo ">" . __('No');
								echo "</option>";

								echo "</select>";
								break;
					case '4':	// Option
								$options = explode("\n", $meta->metaoptions);
								if($options) {
									echo "<select name='meta[" . $meta->id . "]' id='meta-" . $meta->id . "' class='metavalue'>";
									echo "<option value=''";
									if(isset($metadata[$meta->id]) && $metadata[$meta->id]->meta_value == '') echo " selected='selected'";
									echo "></option>";
									foreach($options as $opt) {
										if(!empty($opt)) {
											echo "<option value='" . esc_attr(trim($opt)) . "'";
											if(isset($metadata[$meta->id]) && $metadata[$meta->id]->meta_value == esc_attr(trim($opt))) echo " selected='selected'";
											echo ">" . esc_html(trim($opt));
											echo "</option>";
										}
									}
									echo "</select>";
								} else {
									echo "<input type='text' name='meta[" . $meta->id . "]' id='meta-" . $meta->id . "' class='metavalue text' value='";
									if(isset($metadata[$meta->id])) echo esc_attr(stripslashes($metadata[$meta->id]->meta_value));
									echo "' />";
								}
								break;


				}

				echo "</li>";

			}
			echo "</ul>";
			echo "<div class='clear'></div>";
		}


	}

	function show_property_contact($id = false, $property = false, $error = false) {

		if($id) {
			echo "<h3>" . __('Edit Contact Details','property') . "</h3>\n";
		} else {
			echo "<h3>" . __('Add Contact Details','property') . "</h3>\n";
		}

		// Get the data

		$contacts = $this->property->get_propertycontacts($id);

		if(empty($contacts) || is_wp_error($contacts)) {
			$contact = new stdClass();
		} else {
			// We only want the first one in this case - for now anyway
			$contact = array_shift($contacts);
		}

		// Hidden fields
		echo "<input type='hidden' name='propertycontactid' id='propertycontactid' value='" . esc_attr(stripslashes( (isset($contact->ID) ? $contact->ID : '' ))) . "' class='main narrow' />";

		// Visible fields
		echo "<p>" . __('Enter the details of any additional person you want to be contacted with enquiries.','property') . "</p>";

		echo "<label for='propertycontactname' class='main'>" . __('Name', 'property') . "</label>";
		echo "<input type='text' name='propertycontactname' id='propertycontactname' value='" . esc_attr(stripslashes( (isset($contact->post_title) ? $contact->post_title : '' ))) . "' class='main narrow' />";

		if(!empty($contact->ID)) {
			$contactmetadata = get_post_custom($contact->ID);
		} else {
			$contactmetadata = array();
		}

		if(array_key_exists('contact_email', $contactmetadata) && is_array($contactmetadata['contact_email'])) {
			$contact->contact_email = array_shift($contactmetadata['contact_email']);
		} else {
			$contact->contact_email = '';
		}

		if(array_key_exists('contact_tel', $contactmetadata) && is_array($contactmetadata['contact_tel'])) {
			$contact->contact_tel = array_shift($contactmetadata['contact_tel']);
		}  else {
			$contact->contact_tel = '';
		}

		echo "<label for='propertycontactemail' class='main'>" . __('Email', 'property') . "</label>";
		echo "<input type='text' name='propertycontactemail' id='propertycontactemail' value='" . esc_attr(stripslashes($contact->contact_email)) . "' class='main narrow' />";

		echo "<label for='propertycontacttel' class='main'>" . __('Telephone', 'property') . "</label>";
		echo "<input type='text' name='propertycontacttel' id='propertycontacttel' value='" . esc_attr(stripslashes($contact->contact_tel)) . "' class='main narrow' />";

		echo "<label for='propertycontactnotes' class='main'>" . __('Notes (public)', 'property') . "</label>";
		echo "<textarea name='propertycontactnotes' id='propertycontactnotes' class='main wide short'>" . esc_html(stripslashes( (isset($contact->post_content) ? $contact->post_content : '' ))) . "</textarea>";


	}

	function show_description_label() {
		echo "<label for='propertydescription' class='main'>" . __('Description', 'property') . "</label>";
	}

	// Property listing functions

	function show_pagination($found, $startat) {
		//echo $found;
	}

	function show_nodata_page() {
		// No properties have been entered or found so go through the clean slate check list.

		echo "<p>" . __('Hello, before we get started entering your properties, there are one or two things we need to complete.','property') . "</p>";
		echo "<p>" . __('Complete the steps below and hopefully we will have you up and running in no time.','property') . "</p>";

		$count = 1;
		if(current_user_can('manage_categories')) {
			// Facilities
			echo "<p class='nodatalist'>";
			echo "<span class='nodatanumber'>" . $count++ . "</span>";
			$groups = $this->property->get_metagroups();
			if(!empty($groups)) {
				$class = 'completed';
			} else {
				$class = '';
			}
			echo "<span class='$class'>";
			echo "<a href='?page=property-facilities'>" . __('Create some facilities','property') . "</a>" . __(' for your properties so that you have some standard information for a user to search on.','property');
			echo "</span>";
			echo "</p>";

			// Tags
			echo "<p class='nodatalist'>";
			echo "<span class='nodatanumber'>" . $count++ . "</span>";
			$tags = wp_count_terms('propertyfeature');
			if(!empty($tags)) {
				$class = 'completed';
			} else {
				$class = '';
			}
			echo "<span class='$class'>";
			echo "<a href='?page=property-tags'>" . __('Create some initial tags.' , 'property') . "</a>" . __(' By creating some tags now, you are more likely to use a common list. This makes it easier for users to find related properties.','property');
			echo "</span>";
			echo "</p>";

		}

		// Property
		echo "<p class='nodatalist'>";
		echo "<span class='nodatanumber'>" . $count++ . "</span>";
		echo "<span>";
		echo "<a href='?page=property-add'>" . __("Add a property.",'property') . "</a>" . __("<br/>Seriously, that's it, you're ready to add that first property's details.",'property');
		echo "</span>";
		echo "</p>";

		echo "<p>&nbsp;</p>";

	}

	function show_noproperties_page() {

		// Message if there needs to be one
		$this->show_property_panel_messages();

		echo "<div class='notfound'>" . __('Sorry, I couldn\'t find any properties that match your criteria.','property') . "</div>";

	}

	function show_property_list($properties) {

		if($properties) {

			// Message if there needs to be one
			$this->show_property_panel_messages();

			foreach($properties as $key => $property) {

				if($property->post_parent == 0) {
					// This is a parent so find the children and display them
					$this->show_property_excerpt($property);

					$children = $this->property->get_childpropertylist( $property->ID );
					if(!empty( $children )) {
						$this->show_property_children($children);
					}
				}  else {
					// This is a child, so find the parent and display it first

					$parent = $this->property->get_property( $property->post_parent );

					if(!empty($parent)) {
						$this->show_property_excerpt($parent);
					}

					$this->show_property_excerpt($property);

				}


			}

		}

	}

	function show_property_children( $properties ) {

		foreach( $properties as $property ) {
			$this->show_property_excerpt( $property );
		}

	}

	function show_property_excerpt($property) {

		global $page, $action;

		if($property->post_parent == 0) {
			echo "<div id='propertylistitem-" . $property->ID . "' class='propertylistitem'>";
		} else {
			echo "<div id='propertylistitem-" . $property->ID . "' class='childpropertylistitem propertylistitem'>";
		}


		echo "<div class='thumbnailimage'>";

		$reference = $property->reference;

		if($this->property->has_property_thumbnail($property->ID)) {
			$url = $this->property->get_property_thumbnail_url($property->ID);
			$alt = __("Property : ","staypress") . stripslashes($reference);

			echo "<img src='$url' alt='$alt' class='thumbnail' />";

		} else {
			// Holding image for later
			$alt = __("Property : ","staypress") . stripslashes($reference);
			$url = SPPCommon::property_url("images/nothumbnail.png");
			echo "<img src='$url' alt='$alt' class='thumbnail' />";
		}
		echo "</div>";

		echo "<div class='propertyshortdetails'>";

		if(!empty($reference)) {
			echo "<a href='?page={$page}&amp;action=edit&amp;id={$property->ID}' class='propertyreference'>";
			echo stripslashes($reference);
			echo "</a>";
		}

		echo "<a href='?page={$page}&amp;action=edit&amp;id={$property->ID}' class='propertytitle'>";
		if(!empty($property->post_title))
			echo stripslashes($property->post_title);
		else
		 	echo __('(no title)', 'property');
		echo "</a>";

		echo "<div class='propertyshortdesc'><p>";

		if(!empty($property->post_excerpt)) {
			echo stripslashes($property->post_excerpt);
		} else {
			echo substr(strip_tags(stripslashes($property->post_content)),0,500);
		}

		echo "</p></div>";

		// Property links
		$actions = array();
		if(current_user_can('edit_property')) {
			$actions[] = "<a href='?page={$page}&amp;action=edit&amp;id={$property->ID}' class='propertyeditlink' title='" . __('Edit this property','property') . "'>" . __('Edit', 'property') . "</a>";
		}
		if(current_user_can('delete_property')) {
			$deleteurl = wp_nonce_url("?page={$page}&amp;action=delete&amp;id=" . $property->ID, 'deleteproperty-' . $property->ID );
			$actions[] = "<a href='" . $deleteurl . "' class='propertydeletelink delete' id='delete-{$property->ID}' title='" . __('Delete this property','property') . "'>" . __('Delete', 'property') .  "</a>";
		}

		$actions = apply_filters('staypress_property_links', $actions, $property->ID);

		// Property status
		$status = __($this->property->translate_status($property->post_status), 'property');

		echo "<div class='propertystatus " . strtolower($status) . "'>";
		echo "<span>" . stripslashes($status) . "</span>";
		echo "</div>";

		echo "</div>";

		echo "<div class='clear'></div>";

		echo "<div class='propertymeta'>";

		echo "<div class='propertyuser'>";
		echo __('Author : ', 'property');
		if(!empty($property->post_author)) {
			$author = new WP_User( $property->post_author );
			if(current_user_can('edit_users')) {
				echo "<a href='" . admin_url('users.php?usersearch=' . $author->user_login) . "'>";
			}
			echo $author->user_login;
			if(current_user_can('edit_users')) {
				echo "</a>";
			}
		} else {
			echo __('Unknown','property');
		}

		echo "&nbsp;&nbsp;&nbsp;&nbsp;";
		echo __('Updated : ', 'property');
		echo mysql2date("Y-m-d", $property->post_modified);
		echo "</div>";

		echo "<div class='propertylinks'>";
		echo implode(' | ', $actions);
		echo "</div>";

		echo "</div>";

		echo "</div>";

	}

	function show_tag_filter() {

		$taxes = get_object_taxonomies('property');
		if($taxes) {

			if(!empty($_GET['tagfilter'])) {
				$seltags = (array) $_GET['tagfilter'];
				$taglist = "";
			} else {
				$seltags = array();
			}

			echo "<h2>" . __('Filter list based on tags','property') . "</h2>";

			$group = '';

			echo "<select name='addtagselect' id='addtagselect' class='rightsidebarselect'>";
			echo "<option value=''></option>";
			foreach($taxes as $key => $value) {
				$mtax = get_taxonomy($value);
				// put tags in here
				$tags = get_terms( $value );
				if($tags) {
					echo "<optgroup label='";
					echo $mtax->label;
					echo "'>";
					foreach($tags as $tag) {
						echo "<option value='" . $tag->term_id . "'>";
						echo esc_html($tag->name);
						echo "</option>";

						if(in_array($tag->term_id, $seltags)) {
							$taglist .= "<li class='selectedtag'><a href='#filterremovetag' class='selectedtagremove' title=''>&nbsp;</a>";
							$taglist .= esc_html($tag->name);
							$taglist .= "<input type='hidden' name='tagfilter[]' value='" . $tag->term_id . "' /></li>";
						}
					}
					echo "</optgroup>";
				}
			}
			echo "</select>";

			echo "<a href='#filteraddtag' class='addtaglink' id='addtaglink' title='" . __('Add tag to the filter','property') . "'>";
			echo "<img src='" . SPPCommon::property_url('images/add.png') . "' alt='' />";
			echo "</a>";

			echo "<ul class='selectedtaglist'>";

			if(!empty($taglist)) {
				echo $taglist;
			}


			echo "</ul>";
		}

	}

	function show_meta_filter() {

		echo "<h2>" . __('Filter list based on facilities','property') . "</h2>";

		$metas = $this->property->get_metadesc();

		if(isset($_GET['meta'])) {
			$searched = (array) $_GET['meta'];
		} else {
			$searched = array();
		}


		if($metas) {
			$groupid = false;

			echo "<ul>";
			foreach($metas as $meta) {
				if($groupid != $meta->metagroup_id) {
					echo "<li>";
					echo "<div class='metagroupname'>" . __($meta->groupname,'property') . "</div>";
					echo "</li>";
					$groupid = $meta->metagroup_id;
				}
				echo "<li>";
				echo "<div class='metaname'>" . __($meta->metaname,'property') . "</div>";

				switch($meta->metatype) {
					case '1':	// Numeric
								echo "<input type='text' name='meta[" . $meta->id . "]' id='' class='metavalue numeric' value='";
								if(!empty($searched[$meta->id])) echo esc_attr(stripslashes($searched[$meta->id]));
								echo "' />";
								break;
					case '2':	// Text
								echo "<input type='text' name='meta[" . $meta->id . "]' id='' class='metavalue text' value='";
								if(!empty($searched[$meta->id])) echo esc_attr(stripslashes($searched[$meta->id]));
								echo "' />";
								break;
					case '3':	// Yes / No
								echo "<select name='meta[" . $meta->id . "]' id='' class='metavalue'>";

								echo "<option value=''";
								if(!empty($searched[$meta->id]) && $searched[$meta->id] == '') echo " selected='selected'";
								echo "></option>";
								echo "<option value='yes'";
								if(!empty($searched[$meta->id]) && $searched[$meta->id] == 'yes') echo " selected='selected'";
								echo ">" . __('Yes');
								echo "</option>";
								echo "<option value='no'";
								if(!empty($searched[$meta->id]) && $searched[$meta->id] == 'no') echo " selected='selected'";
								echo ">" . __('No');
								echo "</option>";

								echo "</select>";
								break;
					case '4':	// Option
								$options = explode("\n", $meta->metaoptions);
								if(!empty($options)) {
									echo "<select name='meta[" . $meta->id . "]' id='' class='metavalue'>";
									echo "<option value=''";
									if(!empty($searched[$meta->id]) && $searched[$meta->id] == '') echo " selected='selected'";
									echo "></option>";
									foreach($options as $opt) {
										if(!empty($opt)) {
											echo "<option value='" . esc_attr(trim($opt)) . "'";
											if(!empty($searched[$meta->id]) && $searched[$meta->id] == esc_attr(trim($opt))) echo " selected='selected'";
											echo ">" . esc_html(trim($opt));
											echo "</option>";
										}
									}
									echo "</select>";
								} else {
									echo "<input type='text' name='meta[" . $meta->id . "]' id='' class='metavalue text' value='";
									if(!empty($searched[$meta->id])) echo esc_attr(stripslashes($searched[$meta->id]));
									echo "' />";
								}
								break;
				}

				echo "</li>";

			}
			echo "</ul>";
		}

	}

	function show_deleted_filter() {

		echo "<h2>" . __('Include deleted properties','property') . "</h2>";

		echo "<p>";
		echo "<input type='checkbox' id='includedeleted' name='includedeleted' value='yes'";
		if(!empty($_GET['includedeleted']) && addslashes($_GET['includedeleted']) == 'yes') echo " checked='checked'";
		echo " />";
		echo "<label for='includedeleted' id='includedeletedlabel'>" . __('Include deleted properties.','property') . "</label>";
		echo "</p>";

	}


	function handle_property_rightpanel() {

		switch(addslashes($_GET['action'])) {

			case 'edit':	if(isset($_GET['id'])) {
								$id = intval($_GET['id']);
								$this->show_propertystatus_rightpanel($id);
								$this->quicktest();
								$this->show_images_rightpanel($id);
							}
							break;

			default:
						$this->show_property_rightpanel();

		}

	}

	function show_property_rightpanel() {

		// Search
		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlestatus"><br></div>';
		echo "<h2 class='searchformheading rightbarheading'>" . __('Find properties','property') . "</h2>";
		echo "<div class='innersidebarbox'>";
		echo "<form action='' method='get' name='searchform' id='searchform'>";
		echo "<input type='hidden' name='page' value='" . addslashes($_GET['page']) . "' />";
		echo "<input type='text' name='propertysearch' id='propertysearch' value='";
		if(!empty($_GET['propertysearch'])) esc_html_e(stripslashes($_GET['propertysearch']));
		echo "' class='propertysearch' />";
		echo "<br/>";
		echo "<input type='submit' id='propertysearchbutton' name='propertysearchbutton' value='" . __('Search', 'property') . "' class='button' />";
		echo "</form>";
		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";
		echo "<br/>";

		// Start the filter form
		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlestatus"><br></div>';
		echo "<h2 class='filterformheading rightbarheading'>" . __('Filter property list','property') . "</h2>";
		echo "<div class='innersidebarbox'>";
		echo "<form action='' method='get' name='filterform' id='filterform'>";
		echo "<input type='hidden' name='page' value='" . addslashes($_GET['page']) . "' />";
		// Calendar find
		do_action('staypress_property_filter');
		// Tag find
		$this->show_tag_filter();

		$this->show_meta_filter();

		$this->show_deleted_filter();


		echo "<input type='submit' id='filteronall' name='filteronall' value='" . __('Filter results', 'property') . "' class='button' />";

		echo "</form>";
		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";


	}

	function show_propertystatus_rightpanel($property) {

		// status box

		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlestatus"><br></div>';
		echo "<h2 class='rightbarheading'>" . __('Property Status','property');
		echo "</h2>";

		echo "<div class='innersidebarbox'>";
		echo "<div class='statuslabel'>" . __('Status','property');

		echo "<input type='hidden' name='oldstatus' id='oldstatus' value='" . esc_attr( (isset($property->post_status) ? $property->post_status : '' )) . "' />\n";

		$statusoptions = $this->property->get_statuslist();
		echo "<select name='status' id='savestatus'>";
		if($property->post_status == 'trash') {
			echo "<option value='trash' selected='selected'>" . __('Trash') . "</option>";
		}
		foreach($statusoptions as $key => $value) {

			if($key == 'publish' && !current_user_can( 'publish_properties' )) {
				continue;
			}

			echo "<option value='" . $key . "'";
			if(isset($property->post_status) && $property->post_status == $key) {
				echo " selected='selected'";
			}
			echo ">" . $value . "</option>";
		}
		echo "</select>";
		echo "</div>";

		echo "<div class='statusbuttons'>";

		//current_user_can( 'publish_properties' )
		if( isset($property->post_status) && in_array($property->post_status, array('private', 'publish')) ) {

			echo "<input type='submit' name='save' value='" . __('Save','property') . "' class='button-primary' />";

		} elseif( isset($property->post_status) && in_array($property->post_status, array('pending', 'draft', 'trash', '')) ) {

			if(current_user_can( 'publish_properties' )) {
				echo "<input type='submit' name='publish' value='" . __('Publish','property') . "' class='button-primary' />";
				echo "<input type='submit' name='save' value='" . __('Save','property') . "' class='button' />";
			} else {
				echo "<input type='submit' name='submit' value='" . __('Submit','property') . "' class='button-primary' />";
				echo "<input type='submit' name='save' value='" . __('Save','property') . "' class='button' />";
			}

		}

		echo "<a href='" . wp_get_referer() . "' class='cancellink' title='Cancel editing and return to property list'>Cancel</a>";
		echo "</div>";

		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";

	}

	function show_propertyparent_rightpanel($property) {

		if($this->property->property_has_children( $property->ID )) {
			return false;
		}

		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlestatus"><br></div>';
		echo "<h2 class='rightbarheading'>" . __('Property Parent','property');
		echo "</h2>";

		echo "<div class='innersidebarbox'>";
		//echo "<div class='statuslabel'>";

		$properties = $this->property->get_propertylist(0, 9999);

		echo "<select name='post_parent' id='post_parent'>";
				echo "<option value='0'>" . __('Select a parent', 'property') . "</option>";
		foreach($properties as $parent) {
			echo "<option value='" . $parent->ID . "' ";
			if($parent->ID == $property->post_parent) {
				echo "selected='selected'";
			}
			echo ">";
			if(!empty($parent->reference)) {
				echo $parent->reference . " - ";
			}
			echo $parent->post_title;
			echo "</option>";
		}
		echo "</select>";

		//echo "</div>";

		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";

	}

	function show_hierachical_metabox( $post, $box ) {
		$defaults = array('taxonomy' => 'category');
		if ( !isset($box['args']) || !is_array($box['args']) )
			$args = array();
		else
			$args = $box['args'];
		extract( wp_parse_args($args, $defaults), EXTR_SKIP );
		$tax = get_taxonomy($taxonomy);

		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" ><br></div>';
		echo "<h2 class='rightbarheading'>" . esc_html($tax->label) . "</h2>";

		echo "<div class='innersidebarbox'>";

		?>
		<div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
			<ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
				<li class="tabs"><a href="#<?php echo $taxonomy; ?>-all" tabindex="3"><?php echo $tax->labels->all_items; ?></a></li>
				<li class="hide-if-no-js"><a href="#<?php echo $taxonomy; ?>-pop" tabindex="3"><?php _e( 'Most Used' ); ?></a></li>
			</ul>

			<div id="<?php echo $taxonomy; ?>-pop" class="tabs-panel" style="display: none;">
				<ul id="<?php echo $taxonomy; ?>checklist-pop" class="categorychecklist form-no-clear" >
					<?php $popular_ids = wp_popular_terms_checklist($taxonomy); ?>
				</ul>
			</div>

			<div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
				<?php
	            $name = ( $taxonomy == 'category' ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
	            echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
	            ?>
				<ul id="<?php echo $taxonomy; ?>checklist" class="list:<?php echo $taxonomy?> categorychecklist form-no-clear">
					<?php wp_terms_checklist($post->ID, array( 'taxonomy' => $taxonomy, 'popular_cats' => $popular_ids ) ) ?>
				</ul>
			</div>
		<?php if ( !current_user_can($tax->cap->assign_terms) ) : ?>
		<p><em><?php _e('You cannot modify this taxonomy.'); ?></em></p>
		<?php endif; ?>
		<?php if ( current_user_can($tax->cap->edit_terms) ) : ?>
				<div id="<?php echo $taxonomy; ?>-adder" class="wp-hidden-children">
					<h4>
						<a id="<?php echo $taxonomy; ?>-add-toggle" href="#<?php echo $taxonomy; ?>-add" class="hide-if-no-js" tabindex="3">
							<?php
								/* translators: %s: add new taxonomy label */
								printf( __( '+ %s' ), $tax->labels->add_new_item );
							?>
						</a>
					</h4>
					<p id="<?php echo $taxonomy; ?>-add" class="category-add wp-hidden-child">
						<label class="screen-reader-text" for="new<?php echo $taxonomy; ?>"><?php echo $tax->labels->add_new_item; ?></label>
						<input type="text" name="new<?php echo $taxonomy; ?>" id="new<?php echo $taxonomy; ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $tax->labels->new_item_name ); ?>" tabindex="3" aria-required="true"/>
						<label class="screen-reader-text" for="new<?php echo $taxonomy; ?>_parent">
							<?php echo $tax->labels->parent_item_colon; ?>
						</label>
						<?php wp_dropdown_categories( array( 'taxonomy' => $taxonomy, 'hide_empty' => 0, 'name' => 'new'.$taxonomy.'_parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => '&mdash; ' . $tax->labels->parent_item . ' &mdash;', 'tab_index' => 3 ) ); ?>
						<input type="button" id="<?php echo $taxonomy; ?>-add-submit" class="add:<?php echo $taxonomy ?>checklist:<?php echo $taxonomy ?>-add button category-add-sumbit" value="<?php echo esc_attr( $tax->labels->add_new_item ); ?>" tabindex="3" />
						<?php wp_nonce_field( 'add-'.$taxonomy, '_ajax_nonce-add-'.$taxonomy, false ); ?>
						<span id="<?php echo $taxonomy; ?>-ajax-response"></span>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";
	}

	function show_property_tags_rightpanel($property) {

		$taxes = get_object_taxonomies(STAYPRESS_PROPERTY_POST_TYPE);
		if($taxes) {
			foreach($taxes as $key => $value) {
				if(is_taxonomy_hierarchical($value)) {
					//$mtax = get_taxonomy($value);
					// Test with standard box
					$this->show_hierachical_metabox( $property, array( 'args' => array( 'taxonomy' => $value ) ));

				} else {
					$mtax = get_taxonomy($value);

					echo "<div class='sidebarbox'>";
					echo '<div title="Click to toggle" class="handlediv" ><br></div>';
					echo "<h2 class='rightbarheading'>" .esc_html($mtax->label) . "</h2>";

					echo "<div class='innersidebarbox'>";
					$tax_name = $value;
					?>
					<div class="tagsdiv" id="<?php echo $tax_name; ?>">
						<div class="jaxtag">
							<div class="nojs-tags hide-if-js">
								<p><?php _e('Add or remove tags'); ?></p>
								<textarea name="<?php echo "tax_input[$tax_name]"; ?>" class="the-tags" id="tax-input[<?php echo $tax_name; ?>]"><?php echo esc_attr(get_terms_to_edit( $property->ID, $tax_name )); ?></textarea>
							</div>

							<div class="ajaxtag hide-if-no-js">
								<label class="screen-reader-text" for="new-tag-<?php echo $tax_name; ?>"><?php echo $mtax->label; ?></label>
								<div class="taghint"><?php _e('Add new tag'); ?></div>
								<input type="text" id="new-tag-<?php echo $tax_name; ?>" name="newtag[<?php echo $tax_name; ?>]" class="newtag form-input-tip" size="16" autocomplete="off" value="" />
								<input type="button" class="button tagadd" value="<?php esc_attr_e('Add'); ?>" tabindex="3" />
							</div>
						</div>
						<p class="howto"><?php echo _('Separate tags with commas.'); ?></p>
						<div class="tagchecklist"></div>
					</div>
					<p class="hide-if-no-js"><a href="#titlediv" class="tagcloud-link" id="link-<?php echo $tax_name; ?>"><?php printf( __('Choose from the most used tags in %s'), $mtax->label ); ?></a></p>
					<?php
					echo "</div> <!-- innersidebarbox -->";
					echo "</div> <!-- sidebarbox -->";
				}

			}
		}
	}

	function show_images_rightpanel($property) {

		// Function to fix the property images, to be removed before release
		//$property = $this->property->get_property($propertyid);

		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" ><br></div>';
		echo "<h2 class='rightbarheading'>" . __('Property Images','property') . "</h2>";

		$formaction = admin_url("admin-ajax.php?action=_imageupload&amp;postid=" . $property->ID);

		echo "<div class='innersidebarbox'>";

		echo "<div id='imagesrightpanel'>";

		echo "<div class='highlightbox blue'>";
		echo "<form action='$formaction' method='post' enctype='multipart/form-data' name='uploadimageform' id='uploadimageform'>";

		echo "<p>";
		echo __('To add a new image, click on the <strong>Add Image</strong> button below to pick your image for automatic upload.','property');
		echo "</p>";

		echo "<div class='imageuploadholder'>";
		echo "<input type='file' id='uploadimagefile' name='uploadimagefile'>";
		echo "<img src='" . SPPCommon::property_url('images/addimagebuttonplus.png') . "' id='uploadimage' alt='" . SPPCommon::property_url('images/addimagebuttonloading.png') . "' />";

		echo "</div>";

		echo "</form>";
		echo "</div>";

		echo "<div id='uploadstatusmessage'>";
		echo "<p>" . __('Image Uploaded', 'property') . "</p>";
		echo "</div>";

		echo "<div class='imageblock' id='imageblock'>";

		$images = $this->property->get_property_images($property->ID);

		$mainimage = false;
		$listimage = false;

		$imagehtml = '';

		$imagehtml .= "<ul id='imagelist' class='editimagelist'>";

		// Show the images
		if($images) {

			$listimageid = $this->property->get_property_thumbnail_id($property->ID);
			$mainimageid = $this->property->get_property_mainimage_id($property->ID);

			foreach($images as $image) {

				if(wp_attachment_is_image($image->ID)) {

					$url = wp_get_attachment_thumb_url($image->ID);

					if(empty($url)) continue;

					if(!$listimage && !empty($listimageid)) {
						if($image->ID == $listimageid) $listimage = $url;
					}

					if(!$mainimage && !empty($mainimageid)) {
						if($image->ID == $mainimageid) $mainimage = $url;
					}

					$imagehtml .= "<li class='editimageitem' id='imageitem-" . $image->ID . "'>";
					$imagehtml .= "<img src='" . $url . "' id='image-" . $image->ID . "' alt='' class='editimage' />";

					$imagehtml .= "<div class='imgnavholder'>";
					// Add the delete image icon
					$nonce = wp_create_nonce('deleteimage-' . $image->ID);
					$ajaxurl = admin_url("admin-ajax.php?action=_deleteimage&amp;id=" . $image->ID . "&amp;_ajax_nonce=" . $nonce);
					$imagehtml .= "<a href='" . $ajaxurl . "' class='delimagelink' id='delimage-" . $image->ID . "' title='" . __('Delete this image','property') . "'></a>";
					$imagehtml .= "</div>";

					$imagehtml .= "</li>";

				}

			}

		}

		$imagehtml .= "</ul>";

		// Show the main image and listing image boxes
		echo "<div class='imageprocessors'>";
		echo "<ul class='keyimageholders'>";

		echo "<li class='imageholder mainimage'>";
		if($mainimage !== false) {
			echo "<img src='" . $mainimage . "' alt='' />";
		} else {
			echo "<img src='" . SPPCommon::property_url('images/opaque.png') . "' alt='' />";
		}
		echo "<div>" . __("Main Image", 'property') . "</div>";
		echo "</li>";

		if(!empty($listimage)) {
			$style = "style='background: #FFF url(" . $listimage . ") no-repeat center center;'";
		}

		echo "<li class='imageholder listimage'>";
		if($listimage !== false) {
			echo "<img src='" . $listimage . "' alt='' />";
		} else {
			echo "<img src='" . SPPCommon::property_url('images/opaque.png') . "' alt='' />";
		}
		echo "<div>" . __("&nbsp;List Image", 'property') . "</div>";
		echo "</li>";
		echo "</ul>";

		echo "</div>";

		// Display the actual image list
		if(!empty($imagehtml)) echo $imagehtml;

		echo "<div class='clear'></div>";
		echo "</div>";

		echo "<div class='clear'></div>";
		echo "</div>";

		echo "</div> <!-- innersidebarbox -->";

		echo "</div>";

	}

	function show_options_rightpanel() {

		if(!defined('STAYPRESS_HIDE_DONATIONS')) {
			echo "<div class='sidebarbox'>";
			echo '<div title="Click to toggle" class="handlediv" id="handlestatus"><br></div>';
			echo "<h2 class='rightbarheading'>" . __('Show Your Support','property') . "</h2>";
			echo "<div class='innersidebarbox'>";


			echo "<div class='highlightbox blue'>";
			echo "<p>";
			echo __('We don\'t take donations. Instead, we pick a charity every month and ask you to donate directly to them if you feel the urge to give.','property');
			echo "</p>";
			echo "</div>";

			echo "<div class='highlightbox'>";
			echo "<p>";
			echo __('<strong>Support Bite-Back</strong><br/><br/>Bite-Back is a pioneering shark and marine conservation charity which is running successful campaigns to end the sale of shark fin soup in Britain.<br/><br/>','property');
			echo '<img src="' . SPPCommon::property_url('images/biteback.jpg') . '" alt="bite-back" style="margin-left: 30px;" />';
			echo "</p>";

			echo "<p>";
			echo __('To find out more about Bite-Back visit their website <a href="http://www.bite-back.com/">here</a>.<br/><br/><strong>To make a direct donation please go <a href="https://uk.virginmoneygiving.com/fundraiser-web/donate/makeDonationForCharityDisplay.action?charityId=1002357&frequencyType=S">here</a></strong>.','property');
			echo "</p>";

			echo "<p>";
			echo __('If you make a donation, then please let us know and we\'ll make sure we put out a big thank you on our site.','property');
			echo "</p>";
			echo "</div>";

			echo "<br/>";

			echo "</div> <!-- innersidebarbox -->";
			echo "</div> <!-- sidebarbox -->";
			echo "<br/>";

		}

	}

	function temporary_uploaddir_override( $uploaddetails ) {

		$pid = (int) $_GET['postid'];

		$siteurl = get_option( 'siteurl' );
		$upload_path = get_option( 'upload_path' );
		$upload_path = trim($upload_path);
		if ( empty($upload_path) ) {
			$dir = WP_CONTENT_DIR . '/uploads';
		} else {
			$dir = $upload_path;
			if ( 'wp-content/uploads' == $upload_path ) {
				$dir = WP_CONTENT_DIR . '/uploads';
			} elseif ( 0 !== strpos($dir, ABSPATH) ) {
				// $dir is absolute, $upload_path is (maybe) relative to ABSPATH
				$dir = path_join( ABSPATH, $dir );
			}
		}

		if ( !$url = get_option( 'upload_url_path' ) ) {
			if ( empty($upload_path) || ( 'wp-content/uploads' == $upload_path ) || ( $upload_path == $dir ) )
				$url = WP_CONTENT_URL . '/uploads';
			else
				$url = trailingslashit( $siteurl ) . $upload_path;
		}

		if ( defined('UPLOADS') && ( WP_CONTENT_DIR . '/uploads' != ABSPATH . $upload_path ) ) {
			$dir = ABSPATH . UPLOADS;
			$url = trailingslashit( $siteurl ) . UPLOADS;
		}

		if ( is_multisite() && ( WP_CONTENT_DIR . '/uploads' != ABSPATH . $upload_path ) ) {
			if ( defined( 'BLOGUPLOADDIR' ) )
				$dir = untrailingslashit(BLOGUPLOADDIR);
			$url = str_replace( UPLOADS, 'files', $url );
		}

		$bdir = $dir;
		$burl = $url;

		$subdir = '/property/' . $pid;


		$dir .= $subdir;
		$url .= $subdir;

		 return array( 'path' => $dir, 'url' => $url, 'subdir' => $subdir, 'basedir' => $bdir, 'baseurl' => $burl, 'error' => false );
	}

	// Ajax processing functions
	function upload_image($pid) {

		require_once(ABSPATH . 'wp-admin/includes/media.php');

		// todo - limit mime types to images

		add_filter( 'upload_dir', array(&$this, 'temporary_uploaddir_override') );
		$id = media_handle_upload('uploadimagefile', $pid);
		remove_filter( 'upload_dir', array(&$this, 'temporary_uploaddir_override') );

		if(!is_wp_error($id)) {
			// Uploaded main image, now could do with shrinking it to a thumbnail
			// We need to add in the image files for this version
			require_once(ABSPATH . 'wp-admin/includes/image.php');

			$nonce = wp_create_nonce('deleteimage-' . $id);
			$ajaxurl = admin_url("admin-ajax.php?action=_deleteimage&amp;id=" . $id . "&amp;_ajax_nonce=" . $nonce);

			$status = array(	'errorcode' => '200',
								'message' => 'File uploaded correctly',
								'imageid' => $id,
								'imgurl' => wp_get_attachment_thumb_url($id),
								'deleteurl' => $ajaxurl
								);
			return $status;


		} else {

			$status = array('errorcode' => '401', 'message' => $id->get_error_message());
			return $status;
		}

	}

	function rename_propertydirectory($oldid, $newid) {
		// renames the directories for the uploaded images

		$upload = get_option('upload_path');
		$path = str_replace(ABSPATH, '', trim($upload));
		$dir = ABSPATH . $path;

		$dir = trailingslashit($dir) . 'property/';

		$olddir = $dir . $oldid;
		$newdir = $dir . $newid;

		if(file_exists($olddir) && rename($olddir,$newdir)) {
			return true;
		} else {
			return false;
		}

	}

	function removeimagefile($imageid) {
			$upload = get_option('upload_path');
			$path = str_replace(ABSPATH, '', trim($upload));
			$dir = ABSPATH . $path;

			$dir = trailingslashit($dir) . 'property/';

			$image = $this->property->get_property_image($imageid);
			if($image) {

				$file = $this->build_image_dir($image->property_id, $image, false);
				@unlink($file);
				$file = $this->build_image_dir($image->property_id, $image, '210');
				@unlink($file);
				$file = $this->build_image_dir($image->property_id, $image, '420');
				@unlink($file);

			}
		}

	function is_valid_email($email)
	{
		return (eregi ("^([a-z0-9_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,4}$", $email));
	}

	// Facilities panel and updates
	function update_property_facilities() {

		global $action, $page, $facility;

		wp_reset_vars( array('action', 'facility', 'page') );

		if ( isset( $_GET['action'] ) && isset($_GET['delete_facs']) && ( 'delete' == $_GET['action'] || 'delete' == $_GET['action2'] ) ) {
			$action = 'bulk-delete';
		}

		if( ( ( isset($_GET['doaction']) && 'Add Group' == $_GET['doaction']) || ( isset($_GET['doaction2']) && 'Add Group' == $_GET['doaction2'])) && $action != 'addedfacgroup') {
			$action = 'addgroup';
		}

		if( ( ( isset($_GET['doaction']) && 'Add Facility' == $_GET['doaction']) || (isset($_GET['doaction2']) && 'Add Facility' == $_GET['doaction2'])) && $action != 'addedfac') {
			$action = 'add';
		}

		switch($action) {

			case 'deletegroup':
					$fac_ID = (int) $_GET['fac_ID'];
					check_admin_referer('delete-facgroup_' . $fac_ID);
					if ( !current_user_can('manage_categories') )
						wp_die(__('Cheatin&#8217; uh?'));

					if($this->property->delete_metagroup($fac_ID)) {
						wp_safe_redirect( add_query_arg('msg', 8, wp_get_referer()));
					} else {
						wp_safe_redirect( add_query_arg('msg', 12, wp_get_referer()));
					}

					break;

			case 'editedfacgroup':
					$facgroup_ID = (int) $_POST['facgroup_ID'];
					check_admin_referer('update-facgroup_' . $facgroup_ID);
					if ( !current_user_can('manage_categories') )
						wp_die(__('Cheatin&#8217; uh?'));

					if($this->property->update_metagroup($facgroup_ID, $_POST['groupname'])) {
						wp_safe_redirect( add_query_arg('msg', 9, 'admin.php?page=' . $page));
					} else {
						wp_safe_redirect( add_query_arg('msg', 11, 'admin.php?page=' . $page));
					}

					break;

			case 'addedfacgroup':
					check_admin_referer('update-facgroup_-1');
					if ( !current_user_can('manage_categories') )
						wp_die(__('Cheatin&#8217; uh?'));

					if($this->property->add_metagroup($_POST['groupname'])) {
						wp_safe_redirect( add_query_arg('msg', 7, 'admin.php?page=' . $page));
					} else {
						wp_safe_redirect( add_query_arg('msg', 10, 'admin.php?page=' . $page));
					}

					break;

			case 'addedfac':
					check_admin_referer('update-fac_-1');
					if ( !current_user_can('manage_categories') )
						wp_die(__('Cheatin&#8217; uh?'));

					$fac = array();
					$fac['metagroup_id'] = $_POST['metagroup'];
					$fac['metaname'] = $_POST['metaname'];
					$fac['metatype'] = $_POST['metatype'];
					$fac['metaoptions'] = $_POST['metaoptions'];

					if(!empty($fac['metaname']) && !empty($fac['metagroup_id'])) {
						if($this->property->add_meta($fac)) {
							wp_safe_redirect( add_query_arg('msg', 1, 'admin.php?page=' . $page));
						} else {
							wp_safe_redirect( add_query_arg('msg', 4, 'admin.php?page=' . $page));
						}
					} else {
						wp_safe_redirect( add_query_arg('msg', 4, 'admin.php?page=' . $page));
					}

					break;

			case 'editedfac':
					$fac_ID = (int) $_POST['fac_ID'];
					check_admin_referer('update-fac_' . $fac_ID);
					if ( !current_user_can('manage_categories') )
						wp_die(__('Cheatin&#8217; uh?'));

					$fac = array();
					$fac['metagroup_id'] = $_POST['metagroup'];
					$fac['metaname'] = $_POST['metaname'];
					$fac['metatype'] = $_POST['metatype'];
					$fac['metaoptions'] = $_POST['metaoptions'];

					if(!empty($fac['metaname'])) {
						if($this->property->update_meta($fac_ID, $fac)) {
							wp_safe_redirect( add_query_arg('msg', 3, 'admin.php?page=' . $page));
						} else {
							wp_safe_redirect( add_query_arg('msg', 5, 'admin.php?page=' . $page));
						}
					} else {
						wp_safe_redirect( add_query_arg('msg', 5, 'admin.php?page=' . $page));
					}

					break;

			case 'delete':
				$fac_ID = (int) $_GET['fac_ID'];
				check_admin_referer('delete-fac_' .  $fac_ID);

				if ( !current_user_can('manage_categories') )
					wp_die(__('Cheatin&#8217; uh?'));

				if($this->property->delete_meta($fac_ID)) {
					wp_safe_redirect( add_query_arg('msg', 2, wp_get_referer()));
				} else {
					wp_safe_redirect( add_query_arg('msg', 6, wp_get_referer()));
				}

			break;

			case 'bulk-delete':
				check_admin_referer('bulk-facs');
				if ( !current_user_can('manage_categories') )
					wp_die(__('Cheatin&#8217; uh?'));

				$facs = $_GET['delete_facs'];
				foreach( (array) $facs as $fac_ID ) {
					$this->property->delete_meta($fac_ID);
				}

				wp_safe_redirect( add_query_arg('msg', 13, wp_get_referer()));
				break;

		}

	}

	function show_facilities_panel() {

		global $action, $page, $facility;

		switch($action) {

			case 'editgroup':
					if ( !current_user_can('manage_categories') )
						wp_die(__('Cheatin&#8217; uh?'));

					// Show the edit group form
					$fac_ID = (int) $_GET['fac_ID'];
					$this->show_edit_facgroup_form($fac_ID);
					return;
					break;

			case 'addgroup':
					check_admin_referer('bulk-facs');

					if ( !current_user_can('manage_categories') )
						wp_die(__('Cheatin&#8217; uh?'));

					$this->show_edit_facgroup_form();
					return;
					break;

			case 'add':
					check_admin_referer('bulk-facs');

					if ( !current_user_can('manage_categories') )
						wp_die(__('Cheatin&#8217; uh?'));

					$this->show_edit_fac_form();
					return;
			break;

			case 'edit':
					$title = __('Edit Tag');

					$fac_ID = (int) $_GET['fac_ID'];

					$this->show_edit_fac_form($fac_ID);
					return;
					break;

		}

		$messages = array();
		$messages[1] = __('Facility added.');
		$messages[2] = __('Facility deleted.');
		$messages[3] = __('Facility updated.');
		$messages[4] = __('Facility not added.');
		$messages[5] = __('Facility not updated.');
		$messages[6] = __('Facility not deleted.');
		$messages[13] = __('Facilities deleted.');

		$messages[7] = __('Facility group added.');
		$messages[8] = __('Facility group deleted.');
		$messages[9] = __('Facility group updated.');
		$messages[10] = __('Facility group not added.');
		$messages[11] = __('Facility group not updated.');
		$messages[12] = __('Facility group not deleted.');

		$title = __('Edit facilities','property');

		echo '<div class="wrap nosubsub">';
		echo '<div class="icon32" id="icon-edit"><br></div>';
		echo '<h2>' . esc_html( $title );
		if ( isset($_GET['s']) && $_GET['s'] )
			printf( '<span class="subtitle">' . __('Search results for &#8220;%s&#8221;') . '</span>', esc_html( stripslashes($_GET['s']) ) );
		echo '</h2>';

		if ( isset($_GET['msg']) ) {
			echo '<div id="message" class="updated fade"><p>' . $messages[addslashes($_GET['msg'])] . '</p></div>';
			$_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
		}

		echo '<form class="search-form" action="" method="get">';
		echo '<input type="hidden" name="taxonomy" value="' . (isset($taxonomy) ? esc_attr($taxonomy) : '') .'" />';
		echo '<input type="hidden" name="page" value="' . esc_attr($page) .'" />';
		echo '<p class="search-box">';
			echo '<label class="screen-reader-text" for="tag-search-input">' . __( 'Search Tags' ) . ':</label>';
			echo '<input type="text" id="tag-search-input" name="s" value="' . esc_attr( stripslashes( (isset($_GET['s']) ? $_GET['s'] : '') ) ) . '" />';
			echo '<input type="submit" value="' . esc_attr( 'Search Facilities' ) . '" class="button" />';
		echo '</p>';
		echo '</form>';
		echo '<br class="clear" />';

		echo '<div id="col-container">';

		echo '<div class="col-wrap">';
		echo '<form id="posts-filter" action="" method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr($page) .'" />';
		echo '<div class="tablenav">';

		$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 0;
		if ( empty($pagenum) )
			$pagenum = 1;

		$tags_per_page = get_user_option('edit_tags_per_page');
		if ( empty($tags_per_page) )
			$tags_per_page = 20;
		$tags_per_page = apply_filters('edit_tags_per_page', $tags_per_page);
		$tags_per_page = apply_filters('tagsperpage', $tags_per_page); // Old filter

		$searchterms = isset( $_GET['s'] ) ? trim( $_GET['s'] ) : '';

		$metadesc = $this->property->get_metadesc($searchterms, ($pagenum - 1) * $tags_per_page, $tags_per_page);
		$totalmeta = $this->property->get_foundrows();

		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => ceil($totalmeta / $tags_per_page),
			'current' => $pagenum
		));

		if ( $page_links ) echo "<div class='tablenav-pages'>$page_links</div>";

		echo '<div class="alignleft actions">';
		echo '<select name="action">';
		echo '<option value="" selected="selected">' . __('Bulk Actions') . '</option>';
		echo '<option value="delete">' . __('Delete facility', 'property') . '</option>';
		echo '</select>';
		echo '<input type="submit" value="' . esc_attr('Apply') . '" name="doaction" id="doaction" class="button-secondary action" />';
		wp_nonce_field('bulk-facs');

		echo '&nbsp;';
		echo '<input type="submit" value="' . esc_attr('Add Group') . '" name="doaction" id="doaction" class="button-secondary action" />';
		echo '<input type="submit" value="' . esc_attr('Add Facility') . '" name="doaction" id="doaction" class="button-secondary action" />';

		echo '</div>';



		echo '<br class="clear" />';
		echo '</div>';

		echo '<div class="clear"></div>';

		echo '<table class="widefat tag fixed" cellspacing="0">';
		echo '<thead>';
		echo '<tr>';

		$columns = array( 	'cb' => '<input type="checkbox" />',
							'group'	=>	__('Group','property'),
							'facility'	=> __('Facility', 'property'),
							'type'	=>	__('Type', 'property')
						);
		foreach ( $columns as $column_key => $column_display_name ) {
			$class = ' class="manage-column';
			$class .= " column-$column_key";
			if ( 'cb' == $column_key ) {
				$class .= ' check-column';
			} elseif ( in_array($column_key, array('properties')) ) {
				$class .= ' num';
			}
			$class .= '"';
			$style = '';

			if ( isset($type) && isset($styles[$type]) && isset($styles[$type][$column_key]) ) {
				$style .= ' ' . $styles[$type][$column_key];
			}
			$style = ' style="' . $style . '"';

			echo '<th scope="col"';
			echo isset($id) ? "id=\"$column_key\"" : ""; echo $class; echo $style;
			echo '>' . $column_display_name . '</th>';
		}
		echo '</tr>';
		echo '</thead>';

		echo '<tfoot>';
		echo '<tr>';
		reset($columns);
		foreach ( $columns as $column_key => $column_display_name ) {
			$class = ' class="manage-column';
			$class .= " column-$column_key";
			if ( 'cb' == $column_key ) {
				$class .= ' check-column';
			} elseif ( in_array($column_key, array('properties')) ) {
				$class .= ' num';
			}
			$class .= '"';
			$style = '';

			if ( isset($type) && isset($styles[$type]) && isset($styles[$type][$column_key]) ) {
				$style .= ' ' . $styles[$type][$column_key];
			}
			$style = ' style="' . $style . '"';

			echo '<th scope="col"';
			echo isset($id) ? "id=\"$column_key\"" : ""; echo $class; echo $style;
			echo '>' . $column_display_name . '</th>';
		}
		echo '</tr>';
		echo '</tfoot>';

		echo '<tbody id="the-list" class="list:tag">';

		if(!empty($metadesc)) {
			$groupid = 0;
			foreach($metadesc as $key => $meta) {
				echo "<tr id='fac-" . $meta->id . "'>";
					echo "<th class='check-column' scope='row'><input type='checkbox' value='" . $meta->id . "' name='delete_facs[]'></th>";

					echo "<td class='name column-name'>";
					if($groupid != $meta->metagroup_id) {
						$groupid = $meta->metagroup_id;
						echo $meta->groupname;
					} else {
						echo "&#8230;";
					}

					$actions = array();
					$actions['edit'] = "<a href='" . "?page=$page&amp;action=editgroup&amp;fac_ID=$meta->id" . "'>" . __('Edit') . "</a>";
					$actions['delete'] = "<a class='delete:the-list:facgroup-$meta->id submitdelete deletefacgroup' href='" . wp_nonce_url("?page=$page&amp;action=deletegroup&amp;fac_ID=$meta->id", 'delete-facgroup_' . $meta->id) . "'>" . __('Delete') . "</a>";

					echo "<br/>";
					echo "<div class='row-actions'>";
					echo implode(" | ", $actions);
					echo "</div>";

					echo "</td>";

					echo "<td class='name column-name'>";
					echo $meta->metaname;

					$actions = array();
					$actions['edit'] = "<a href='" . "?page=$page&amp;action=edit&amp;fac_ID=$meta->id" . "'>" . __('Edit') . "</a>";
					$actions['delete'] = "<a class='delete:the-list:fac-$meta->id submitdelete deletefac' href='" . wp_nonce_url("?page=$page&amp;action=delete&amp;fac_ID=$meta->id", 'delete-fac_' . $meta->id) . "'>" . __('Delete') . "</a>";

					echo "<br/>";
					echo "<div class='row-actions'>";
					echo implode(" | ", $actions);
					echo "</div>";

					echo "</td>";

					echo "<td class='description column-description'>";

					switch($meta->metatype) {
						case 1:		echo __('Numeric','property');
									break;
						case 2:		echo __('Text','property');
									break;
						case 3:		echo __('Yes / No','property');
									break;
						case 4:		echo __('Option','property');
									break;

						default:	echo __('Numeric','property');
									break;
					}

					echo "</td>";
				echo "</tr>";

			}
		} else {
			echo "<tr>";
			echo "<td class='name column-name' colspan='4'>";
			echo __('You do not have any facilities at the moment, please use the buttons above to add some.','property');
			echo "</td>";
			echo "</tr>";
		}


		echo '</tbody>';
		echo '</table>';

		echo '<div class="tablenav">';
		if ( $page_links ) {
			echo "<div class='tablenav-pages'>$page_links</div>";
		}

		echo '<div class="alignleft actions">';
		echo '<select name="action2">';
		echo '<option value="" selected="selected">' . __('Bulk Actions') . '</option>';
		echo '<option value="delete">' . __('Delete facility', 'property') . '</option>';
		echo '</select>';
		echo '<input type="submit" value="' . esc_attr('Apply') . '" name="doaction2" id="doaction2" class="button-secondary action" />';
		echo '</div>';

		echo '&nbsp;';
		echo '<input type="submit" value="' . esc_attr('Add Group') . '" name="doaction2" id="doaction" class="button-secondary action" />';
		echo '<input type="submit" value="' . esc_attr('Add Facility') . '" name="doaction2" id="doaction" class="button-secondary action" />';

		echo '<br class="clear" />';
		echo '</div>';

		echo '<br class="clear" />';
		echo '</form>';
		echo '</div>';

		echo '</div><!-- /col-container -->';

		echo '</div><!-- /wrap -->';

	}

	function show_edit_facgroup_form($fac_ID = false) {

		if($fac_ID) {
			$group = $this->property->get_metagroupforfac($fac_ID);
		} else {
			$group = new stdClass;
			$group->id = -1;
		}


		echo '<div class="wrap">';
		echo '<div class="icon32" id="icon-edit"><br></div>';

		if($fac_ID) {
			echo '<h2>' . __('Edit Facility Group', 'property') . '</h2>';
		} else {
			echo '<h2>' . __('Add Facility Group', 'property') . '</h2>';
		}
		echo '<div id="ajax-response"></div>';
		echo '<form name="editfacgroup" id="editfacgroup" method="post" action="" class="validate">';
		if($fac_ID) {
			echo '<input type="hidden" name="action" value="editedfacgroup" />';
		} else {
			echo '<input type="hidden" name="action" value="addedfacgroup" />';
		}
		echo '<input type="hidden" name="facgroup_ID" value="' . esc_attr($group->id) . '" />';

		wp_original_referer_field(true, 'previous'); wp_nonce_field('update-facgroup_' . $group->id);

		echo '<table class="form-table">';
		echo '<tr class="form-field form-required">';

		echo '<th scope="row" valign="top"><label for="groupname">' . __('Group name') . '</label></th>';
		echo '<td><input name="groupname" id="groupname" type="text" value="';
		if ( isset( $group->groupname ) ) echo esc_attr($group->groupname);
		echo '" size="40" aria-required="true" />';

		echo '<p class="description">' . __('The name is how the tag appears on your site.') . '</p></td>';
		echo '</tr>';

		echo '</table>';
		if($fac_ID) {
			echo '<p class="submit"><input type="submit" class="button-primary" name="submit" value="' . esc_attr('Update Group') . '" />';
		} else {
			echo '<p class="submit"><input type="submit" class="button-primary" name="submit" value="' . esc_attr('Add Group') . '" />';
		}
		echo '&nbsp;';
		echo '<a href="' . wp_get_referer() . '" style="margin-left: 20px;">' . __('Cancel edit','property') . '</a>';
		echo '</p>';
		echo '</form>';
		echo '</div>';

	}

	function show_edit_fac_form($fac_ID = false) {

		global $page;

		if($fac_ID) {
			$fac = $this->property->get_metaforfac($fac_ID);
		} else {
			$fac = new stdClass;
			$fac->id = -1;
		}

		$groups = $this->property->get_metagroups();

		echo '<div class="wrap">';
		echo '<div class="icon32" id="icon-edit"><br></div>';

		if($fac_ID) {
			echo '<h2>' . __('Edit Facility', 'property') . '</h2>';
		} else {
			echo '<h2>' . __('Add Facility', 'property') . '</h2>';
		}

		echo '<div id="ajax-response"></div>';
		echo '<form name="editfac" id="editfac" method="post" action="" class="validate">';
		if($fac_ID) {
			echo '<input type="hidden" name="action" value="editedfac" />';
		} else {
			echo '<input type="hidden" name="action" value="addedfac" />';
		}
		echo '<input type="hidden" name="fac_ID" value="' . esc_attr($fac->id) . '" />';

		wp_original_referer_field(true, 'previous'); wp_nonce_field('update-fac_' . $fac->id);

		echo '<table class="form-table">';
		echo '<tr class="form-field form-required">';

		echo '<th scope="row" valign="top"><label for="metaname">' . __('Facility name', 'property') . '</label></th>';
		echo '<td><input name="metaname" id="metaname" type="text" value="';
		if ( isset( $fac->metaname ) ) echo esc_attr($fac->metaname);
		echo '" size="40" aria-required="true" />';

		echo '<p class="description">' . __('The name is how the tag appears on your site.') . '</p></td>';
		echo '</tr>';

		echo '<tr class="form-field form-required">';

		echo '<th scope="row" valign="top"><label for="metatype">' . __('Facility type', 'property') . '</label></th>';
		echo '<td>';

		echo '<select name="metatype" id_"metatype">';

		echo '<option value="1"';
		if(isset($fac->metatype) && $fac->metatype == 1) echo " selected='selected'";
		echo '>' . __('Numeric','property') . '</option>';

		echo '<option value="2"';
		if(isset($fac->metatype) && $fac->metatype == 2) echo " selected='selected'";
		echo '>' . __('Text','property') . '</option>';

		echo '<option value="3"';
		if(isset($fac->metatype) && $fac->metatype == 3) echo " selected='selected'";
		echo '>' . __('Yes / No','property') . '</option>';

		echo '<option value="4"';
		if(isset($fac->metatype) && $fac->metatype == 4) echo " selected='selected'";
		echo '>' . __('Option','property') . '</option>';

		echo '</select>';

		echo '</td>';
		echo '</tr>';

		echo '<tr class="form-field form-required">';

		echo '<th scope="row" valign="top"><label for="metagroup">' . __('Facility group', 'property') . '</label></th>';
		echo '<td>';
		if(!empty($groups)) {

			echo '<select name="metagroup" id="metagroup">';
			foreach($groups as $group) {
				echo '<option value="' . $group->id . '"';
				if($group->id == $fac->metagroup_id) echo " selected='selected'";
				echo '>';
				echo $group->groupname;

				echo '</option>';

			}
			echo '</select>';

		} else {
			// No groups to recommend creating one
			echo "<a href='" . wp_nonce_url('admin.php?page=' . $page. '&amp;doaction=Add+Group','bulk-facs') . "'>" . __('You do not have any groups set up, click here to add one now.','property') . "</a>";
		}

		echo '</td>';
		echo '</tr>';

		echo '<tr class="form-field form-required">';

		echo '<th scope="row" valign="top"><label for="metaoptions">' . __('Facility options', 'property') . '</label></th>';
		echo '<td>';
		echo '<textarea name="metaoptions" id="metaoptions">';
		echo (isset($fac->metaoptions) ? esc_html($fac->metaoptions) : '');
		echo '</textarea>';
		echo '<p class="description">' . __('If the type, above, is option, then enter the available options here - 1 per line.') . '</p></td>';
		echo '</tr>';

		echo '</table>';
		if($fac_ID) {
			echo '<p class="submit"><input type="submit" class="button-primary" name="submit" value="' . esc_attr('Update Facility') . '" />';
		} else {
			echo '<p class="submit"><input type="submit" class="button-primary" name="submit" value="' . esc_attr('Add Facility') . '" />';
		}
		echo '&nbsp;';
		echo '<a href="' . wp_get_referer() . '" style="margin-left: 20px;">' . __('Cancel edit','property') . '</a>';
		echo '</p>';
		echo '</form>';
		echo '</div>';

	}

	// Tags panel and updates
	function update_property_tags() {

		global $action, $tag, $taxonomy, $page;

		wp_reset_vars( array('action', 'tag', 'taxonomy', 'page') );

		if ( isset( $_GET['action'] ) && isset($_GET['delete_tags']) && ( 'delete' == $_GET['action'] || 'delete' == $_GET['action2'] ) ) {
			$action = 'bulk-delete';
		}

		if(empty($taxonomy)) $taxonomy = 'propertyfeature';

		switch($action) {

			case 'addtag':
				check_admin_referer('add-tag');

				if ( !current_user_can('manage_categories') )
					wp_die(__('Cheatin&#8217; uh?'));

				$ret = wp_insert_term($_POST['name'], $taxonomy, $_POST);
				if ( $ret && !is_wp_error( $ret ) ) {
					wp_safe_redirect( add_query_arg('msg', 1, wp_get_referer()));
				} else {
					wp_safe_redirect( add_query_arg('msg', 4, wp_get_referer()));
				}
			break;

			case 'delete':
				$tag_ID = (int) $_GET['tag_ID'];
				check_admin_referer('delete-tag_' .  $tag_ID);

				if ( !current_user_can('manage_categories') )
					wp_die(__('Cheatin&#8217; uh?'));

				wp_delete_term( $tag_ID, $taxonomy);

				wp_safe_redirect( add_query_arg('msg', 2, wp_get_referer()));

			break;

			case 'bulk-delete':
				check_admin_referer('bulk-tags');

				if ( !current_user_can('manage_categories') )
					wp_die(__('Cheatin&#8217; uh?'));

				$tags = $_GET['delete_tags'];
				foreach( (array) $tags as $tag_ID ) {
					wp_delete_term( $tag_ID, $taxonomy);
				}

				wp_safe_redirect( add_query_arg('msg', 6, wp_get_referer()));

			break;

			case 'editedtag':
				$tag_ID = (int) $_POST['tag_ID'];
				check_admin_referer('update-tag_' . $tag_ID);

				if ( !current_user_can('manage_categories') )
					wp_die(__('Cheatin&#8217; uh?'));

				$ret = wp_update_term($tag_ID, $taxonomy, $_POST);

				if ( $ret && !is_wp_error( $ret ) ) {
					wp_safe_redirect( add_query_arg('msg', 3, 'admin.php?page=' . $page . '&taxonomy=' . $taxonomy));
				} else {
					wp_safe_redirect( add_query_arg('msg', 5, 'admin.php?page=' . $page . '&taxonomy=' . $taxonomy));
				}
				break;

		}

	}

	function show_tags_panel() {

		global $action, $tag, $taxonomy, $page;

		switch($action) {

			case 'edit':
				$title = __('Edit Tag');

				require_once ('admin-header.php');
				$tag_ID = (int) $_GET['tag_ID'];

				$this->show_tag_edit_form($tag_ID);
				return;
			break;

		}

		$messages = array();
		$messages[1] = __('Tag added.');
		$messages[2] = __('Tag deleted.');
		$messages[3] = __('Tag updated.');
		$messages[4] = __('Tag not added.');
		$messages[5] = __('Tag not updated.');
		$messages[6] = __('Tags deleted.');

		if(taxonomy_exists($taxonomy)) {
			$tax = get_taxonomy($taxonomy);
			$title = __('Edit','property') . " " . $tax->label;
		}

		echo '<div class="wrap">';
		echo '<div class="icon32" id="icon-edit"><br></div>';
		echo '<h2>' . esc_html( $title );
		if ( isset($_GET['s']) && $_GET['s'] )
			printf( '<span class="subtitle">' . __('Search results for &#8220;%s&#8221;') . '</span>', esc_html( stripslashes($_GET['s']) ) );
		echo '</h2>';

		if ( isset($_GET['msg']) ) {
			echo '<div id="message" class="updated fade"><p>' . $messages[addslashes($_GET['msg'])] . '</p></div>';
			$_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
		}

		$taxes = get_object_taxonomies(STAYPRESS_PROPERTY_POST_TYPE);
		if($taxes) {
			echo '<ul class="subsubsub">';
			$list = array();
			foreach($taxes as $key => $value) {
				if(!is_taxonomy_hierarchical($value)) {
					$mtax = get_taxonomy($value);
					$mnum = wp_count_terms($value);
					$list[$key] = '<li><a class="';
					if($value == $taxonomy) $list[$key] .= 'current';
					$list[$key] .= '" href="?page=property-tags&amp;taxonomy=' . esc_attr($value) . '">' . esc_html($mtax->label) . ' <span class="count">(' . $mnum . ')</span></a></li>';
				}
			}
			echo implode(' | ', $list);
			echo '</ul>';
		}

		echo '<form class="search-form" action="" method="get">';
		echo '<input type="hidden" name="taxonomy" value="' . esc_attr($taxonomy) .'" />';
		echo '<input type="hidden" name="page" value="' . esc_attr($page) .'" />';
		echo '<p class="search-box">';
			echo '<label class="screen-reader-text" for="tag-search-input">' . __( 'Search Tags' ) . ':</label>';
			echo '<input type="text" id="tag-search-input" name="s" value="' . esc_attr( stripslashes( (isset($_GET['s']) ? $_GET['s'] : '') ) ) . '" />';
			echo '<input type="submit" value="' . esc_attr( 'Search Tags' ) . '" class="button" />';
		echo '</p>';
		echo '</form>';
		echo '<br class="clear" />';

		echo '<div id="col-container">';

		echo '<div id="col-right">';
		echo '<div class="col-wrap">';
		echo '<form id="posts-filter" action="" method="get">';
		echo '<input type="hidden" name="taxonomy" value="' . esc_attr($taxonomy) . '" />';
		echo '<input type="hidden" name="page" value="' . esc_attr($page) .'" />';
		echo '<div class="tablenav">';

		$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 0;
		if ( empty($pagenum) )
			$pagenum = 1;

		$tags_per_page = get_user_option('edit_tags_per_page');
		if ( empty($tags_per_page) )
			$tags_per_page = 20;
		$tags_per_page = apply_filters('edit_tags_per_page', $tags_per_page);
		$tags_per_page = apply_filters('tagsperpage', $tags_per_page); // Old filter

		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => ceil(wp_count_terms($taxonomy) / $tags_per_page),
			'current' => $pagenum
		));

		if ( $page_links ) echo "<div class='tablenav-pages'>$page_links</div>";

		echo '<div class="alignleft actions">';
		echo '<select name="action">';
		echo '<option value="" selected="selected">' . __('Bulk Actions') . '</option>';
		echo '<option value="delete">' . __('Delete') . '</option>';
		echo '</select>';
		echo '<input type="submit" value="' . esc_attr('Apply') . '" name="doaction" id="doaction" class="button-secondary action" />';
		wp_nonce_field('bulk-tags');
		echo '</div>';

		echo '<br class="clear" />';
		echo '</div>';

		echo '<div class="clear"></div>';

		echo '<table class="widefat tag fixed" cellspacing="0">';
		echo '<thead>';
		echo '<tr>';
		//$columns = get_column_headers('edit-tags');
		//unset($columns['posts']);

		$columns = array();
		$columns['cb'] = '';
		$columns['name'] = __('Name', 'property');
		$columns['description'] = __('Description', 'property');
		$columns['slug'] = __('Slug', 'property');
		$columns['properties'] = __('Properties', 'property');

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = ' class="manage-column';
			$class .= " column-$column_key";
			if ( 'cb' == $column_key ) {
				$class .= ' check-column';
			} elseif ( in_array($column_key, array('properties')) ) {
				$class .= ' num';
			}
			$class .= '"';
			$style = '';

			if ( isset($type) && isset($styles[$type]) && isset($styles[$type][$column_key]) ) {
				$style .= ' ' . $styles[$type][$column_key];
			}
			$style = ' style="' . $style . '"';

			echo '<th scope="col"';
			echo isset($id) ? "id=\"$column_key\"" : ""; echo $class; echo $style;
			echo '>' . $column_display_name . '</th>';
		}
		echo '</tr>';
		echo '</thead>';

		echo '<tfoot>';
		echo '<tr>';
		reset($columns);
		foreach ( $columns as $column_key => $column_display_name ) {
			$class = ' class="manage-column';
			$class .= " column-$column_key";
			if ( 'cb' == $column_key ) {
				$class .= ' check-column';
			} elseif ( in_array($column_key, array('properties')) ) {
				$class .= ' num';
			}
			$class .= '"';
			$style = '';

			if ( isset($type) && isset($styles[$type]) && isset($styles[$type][$column_key]) ) {
				$style .= ' ' . $styles[$type][$column_key];
			}
			$style = ' style="' . $style . '"';

			echo '<th scope="col"';
			echo isset($id) ? "id=\"$column_key\"" : ""; echo $class; echo $style;
			echo '>' . $column_display_name . '</th>';
		}
		echo '</tr>';
		echo '</tfoot>';

		echo '<tbody id="the-list" class="list:tag">';

		$searchterms = isset( $_GET['s'] ) ? trim( $_GET['s'] ) : '';

		$count = $this->tag_rows( $pagenum, $tags_per_page, $searchterms, $taxonomy );

		echo '</tbody>';
		echo '</table>';

		echo '<div class="tablenav">';
		if ( $page_links ) {
			echo "<div class='tablenav-pages'>$page_links</div>";
		}

		echo '<div class="alignleft actions">';
		echo '<select name="action2">';
		echo '<option value="" selected="selected">' . __('Bulk Actions') . '</option>';
		echo '<option value="delete">' . __('Delete') . '</option>';
		echo '</select>';
		echo '<input type="submit" value="' . esc_attr('Apply') . '" name="doaction2" id="doaction2" class="button-secondary action" />';
		echo '</div>';

		echo '<br class="clear" />';
		echo '</div>';

		echo '<br class="clear" />';
		echo '</form>';
		echo '</div>';
		echo '</div><!-- /col-right -->';

		echo '<div id="col-left">';
		echo '<div class="col-wrap">';

		echo '<div class="form-wrap">';
		echo '<h3>' . __('Add a New Tag') . '</h3>';
		echo '<div id="ajax-response"></div>';

		echo '<form name="addtag" id="addtag" method="post" action="" class="add:the-list: validate">';
		echo '<input type="hidden" name="action" value="addtag" />';

		echo '<input type="hidden" name="taxonomy" value="' . esc_attr($taxonomy) . '" />';
		wp_original_referer_field(true, 'previous');
		wp_nonce_field('add-tag');

		echo '<div class="form-field form-required">';
		echo '<label for="name">' . __('Tag name') . '</label>';
		echo '<input name="name" id="name" type="text" value="" size="40" aria-required="true" />';
		echo '<p>' . __('The name is how the tag appears on your site.') . '</p>';
		echo '</div>';

		echo '<div class="form-field">';
		echo '<label for="tag-slug">' . __('Slug') . '</label>';
		echo '<input name="slug" id="tag-slug" type="text" value="" size="40" />';
		echo '<p>' . __('The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.') . '</p>';
		echo '</div>';

		echo '<div class="form-field">';
		echo '<label for="description">' . __('Description') . '</label>';
		echo '<textarea name="description" id="description" rows="5" cols="40"></textarea>';
		echo '<p>' . __('The description is not prominent by default, however some themes may show it.') . '</p>';
		echo '</div>';

		echo '<p class="submit"><input type="submit" class="button" name="submit" value="' . esc_attr('Add Tag') . '" /></p>';

		echo '</form></div>';

		echo '</div>';
		echo '</div><!-- /col-left -->';

		echo '</div><!-- /col-container -->';

		echo '</div><!-- /wrap -->';

	}

	// Taxonomy management code
	function show_tag_edit_form($tagid) {

		global $taxonomy;

		$tag = get_term($tagid, $taxonomy, OBJECT, 'edit');

		echo '<div class="wrap">';
		echo '<div class="icon32" id="icon-edit"><br></div>';

		echo '<h2>' . __('Edit Tag') . '</h2>';

		echo '<div id="ajax-response"></div>';
		echo '<form name="edittag" id="edittag" method="post" action="" class="validate">';
		echo '<input type="hidden" name="action" value="editedtag" />';
		echo '<input type="hidden" name="tag_ID" value="' . esc_attr($tag->term_id) . '" />';
		echo '<input type="hidden" name="taxonomy" value="' . esc_attr($taxonomy) . '" />';

		wp_original_referer_field(true, 'previous'); wp_nonce_field('update-tag_' . $tagid);

		echo '<table class="form-table">';
		echo '<tr class="form-field form-required">';

		echo '<th scope="row" valign="top"><label for="name">' . __('Tag name') . '</label></th>';
		echo '<td><input name="name" id="name" type="text" value="';
		if ( isset( $tag->name ) ) echo esc_attr($tag->name);
		echo '" size="40" aria-required="true" />';

		echo '<p class="description">' . __('The name is how the tag appears on your site.') . '</p></td>';
		echo '</tr>';

		echo '<tr class="form-field">';
		echo '<th scope="row" valign="top"><label for="slug">' . __('Slug') . '</label></th>';
		echo '<td><input name="slug" id="slug" type="text" value="';
		if ( isset( $tag->slug ) ) echo esc_attr(apply_filters('editable_slug', $tag->slug));
		echo '" size="40" />';
		echo '<p class="description">' . __('The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.') . '</p></td>';
		echo '</tr>';

		echo '<tr class="form-field">';

		echo '<th scope="row" valign="top"><label for="description">' . __('Description') . '</label></th>';
		echo '<td><textarea name="description" id="description" rows="5" cols="50" style="width: 97%;">' . esc_html($tag->description) . '</textarea><br />';
		echo '<span class="description">' . __('The description is not prominent by default, however some themes may show it.') . '</span></td>';
		echo '</tr>';

		echo '</table>';
		echo '<p class="submit"><input type="submit" class="button-primary" name="submit" value="' . esc_attr('Update Tag') . '" />';
		echo '&nbsp;';
		echo '<a href="' . wp_get_referer() . '" style="margin-left: 20px;">' . __('Cancel edit','property') . '</a>';
		echo '</p>';

		echo '</form>';
		echo '</div>';

	}

	function tag_rows( $page = 1, $pagesize = 20, $searchterms = '', $taxonomy = 'post_tag' ) {

		// Get a page worth of tags
		$start = ($page - 1) * $pagesize;

		$args = array('offset' => $start, 'number' => $pagesize, 'hide_empty' => 0);

		if ( !empty( $searchterms ) ) {
			$args['search'] = $searchterms;
		}

		$tags = get_terms( $taxonomy, $args );

		// convert it to table rows
		$out = '';
		$count = 0;
		foreach( $tags as $tag )
			$out .= $this->sp_tag_row( $tag, ++$count % 2 ? ' class="iedit alternate"' : ' class="iedit"', $taxonomy );

		// filter and send to screen
		echo $out;
		return $count;
	}

	function sp_tag_row( $tag, $class = '', $taxonomy = 'post_tag' ) {

			//print_r($tag);

			$count = number_format_i18n( $tag->count );
			$tagsel = ($taxonomy == 'post_tag' ? 'tag' : $taxonomy);
			$count = ( $count > 0 ) ? "<a href='edit.php?$tagsel=$tag->slug'>$count</a>" : $count;

			$name = apply_filters( 'term_name', $tag->name );

			//echo $name;

			$qe_data = get_term($tag->term_id, $taxonomy, object, 'edit');
			//print_r($qe_data);
			$edit_link = "?page=property-tags&amp;action=edit&amp;taxonomy=$taxonomy&amp;tag_ID=$tag->term_id";
			$out = '';
			$out .= '<tr id="tag-' . $tag->term_id . '"' . $class . '>';
			// Overridden for WP 3.1 till i work out what has changed
			//$columns = get_column_headers('edit-tags');
			//unset($columns['posts']);

			$columns = array();
			$columns['cb'] = '';
			$columns['name'] = __('Name', 'property');
			$columns['description'] = __('Description', 'property');
			$columns['slug'] = __('Slug', 'property');
			$columns['properties'] = __('Properties', 'property');

			foreach ( $columns as $column_name => $column_display_name ) {
				$class = "class=\"$column_name column-$column_name\"";

				$style = '';

				$attributes = "$class$style";

				switch ($column_name) {
					case 'cb':
						$out .= '<th scope="row" class="check-column"> <input type="checkbox" name="delete_tags[]" value="' . $tag->term_id . '" /></th>';
						break;
					case 'name':
						$out .= '<td ' . $attributes . '><strong><a class="row-title" href="' . $edit_link . '" title="' . esc_attr(sprintf(__('Edit &#8220;%s&#8221;'), $name)) . '">' . $name . '</a></strong><br />';
						$actions = array();
						$actions['edit'] = '<a href="' . $edit_link . '">' . __('Edit') . '</a>';
						$actions['delete'] = "<a class='delete:the-list:tag-$tag->term_id submitdelete' href='" . wp_nonce_url("?page=property-tags&amp;action=delete&amp;taxonomy=$taxonomy&amp;tag_ID=$tag->term_id", 'delete-tag_' . $tag->term_id) . "'>" . __('Delete') . "</a>";
						$actions = apply_filters('tag_row_actions', $actions, $tag);
						$action_count = count($actions);
						$i = 0;
						$out .= '<div class="row-actions">';
						foreach ( $actions as $action => $link ) {
							++$i;
							( $i == $action_count ) ? $sep = '' : $sep = ' | ';
							$out .= "<span class='$action'>$link$sep</span>";
						}
						$out .= '</div>';
						$out .= '<div class="hidden" id="inline_' . $qe_data->term_id . '">';
						$out .= '<div class="name">' . $qe_data->name . '</div>';
						$out .= '<div class="slug">' . $qe_data->slug . '</div></div></td>';
						break;
					case 'description':
						$out .= "<td $attributes>$tag->description</td>";
						break;
					case 'slug':
						$out .= "<td $attributes>$tag->slug</td>";
						break;
					case 'properties':
						$attributes = 'class="posts column-posts num"' . $style;
						$out .= "<td $attributes>$count</td>";
						break;
					default:
						$out .= "<td $attributes>";
						$out .= apply_filters("manage_${taxonomy}_custom_column", '', $column_name, $tag->term_id);
						$out .= "</td>";
				}
			}

			$out .= '</tr>';

			return $out;
	}

	// Options panel and updates
	function update_property_options() {

		global $action, $page, $wp_rewrite;

		wp_reset_vars( array('action', 'page') );

		if(isset($action) && $action == 'updateoptions') {

			check_admin_referer('update-property-options');

			$options = array();
			$options['propertytext'] = strtolower($_POST['propertytext']);
			$options['propertiestext'] = strtolower($_POST['propertiestext']);

			$options['listingmethod'] = $_POST['listingmethod'];

			$options['permalinkhasid'] = strtolower($_POST['permalinkhasid']);
			$options['firstelement'] = strtolower($_POST['firstelement']);
			$options['propertytitlelayout'] = strtolower($_POST['propertytitlelayout']);
			$options['propertytitlemarker'] = strtolower($_POST['propertytitlemarker']);
			$options['locationcategory'] = strtolower($_POST['locationcategory']);
			$options['propertysearchtext'] = $_POST['propertysearchtext'];

			$options['propertylisttitle'] = $_POST['propertylisttitle'];
			$options['propertydetailstitle'] = $_POST['propertydetailstitle'];
			$options['propertytagtitle'] = $_POST['propertytagtitle'];
			$options['propertydesttitle'] = $_POST['propertydesttitle'];
			$options['propertyneartitle'] = $_POST['propertyneartitle'];
			$options['propertysearchtitle'] = $_POST['propertysearchtitle'];

			$options = apply_filters( 'staypress_property_preoptions_update', $options );

			SPPCommon::update_option('sp_property_options', $options);

			SPPCommon::update_option('property_rewrite_prefix', strtolower($_POST['propertyrewriteprefix']) );

			if(!empty($_POST['tax'])) {
				$taxonomies = SPPCommon::get_option('property_taxonomies', array());
				$taxonomies = apply_filters('staypress_property_taxonomies', $taxonomies);

				foreach( $_POST['tax'] as $key => $value ) {

					switch($key) {

						case 'propertyfeature':			$taxonomies['propertyfeature'] = array( 'label' => esc_html($value), 'slug' => 'features');
														break;
						case 'propertytype':			$taxonomies['propertytype'] = array( 'label' => esc_html($value), 'slug' => 'propertytypes');
														break;
						case 'propertyrental':			$taxonomies['propertyrental'] = array( 'label' => esc_html($value), 'slug' => 'rentaltypes');
														break;
						case 'propertysetting':			$taxonomies['propertysetting'] = array( 'label' => esc_html($value), 'slug' => 'settings');
														break;
						case 'propertyactivity':		$taxonomies['propertyactivity'] = array( 'label' => esc_html($value), 'slug' => 'activities');
														break;
						case 'propertysuitability':		$taxonomies['propertysuitability'] = array( 'label' => esc_html($value), 'slug' => 'suitability');
														break;
						default:						$taxonomies[$key] = array( 'label' => esc_html($value), 'slug' => strtolower($key));
														break;
					}

				}
				SPPCommon::update_option('property_taxonomies', $taxonomies);
			}

			SPPCommon::update_option('sp_property_fields', $_POST['propertyfields'] );

			do_action( 'staypress_property_postoptions_update' );

			$wp_rewrite->flush_rules();

			wp_safe_redirect( add_query_arg('msg', 1, wp_get_referer()));
		}

	}

	function show_options_panel() {

		global $action, $page;

		$defaultoptions = array( 	'propertytext'			=> 	'property',
									'propertiestext'		=>	'properties',
									'permalinkhasid'		=>	'no',
									'listingmethod'			=>	'permalink',
									'firstelement'			=>	'slug',
									'propertytitlelayout'	=>	'%title%',
									'propertytitlemarker'	=>	'numeric',
									'locationcategory'		=>	'no',
									'propertysearchtext'	=>	'Search for...',
									'propertylisttitle'		=>	__('Properties for rent %sep% %blogname%', 'property'),
									'propertydetailstitle'	=>	__('%town% rental property %sep% %title%', 'property'),
									'propertytagtitle'		=>	__('Rental properties with %taglist% %sep% %blogname%', 'property'),
									'propertydesttitle'		=>	__('Rental properties in %destlist% %sep% %blogname%', 'property'),
									'propertyneartitle'		=>	__('Rental properties near %destlist% %sep% %blogname%', 'property'),
									'propertysearchtitle'	=>	__('Rental properties with %criteria% %sep% %blogname%', 'property')
								);

		$propertyoptions = SPPCommon::get_option('sp_property_options', $defaultoptions);

		$propertyfields = SPPCommon::get_option('sp_property_fields', array() );

		$taxonomies = SPPCommon::get_option('property_taxonomies', false);
		$taxonomies = apply_filters('staypress_property_taxonomies', $taxonomies);

		$page_prefix = SPPCommon::get_option('property_rewrite_prefix', '');

		$messages = array();
		$messages[1] = __('Your options have been updated.','membership');

		echo "<div class='wrap nosubsub'>";

			echo "<div class='innerwrap'>\n";
			echo "<h2><a href='' class='selected'>" . __('Edit Options','property') . "</a></h2>";

			echo "<div class='wrapcontents'>\n";

					if(isset($_GET['msg'])) {
						echo '<div id="upmessage" class="updatedmessage"><p>' . $messages[(int) $_GET['msg']];
						echo '<a href="#close" id="closemessage">' . __('close', 'property') . '</a>';
						echo '</p></div>';
						$_SERVER['REQUEST_URI'] = remove_query_arg(array('msg'), $_SERVER['REQUEST_URI']);
					}

					echo "<form action='" . admin_url("admin.php?page=" . $page) . "' method='post'>";

					echo "<input type='hidden' name='action' value='updateoptions' />";
					wp_nonce_field('update-property-options');

					echo "<p>";
					echo __('The options below control the settings, text and urls of your StayPress installation, once set you should avoid changing them if possible. For multi-site installs, these may change the settings for <strong>all</strong> your sites, depending on your configuration.','property');
					echo "</p>";

					echo "<h3>" . __('Plugin wide text change','property') . "</h3>";
					echo "<p>" . __('Use the settings options below to change the labels throughout the system (e.g. use Rooms or Venues).','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";
					echo "<tr valign='top'>";
					// Un translated for now
					echo "<th scope='row'>" . 'property' . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertytext' value='" . esc_attr($propertyoptions['propertytext'])  . "' class='narrow' />";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					// Un translated for now
					echo "<th scope='row'>" . 'properties' . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertiestext' value='" . esc_attr($propertyoptions['propertiestext'])  . "' class='narrow' />";
					echo "</td>";
					echo "</tr>";

					echo "</tbody>";
					echo "</table>";

					echo "<h3>" . __('Property listings settings','property') . "</h3>";
					echo "<p>" . __('Use the setting below to select the listings method you want to use in the plugin, standard WordPress pages and shortcodes or permalinks and template files.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Listing method','property') . "</th>";
					echo "<td>";

					echo "<select name='listingmethod'>";

						echo "<option value='permalink'";
						if(!isset($propertyoptions['listingmethod']) || $propertyoptions['listingmethod'] == 'permalink') {
							echo " selected='selected'";
						}
						echo ">" . __('Permalinks and templates','property') . "</option>";
						echo "<option value='pages'";
						if(isset($propertyoptions['listingmethod']) && $propertyoptions['listingmethod'] == 'pages') {
							echo " selected='selected'";
						}
						echo ">" . __('WordPress Pages','property') . "</option>";

					echo "</select>";

					echo "</td>";
					echo "</tr>";
					echo "</table>";

					echo "<h3>" . __('Property Tags','property') . "</h3>";
					echo "<p>" . __('Change the default property tag labels using the fields below.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";

					if($taxonomies) {
						foreach( (array) $taxonomies as $key => $tax ) {

							echo "<tr valign='top'>";
							echo "<th scope='row'>" . $tax['label'] . "</th>";
							echo "<td>";
							echo "<input type='text' name='tax[{$key}]' value='" . esc_attr($tax['label'])  . "' class='narrow' />";
							echo "</td>";
							echo "</tr>";

						}
					}

					echo "</tbody>";
					echo "</table>";

					echo "<p>" . __('Select whether you want to include a hiearchical location category on the property edit page as well.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";
					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Show location category','property') . "</th>";
					echo "<td>";
					echo "<select name='locationcategory'>";

						echo "<option value='no'";
						if(!isset($propertyoptions['locationcategory']) || $propertyoptions['locationcategory'] == 'no') {
							echo " selected='selected'";
						}
						echo ">" . __('No','property') . "</option>";
						echo "<option value='yes'";
						if(isset($propertyoptions['locationcategory']) && $propertyoptions['locationcategory'] == 'yes') {
							echo " selected='selected'";
						}
						echo ">" . __('Yes','property') . "</option>";

					echo "</select>";
					echo "</td>";
					echo "</tr>";

					echo "</tbody>";
					echo "</table>";


					echo "<h3>" . __('Optional Field Visibility','property') . "</h3>";
					echo "<p>" . __('Use the options below to remove some of the optional fields from the property edit / add form.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";
					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Show Reference field','property') . "</th>";
					echo "<td>";
					echo "<select name='propertyfields[reference]'>";

						echo "<option value='yes'";
						if(!isset($propertyfields['reference']) || $propertyfields['reference'] == 'yes') {
							echo " selected='selected'";
						}
						echo ">" . __('Yes','property') . "</option>";
						echo "<option value='no'";
						if(isset($propertyfields['reference']) && $propertyfields['reference'] == 'no') {
							echo " selected='selected'";
						}
						echo ">" . __('No','property') . "</option>";

					echo "</select>";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Show Permalink edit field','property') . "</th>";
					echo "<td>";
					echo "<select name='propertyfields[permalink]'>";

						echo "<option value='yes'";
						if(!isset($propertyfields['permalink']) || $propertyfields['permalink'] == 'yes') {
							echo " selected='selected'";
						}
						echo ">" . __('Yes','property') . "</option>";
						echo "<option value='no'";
						if(isset($propertyfields['permalink']) && $propertyfields['permalink'] == 'no') {
							echo " selected='selected'";
						}
						echo ">" . __('No','property') . "</option>";

					echo "</select>";
					echo "</td>";
					echo "</tr>";

					echo "</tbody>";
					echo "</table>";

					echo "<h3>" . __('Property Title Display','property') . "</h3>";
					echo "<p>" . __('Use the setting below to change the basic layout of the property title to add in map position marker labelling.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";
					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Title layout','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertytitlelayout' value='" . esc_attr($propertyoptions['propertytitlelayout'])  . "' class='wide' />";
					echo "<br/>";
					echo "<strong>%title%</strong> = " . __('Title', 'property') . ", <strong>%listmarker%</strong> = " . __('Identifying marker','property');
					echo ", <strong>%reference%</strong> = " . __('Reference', 'property');
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>&nbsp;</th>"; // . __('Marker type','property') . "</th>";
					echo "<td>";
					echo "<input type='hidden' name='propertytitlemarker' value='numeric' />";
					/*
					echo "<select name='propertytitlemarker'>";

						echo "<option value='numeric'";
						if(!isset($propertyoptions['propertytitlemarker']) || $propertyoptions['propertytitlemarker'] == 'numeric') {
							echo " selected='selected'";
						}
						echo ">" . __('Numeric','property') . "</option>";
						echo "<option value='alphabetic'";
						if(isset($propertyoptions['propertytitlemarker']) && $propertyoptions['propertytitlemarker'] == 'alphabetic') {
							echo " selected='selected'";
						}
						echo ">" . __('Alphabetic','property') . "</option>";

					echo "</select>";
					*/
					echo "</td>";
					echo "</tr>";

					echo "</tbody>";
					echo "</table>";

					echo "<h3>" . __('Property Search text','property') . "</h3>";
					echo "<p>" . __('Use the setting below to change the initial text content of the sites search boxes.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";
					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Default search text','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertysearchtext' value='" . esc_attr($propertyoptions['propertysearchtext'])  . "' class='wide' />";
					echo "</td>";
					echo "</tr>";

					echo "</tbody>";
					echo "</table>";

					echo "<h3>" . __('Property page titles','property') . "</h3>";
					echo "<p>" . __('Use the settings below to change the title layouts for the property pages.','property') . "</p>";
					echo "<p><em><small>" . __('%sep% = separator, %blogname% = Blog name, %taglist% = Tags, %criteria% = Search text, %town% = Town, %region% = Region, %country% = Country, %destlist% = Destination list, %title% = Property title','property') . "</small></em></p>";

					echo "<table class='form-table'>";
					echo "<tbody>";
					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('List page title','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertylisttitle' value='" . esc_attr($propertyoptions['propertylisttitle'])  . "' class='wide' />";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Property page title','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertydetailstitle' value='" . esc_attr($propertyoptions['propertydetailstitle'])  . "' class='wide' />";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Tag page title','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertytagtitle' value='" . esc_attr($propertyoptions['propertytagtitle'])  . "' class='wide' />";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Destination list page title','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertydesttitle' value='" . esc_attr($propertyoptions['propertydesttitle'])  . "' class='wide' />";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Near list page title','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertyneartitle' value='" . esc_attr($propertyoptions['propertyneartitle'])  . "' class='wide' />";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Search list page title','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertysearchtitle' value='" . esc_attr($propertyoptions['propertysearchtitle'])  . "' class='wide' />";
					echo "</td>";
					echo "</tr>";

					echo "</tbody>";
					echo "</table>";


					do_action( 'staypress_property_options_form', $propertyoptions );

					echo "<br style='clear:both;' />";

					echo "<p class='submit'>";
						echo "<input type='submit' name='Submit' class='button-primary' value='" . esc_attr('Update Options') . "' />";
					echo "</p>";

					echo "</form>\n";

			echo "</div> <!-- wrapcontents -->\n";

			echo "</div> <!-- innerwrap -->\n";

			// Start sidebar here
			echo "<div class='rightwrap'>";
			$this->show_options_rightpanel();
			echo "</div> <!-- rightwrap -->";

			echo "</div> <!-- wrap -->\n";

	}

	function update_property_permalinks() {

		global $action, $page, $wp_rewrite;

		wp_reset_vars( array('action', 'page') );

		if(isset($action) && $action == 'updateoptions') {

			check_admin_referer('update-property-permalink-options');

			$options = array();
			$options['permalinkhasid'] = strtolower($_POST['permalinkhasid']);
			$options['firstelement'] = strtolower($_POST['firstelement']);
			$options['propertytitlelayout'] = strtolower($_POST['propertytitlelayout']);

			$options['propertyurl'] = strtolower($_POST['propertyurl']);
			$options['propertylisturl'] = strtolower($_POST['propertylisturl']);
			$options['propertytagurl'] = strtolower($_POST['propertytagurl']);
			$options['propertydesturl'] = strtolower($_POST['propertydesturl']);
			$options['propertynearurl'] = strtolower($_POST['propertynearurl']);
			$options['propertyavailurl'] = strtolower($_POST['propertyavailurl']);
			$options['propertysearchurl'] = strtolower($_POST['propertysearchurl']);
			$options['propertymapurl'] = strtolower($_POST['propertymapurl']);

			$options = apply_filters( 'staypress_property_prepermalinkoptions_update', $options );

			SPPCommon::update_option('sp_property_permalink_options', $options);

			SPPCommon::update_option('property_rewrite_prefix', strtolower($_POST['propertyrewriteprefix']) );

			do_action( 'staypress_property_postpermalinkoptions_update' );

			$wp_rewrite->flush_rules();

			wp_safe_redirect( add_query_arg('msg', 1, wp_get_referer()));
		}

	}

	function show_permalinks_panel() {
		global $action, $page;

		$defaultoptions = array( 	'permalinkhasid'		=>	'no',
									'firstelement'			=>	'slug',
									'propertyurl'			=>	__('property', 'property'),
									'propertylisturl'		=>	__('properties', 'property'),
									'propertytagurl'		=>	__('with','property'),
									'propertydesturl'		=>	__('in','property'),
									'propertynearurl'		=>	__('near','property'),
									'propertyavailurl'		=>	__('available','property'),
									'propertysearchurl'		=>	__('search','property'),
									'propertymapurl'		=>	__('map','property')
								);

		$propertyoptions = SPPCommon::get_option('sp_property_permalink_options', $defaultoptions);

		$page_prefix = SPPCommon::get_option('property_rewrite_prefix', '');

		$messages = array();
		$messages[1] = __('Your options have been updated.','membership');

		echo "<div class='wrap nosubsub'>";

			echo "<div class='innerwrap'>\n";
			echo "<h2><a href='' class='selected'>" . __('Edit Permalink Options','property') . "</a></h2>";

			echo "<div class='wrapcontents'>\n";

					if(isset($_GET['msg'])) {
						echo '<div id="upmessage" class="updatedmessage"><p>' . $messages[(int) $_GET['msg']];
						echo '<a href="#close" id="closemessage">' . __('close', 'property') . '</a>';
						echo '</p></div>';
						$_SERVER['REQUEST_URI'] = remove_query_arg(array('msg'), $_SERVER['REQUEST_URI']);
					}

					echo "<form action='" . admin_url("admin.php?page=" . $page) . "' method='post'>";

					echo "<input type='hidden' name='action' value='updateoptions' />";
					wp_nonce_field('update-property-permalink-options');

					echo "<p>";
					echo __('The options below control the urls of your StayPress installation, once set you should avoid changing them if possible. For multi-site installs, these may change the settings for <strong>all</strong> your sites, depending on your configuration.','property');
					echo "</p>";

					echo "<h3>" . __('Main URL prefix setting','property') . "</h3>";
					echo "<p>" . __('Use the setting below to change the prefix added to some of the system page urls (useful if you may have a possible conflict), and decide if you want the property id as part of the details permalink.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";
					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('URL prefix','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertyrewriteprefix' value='" . esc_attr($page_prefix)  . "' class='narrow' />&nbsp;/";
					echo "</td>";
					echo "</tr>";

					echo "</table>";

					echo "<h3>" . __('Main permalink settings','property') . "</h3>";
					echo "<p>" . __('Use the settings below to change the main page urls. The prefix (above) will be used before all of these except the main property and listings url.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Property page','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertyurl' value='" . esc_attr($propertyoptions['propertyurl'])  . "' class='narrow' />&nbsp;/";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Listing page','property') . "</th>";
					echo "<td>";
					echo "<input type='text' name='propertylisturl' value='" . esc_attr($propertyoptions['propertylisturl'])  . "' class='narrow' />&nbsp;/";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Tag page','property') . "</th>";
					echo "<td>";
					if(!empty($page_prefix)) {
						echo $page_prefix . " / ";
					}
					echo "<input type='text' name='propertytagurl' value='" . esc_attr($propertyoptions['propertytagurl'])  . "' class='narrow' />&nbsp;/";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Destination page','property') . "</th>";
					echo "<td>";
					if(!empty($page_prefix)) {
						echo $page_prefix . " / ";
					}
					echo "<input type='text' name='propertydesturl' value='" . esc_attr($propertyoptions['propertydesturl'])  . "' class='narrow' />&nbsp;/";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Near page','property') . "</th>";
					echo "<td>";
					if(!empty($page_prefix)) {
						echo $page_prefix . " / ";
					}
					echo "<input type='text' name='propertynearurl' value='" . esc_attr($propertyoptions['propertynearurl'])  . "' class='narrow' />&nbsp;/";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Available page','property') . "</th>";
					echo "<td>";
					if(!empty($page_prefix)) {
						echo $page_prefix . " / ";
					}
					echo "<input type='text' name='propertyavailurl' value='" . esc_attr($propertyoptions['propertyavailurl'])  . "' class='narrow' />&nbsp;/";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Search results page','property') . "</th>";
					echo "<td>";
					if(!empty($page_prefix)) {
						echo $page_prefix . " / ";
					}
					echo "<input type='text' name='propertysearchurl' value='" . esc_attr($propertyoptions['propertysearchurl'])  . "' class='narrow' />&nbsp;/";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Map page','property') . "</th>";
					echo "<td>";
					if(!empty($page_prefix)) {
						echo $page_prefix . " / ";
					}
					echo "<input type='text' name='propertymapurl' value='" . esc_attr($propertyoptions['propertymapurl'])  . "' class='narrow' />&nbsp;/";
					echo "</td>";
					echo "</tr>";

					echo "</table>";

					echo "<h3>" . __('Property page permalink settings','property') . "</h3>";
					echo "<p>" . __('Use the setting below to decide if you want the property id as part of the details permalink.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Include property ID in permalink','property') . "</th>";
					echo "<td>";

					echo "<select name='permalinkhasid'>";

						echo "<option value='yes'";
						if(!isset($propertyoptions['permalinkhasid']) || $propertyoptions['permalinkhasid'] == 'yes') {
							echo " selected='selected'";
						}
						echo ">" . __('Yes','property') . "</option>";
						echo "<option value='no'";
						if(isset($propertyoptions['permalinkhasid']) && $propertyoptions['permalinkhasid'] == 'no') {
							echo " selected='selected'";
						}
						echo ">" . __('No','property') . "</option>";

					echo "</select>";

					echo "</td>";
					echo "</tr>";
					//'firstelement'		=>	'reference'
					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('If No above, first element in URL will be the','property') . "</th>";
					echo "<td>";

					echo "<select name='firstelement'>";

						echo "<option value='reference'";
						if(!isset($propertyoptions['firstelement']) || $propertyoptions['firstelement'] == 'reference') {
							echo " selected='selected'";
						}
						echo ">" . __('Reference','property') . "</option>";
						echo "<option value='slug'";
						if(isset($propertyoptions['firstelement']) && $propertyoptions['firstelement'] == 'slug') {
							echo " selected='selected'";
						}
						echo ">" . __('Title slug','property') . "</option>";

					echo "</select>";

					echo "</td>";
					echo "</tr>";

					echo "</tbody>";
					echo "</table>";

					do_action( 'staypress_property_permalinks_options_form', $propertyoptions );

					echo "<br style='clear:both;' />";

					echo "<p class='submit'>";
						echo "<input type='submit' name='Submit' class='button-primary' value='" . esc_attr('Update Options') . "' />";
					echo "</p>";

					echo "</form>\n";

			echo "</div> <!-- wrapcontents -->\n";

			echo "</div> <!-- innerwrap -->\n";

			// Start sidebar here
			echo "<div class='rightwrap'>";
			$this->show_options_rightpanel();
			echo "</div> <!-- rightwrap -->";

			echo "</div> <!-- wrap -->\n";
	}

	function update_property_pages() {

		global $action, $page, $wp_rewrite;

		wp_reset_vars( array('action', 'page') );

		if(isset($action) && $action == 'updateoptions') {

			check_admin_referer('update-property-page-options');

			$options = array();
			$options['permalinkhasid'] = strtolower($_POST['permalinkhasid']);
			$options['firstelement'] = strtolower($_POST['firstelement']);

			$options['propertypage'] = strtolower($_POST['propertypage']);
			$options['propertylistpage'] = strtolower($_POST['propertylistpage']);
			$options['propertytagpage'] = strtolower($_POST['propertytagpage']);
			$options['propertydestpage'] = strtolower($_POST['propertydestpage']);
			$options['propertynearpage'] = strtolower($_POST['propertynearpage']);
			$options['propertyavailpage'] = strtolower($_POST['propertyavailpage']);
			$options['propertysearchpage'] = strtolower($_POST['propertysearchpage']);
			$options['propertymappage'] = strtolower($_POST['propertymappage']);

			$options = apply_filters( 'staypress_property_prepageoptions_update', $options );

			SPPCommon::update_option('sp_property_page_options', $options);

			do_action( 'staypress_property_postpageoptions_update' );

			$wp_rewrite->flush_rules();

			wp_safe_redirect( add_query_arg('msg', 1, wp_get_referer()));
		}

	}

	function show_pages_panel() {
		global $action, $page;

		$defaultoptions = array( 	'permalinkhasid'		=>	'no',
									'firstelement'			=>	'slug',
									'propertypage'			=>	'',
									'propertylistpage'		=>	'',
									'propertytagpage'		=>	'',
									'propertydestpage'		=>	'',
									'propertynearpage'		=>	'',
									'propertyavailpage'		=>	'',
									'propertysearchpage'	=>	'',
									'propertymappage'		=>	''
								);

		$propertyoptions = SPPCommon::get_option('sp_property_page_options', $defaultoptions);

		$messages = array();
		$messages[1] = __('Your options have been updated.','membership');

		echo "<div class='wrap nosubsub'>";

			echo "<div class='innerwrap'>\n";
			echo "<h2><a href='' class='selected'>" . __('Edit Page Options','property') . "</a></h2>";

			echo "<div class='wrapcontents'>\n";

					if(isset($_GET['msg'])) {
						echo '<div id="upmessage" class="updatedmessage"><p>' . $messages[(int) $_GET['msg']];
						echo '<a href="#close" id="closemessage">' . __('close', 'property') . '</a>';
						echo '</p></div>';
						$_SERVER['REQUEST_URI'] = remove_query_arg(array('msg'), $_SERVER['REQUEST_URI']);
					}

					echo "<form action='" . admin_url("admin.php?page=" . $page) . "' method='post'>";

					echo "<input type='hidden' name='action' value='updateoptions' />";
					wp_nonce_field('update-property-page-options');

					echo "<p>";
					echo __('The options below control the pages of your StayPress installation, once set you should avoid changing them if possible. For multi-site installs, these may change the settings for <strong>all</strong> your sites, depending on your configuration.','property');
					echo "</p>";

					/*
					$pages = wp_dropdown_pages(array('post_type' => 'page', 'selected' => $M_options['registration_page'], 'name' => 'registration_page', 'show_option_none' => __('None', 'membership'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0));
					echo $pages;
					*/

					echo "<h3>" . __('Main page settings','property') . "</h3>";
					echo "<p>" . __('Use the settings below to change the main pages used by the system.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Property page','property') . "</th>";
					echo "<td>";
					$pages = wp_dropdown_pages(array('post_type' => 'page', 'selected' => $propertyoptions['propertypage'], 'name' => 'propertypage', 'show_option_none' => __('None', 'membership'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0));
					echo $pages;
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Listing page','property') . "</th>";
					echo "<td>";
					$pages = wp_dropdown_pages(array('post_type' => 'page', 'selected' => $propertyoptions['propertylistpage'], 'name' => 'propertylistpage', 'show_option_none' => __('None', 'membership'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0));
					echo $pages;
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Tag page','property') . "</th>";
					echo "<td>";
					$pages = wp_dropdown_pages(array('post_type' => 'page', 'selected' => $propertyoptions['propertytagpage'], 'name' => 'propertytagpage', 'show_option_none' => __('None', 'membership'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0));
					echo $pages;
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Destination page','property') . "</th>";
					echo "<td>";
					$pages = wp_dropdown_pages(array('post_type' => 'page', 'selected' => $propertyoptions['propertydestpage'], 'name' => 'propertydestpage', 'show_option_none' => __('None', 'membership'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0));
					echo $pages;
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Near page','property') . "</th>";
					echo "<td>";
					$pages = wp_dropdown_pages(array('post_type' => 'page', 'selected' => $propertyoptions['propertynearpage'], 'name' => 'propertynearpage', 'show_option_none' => __('None', 'membership'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0));
					echo $pages;
					echo "</td>";
					echo "</tr>";

					/*
					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Available page','property') . "</th>";
					echo "<td>";
					$pages = wp_dropdown_pages(array('post_type' => 'page', 'selected' => $propertyoptions['propertyavailpage'], 'name' => 'propertyavailpage', 'show_option_none' => __('None', 'membership'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0));
					echo $pages;
					echo "</td>";
					echo "</tr>";
					*/

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Search results page','property') . "</th>";
					echo "<td>";
					$pages = wp_dropdown_pages(array('post_type' => 'page', 'selected' => $propertyoptions['propertysearchpage'], 'name' => 'propertysearchpage', 'show_option_none' => __('None', 'membership'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0));
					echo $pages;
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Map page','property') . "</th>";
					echo "<td>";
					$pages = wp_dropdown_pages(array('post_type' => 'page', 'selected' => $propertyoptions['propertymappage'], 'name' => 'propertymappage', 'show_option_none' => __('None', 'membership'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0));
					echo $pages;
					echo "</td>";
					echo "</tr>";

					echo "</table>";

					echo "<h3>" . __('Property page permalink settings','property') . "</h3>";
					echo "<p>" . __('Use the setting below to decide if you want the property id as part of the details permalink.','property') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";

					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('Include property ID in permalink','property') . "</th>";
					echo "<td>";

					echo "<select name='permalinkhasid'>";

						echo "<option value='yes'";
						if(!isset($propertyoptions['permalinkhasid']) || $propertyoptions['permalinkhasid'] == 'yes') {
							echo " selected='selected'";
						}
						echo ">" . __('Yes','property') . "</option>";
						echo "<option value='no'";
						if(isset($propertyoptions['permalinkhasid']) && $propertyoptions['permalinkhasid'] == 'no') {
							echo " selected='selected'";
						}
						echo ">" . __('No','property') . "</option>";

					echo "</select>";

					echo "</td>";
					echo "</tr>";
					//'firstelement'		=>	'reference'
					echo "<tr valign='top'>";
					echo "<th scope='row'>" . __('If No above, first element in URL will be the','property') . "</th>";
					echo "<td>";

					echo "<select name='firstelement'>";

						echo "<option value='reference'";
						if(!isset($propertyoptions['firstelement']) || $propertyoptions['firstelement'] == 'reference') {
							echo " selected='selected'";
						}
						echo ">" . __('Reference','property') . "</option>";
						echo "<option value='slug'";
						if(isset($propertyoptions['firstelement']) && $propertyoptions['firstelement'] == 'slug') {
							echo " selected='selected'";
						}
						echo ">" . __('Title slug','property') . "</option>";

					echo "</select>";

					echo "</td>";
					echo "</tr>";

					echo "</tbody>";
					echo "</table>";

					do_action( 'staypress_property_permalinks_options_form', $propertyoptions );

					echo "<br style='clear:both;' />";

					echo "<p class='submit'>";
						echo "<input type='submit' name='Submit' class='button-primary' value='" . esc_attr('Update Options') . "' />";
					echo "</p>";

					echo "</form>\n";

			echo "</div> <!-- wrapcontents -->\n";

			echo "</div> <!-- innerwrap -->\n";

			// Start sidebar here
			echo "<div class='rightwrap'>";
			$this->show_options_rightpanel();
			echo "</div> <!-- rightwrap -->";

			echo "</div> <!-- wrap -->\n";
	}

	function replace_text($transtext, $normtext, $domain) {

		static $sp_options, $from, $to;

		if(empty($sp_options)) {
			$sp_options = SPPCommon::get_option('sp_property_options');

			$from = array();
			$from[] = '/property/';
			$from[] = '/Property/';
			$from[] = '/properties/';
			$from[] = '/Properties/';

			$to = array();
			$to[] = strtolower($sp_options['propertytext']);
			$to[] = ucfirst(strtolower($sp_options['propertytext']));
			$to[] = strtolower($sp_options['propertiestext']);
			$to[] = ucfirst(strtolower($sp_options['propertiestext']));

		}

		if(($domain == 'property' || $domain == 'booking') && !empty($sp_options)) {
			$transtext = preg_replace($from, $to, $transtext);
		}

		return $transtext;

	}

	// Helper functions
	function filter_property_get_details($property_id = false) {

		if($property_id) {

			// We want to ensure we get a record regardless of permissions on the property
			// because we'll check that later on.
			$property = $this->property->get_property( $property_id, true, false );

			if($property) {
				return $property;
			} else {
				return false;
			}

		} else {
			return false;
		}

	}

	function filter_property_get_list($limit = 999) {

		$properties = $this->property->get_extendedpropertylist(0, $limit, 'all', false);

		if(!empty($properties)) {
			return $properties;
		} else {
			return false;
		}

	}


}

?>