<?php

class sp_propertypublic {

	var $build = 1;

	var $property;

	//
	var $propertypages = array();

	var $mylocation = "";
	var $plugindir = "";
	var $base_uri = '';

	var $get_option = '';
	var $update_option = '';

	var $urloptions = array();
	var $propertyoptions = array();

	// Images
	var $loadedimages = array();
	var $images = array();
	var $current_image = array();

	// Prices
	var $loadedprices = array();
	var $prices = array();
	var $current_price = array();

	var $datacolumns = array();
	var $pricecolumns = array();
	var $origpricecolumns = array();

	var $price_periods = array();
	var $current_price_period = array();

	// Properties
	var $properties = array();
	var $loadedproperties = array();
	var $currentproperty = array();

	// Theme template
	var $requestedtemplate = '';
	var $locations = array();

	var $enhancetitle = true;
	var $processlist = true;

	// Query helpers
	var $max_pages = 0;
	var $paged = 0;

	// List shortcode helpers
	var $inshortcodelist = false;
	var $shortcodeproperty;

	function __construct() {

		global $wpdb, $user;

		$installed_build = SPPCommon::get_option('staypress_property_build', false);

		if($installed_build === false) {
			$installed_build = $this->build;
			// Create the property class and force table creation
			$this->property =& new property_model($wpdb, 0);
			SPPCommon::update_option('staypress_property_build', $installed_build);
		} else {
			// Create the property class and send through installed build version
			$this->property =& new property_model($wpdb, $installed_build);
		}

		$tz = get_option('gmt_offset');
		$this->property->set_timezone($tz);

		add_action( 'init', array( &$this, 'initialise_property' ) );

		add_action( 'admin_bar_menu', array(&$this, 'add_wp_admin_menu_actions'), 45 );

		// Rewrites
		add_action('generate_rewrite_rules', array(&$this, 'add_rewrite'));
		add_filter('query_vars', array(&$this, 'add_queryvars'));
		// Do the, erm, business
		add_action('pre_get_posts', array(&$this, 'process_staypress_pages') );

		add_action('wp_head', array(&$this, 'grab_query_defaults'));

	}

	function __destruct() {

	}

	function sp_propertypublic() {
		$this->__construct();
	}

	function initialise_property() {

		// Assign the user id to the property model
		$user = wp_get_current_user();
		$this->property->set_userid($user->ID);

		// Set permissions if they haven't already been set
		$role = get_role( 'contributor' );
		if( !$role->has_cap( 'read_destination' ) ) {
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
																'public' => false,
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
											'with_front' => false )
						);

		register_taxonomy( 'propertylocation', array( STAYPRESS_DESTINATION_POST_TYPE, STAYPRESS_PROPERTY_POST_TYPE), $locationsettings );

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

		register_taxonomy( 'propertyadministration', STAYPRESS_PROPERTY_POST_TYPE, array( 'public' => false, 'show_ui' => false, 'show_tagcloud' => false, 'show_in_nav_menus' => false, 'hierarchical' => false, 'label' => __('Adminstration','property') , 'query_var' => false, 'rewrite' => false ) );
		register_taxonomy( 'destinationadministration', STAYPRESS_DESTINATION_POST_TYPE, array( 'public' => false, 'show_ui' => false, 'show_tagcloud' => false, 'show_in_nav_menus' => false, 'hierarchical' => false, 'label' => __('Adminstration','property') , 'query_var' => false, 'rewrite' => false ) );

		// Add in the query extension functions
		// Join our property table so we don't need to mess with a lot of extra look ups later on
		add_filter( 'posts_join_request',	array( &$this->property, 'add_to_property_to_join' ), 10, 2 );
		add_filter( 'posts_fields_request',	array( &$this->property, 'add_to_property_to_fields' ), 10, 2 );

		// Add in extension fields when on a single posts details - if they are not already there.
		add_filter( 'the_post', array( &$this->property, 'extend_property' ) );

		// Load default options
		$defaultoptions = array( 	'propertytext'			=> 	'property',
									'propertiestext'		=>	'properties',
									'permalinkhasid'		=>	'yes',
									'firstelement'			=>	'reference',
									'propertytitlelayout'	=>	'%title%',
									'propertytitlemarker'	=>	'numeric',
									'propertysearchtext'	=>	'Search for...',
									'listingmethod'			=>	'permalink'
								);

		$this->propertyoptions = SPPCommon::get_option('sp_property_options', $defaultoptions);

		// If we are using a mapping widget - then enqueue the data at the footer for it.
		// This may move to the widget itself, but I think it will be useful for other items as well so the if may extend.
		if(is_active_widget(false, false, 'sp_propertylistingmap') || (defined('STAYPRESS_ENFORCE_MAP_DATA') && STAYPRESS_ENFORCE_MAP_DATA == true )) {
			// Add in a filter that enqueues the data for property post types.
			add_filter('the_posts', array(&$this, 'enqueue_propertylist_data'));
			// Output any data that the filter enqueued - make sure it's in the footer so we get the lot.
			add_action( 'wp_footer', array('SPPCommon','print_data'));
		}

		// Register shortcodes
		$this->register_shortcodes();

		// Check for a search URL
		if(!empty($_REQUEST['searchmadeby'])) {
			// We seem to have a search so lets do some parsing.
			$this->handle_search_redirect();
		}

		// register search functions - fallback full search processing
		add_filter('staypress_process_search_positive', array(&$this,'get_fullsearch_results_postive'), 10, 2);

		// enqueue our basic global js stuff - may move to another area for page specific items
		if(!current_theme_supports( 'staypress_property_script' )) {
			wp_enqueue_script('propertypublicjs', SPPCommon::property_url('js/public.js'), array('jquery'), $this->build);
			wp_localize_script('propertypublicjs', 'staypresspublic', array(	"searchtext"	=>	__($this->propertyoptions['propertysearchtext'],'property')) );
		}

		// Permalink overriding
		add_filter('post_type_link', array(&$this, 'override_wp_permalinks'), 10, 4);

	}

	function add_wp_admin_menu_actions() {
		global $wp_admin_bar;

		if(current_user_can('edit_property')) {
			$wp_admin_bar->add_menu( array( 'parent' => 'new-content', 'id' => 'property', 'title' => __('Property','property'), 'href' => admin_url('admin.php?page=property-add') ) );
		}

	}

	function grab_query_defaults() {
		global $wp_query;

		$this->paged = get_query_var('paged');

	}

	function register_shortcodes() {
		add_shortcode('propertyfacilities', array(&$this, 'do_propertyfacilities_shortcode') );
		add_shortcode('propertylowestprice', array(&$this, 'do_propertylowestprice_shortcode') );
		add_shortcode('propertymeta', array(&$this, 'do_propertymeta_shortcode') );

		add_shortcode('propertycountry', array(&$this, 'do_propertycountry_shortcode') );
		add_shortcode('propertyregion', array(&$this, 'do_propertyregion_shortcode') );
		add_shortcode('propertytown', array(&$this, 'do_propertytown_shortcode') );
		add_shortcode('propertydestinationexcerpt', array(&$this, 'do_propertydestinationexcert_shortcode') );
		add_shortcode('propertydestination', array(&$this, 'do_propertydestination_shortcode') );
		add_shortcode('propertydestinationthumbnail', array(&$this, 'do_propertydestinationthumbnail_shortcode') );
		add_shortcode('propertydestinationbreadcrumb', array(&$this, 'do_propertydestinationbreadcrumb_shortcode') );

		add_shortcode('propertyprices', array(&$this, 'do_propertyprices_shortcode') );
		add_shortcode('propertymonthlyprices', array(&$this, 'do_propertymonthlyprices_shortcode') );
		add_shortcode('propertypricenotes', array(&$this, 'do_propertypricenotes_shortcode') );

		add_shortcode('propertymap', array(&$this, 'do_propertymap_shortcode') );

		add_shortcode('propertyid', array(&$this, 'do_propertyid_shortcode') );
		add_shortcode('propertyreference', array(&$this, 'do_propertyreference_shortcode') );

		add_shortcode('propertytitle', array(&$this, 'do_propertytitle_shortcode') );
		add_shortcode('propertypermalink', array(&$this, 'do_propertypermalink_shortcode') );

		add_shortcode('propertysearchbox', array(&$this, 'do_propertysearchbox_shortcode') );
		add_shortcode('propertyadvancedsearchbox', array(&$this, 'do_propertyadvancedsearchbox_shortcode') );

		// Contacts shortcodes
		add_shortcode('propertycontactnotes', array(&$this, 'do_propertycontactnotes_shortcode') );
		add_shortcode('propertycontacttel', array(&$this, 'do_propertycontacttel_shortcode') );
		add_shortcode('propertycontactemail', array(&$this, 'do_propertycontactemail_shortcode') );
		add_shortcode('propertycontactname', array(&$this, 'do_propertycontactname_shortcode') );

		add_shortcode('propertyenquiryform', array(&$this, 'do_propertyenquiryform_shortcode') );

		// Extra little bits of shortcode
		add_shortcode('propertylist', array(&$this, 'do_propertylist_shortcode') );
		add_shortcode('propertylistpagination', array(&$this, 'do_propertylistpagination_shortcode') );
		add_shortcode('propertythumbnail', array(&$this, 'do_propertythumbnail_shortcode') );
		add_shortcode('propertyexcerpt', array(&$this, 'do_propertyexcerpt_shortcode') );
		add_shortcode('propertydescription', array(&$this, 'do_propertydescription_shortcode') );

		add_filter('the_posts', array(&$this, 'check_for_shortcodes'));

	}

	function check_for_shortcodes($posts) {

		foreach( (array) $posts as $post) {
			if(strpos($post->post_content, '[property') !== false) {
				if(!current_theme_supports( 'staypress_property_style' )) {
					// We have a staypress property shortcode somewhere on a page so enqueue the default styles
					wp_enqueue_style('propertydefaultstyles', SPPCommon::property_url('css/default.shortcode.css'));
				}

				if(strpos($post->post_content, '[propertymap') !== false) {
					if(!current_theme_supports( 'staypress_property_script' )) {
						// We have a map shortcode in here so enqueue our map content - put in footer just in case we are past the header already
						wp_enqueue_script('googlemaps', "http://maps.google.com/maps/api/js?sensor=true", array(), $this->build, true);
						wp_enqueue_script('propertymapshortcode', SPPCommon::property_url('js/property.mapshortcode.js'), array('googlemaps', 'jquery'), $this->build, true);
					}
				}
			}


		}

		return $posts;

	}

	function flush_rewrite() {
		// This function clears the rewrite rules and forces them to be regenerated

		global $wp_rewrite;

		$wp_rewrite->flush_rules();

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

		if($this->propertyoptions['listingmethod'] == 'permalink') {

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

			//print_r($pageoptions);

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

	function enhance_title( $title, $args ) {

		extract($args);

		if(empty($this->propertyoptions['propertytitlelayout'])) $this->propertyoptions['propertytitlelayout'] = '%title%';
		$title = str_replace('%title%', $title, $this->propertyoptions['propertytitlelayout']);
		$title = str_replace('%listmarker%', $key, $title);
		$title = str_replace('%reference%', $reference, $title);

		return $title;

	}

	function enqueue_propertylist_data( $posts ) {

		global $wp_query;
		//print_r($wp_query);

		if($this->processlist === false) {
			return $posts;
		}

		static $alphabet = array("-","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		static $propertydata = array();
		static $count = 1;

		foreach($posts as $postkey => $post) {
			if($post->post_type == STAYPRESS_PROPERTY_POST_TYPE && !empty($post->latitude)) {
				if($this->propertyoptions['propertytitlemarker'] == 'alphabetic' && count($posts) <= 26 ) {
					$key = $alphabet[$count++];
				} else {
					$key = $count++;
				}

				$propertydata[$key] = array(		'ID'			=> 	$post->ID,
													'post_title' 	=> 	$post->post_title,
													'post_excerpt' 	=> 	$post->post_excerpt,
													'latitude'		=> 	$post->latitude,
													'longitude'		=>	$post->longitude,
													'permalink'		=>	sp_get_permalink( 'property', $post )
												);


				if($this->enhancetitle) {
					$title = $post->post_title;
					if(!empty($post->reference)) {
						$reference = $post->reference;
					} else {
						$reference = '';
					}
					$posts[$postkey]->post_title = $this->enhance_title($title, array('key' => $key, 'reference' => $reference));
				}


			}
		}

		if(!empty($propertydata)) {
			SPPCommon::enqueue_data('staypress_data', 'propertylist', $propertydata);
		}

		return $posts;
	}

	function enqueue_propertynear_data( $posts ) {

		static $alphabet = array("-","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		static $propertydata = array();
		static $count = 1;

		foreach($posts as $postkey => $post) {
			if($post->post_type == STAYPRESS_PROPERTY_POST_TYPE && !empty($post->latitude)) {
				if($this->propertyoptions['propertytitlemarker'] == 'alphabetic' && count($posts) <= 26 ) {
					$key = $alphabet[$count++];
				} else {
					$key = $count++;
				}

				$propertydata[$key] = array(		'ID'			=> 	$post->ID,
													'post_title' 	=> 	$post->post_title,
													'post_excerpt' 	=> 	$post->post_excerpt,
													'latitude'		=> 	$post->latitude,
													'longitude'		=>	$post->longitude,
													'permalink'		=>	sp_get_permalink( 'property', $post )
												);


				if($this->enhancetitle) {
					$title = $post->post_title;
					$posts[$postkey]->post_title = $this->enhance_title($title, array('key' => $key, 'reference' => ''));
				}

			}
		}

		if(!empty($propertydata)) {
			SPPCommon::enqueue_data('staypress_data', 'propertylist_near', $propertydata);
		}

		return $posts;
	}

	function debug_out( $value ) {
		print_r($value);
		return $value;
	}

	function unset_single_flag( $posts ) {

		global $wp_query;

		$wp_query->is_single = false;

		return $posts;
	}

	function build_title_property( $title, $sep, $seplocation ) {

		global $post;

		if(!defined(STAYPRESS_ON_PROPERTY_PAGE)) {
			$property_id = get_the_id();
		} else {
			$property_id = STAYPRESS_ON_PROPERTY_PAGE;
		}

		$property =& get_post( $property_id );

		$newtitle = $this->propertyoptions['propertydetailstitle'];

		if(empty($newtitle)) return $title;

		$newtitle = str_replace('%blogname%', get_bloginfo('name'), $newtitle);
		$newtitle = str_replace('%taglist%', '', $newtitle);	// Not valid for this
		$newtitle = str_replace('%criteria%', '', $newtitle);	// Not valid for this
		$newtitle = str_replace('%town%', $this->get_town($property_id), $newtitle);
		$newtitle = str_replace('%region%', $this->get_region($property_id), $newtitle);
		$newtitle = str_replace('%country%', $this->get_country($property_id), $newtitle);

		$destination = array();
		if($this->get_town($property_id) != '') {
			$destination[] = $this->get_town($property_id);
		}
		if($this->get_region($property_id) != '') {
			$destination[] = $this->get_region($property_id);
		}
		if($this->get_country($property_id) != '') {
			$destination[] = $this->get_country($property_id);
		}
		$newtitle = str_replace('%destlist%', implode(', ', $destination), $newtitle);

		$newtitle = str_replace('%title%', apply_filters( 'single_post_title', $property->post_title ), $newtitle);

		$newtitle = str_replace('%sep%', $sep, $newtitle);

		return $newtitle;

	}

	function build_title_list( $title, $sep, $seplocation ) {

		$newtitle = $this->propertyoptions['propertylisttitle'];

		if(empty($newtitle)) return $title;

		$newtitle = str_replace('%blogname%', get_bloginfo('name'), $newtitle);
		$newtitle = str_replace('%taglist%', '', $newtitle);	// Not valid for this
		$newtitle = str_replace('%criteria%', '', $newtitle);	// Not valid for this
		$newtitle = str_replace('%town%', '', $newtitle);
		$newtitle = str_replace('%region%', '', $newtitle);
		$newtitle = str_replace('%country%', '', $newtitle);
		$newtitle = str_replace('%destlist%', '', $newtitle);
		$newtitle = str_replace('%title%', '', $newtitle);
		$newtitle = str_replace('%sep%', $sep, $newtitle);

		return $newtitle;

	}

	function get_term_by_slug( $slug ) {

		global $wpdb;

		$sql = $wpdb->prepare( "SELECT * FROM $wpdb->terms as t WHERE slug = %s LIMIT 0, 1", $slug );

		return $wpdb->get_row( $sql );
	}

	function build_title_tag( $title, $sep, $seplocation ) {

		global $sp_tags;

		$newtitle = $this->propertyoptions['propertytagtitle'];

		if(empty($newtitle)) return $title;

		$newtitle = str_replace('%blogname%', get_bloginfo('name'), $newtitle);

		if(!empty($sp_tags)) {
			$tags = array();
			foreach($sp_tags as $tag) {
				$itag = $this->get_term_by_slug($tag);
				$tags[] = $itag->name;
			}
		}

		$newtitle = str_replace('%taglist%', implode(', ', $tags), $newtitle);
		$newtitle = str_replace('%criteria%', '', $newtitle);	// Not valid for this
		$newtitle = str_replace('%town%', '', $newtitle);
		$newtitle = str_replace('%region%', '', $newtitle);
		$newtitle = str_replace('%country%', '', $newtitle);
		$newtitle = str_replace('%destlist%', '', $newtitle);
		$newtitle = str_replace('%title%', '', $newtitle);
		$newtitle = str_replace('%sep%', $sep, $newtitle);

		return $newtitle;

	}

	function build_title_destination( $title, $sep, $seplocation ) {

		$newtitle = $this->propertyoptions['propertydesttitle'];

		if(empty($newtitle)) return $title;

		$newtitle = str_replace('%blogname%', get_bloginfo('name'), $newtitle);
		$newtitle = str_replace('%taglist%', '', $newtitle);	// Not valid for this
		$newtitle = str_replace('%criteria%', '', $newtitle);	// Not valid for this

		if(!empty($this->locations[2])) {
			$newtitle = str_replace('%town%', $this->unmake_url($this->locations[2]), $newtitle);
		} else {
			$newtitle = str_replace('%town%', '', $newtitle);
		}
		if(!empty($this->locations[1])) {
			$newtitle = str_replace('%region%', $this->unmake_url($this->locations[1]), $newtitle);
		} else {
			$newtitle = str_replace('%region%', '', $newtitle);
		}
		if(!empty($this->locations[0])) {
			$newtitle = str_replace('%country%', $this->unmake_url($this->locations[0]), $newtitle);
		} else {
			$newtitle = str_replace('%country%', '', $newtitle);
		}

		$destination = array();
		if(!empty($this->locations[2])) {
			$destination[] = $this->unmake_url($this->locations[2]);
		}
		if(!empty($this->locations[1])) {
			$destination[] = $this->unmake_url($this->locations[1]);
		}
		if(!empty($this->locations[0])) {
			$destination[] = $this->unmake_url($this->locations[0]);
		}
		$newtitle = str_replace('%destlist%', implode(', ', $destination), $newtitle);

		$newtitle = str_replace('%title%', '', $newtitle);

		$newtitle = str_replace('%sep%', $sep, $newtitle);

		return $newtitle;

	}

	function build_title_search( $title, $sep, $seplocation ) {

		global $wp_query;

		$newtitle = $this->propertyoptions['propertysearchtitle'];

		if(empty($newtitle)) return $title;

		$newtitle = str_replace('%blogname%', get_bloginfo('name'), $newtitle);
		$newtitle = str_replace('%taglist%', '', $newtitle);	// Not valid for this
		$newtitle = str_replace('%destlist%', '', $newtitle);
		$newtitle = str_replace('%town%', '', $newtitle);
		$newtitle = str_replace('%region%', '', $newtitle);
		$newtitle = str_replace('%country%', '', $newtitle);
		$newtitle = str_replace('%title%', '', $newtitle);

		$newtitle = str_replace('%criteria%', $this->unmake_url($wp_query->query_vars['search']), $newtitle);	// Not valid for this

		$newtitle = str_replace('%sep%', $sep, $newtitle);

		return $newtitle;

	}

	function build_title_near( $title, $sep, $seplocation ) {

		global $post;

		if(!defined(STAYPRESS_ON_PROPERTY_NEAR)) {
			//$property_id = get_the_id();
		} else {
			$property_id = STAYPRESS_ON_PROPERTY_NEAR;
		}

		$property =& get_post( $property_id );

		$newtitle = $this->propertyoptions['propertyneartitle'];

		if(empty($newtitle)) return $title;

		$newtitle = str_replace('%blogname%', get_bloginfo('name'), $newtitle);
		$newtitle = str_replace('%taglist%', '', $newtitle);	// Not valid for this
		$newtitle = str_replace('%criteria%', '', $newtitle);	// Not valid for this
		$newtitle = str_replace('%town%', $this->get_town($property_id), $newtitle);
		$newtitle = str_replace('%region%', $this->get_region($property_id), $newtitle);
		$newtitle = str_replace('%country%', $this->get_country($property_id), $newtitle);

		$destination = array();
		if($this->get_town($property_id) != '') {
			$destination[] = $this->get_town($property_id);
		}
		if($this->get_region($property_id) != '') {
			$destination[] = $this->get_region($property_id);
		}
		if($this->get_country($property_id) != '') {
			$destination[] = $this->get_country($property_id);
		}
		$newtitle = str_replace('%destlist%', implode(', ', $destination), $newtitle);

		$newtitle = str_replace('%title%', apply_filters( 'single_post_title', $property->post_title ), $newtitle);

		$newtitle = str_replace('%sep%', $sep, $newtitle);

		return $newtitle;

	}

	function process_staypress_pages($wp_query) {
		global $wpdb, $sp_locations;

		if(isset($wp_query->query_vars['namespace']) && $wp_query->query_vars['namespace'] == 'staypress') {

			$defaultoptions = array( 	'propertytext'		=> 	'property',
										'propertiestext'	=>	'properties',
										'listingmethod'		=>	'permalink',
										'permalinkhasid'	=>	'yes',
										'firstelement'		=>	'reference'
									);

			$propertyoptions = SPPCommon::get_option('sp_property_options', $defaultoptions);

			if($propertyoptions['listingmethod'] == 'permalink') {

				// set up the defaults for all pages
				$wp_query->query_vars['post_type'] = 'property';
				$wp_query->query_vars['orderby'] = 'post_modified';
				$wp_query->query_vars['order'] = 'DESC';

				switch($wp_query->query_vars['type']) {

					case 'list':			$wp_query->query_vars['post_status'] = 'publish';

											$positive_ids = array();
											$negative_ids = array();

											$positive_ids = apply_filters( 'staypress_process_list_positive', $positive_ids, $wp_query );
											$negative_ids = apply_filters( 'staypress_process_list_negative', $negative_ids, $wp_query );

											$positive_ids = array_diff($positive_ids, $negative_ids);

											$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

											$wp_query->query_vars['post__in'] = $positive_ids;
											// reset incorrectly set wp_query variables
											$wp_query->is_singular = false;
											$wp_query->is_home = false;
											$wp_query->is_archive = true;

											// Title
											add_filter('wp_title', array(&$this,'build_title_list'), 10, 3 );

											$this->requestedtemplate = 'property-list.php';

											add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
											break;

					case 'property':		$defaultoptions = array( 	'permalinkhasid'		=>	'no',
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
											if(empty($permaoptions['permalinkhasid']) || $permaoptions['permalinkhasid'] == 'yes') {
												// we are using the already passed p for the id directly from the url
												// so don't really need to do anything else here.
											} else {
												// we need to check if we are using reference or title
												if((empty($permaoptions['firstelement']) || $permaoptions['firstelement'] == 'reference') && !empty($wp_query->query_vars['reference'])) {
													$property = $this->property->get_postid_for_reference( $wp_query->query_vars['reference'] );
													if($property) {
														$wp_query->query_vars['p'] = $property;
													}
												}
											}

											$wp_query->query_vars['post_status'] = 'publish,private';

											// On a single property page - we're going to define the current property for use elsewhere
											if(!defined('STAYPRESS_ON_PROPERTY_PAGE')) define('STAYPRESS_ON_PROPERTY_PAGE', $wp_query->query_vars['p'] );
											// May come back and alter this to a neater option
											// reset incorrectly set wp_query variables
											$wp_query->is_single = true;
											$wp_query->is_singular = true;
											$wp_query->is_home = false;
											$wp_query->is_archive = false;
											$wp_query->is_search = false;

											// Title
											add_filter('wp_title', array(&$this,'build_title_property'), 10, 3 );

											$this->requestedtemplate = 'property-details.php';
											$this->enhancetitle = false;
											break;

					case 'tag':				global $sp_tags;

											$wp_query->query_vars['post_status'] = 'publish';
											$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

											$tag = $wp_query->query_vars['with'];
											if(!empty($tag)) {
												$sp_tags = explode('/', $tag);
												$positive_ids = $this->property->get_properties_with_tags( $sp_tags );
												$wp_query->query_vars['tag'] = '';
											}

											$positive_ids = apply_filters( 'staypress_process_tag_positive', $positive_ids, $wp_query );
											$negative_ids = apply_filters( 'staypress_process_tag_negative', array(), $wp_query );
											$positive_ids = array_diff($positive_ids, $negative_ids);

											if(empty($positive_ids)) $positive_ids = array(0);

											$wp_query->query_vars['post__in'] = $positive_ids;

											// reset incorrectly set wp_query variables
											$wp_query->is_singular = false;
											$wp_query->is_home = false;
											$wp_query->is_archive = true;
											$wp_query->is_category = false;
											$wp_query->is_tag = false;
											$wp_query->is_tax = false;

											// Title
											add_filter('wp_title', array(&$this,'build_title_tag'), 10, 3 );

											$this->requestedtemplate = 'property-tag.php';

											add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
											break;

					case 'dest':
											$wp_query->query_vars['post_status'] = 'publish';
											$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

											if(!empty($wp_query->query_vars['name'])) unset($wp_query->query_vars['name']);
											$this->locations = explode('/', $wp_query->query_vars['dest']);

											switch(count($this->locations)) {
												case 1:	// country
														$positive_ids = $this->property->get_country_ids($this->locations[0]);
														break;
												case 2: // region
														$positive_ids = $this->property->get_region_ids($this->locations[0], $this->locations[1]);
														break;
												case 3: // town
														$positive_ids = $this->property->get_town_ids($this->locations[0], $this->locations[1], $this->locations[2]);
														break;
											}

											$positive_ids = apply_filters( 'staypress_process_dest_positive', $positive_ids, $wp_query );
											$negative_ids = apply_filters( 'staypress_process_dest_negative', array(), $wp_query );
											$positive_ids = array_diff($positive_ids, $negative_ids);

											if(empty($positive_ids)) $positive_ids = array(0);

											$wp_query->query_vars['post__in'] = $positive_ids;
											// reset incorrectly set wp_query variables
											$wp_query->is_singular = false;
											$wp_query->is_home = false;
											$wp_query->is_archive = true;

											// Title
											add_filter('wp_title', array(&$this,'build_title_destination'), 10, 3 );

											$this->requestedtemplate = 'property-destination.php';

											add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
											break;

					case 'near':
											$wp_query->query_vars['post_status'] = 'publish';
											$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

											if(isset($_GET['radius'])) {
												$radius = (int) $_GET['radius'];
											} else {
												$radius = 100;
											}
											//$wp_query->query_vars['paged'] = $this->paged;

											// Need to add in a bounds calculation to these two following functions
											$near = $wp_query->query_vars['near'];
											if(strpos($near, ',') === false) {
												// we want properties near an id
												$near = (int) $near;
												if(!defined('STAYPRESS_ON_PROPERTY_NEAR')) define('STAYPRESS_ON_PROPERTY_NEAR', $near );
												$nearby = $this->get_properties_near_property( $near, $radius, STAYPRESS_PROPERTY_PER_PAGE );

											} else {
												// we want properties near a location
												$near = explode(',', $near);
												if(count($near) >= 2) {
													$nearby = $this->get_properties_near( $near[0], $near[1], $radius, STAYPRESS_PROPERTY_PER_PAGE );
												} else {
													$nearby = array();
												}
											}

											$positive_ids = array();
											if(!empty($nearby)) {
												foreach($nearby as $key => $property ) {
													if($property->post_id != $near) {
														// we don't want our current property listed
														$positive_ids[] = $property->post_id;
													}
												}
											}

											$positive_ids = apply_filters( 'staypress_process_near_positive', $positive_ids, $wp_query );
											$negative_ids = apply_filters( 'staypress_process_near_negative', array(), $wp_query );
											$positive_ids = array_diff($positive_ids, $negative_ids);

											if(empty($positive_ids)) $positive_ids = array(0);

											$wp_query->query_vars['post__in'] = $positive_ids;
											// reset incorrectly set wp_query variables
											$wp_query->is_singular = false;
											$wp_query->is_home = false;
											$wp_query->is_archive = true;

											// Title
											add_filter('wp_title', array(&$this,'build_title_near'), 10, 3 );

											$this->requestedtemplate = 'property-near.php';

											add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
											break;

					case 'available':
											$wp_query->query_vars['post_status'] = 'publish';
											$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

											$negative_ids = apply_filters( 'staypress_process_unavailable_properties', $wp_query );
											if(empty($negative_ids)) $negative_ids = array(0);
											$wp_query->query_vars['post__not_in'] = $negative_ids;
											// reset incorrectly set wp_query variables
											$wp_query->is_singular = false;
											$wp_query->is_home = false;
											$wp_query->is_archive = true;

											$this->requestedtemplate = 'property-available.php';

											add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
											break;

					case 'search':
											$searchfrom = addslashes($_REQUEST['searchmadeby']);
											$wp_query->query_vars['post_status'] = 'publish';

											// default is no results
											$positive_ids = array(0);
											$negative_ids = array(0);

											if(has_filter('staypress_process_full_search_positive')) {
												$positive_ids = apply_filters('staypress_process_full_search_positive', $positive_ids, $wp_query);
												$negative_ids = apply_filters( 'staypress_process_full_search_negative', $negative_ids, $wp_query );
											} elseif(has_filter('staypress_process_' . $searchfrom . '_search_positive')) {
												$positive_ids = apply_filters('staypress_process_' . $searchfrom . '_search_positive', $positive_ids, $wp_query);
												$negative_ids = apply_filters( 'staypress_process_' . $searchfrom . '_search_negative', $negative_ids, $wp_query );
											} else {
												$positive_ids = apply_filters( 'staypress_process_search_positive', $positive_ids, $wp_query );
												$negative_ids = apply_filters( 'staypress_process_search_negative', $negative_ids, $wp_query );
											}

											$positive_ids = array_diff( (array) $positive_ids, (array) $negative_ids );

											if(empty($positive_ids)) {
												$positive_ids = array(0);
											}

											$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

											$wp_query->query_vars['post__in'] = $positive_ids;
											// reset incorrectly set wp_query variables
											$wp_query->is_singular = false;
											$wp_query->is_home = false;
											$wp_query->is_search = true;

											// Title
											add_filter('wp_title', array(&$this,'build_title_search'), 10, 3 );

											$this->requestedtemplate = 'property-search.php';

											add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );

											break;

					case 'map':				$wp_query->query_vars['post_status'] = 'publish';
											$this->requestedtemplate = 'property-map.php';
											break;

					default:				// Everything else
											$wp_query->query_vars['post_status'] = 'publish';
											$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

											do_action('staypress_process_page_type_' . $wp_query->query_vars['type'], $wp_query);
											$this->requestedtemplate = 'property-list.php';

											add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
											break;


				}

				add_filter('template_include', array(&$this, 'use_template'));

			} else {
				switch($wp_query->query_vars['type']) {

					case 'list':
											// Title
											add_filter('wp_title', array(&$this,'build_title_list'), 10, 3 );

											break;

					case 'property':		$defaultoptions = array( 	'permalinkhasid'		=>	'no',
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

											if(empty($pageoptions['permalinkhasid']) || $pageoptions['permalinkhasid'] == 'yes') {
												// we are using the already passed p for the id directly from the url
												// so don't really need to do anything else here.
												if(!defined('STAYPRESS_ON_PROPERTY_PAGE')) define('STAYPRESS_ON_PROPERTY_PAGE', $wp_query->query_vars['propertyid'] );
											} else {
												// we need to check if we are using reference or title
												if((empty($pageoptions['firstelement']) || $pageoptions['firstelement'] == 'reference') && !empty($wp_query->query_vars['reference'])) {
													$property = $this->property->get_postid_for_reference( $wp_query->query_vars['reference'] );
													if($property) {
														if(!defined('STAYPRESS_ON_PROPERTY_PAGE')) define('STAYPRESS_ON_PROPERTY_PAGE', $property );
													}
												} else {
													// Need to find a property based on the title here.

												}
											}

											// Title
											add_filter('wp_title', array(&$this,'build_title_property'), 10, 3 );

											$this->enhancetitle = false;
											break;

					case 'tag':
											// Title
											add_filter('wp_title', array(&$this,'build_title_tag'), 10, 3 );
											break;

					case 'dest':
											// Title
											add_filter('wp_title', array(&$this,'build_title_destination'), 10, 3 );
											break;

					case 'near':
											// Title
											add_filter('wp_title', array(&$this,'build_title_near'), 10, 3 );
											break;

					case 'available':

											break;

					case 'search':
											// Title
											add_filter('wp_title', array(&$this,'build_title_search'), 10, 3 );
											break;

					case 'map':
											break;

					default:				break;


				}
			}
		}
	}

	function use_drilldown_post( $key = '', $drilldown = array(), $return = 0 ) {

		if($key == '') $key = '_staypress_destination_information';

		if(empty($drilldown) && !empty($this->locations)) {
			$drilldown = $this->locations;
		}

		$innerdrilldown = array();

		while(!empty($drilldown)) {
			$innerdrilldown[] = implode('-', $drilldown);
			$remove = array_pop($drilldown);
		}

		$ids = $this->property->get_posts_for_meta_key( $key, $innerdrilldown );

		if(!empty($ids)) {
			return $ids[$return];
		} else {
			return false;
		}

	}

	function use_template($template) {

		if(!empty($this->requestedtemplate)) {
			if ( file_exists(STYLESHEETPATH . '/' . $this->requestedtemplate)) {
				$template = STYLESHEETPATH . '/' . $this->requestedtemplate;
			} else if ( file_exists(TEMPLATEPATH . '/' . $this->requestedtemplate) ) {
				$template = TEMPLATEPATH . '/' . $this->requestedtemplate;
			} else if ( file_exists(TEMPLATEPATH . '/property-list.php') ) {
				// defaults to propertylist if it exists.
				$template = TEMPLATEPATH . '/property-list.php';
			}
		}

		return $template;

	}

	function make_url($thestring) {

		return str_replace(' ','-', strtolower($thestring));

	}

	function unmake_url($thestring) {

		return ucwords(str_replace('-',' ', $thestring));

	}

	function override_wp_permalinks( $permalink, $post, $leavename, $sample ) {

		if(!empty($post) && !empty($post->post_type) && $post->post_type == STAYPRESS_PROPERTY_POST_TYPE) {
			$permalink = $this->get_permalink('property', $post);
		}

		return $permalink;

	}

	function get_permalink($type = 'property', $post = false, $extend = '') {

		$permalink = '';

		if($this->propertyoptions['listingmethod'] == 'permalink') {
			$page_prefix = SPPCommon::get_option('property_rewrite_prefix', '');
			if(!empty($page_prefix)) $page_prefix = trailingslashit($page_prefix);

			switch($type) {

				case 'properties': 	$permalink = __('properties', 'property');
									break;

				case 'property':
									if(empty($this->propertyoptions['permalinkhasid']) || $this->propertyoptions['permalinkhasid'] == 'yes') {
										$permalink = __('property', 'property') . '/' . $post->ID . '/' . $post->post_name;
									} else {
										// we need to check if we are using reference or title
										if(empty($this->propertyoptions['firstelement']) || $this->propertyoptions['firstelement'] == 'reference') {
											$permalink = __('property', 'property') . '/' . $this->make_url($this->get_reference($post->ID));
										} else {
											$permalink = __('property', 'property') . '/' . $post->post_name;
										}
									}

									break;

				case 'with':		$permalink = $page_prefix . __('with', 'property');
									break;

				case 'dest':		$permalink = $page_prefix . __('in', 'property');
									break;

				case 'country':		$permalink = $page_prefix . __('in', 'property') . '/' . $this->make_url($this->get_country($post->ID));
									break;

				case 'region':		$permalink = $page_prefix . __('in', 'property') . '/' . $this->make_url($this->get_country($post->ID));
									$permalink .= '/' . $this->make_url($this->get_region($post->ID));
									break;

				case 'town':		$permalink = $page_prefix . __('in', 'property') . '/' . $this->make_url($this->get_country($post->ID));
									$permalink .= '/' . $this->make_url($this->get_region($post->ID));
									$permalink .= '/' . $this->make_url($this->get_town($post->ID));
									break;

				case 'nearproperty':
									$permalink = $page_prefix . __('near', 'property');
									$permalink .= '/' . $post->ID;
									break;

				case 'nearcoords':
									$permalink = $page_prefix . __('near', 'property');
									$permalink .= '/' . $this->get_latitude($post->ID) . ',' . $this->get_longitude($post->ID);
									break;

				case 'search':		$permalink = $page_prefix . __('search', 'property');
									if(!empty($_GET['s'])) {
										$permalink .= '/' . $this->make_url($_GET['s']);
									}
									break;

				case 'mapproperty':
									$permalink = $page_prefix . __('map', 'property');
									$permalink .= '/' . $post->ID;
									break;

				case 'mapcoords':
									$permalink = $page_prefix . __('map', 'property');
									$permalink .= '/' . $this->get_latitude($post->ID) . ',' . $this->get_longitude($post->ID);
									break;

			}

		} else {
			// Pages method
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

			switch( $type ) {

				case 'property':				$propertypage = untrailingslashit( get_permalink( $pageoptions['propertypage'] ) );
												$propertypage = str_replace( trailingslashit( get_option('home') ), '', $propertypage );

												if(empty($pageoptions['permalinkhasid']) || $pageoptions['permalinkhasid'] == 'yes') {
													$permalink = $propertypage . '/' . $post->ID . '/' . $post->post_name;
												} else {
													// we need to check if we are using reference or title
													if(empty($pageoptions['firstelement']) || $pageoptions['firstelement'] == 'reference') {
														$permalink = $propertypage . '/' . $this->make_url($this->get_reference($post->ID));
													} else {
														$permalink = $propertypage . '/' . $post->post_name;
													}
												}
												break;

				case 'properties':				$propertylistpage = untrailingslashit( get_permalink( $pageoptions['propertylistpage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertylistpage );
												break;

				case 'with':					$propertytagpage = untrailingslashit( get_permalink( $pageoptions['propertytagpage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertytagpage );
												break;

				case 'dest':					$propertydestpage = untrailingslashit( get_permalink( $pageoptions['propertydestpage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertydestpage );
												break;

				case 'country':					$propertydestpage = untrailingslashit( get_permalink( $pageoptions['propertydestpage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertydestpage );
												$permalink .= '/' . $this->make_url($this->get_country($post->ID));
												break;

				case 'region':					$propertydestpage = untrailingslashit( get_permalink( $pageoptions['propertydestpage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertydestpage );
												$permalink .= '/' . $this->make_url($this->get_country($post->ID));
												$permalink .= '/' . $this->make_url($this->get_region($post->ID));
												break;

				case 'town':					$propertydestpage = untrailingslashit( get_permalink( $pageoptions['propertydestpage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertydestpage );
												$permalink .= '/' . $this->make_url($this->get_country($post->ID));
												$permalink .= '/' . $this->make_url($this->get_region($post->ID));
												$permalink .= '/' . $this->make_url($this->get_town($post->ID));
												break;

				case 'nearproperty':			$propertynearpage = untrailingslashit( get_permalink( $pageoptions['propertynearpage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertynearpage );
												$permalink .= '/' . $post->ID;
												break;

				case 'nearcoords':				$propertynearpage = untrailingslashit( get_permalink( $pageoptions['propertynearpage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertynearpage );
												$permalink .= '/' . $this->get_latitude($post->ID) . ',' . $this->get_longitude($post->ID);
												break;

				case 'search':					$propertysearchpage = untrailingslashit( get_permalink( $pageoptions['propertysearchpage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertysearchpage );
												if(!empty($_GET['s'])) {
													$permalink .= '/' . $this->make_url($_GET['s']);
												}
												break;

				case 'mapproperty':				$propertymappage = untrailingslashit( get_permalink( $pageoptions['propertymappage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertymappage );

												$permalink .= '/' . $post->ID;
												break;

				case 'mapcoords':				$propertymappage = untrailingslashit( get_permalink( $pageoptions['propertymappage'] ) );
												$permalink = str_replace( trailingslashit( get_option('home') ), '', $propertymappage );

												$permalink .= '/' . $this->get_latitude($post->ID) . ',' . $this->get_longitude($post->ID);
												break;

			}

		}

		if(!empty($extend)) {
			return trailingslashit(get_option('home')) . trailingslashit($permalink) . $extend;
		} else {
			return trailingslashit(get_option('home')) . $permalink;
		}

	}

	/**********************************************************
	* Extended information functions
	**********************************************************/

	function extend_post( $id = 0 ) {

		if($id == 0) {
			global $post;
		} else {
			$post =& get_post($id);
		}

		if(!empty($post)) {
			$post = $this->property->extend_property($post);
		}

		return $post;

	}

	/**********************************************************
	* Image functions
	**********************************************************/
	function get_images($property_id, $type = 'thumb') {

		if(empty($this->images[$property_id]) && ( empty($this->loadedimages[$property_id]) || !$this->loadedimages[$property_id])) {
			$this->images[$property_id] = $this->property->get_property_images($property_id, $type);
			$this->loadedimages[$property_id] = true;
			$this->current_image[$property_id] = 0;
		}

	}

	function have_images($property_id, $type = 'thumb') {

		$this->get_images($property_id, $type);

		if(!empty($this->images[$property_id]) && $this->current_image[$property_id] < count($this->images[$property_id])) {
			return true;
		} else {
			return false;
		}

	}

	function get_image($property_id, $type = 'thumb') {
		$image = array_slice($this->images[$property_id], $this->current_image[$property_id], 1);
		$this->current_image[$property_id]++;

		return $image;
	}

	function reset_images($property_id) {
		$this->current_image[$property_id] = 0;
	}

	function get_image_url($property_id, $type = 'thumb') {
		$image = array_slice($this->images[$property_id], $this->current_image[$property_id], 1);

		$this->current_image[$property_id]++;

		if(wp_attachment_is_image($image[0]->ID)) {
			if($type == 'thumb') {
				return wp_get_attachment_thumb_url($image[0]->ID);
			} else {
				return wp_get_attachment_url($image[0]->ID);
			}
		} else {
			return wp_mime_type_icon($image[0]->post_mime_type);
		}
	}

	/**********************************************************
	* Price functions
	**********************************************************/

	function get_prices($property_id) {

		if(empty($this->prices[$property_id]) && ( empty($this->loadedprices[$property_id]) || !$this->loadedprices[$property_id])) {
			// get the price information
			$this->prices[$property_id] = $this->property->get_full_propertyprices($property_id);
			$this->loadedprices[$property_id] = true;
			$this->current_price[$property_id] = 0;
		}
	}

	function have_prices($property_id) {

		$this->get_prices($property_id);

		if(!empty($this->prices[$property_id]) && $this->current_price[$property_id] < count($this->prices[$property_id])) {
			return true;
		} else {
			return false;
		}

	}

	function get_price($property_id) {

		$price = array_slice($this->prices[$property_id], $this->current_price[$property_id], 1);

		if(is_array($price)) {
			$price = $price[0];
		}

		$outputdata = array(	"day" 			=> $price->price_day,
								"month_num" 	=> $price->price_month,
								"month_short"	=> __(date("M", strtotime(date("Y") . '-' . $price->price_month . '-01'))),
								"month_full"	=> __(date("F", strtotime(date("Y") . '-' . $price->price_month . '-01'))),
								"amount"		=> $price->price_amount,
								"period"		=> $price->price_period,
								"period_type"	=> $price->price_period_type,
								"full_period"	=> $price->price_period . $price->price_period_type,
								"currency"		=> $price->price_currency
							);

		$this->current_price[$property_id]++;

		return $outputdata;

	}

	// Price periods
	function get_price_periods($property_id) {
		if(empty($this->price_periods[$property_id])) {
			// Not loaded the priceperiods (or there are none) - so load here
			$this->price_periods[$property_id] = $this->property->get_price_periods( $property_id );
			$this->current_price_period[$property_id] = 0;
		}
	}

	function have_price_periods($property_id) {

		$this->get_price_periods($property_id);

		if(!empty($this->price_periods[$property_id]) && $this->current_price_period[$property_id] < count($this->price_periods[$property_id])) {
			return true;
		} else {
			return false;
		}
	}

	function get_price_period($property_id) {

		$price_period = array_slice($this->price_periods[$property_id], $this->current_price_period[$property_id], 1);

		$this->current_price_period[$property_id]++;

		if(is_array($price_period) && count($price_period) == 1) {
			return $price_period[0];
		} else {
			return $price_period;
		}


	}

	// period price lines
	function get_period_prices($property_id, $period = 1, $period_type = 'm') {

		if(!is_array($this->loadedprices[$property_id])) {
			$this->loadedprices[$property_id] = array();
		}
		if(!is_array($this->current_price[$property_id])) {
			$this->current_price[$property_id] = array();
		}

		if(empty($this->prices[$property_id][$period . $period_type]) && ( empty($this->loadedprices[$property_id][$period . $period_type]) || !$this->loadedprices[$property_id][$period . $period_type])) {
			// get the price information
			$this->prices[$property_id][$period . $period_type] = $this->property->get_period_propertyprices($property_id, $period, $period_type);
			$this->loadedprices[$property_id][$period . $period_type] = true;
			$this->current_price[$property_id][$period . $period_type] = 0;
		}

	}

	function have_period_prices($property_id, $period = 1, $period_type = 'm') {

		$this->get_period_prices($property_id, $period, $period_type);

		if(!empty($this->prices[$property_id][$period . $period_type]) && $this->current_price[$property_id][$period . $period_type] < count($this->prices[$property_id][$period . $period_type])) {
			return true;
		} else {
			return false;
		}

	}

	function get_period_price( $property_id, $period = 1, $period_type = 'm' ) {

		//$this->get_prices($property_id);
		$prices = array_slice($this->prices[$property_id][$period . $period_type], $this->current_price[$property_id][$period . $period_type], 1);

		//print_r($price);
		$this->current_price[$property_id][$period . $period_type]++;

		$outputdata = array();

		foreach($prices as $key => $price) {
			$outputdata[] = array(	"day" 			=> $price->price_day,
									"month_num" 	=> $price->price_month,
									"month_short"	=> __(date("M", strtotime(date("Y") . '-' . $price->price_month . '-01'))),
									"month_full"	=> __(date("F", strtotime(date("Y") . '-' . $price->price_month . '-01'))),
									"amount"		=> $price->price_amount,
									"period"		=> $price->price_period,
									"period_type"	=> $price->price_period_type,
									"full_period"	=> $price->price_period . $price->price_period_type,
									"currency"		=> $price->price_currency
								);
		}

		return $outputdata;

	}

	function get_period_price_rows( $property_id, $period = 1, $period_type = 'm' ) {

		$this->get_period_prices($property_id, $period, $period_type);

		// Variable to hold the relevant price line
		$founddates = array();

		// Loop the data
		foreach($this->prices[$property_id] as $key => $price) {
			if( $price->price_month <= $wantmonth && $price->price_day <= $wantday ) {
				// We have one - woot
				$founddates[] = $price;
			}
		}

		if( !empty($founddates) ) {
			$outputdata = array();
			foreach($founddates as $key => $price) {
				$outputdata[] = array(	"day" 			=> $price->price_day,
										"month_num" 	=> $price->price_month,
										"month_short"	=> __(date("M", strtotime(date("Y") . '-' . $price->price_month . '-01'))),
										"month_full"	=> __(date("F", strtotime(date("Y") . '-' . $price->price_month . '-01'))),
										"amount"		=> $price->price_amount,
										"period"		=> $price->price_period,
										"period_type"	=> $price->price_period_type,
										"full_period"	=> $price->price_period . $price->price_period_type,
										"currency"		=> $price->price_currency
									);
			}

			return $outputdata;
		} else {
			return false;
		}

	}

	function has_price_row_for_date( $property_id, $date ) {

		$wantdate = strtotime($date);
		$wantmonth = date("n", $wantdate);
		$wantday = date("j", $wantdate);

		// Check we have the data
		$this->get_prices($property_id);

		// Variable to hold the relevant price line
		$founddate = false;

		// Loop the data
		foreach($this->prices[$property_id] as $price) {
			if( $price->price_month <= $wantmonth && $price->price_day <= $wantday ) {
				$founddate = $price;
			}
		}

		if($founddate !== false) {
			return true;
		} else {
			return false;
		}

	}

	function get_price_rows_for_date( $property_id, $date ) {

		$wantdate = strtotime($date);
		$wantmonth = date("n", $wantdate);
		$wantday = date("j", $wantdate);

		// Check we have the data
		$this->get_prices($property_id);

		// Variable to hold the relevant price line
		$founddates = array();

		// Loop the data
		foreach($this->prices[$property_id] as $key => $price) {
			if( $price->price_month <= $wantmonth && $price->price_day <= $wantday ) {
				// We have one - woot
				$founddates[] = $price;
			}
		}

		if( !empty($founddates) ) {
			$outputdata = array();
			foreach($founddates as $key => $price) {
				$outputdata[] = array(	"day" 			=> $price->price_day,
										"month_num" 	=> $price->price_month,
										"month_short"	=> __(date("M", strtotime(date("Y") . '-' . $price->price_month . '-01'))),
										"month_full"	=> __(date("F", strtotime(date("Y") . '-' . $price->price_month . '-01'))),
										"amount"		=> $price->price_amount,
										"period"		=> $price->price_period,
										"period_type"	=> $price->price_period_type,
										"full_period"	=> $price->price_period . $price->price_period_type,
										"currency"		=> $price->price_currency
									);
			}

			return $outputdata;
		} else {
			return false;
		}

	}

	function get_period_price_for_date( $property_id, $date, $period = 1, $period_type = 'm' ) {

		$wantdate = strtotime($date);
		$wantmonth = date("n", $wantdate);
		$wantday = date("j", $wantdate);

		// Check we have the data
		$this->get_prices($property_id);

		$founddate = false;

		//print_r($this->prices[$property_id]);

		// Loop the data
		foreach((array) $this->prices[$property_id][$period . $period_type] as $price) {
			if( $price->price_month <= $wantmonth && $price->price_day <= $wantday && $price->price_period == $period && $price->price_period_type == $period_type ) {
				$founddate = $price;
			}
		}

		// Have we found something?
		if(!empty($founddate)) {
			// We have one - woot
			// Now need to get all those on same price row with same setting
			$outputdata = array();
			foreach($this->prices[$property_id][$period . $period_type] as $price) {
				if($founddate->price_row == $price->price_row && $founddate->price_period == $price->price_period && $founddate->price_period_type == $price->price_period_type) {
					$outputdata[] = array(	"day" 			=> $price->price_day,
											"month_num" 	=> $price->price_month,
											"month_short"	=> __(date("M", strtotime(date("Y") . '-' . $price->price_month . '-01'))),
											"month_full"	=> __(date("F", strtotime(date("Y") . '-' . $price->price_month . '-01'))),
											"amount"		=> $price->price_amount,
											"period"		=> $price->price_period,
											"period_type"	=> $price->price_period_type,
											"full_period"	=> $price->price_period . $price->price_period_type,
											"currency"		=> $price->price_currency
										);
				}
			}

			return $outputdata;

		} else {
			return false;
		}
	}

	function get_lowest_period_price( $property_id, $period = 1, $period_type = 'm', $currency = 'USD' ) {

		// Check we have the data
		$this->get_prices($property_id);

		$lowest = false;
		// Loop the data
		foreach($this->prices[$property_id] as $price) {
			if( $price->price_period == $period && $price->price_period_type == $period_type && $price->price_currency == $currency ) {
				if($lowest === false || $lowest > $price->price_amount) {
					$lowest = $price->price_amount;
				}
			}
		}

		// Have we found something?
		if($lowest !== false) {
			// We have one - woot
			return array( 'price' => $lowest, 'currency' => $currency );

		} else {
			return false;
		}

	}

	function get_lowest_period_price_any_currency( $property_id, $period = 1, $period_type = 'm' ) {

		// Check we have the data
		$this->get_prices($property_id);

		$lowest = false;
		$lowestcurrency = false;
		// Loop the data
		foreach($this->prices[$property_id] as $price) {
			if( $price->price_period == $period && $price->price_period_type == $period_type ) {
				if($lowest === false || $lowest > $price->price_amount) {
					$lowest = $price->price_amount;
					$lowestcurrency = $price->price_currency;
				}
			}
		}

		// Have we found something?
		if($lowest !== false) {
			// We have one - woot
			return array( 'price' => $lowest, 'currency' => $lowestcurrency );

		} else {
			return false;
		}

	}

	function get_monthly_price_for_date( $property_id, $date ) {
		return $this->get_period_price_for_date( $property_id, $date, 1, 'm' );
	}

	function get_weekly_price_for_date( $property_id, $date ) {
		return $this->get_period_price_for_date( $property_id, $date, 1, 'w' );
	}

	function get_daily_price_for_date( $property_id, $date ) {
		return $this->get_period_price_for_date( $property_id, $date, 1, 'd' );
	}

	function get_lowest_monthly_price( $property_id, $currency = 'USD' ) {
		return $this->get_lowest_period_price( $property_id, 1, 'm', $currency );
	}

	function get_lowest_weekly_price( $property_id, $currency = 'USD' ) {
		return $this->get_lowest_period_price( $property_id, 1, 'w', $currency );
	}

	function get_lowest_daily_price( $property_id, $currency = 'USD' ) {
		return $this->get_lowest_period_price( $property_id, 1, 'd', $currency );
	}



	/**********************************************************
	* Extended functions
	**********************************************************/

	function get_reference($property_id) {

		if(!isset($this->loadedproperties[$property_id]) || $this->loadedproperties[$property_id] == false) {
			$this->properties[$property_id] = $this->property->get_property($property_id, true, false);
			$this->loadedproperties[$property_id] = true;
		}

		return $this->properties[$property_id]->reference;

	}


	/**********************************************************
	* Location functions
	**********************************************************/

	function get_latitude($property_id, $places = 5, $plusminuskm = 0) {

		if(!isset($this->loadedproperties[$property_id]) || $this->loadedproperties[$property_id] == false) {
			$this->properties[$property_id] = $this->property->get_property($property_id, true, false);
			$this->loadedproperties[$property_id] = true;
		}

		return $this->properties[$property_id]->latitude;

	}

	function get_longitude($property_id, $places = 5, $plusminuskm = 0) {

		if(!isset($this->loadedproperties[$property_id]) || $this->loadedproperties[$property_id] == false) {
			$this->properties[$property_id] = $this->property->get_property($property_id, true, false);
			$this->loadedproperties[$property_id] = true;
		}

		return $this->properties[$property_id]->longitude;

	}

	function get_town($property_id) {

		if(!isset($this->loadedproperties[$property_id]) || $this->loadedproperties[$property_id] == false) {
			$this->properties[$property_id] = $this->property->get_property($property_id, true, false);
			$this->loadedproperties[$property_id] = true;
		}

		return $this->properties[$property_id]->town;

	}

	function get_region($property_id) {

		if(!isset($this->loadedproperties[$property_id]) || $this->loadedproperties[$property_id] == false) {
			$this->properties[$property_id] = $this->property->get_property($property_id, true, false);
			$this->loadedproperties[$property_id] = true;
		}

		return $this->properties[$property_id]->region;

	}

	function get_country($property_id) {

		if(!isset($this->loadedproperties[$property_id]) || $this->loadedproperties[$property_id] == false) {
			$this->properties[$property_id] = $this->property->get_property($property_id, true, false);
			$this->loadedproperties[$property_id] = true;
		}

		return $this->properties[$property_id]->country;

	}

	function get_contact($property_id) {
		return $this->property->public_get_propertycontacts($property_id);
	}

	function get_owner($property_id) {
		return $this->property->public_get_owner_id($property_id);
	}

	function get_properties_in_town($country, $region, $town) {
		return $this->property->get_town_ids( $country, $region, $town );
	}

	function get_properties_in_country($country) {
		return $this->property->get_country_ids( $country );
	}

	function get_properties_in_region($country, $region) {
		return $this->property->get_region_ids( $country, $region );
	}

	function get_properties_near($lat, $lng, $radiuskm, $number = STAYPRESS_PROPERTY_PER_PAGE ) {

		$point = array(	'lat' => $lat,
						'lng' => $lng
						);

		$bounds = $this->property->getBoundingBox($point, $radiuskm + 10, 'km');
		list($lat1, $lat2, $lon1, $lon2) = $bounds;
		$tright = array( 'lat' => $lat2, 'lng' => $lon2);
		$bleft = array( 'lat' => $lat1, 'lng' => $lon1);

		return $this->property->get_withinrangeandbounds( $point, $tright, $bleft, $radiuskm, 'km', 0, $number, true, true );

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


			return $this->property->get_withinrangeandbounds( $point, $tright, $bleft,$radiuskm, 'km', 0, $number, true, true );

		} else {
			return false;
		}

	}

	function get_property_meta( $property_id, $key, $default ) {

		return $this->property->get_meta_value($property_id, $key, $default);

	}

	/**********************************************************
	* Shortcode functions
	**********************************************************/

	function do_propertyfacilities_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"					=>	"define",
							"facility"					=>	"all",
							"prefix"					=>	"",
							"postfix"					=>	"",
							"holder"					=>	"ul",
							"holderclass"				=>	"termsholder",
							"wrapwith"					=>	"",
							"wrapwithclass"				=>	"",
							"labelclass"				=>	"termslabel",
							"item"						=>	"li",
							"itemclass"					=>	"termslist",
							"itemislink"				=>	"yes",
							"facilityholder"			=>	"ul",
							"facilityholderclass"		=>	"",
							"facilityitem"				=>	"li",
							"facilityitemclass"			=>	""
						);

		extract(shortcode_atts($defaults, $atts));

		if($facility == 'all') {
			$taxes = get_object_taxonomies( STAYPRESS_PROPERTY_POST_TYPE );
		} else {
			$taxes = array_map('trim', explode(",", $facility));
		}

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';
		if(!empty($taxes) && is_array($taxes)) {
			foreach($taxes as $value) {

				$mtax = get_taxonomy($value);
				$terms = wp_get_post_terms( $property, $value);

				if(!empty($terms)) {

					$html .= "<{$holder} class='{$holderclass}'>\n";

					$html .= "<{$item} class='{$labelclass}'>";
					$html .= $mtax->label;
					$html .= "</{$item}>\n";

					$html .= "<{$item} class='{$itemclass}'>";

					if(!empty($facilityholder)) {
						$html .= "<{$facilityholder} class='{$facilityholderclass}'>";
					}

					foreach($terms as $term) {

						$html .= "<{$facilityitem} class='{$facilityitemclass}'>";

						if($itemislink == 'yes') {
							$html .= "<a href='" . sp_get_permalink('with', false, $term->slug) . "'>";
						}
						$html .= $prefix;
						if(!empty($wrapwith)) {
							$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
						}

						$html .= $term->name;
						if(!empty($wrapwith)) {
							$html .= "</{$wrapwith}>";
						}
						$html .= $postfix;

						if($itemislink == 'yes') {
							$html .= "</a>";
						}

						$html .= "</{$facilityitem}>\n";

						$iterm = $term;
						while(is_taxonomy_hierarchical($value) && $iterm->parent > 0) {
							$iterm = get_term($iterm->parent, $value);

							$html .= "<{$facilityitem} class='{$facilityitemclass}'>";
							if($itemislink == 'yes') {
								$html .= "<a href='" . sp_get_permalink('with', false, $iterm->slug) . "'>";
							}

							$html .= $prefix;
							if(!empty($wrapwith)) {
								$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
							}

							$html .= $iterm->name;

							if(!empty($wrapwith)) {
								$html .= "</{$wrapwith}>";
							}
							$html .= $postfix;

							if($itemislink == 'yes') {
								$html .= "</a>";
							}
							$html .= "</{$facilityitem}>\n";
						}
					}
					if(!empty($facilityholder)) {
						$html .= "</{$facilityholder}>";
					}

					$html .= "</{$item}>\n";

					$html .= "</{$holder}>\n";
				}

			}
		}

		return $html;
	}

	function do_propertymeta_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"			=>	"define",
							"prefix"			=>	"",
							"postfix"			=>	"",
							"holder"			=>	"",
							"holderclass"		=>	"",
							"item"				=>	"",
							"itemclass"			=>	"",
							"wrapwith"			=>	"",
							"wrapwithclass"		=>	"",
							"meta"				=>	"all",
							"default"			=>	0,
							"forceshow"			=>	'yes',
							"showname"			=>	'yes',
							"nameholder"		=>	'',
							"nameholderclass"	=>	'',
							"nameprefix"		=>	"",
							"namepostfix"		=>	""
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		if($meta == 'all') {
			$meta = array('all');
		} else {
			$meta = array_map('trim', explode(",", $meta));
		}

		$metas = $this->property->get_metadesc();

		$html = '';

		if(!empty($metas)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}

			foreach($metas as $m) {
				if($meta[0] == 'all' || in_array($m->metaname, $meta)) {
					$details = sp_get_property_meta( $property, $m->metaname, $default );

					if($forceshow == 'yes' || $details != $default) {
						// Show if we always want to or the value returned isn't the default value

						if(!empty($item)) {
							$html .= "<{$item} class='{$itemclass}'>";
						}
						$html .= $prefix;

						if($showname == 'yes') {

							$html .= $nameprefix;

							if(!empty($nameholder)) {
								$html .= "<{$nameholder} class='{$nameholderclass}'>";
							}
							$html .= $m->metaname;
							if(!empty($nameholder)) {
								$html .= "</{$nameholder}>";
							}

							$html .= $namepostfix;
						}

						if(!empty($wrapwith)) {
							$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
						}
						// output the actual details
						$html .= $details;

						if(!empty($wrapwith)) {
							$html .= "</{$wrapwith}>";
						}
						$html .= $postfix;

						if(!empty($item)) {
							$html .= "</{$item}'>";
						}

					}

				}
			}

			if(!empty($holder)) {
				$html .= "</{$holder}'>";
			}
		}

		return $html;
	}

	function return_priceperiods($period, $period_type) {

		switch($period_type) {

			case 'd':
						switch($period) {
							case 1:		return __('Daily', 'property');
										break;
							case 2:
							case 3:
							default:	return $period . " " . __(' days', 'property');
						}
						break;
			case 'w':
						switch($period) {
							case 1:		return __('Weekly', 'property');
										break;
							case 2:		return __('Fortnightly', 'property');
										break;
							case 3:
							default:	return $period . " " . __(' weekly', 'property');
						}
						break;
			case 'm':
						switch($period) {
							case 1:		return __('Monthly', 'property');
										break;
							case 2:
							case 3:
							default:	return $period . " " . __(' months', 'property');
						}
						break;


		}

		return "none";

	}

	function do_propertymonthlyprices_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"showtitle"				=>	'yes',
							"titlewrap"				=>	'h4',
							"titlewrapclass"		=>	'',
							"titleprefix"			=>	'',
							"titlepostfix"			=>	__(' prices', 'property'),
							"startyear"				=>	"now",
							"startmonth"			=>	"now",
							"priceperiod"			=>	"1",
							"priceperiodtype"		=>	"m"
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		if(sp_have_period_prices( $property, $priceperiod, $priceperiodtype)) {

			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}

			// Title here
			if($showtitle == 'yes') {
				$html .= "<{$titlewrap} class={$titlewrapclass}>" . $titleprefix .  $this->return_priceperiods( $priceperiod, $priceperiodtype ) . $titlepostfix . "</{$titlewrap}>";
			}

			// monthly prices so we'll cycle through all months
			$startdate = date("Y-01-01");
			$month = 1;
			$months = array();
			$maxcur = 1;
			$currencies = array();
			do {
				$thedate = date("Y-" . $month . "-01");

				$months[$month] = $this->get_monthly_price_for_date( $property, $thedate );
				foreach((array) $months[$month] as $curcheck) {
					if(!in_array($curcheck['currency'], $currencies)) {
						$currencies[$curcheck['currency']] = $curcheck['currency'];
					}
				}
				$maxcur = max(count($months[$month]), $maxcur);
				$month++;
			} while ($month <= 12);

			if(!empty($months)) {
				// We have some monthly prices

				$html .= "<table class='pricingtable'>";
				$html .= "<thead>";
				$html .= "<th class='labelcolumn'></th>";

				foreach($currencies as $key => $value) {
					$html .= "<th class='pricecolumn'>" . $value . "</th>";
				}

				$html .= "</thead>";
				$html .= "<tbody>";

				$alt = 'alt';
				foreach($months as $key => $month) {

					$html .= "<tr class='" . $alt . "'>";
					$html .= "<td class='labelcolumn'>" . date('F', strtotime(date("Y-") . $key . '-1')) . "</td>";

					foreach($currencies as $key => $value) {

						$html .= "<td class='pricecolumn'>";
						foreach((array) $month as $mkey => $m) {
							if($m['currency'] == $key) {
								$html .= $this->currenciesformat($m['currency'], $m['amount']);
								break;
							}
						}

						$html .= "</td>";
					}

					$html .= "</tr>";

					if($alt == '') {
						$alt = 'alt';
					} else {
						$alt = '';
					}
				}

				$html .= "</tbody>";
				$html .= "</table>";
			}

			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}

		}

		return $html;

	}

	function do_propertyprices_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"showtitle"				=>	'yes',
							"titlewrap"				=>	'h4',
							"titlewrapclass"		=>	'',
							"titleprefix"			=>	'',
							"titlepostfix"			=>	__(' prices', 'property'),
							"startyear"				=>	"now",
							"startmonth"			=>	"now",
							"priceperiod"			=>	"1",
							"priceperiodtype"		=>	"w"
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		if(sp_have_period_prices( $property, $priceperiod, $priceperiodtype)) {

			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}

			// Title here
			if($showtitle == 'yes') {
				$html .= "<{$titlewrap} class={$titlewrapclass}>" . $titleprefix .  $this->return_priceperiods( $priceperiod, $priceperiodtype ) . $titlepostfix . "</{$titlewrap}>";
			}

			// Content here
			$periods = array();
			$currencies = array();
			$periodcount = 1;

			while( sp_have_period_prices( $property, $priceperiod, $priceperiodtype ) ) {
				$periods[$periodcount] = sp_get_period_price( $property, $priceperiod, $priceperiodtype );
				foreach($periods[$periodcount] as $curcheck) {
					if(!in_array($curcheck['currency'], $currencies)) {
						$currencies[$curcheck['currency']] = $curcheck['currency'];
					}
				}
				$periodcount++;
			}

			$html .= "<table class='pricingtable'>";
			$html .= "<thead>";
			$html .= "<th class='labelcolumn'></th>";

			foreach($currencies as $key => $value) {
				$html .= "<th class='pricecolumn'>" . $value . "</th>";
			}

			$html .= "</thead>";
			$html .= "<tbody>";

			$firstdate = '';
			$onrow = 1;
			$alt = 'alt';

			foreach($periods as $pkey => $p) {
				if(empty($firstdate)) {
					$firstdate = strtotime(date("Y") . '-' . $p[0]['month_num'] . '-' . $p[0]['day']);
				}
				$html .= "<tr class='" . $alt . "'>";
				$html .= "<td class='labelcolumn'>";

				if($onrow == 1) {
					$html .= date("jS F", $firstdate) . " - ";
				} else {
					$html .= date("jS F", strtotime(date("Y") . '-' . $p[0]['month_num'] . '-' . $p[0]['day'])) . " - ";
				}

				if(!empty($periods[$onrow + 1])) {
					$html .= date("jS F", strtotime("-1 day", strtotime(date("Y") . '-' . $periods[$onrow + 1][0]['month_num'] . '-' . $periods[$onrow + 1][0]['day'])));
				} else {
					$html .= date("jS F", strtotime("-1 day", $firstdate));
				}

				$onrow++;

				$html .= "</td>";

				foreach($currencies as $key => $value) {
					$html .= "<td class='pricecolumn'>";
					foreach($p as $pkey2 => $pp) {
						if($pp['currency'] == $key) {
							$html .= $this->currenciesformat($pp['currency'], $pp['amount']);
							break;
						}
					}
					$html .= "</td>";
				}

				$html .= "</tr>";

				if($alt == '') {
					$alt = 'alt';
				} else {
					$alt = '';
				}
			}

			$html .= "</tbody>";
			$html .= "</table>";

			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}

		}

		return $html;

	}

	function currenciesformat($currency, $amount, $encodecurrency = true) {

		if($encodecurrency) {
			switch($currency) {

				case 'USD':	return "$" . number_format($amount, 2, '.', ',');
				case 'GBP': return "&pound;" . number_format($amount, 2, '.', ',');
				case 'EURO': return "&euro;" . number_format($amount, 2, ',', '.');

				default: return $currency . number_format($amount, 2, ',', '.');
			}
		} else {
			switch($currency) {

				case 'USD':	return "USD" . number_format($amount, 2, '.', ',');
				case 'GBP': return "GBP" . number_format($amount, 2, '.', ',');
				case 'EURO': return "EURO" . number_format($amount, 2, ',', '.');

				default: return $currency . number_format($amount, 2, ',', '.');
			}
		}


	}

	function do_propertylowestprice_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"priceperiod"			=>	"1",
							"priceperiodtype"		=>	"m",
							"currency"				=>	"any",
							"prefix"				=>	'',
							"postfix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"holder"				=>	'',
							"holderclass"			=>	'',
							"textcurrency"			=>	'yes'
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';
		if($currency == 'any') {
			// lowest numerical price for any currency
			$price = sp_get_lowest_period_price_any_currency( $property, $priceperiod, $priceperiodtype );
		} else {
			// lowest numerical price for a set currency
			$price = sp_get_lowest_period_price( $property, $priceperiod, $priceperiodtype, $currency );
		}

		if(!empty($price)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			// Prefix
			$html .= $prefix;
			// Price wrap
			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}

			if($textcurrency == 'yes') {
				$html .= $this->currenciesformat($price['currency'], $price['price'], false);
			} elseif($textcurrency == 'no') {
				$html .= $this->currenciesformat($price['currency'], $price['price'], true);
			} else {
				// no currency to display
				$html .= $this->currenciesformat('', $price['price']);
			}

			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}
			// Postfix
			$html .= $postfix;
			if(!empty($wrappallwith)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertypricenotes_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';
		$notes = get_post_meta($property, '_property_price_notes', true);

		if(!empty($notes)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}

			$html .= esc_html(stripslashes($notes));

			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertycountry_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		$country = sp_get_country( $property );

		if(!empty($country)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "<a href='";
				if(!empty($itemlink)) {
					$html .= $itemlink;
				} else {
					$post = get_post($property);
					$html .= sp_get_permalink( 'country', $post );
				}
				$html .= "'>";
			}

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}
			$html .= $country;
			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "</a>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertyregion_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		$region = sp_get_region( $property );

		if(!empty($region)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "<a href='";
				if(!empty($itemlink)) {
					$html .= $itemlink;
				} else {
					$post = get_post($property);
					$html .= sp_get_permalink( 'region', $post );
				}
				$html .= "'>";
			}

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}
			$html .= $region;
			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "</a>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertytown_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		$town = sp_get_town( $property );

		if(!empty($town)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "<a href='";
				if(!empty($itemlink)) {
					$html .= $itemlink;
				} else {
					$post = get_post($property);
					$html .= sp_get_permalink( 'town', $post );
				}
				$html .= "'>";
			}

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}
			$html .= $town;
			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "</a>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertydestinationexcert_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"metakey"				=>	'_staypress_destination_information',
							"metavalve"				=>	'define',
							"showtitle"				=>	'yes',
							"titlewrap"				=>	'h4',
							"titlewrapclass"		=>	'',
							"titleislink"			=>	'no',
							"itemlinktext"			=>	'More...'
						);

		extract(shortcode_atts($defaults, $atts));

		if($metavalve == 'define' && !empty($this->locations)) {
			$metavalve = $this->locations;
		} elseif(!empty($metavalue)) {
			$metavalue = explode('/', $metavalue);
		} else {
			return '';
		}

		$html = '';

		$infopostid = $this->use_drilldown_post( $metakey, $metavalue );
		if(!empty($infopostid)) {
			// get the post here and output the content at the top of the page
			// this will generally be a description of the area.
			$infopost =& new WP_Query('post_type=destination&post_status=publish&p=' . $infopostid);

			if($infopost->have_posts()) {
				$infopost->the_post();

				if(!empty($holder)) {
					$html .= "<{$holder} class='{$holderclass}'>";
				}
				if(!empty($item)) {
					$html .= "<{$item} class='{$itemclass}'>";
				}
				$html .= $prefix;

				if(!empty($wrapwith)) {
					$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
				}
				if($showtitle == 'yes') {
					if(!empty($holder)) {
						$html .= "<{$titlewrap} class='{$titlewrapclass}'>";
					}
					if($titleislink == 'yes') {
						$html .= "<a href='";
						if(!empty($itemlink)) {
							$html .= $itemlink;
						} else {
							$html .= get_permalink( get_the_id() );
						}
						$html .= "'>";
					}

					$html .= the_title();
					if(!empty($wrapwith)) {
						$html .= "</{$wrapwith}>";
					}
					if($titleislink == 'yes') {
						$html .= "</a>";
					}
					if(!empty($holder)) {
						$html .= "</{$titlewrap}>";
					}
				}

				$html .= the_excerpt();

				if(!empty($itemlinktext) && $itemislink == 'yes') {
					$html .= "<p><a href='";
					if(!empty($itemlink)) {
						$html .= $itemlink;
					} else {
						$html .= get_permalink( get_the_id() );
					}
					$html .= "'>";
					$html .= $itemlinktext;
					$html .= "</a></p>";
				}
				if(!empty($wrapwith)) {
					$html .= "</{$wrapwith}>";
				}

				$html .= $postfix;
				if(!empty($item)) {
					$html .= "</{$item}>";
				}
				if(!empty($holder)) {
					$html .= "</{$holder}>";
				}

			}
		}

		return $html;
	}

	function do_propertydestination_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'yes',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"metakey"				=>	'_staypress_destination_information',
							"metavalve"				=>	'define',
							"showtitle"				=>	'yes',
							"titlewrap"				=>	'h4',
							"titlewrapclass"		=>	'',
							"titleislink"			=>	'no',
							"itemlinktext"			=>	'More...'
						);

		extract(shortcode_atts($defaults, $atts));

		if($metavalve == 'define' && !empty($this->locations)) {
			$metavalue = $this->locations;
		} elseif($metavalve == 'define' && !empty($wp_query->query_vars['dest'])) {
			$metavalue = explode('/',$wp_query->query_vars['dest']);
		} elseif(!empty($metavalue)) {
			$metavalue = explode('/', $metavalue);
		} else {
			return '';
		}

		$html = '';

		$infopostid = $this->use_drilldown_post( $metakey, $metavalue );

		if(!empty($infopostid)) {
			// get the post here and output the content at the top of the page
			// this will generally be a description of the area.
			$infopost =& new WP_Query('post_type=destination&post_status=publish&p=' . $infopostid);

			if($infopost->have_posts()) {
				$infopost->the_post();

				if(!empty($holder)) {
					$html .= "<{$holder} class='{$holderclass}'>";
				}
				if(!empty($item)) {
					$html .= "<{$item} class='{$itemclass}'>";
				}
				$html .= $prefix;

				if(!empty($wrapwith)) {
					$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
				}

				if($showtitle == 'yes') {
					if(!empty($holder)) {
						$html .= "<{$titlewrap} class='{$titlewrapclass}'>";
					}
					if($titleislink == 'yes') {
						$html .= "<a href='";
						if(!empty($itemlink)) {
							$html .= $itemlink;
						} else {
							$html .= get_permalink( get_the_id() );
						}
						$html .= "'>";
					}
					$html .= the_title();
					if($titleislink == 'yes') {
						$html .= "</a>";
					}
					if(!empty($holder)) {
						$html .= "</{$titlewrap}>";
					}
				}

				$html .= the_content();

				if(!empty($itemlinktext) && $itemislink == 'yes') {
					$html .= "<p><a href='";
					if(!empty($itemlink)) {
						$html .= $itemlink;
					} else {
						$html .= get_permalink( get_the_id() );
					}
					$html .= "'>";
					$html .= $itemlinktext;
					$html .= "</a></p>";
				}

				if(!empty($wrapwith)) {
					$html .= "</{$wrapwith}>";
				}
				$html .= $postfix;
				if(!empty($item)) {
					$html .= "</{$item}>";
				}
				if(!empty($holder)) {
					$html .= "</{$holder}>";
				}

			}
		}

		return $html;
	}

	function do_propertydestinationthumbnail_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'yes',
							"itemlink"				=>	'',
							"imageclass"			=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"metakey"				=>	'_staypress_destination_information',
							"metavalve"				=>	'define',
							"size"					=>	'post-thumbnail',
							"width" 				=> 	null,
					        "height" 				=> 	null,
							"title"					=> '',
							"alt"					=> ''
						);

		extract(shortcode_atts($defaults, $atts));

		if($metavalve == 'define' && !empty($this->locations)) {
			$metavalue = $this->locations;
		} elseif($metavalve == 'define' && !empty($wp_query->query_vars['dest'])) {
			$metavalue = explode('/',$wp_query->query_vars['dest']);
		} elseif(!empty($metavalue)) {
			$metavalue = explode('/', $metavalue);
		} else {
			return '';
		}

		$html = '';

		$infopostid = $this->use_drilldown_post( $metakey, $metavalue );
		if(!empty($infopostid)) {
			// get the post here and output the content at the top of the page
			// this will generally be a description of the area.
			$infopost =& new WP_Query('post_type=destination&post_status=publish&p=' . $infopostid);

			if($infopost->have_posts()) {
				$infopost->the_post();

				if (isset($width) && isset($height)) {
					$size = array($width,$height);
				}


				if(!empty($holder)) {
					$html .= "<{$holder} class='{$holderclass}'>";
				}
				if(!empty($item)) {
					$html .= "<{$item} class='{$itemclass}'>";
				}
				$html .= $prefix;

				if(!empty($wrapwith)) {
					$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
				}

				if ( function_exists('has_post_thumbnail') && has_post_thumbnail( $property ) ) {
			      $html .= get_the_post_thumbnail( $infopostid, $size, array('title' => $title, 'alt' => $alt, 'class' => $imageclass));
			   	}

				if(!empty($wrapwith)) {
					$html .= "</{$wrapwith}>";
				}

				$html .= $postfix;
				if(!empty($item)) {
					$html .= "</{$item}>";
				}
				if(!empty($holder)) {
					$html .= "</{$holder}>";
				}

			}
		}

		return $html;
	}

	function do_propertydestinationbreadcrumb_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'yes',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"metakey"				=>	'_staypress_destination_information',
							"metavalve"				=>	'define',
							"showtitle"				=>	'yes',
							"titlewrap"				=>	'h4',
							"titlewrapclass"		=>	'',
							"titleislink"			=>	'no',
							"itemlinktext"			=>	'More...'
						);

		extract(shortcode_atts($defaults, $atts));

		if($metavalve == 'define' && !empty($this->locations)) {
			$metavalue = $this->locations;
		} elseif($metavalve == 'define' && !empty($wp_query->query_vars['dest'])) {
			$metavalue = explode('/',$wp_query->query_vars['dest']);
		} elseif(!empty($metavalue)) {
			$metavalue = explode('/', $metavalue);
		} else {
			return '';
		}

		$html = '';

		$infopostid = $this->use_drilldown_post( $metakey, $metavalue );
		if(!empty($infopostid)) {
			// get the post here and output the content at the top of the page
			// this will generally be a description of the area.
			$infopost =& new WP_Query('post_type=destination&post_status=publish&p=' . $infopostid);

			if($infopost->have_posts()) {
				$infopost->the_post();

				if(!empty($holder)) {
					$html .= "<{$holder} class='{$holderclass}'>";
				}
				if(!empty($item)) {
					$html .= "<{$item} class='{$itemclass}'>";
				}
				$html .= $prefix;

				if(!empty($wrapwith)) {
					$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
				}



				if(!empty($wrapwith)) {
					$html .= "</{$wrapwith}>";
				}

				$html .= $postfix;
				if(!empty($item)) {
					$html .= "</{$item}>";
				}
				if(!empty($holder)) {
					$html .= "</{$holder}>";
				}

			}
		}

		return $html;

	}

	function get_posts_near($lat, $lng, $radiuskm, $number = STAYPRESS_PROPERTY_PER_PAGE ) {

		$point = array(	'lat' => $lat,
						'lng' => $lng
						);

		$bounds = $this->property->getBoundingBox($point, $radiuskm + 10, 'km');
		list($lat1, $lat2, $lon1, $lon2) = $bounds;
		$tright = array( 'lat' => $lat2, 'lng' => $lon2);
		$bleft = array( 'lat' => $lat1, 'lng' => $lon1);

		return $this->property->get_withinrangeandbounds( $point, $tright, $bleft, $radiuskm, 'km', 0, $number, true, false );

	}

	function do_propertymap_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'div',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"width"					=>	'500px',
							"height"				=>	'250px',
							"background"			=>	'#99B3CC',
							"style"					=>	'',
							"marker"				=>	SPPCommon::property_url('images/mapicons/black%%.png'),
							"markershadow"			=>	SPPCommon::property_url('images/mapicons/shadow.png'),
							"markerobfuscate"		=>	'0',
							"shownear"				=>	'no',
							"shownearreadiuskm"		=>	'25',
							"shownearmarker"		=>	SPPCommon::property_url('images/mapicons/gray%%.png'),
							"shownearmarkershadow"	=>	SPPCommon::property_url('images/mapicons/shadow.png'),
							"showneartypes"			=>	STAYPRESS_PROPERTY_POST_TYPE,
							"shownearnumber"		=>	25
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} id='staypress_map_{$property}' class='staypress_map {$itemclass}' style='width: {$width}; height: {$height}; background: {$background}; {$style}'>";
		}

		$html .= $prefix;
		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}

		$html .= $content;

		if(!empty($wrapwith)) {
			$html .= "</{$wrapwith}>";
		}

		$html .= $postfix;

		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		// Enqueue some data
		$post = get_post( $property );
		if(!empty($post)) {
			if(!empty($post->latitude)) {
				$maindata = array(	"ID"			=>	$property,
									"post_title"	=>	$post->post_title,
									"post_excerpt"	=>	$post->post_excerpt,
									"latitude"		=>	$post->latitude,
									"longitude"		=>	$post->longitude
								);

				switch( $post->post_type ) {
					case STAYPRESS_PROPERTY_POST_TYPE:
									$maindata['permalink'] = sp_get_permalink( 'property', $post );
									break;

					case STAYPRESS_DESTINATION_POST_TYPE:
									// Find the location
									$maindata['permalink'] = get_permalink( $post->ID );
									break;

					case 'post':	//
					case 'page':	$maindata['permalink'] = get_permalink( $post->ID );
									break;
					default:
				}

				SPPCommon::enqueue_data('staypress_shortcode_data', 'property_' . $property, array( "1" =>$maindata) );

				if(strtolower($shownear) == 'yes') {
					// Need to show the nearby items
					$nearby = $this->get_posts_near($post->latitude, $post->longitude, $shownearreadiuskm, $shownearnumber );

					if(!empty($nearby)) {
						$startat = 2;
						$nears = array();
						foreach($nearby as $key => $near) {

							if($property == $near->ID) continue;

							$nears[$startat] = array(	"ID"			=>	$near->ID,
														"post_title"	=>	$near->post_title,
														"post_excerpt"	=>	$near->post_excerpt,
														"latitude"		=>	$near->latitude,
														"longitude"		=>	$near->longitude
														);
							switch( $post->post_type ) {
								case STAYPRESS_PROPERTY_POST_TYPE:
												$nears[$startat]['permalink'] = sp_get_permalink( 'property', $post );
												break;

								case STAYPRESS_DESTINATION_POST_TYPE:
												// Find the location
												$nears[$startat]['permalink'] = get_permalink( $post->ID );
												break;

								case 'post':	//
								case 'page':	$nears[$startat]['permalink'] = get_permalink( $post->ID );
												break;
								default:
							}
							$startat++;
						}
						SPPCommon::enqueue_data('staypress_shortcode_data', 'near_' . $property, $nears );
					}
				}

			}
		}

		// enqueue the map icon locations
		if(!empty($marker)) {
			SPPCommon::enqueue_data('staypress_shortcode_data', 'mapicon_' . $property, $marker);
			SPPCommon::enqueue_data('staypress_shortcode_data', 'mapshadow_' . $property, $markershadow);
		}
		if(!empty($shownearmarker)) {
			SPPCommon::enqueue_data('staypress_shortcode_data', 'nearicon_' . $property, $shownearmarker);
			SPPCommon::enqueue_data('staypress_shortcode_data', 'nearshadow_' . $property, $shownearmarkershadow);
		}

		return $html;
	}

	function do_propertyid_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		$post = get_post($property);

		if(!empty($post)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}
			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "<a href='";
				if(!empty($itemlink)) {
					$html .= $itemlink;
				} else {
					$html .= sp_get_permalink( 'property', $post );
				}
				$html .= "'>";
			}

			$html .= $property;

			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "</a>";
			}
			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertyreference_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		$post = get_post($property);

		if(!empty($post)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}
			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "<a href='";
				if(!empty($itemlink)) {
					$html .= $itemlink;
				} else {
					$html .= sp_get_permalink( 'property', $post );
				}
				$html .= "'>";
			}

			$html .= $this->property->get_reference($post);

			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "</a>";
			}
			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertypermalink_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		$post = get_post($property);

		if(!empty($post)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}
			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "<a href='";
				if(!empty($itemlink)) {
					$html .= $itemlink;
				} else {
					$html .= sp_get_permalink( 'property', $post );
				}
				$html .= "'>";
			}

			$html .= sp_get_permalink( 'property', $post );

			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "</a>";
			}
			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertytitle_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		$post = get_post($property);

		if(!empty($post)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}
			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "<a href='";
				if(!empty($itemlink)) {
					$html .= $itemlink;
				} else {
					$html .= sp_get_permalink( 'property', $post );
				}
				$html .= "'>";
			}

			$html .= $post->post_title;

			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "</a>";
			}
			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertyexcerpt_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		$post = get_post($property);

		if(!empty($post)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}
			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "<a href='";
				if(!empty($itemlink)) {
					$html .= $itemlink;
				} else {
					$html .= sp_get_permalink( 'property', $post );
				}
				$html .= "'>";
			}

			$html .= apply_filters('the_excerpt', apply_filters('get_the_excerpt', $post->post_excerpt));

			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "</a>";
			}
			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertydescription_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		$post = get_post($property);

		if(!empty($post)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}
			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "<a href='";
				if(!empty($itemlink)) {
					$html .= $itemlink;
				} else {
					$html .= sp_get_permalink( 'property', $post );
				}
				$html .= "'>";
			}

			$html .= apply_filters('the_content', $post->post_content);

			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "</a>";
			}
			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertythumbnail_shortcode($atts){

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"itemislink"			=>	'no',
							"itemlink"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"size"					=>	'post-thumbnail',
							"width" 				=> null,
					        "height" 				=> null,
					        "alt" 					=> '',
					        "title" 				=> '',
							"imageclass"			=> ''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		if (isset($width) && isset($height)) {
			$size = array($width,$height);
		}

		$html = '';

		$post = get_post($property);

		if(!empty($post)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}
			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "<a href='";
				if(!empty($itemlink)) {
					$html .= $itemlink;
				} else {
					$html .= sp_get_permalink( 'property', $post );
				}
				$html .= "'>";
			}

			if ( function_exists('has_post_thumbnail') && has_post_thumbnail( $property ) ) {
		      $html .= get_the_post_thumbnail( $property, $size, array('title' => $title, 'alt' => $alt, 'class' => $imageclass));
		   	}

			if($itemislink == 'yes' || !empty($itemlink)) {
				$html .= "</a>";
			}
			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;

	}

	function do_propertylistpagination_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		$post = get_post($property);

		if(!empty($post)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;
			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}

			$html .= $this->build_pagination( $item, $itemclass );

			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}
			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;

	}

	/*
	add_shortcode('propertylist', array(&$this, 'do_propertylist_shortcode') );
	add_shortcode('propertylistpagination', array(&$this, 'do_propertylistpagination_shortcode') );
	*/

	function do_propertysearchbox_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"allowextend"			=>	'yes',
							"buttontext"			=>	'Search'
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} class='{$itemclass}'>";
		}
		$html .= $prefix;
		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}
		$html .= "<form action='" . sp_get_permalink('search') . "' method='get' class='sp_shortcodesearchform'>";
		$html .= "<input type='hidden' name='searchmadeby' value='shortcodesearch' />";
		$html .= "<input type='text' name='sp_shortcodesearchfor' class='sp_shortcodesearchfor' value='" . __($this->propertyoptions['propertysearchtext'],'property') . "' />";
		$html .= "<input type='submit' name='sp_shortcodesearch' class='sp_shortcodesearch' value='" . __($buttontext,'property') . "' />";

		if($allowextend == 'yes') {
			$html .= "<div style='clear:both;'></div>";
			$html = apply_filters( 'staypress_preextend_short_search_form', $html, 'sp_shortavail_');
			$html = apply_filters( 'staypress_extend_short_search_form', $html, 'sp_shortavail_');
		}

		$html .= "</form>";
		if(!empty($wrapwith)) {
			$html .= "</{$wrapwith}>";
		}
		$html .= $postfix;
		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		return $html;
	}

	function show_meta_searchform( $passedmeta = array(), $excludemeta = array(), $searched = array(), $repopulate = false ) {

		$metas = $this->property->get_metadesc();

		$groupitems = array();
		$groupheading = array();

		if($metas) {
			$groupid = false;
			foreach($metas as $meta) {

				if(!empty($passedmeta) && !in_array($meta->id, $passedmeta)) {
					continue;
				}

				if(!empty($excludemeta) && in_array($meta->id, $excludemeta)) {
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
								if(!empty($searched[$this->make_url($meta->metaname)])) $ihtml .= esc_attr(stripslashes($searched[$this->make_url($meta->metaname)]));
								$ihtml .= "' />";
								break;
					case '2':	// Text
								$ihtml .= "<input type='text' name='meta[" . $meta->id . "]' id='' class='metavalue text' value='";
								if(!empty($searched[$this->make_url($meta->metaname)])) $ihtml .= esc_attr(stripslashes($searched[$this->make_url($meta->metaname)]));
								$ihtml .= "' />";
								break;
					case '3':	// Yes / No
								$ihtml .= "<select name='meta[" . $meta->id . "]' id='' class='metavalue'>";
								$ihtml .= "<option value=''";
								if(!empty($searched[$this->make_url($meta->metaname)]) && $searched[$this->make_url($meta->metaname)] == '') $ihtml .= " selected='selected'";
								$ihtml .= "></option>";
								$ihtml .= "<option value='yes'";
								if(!empty($searched[$this->make_url($meta->metaname)]) && $searched[$this->make_url($meta->metaname)] == 'yes') $ihtml .= " selected='selected'";
								$ihtml .= ">" . __('Yes');
								$ihtml .= "</option>";
								$ihtml .= "<option value='no'";
								if(!empty($searched[$this->make_url($meta->metaname)]) && $searched[$this->make_url($meta->metaname)] == 'no') $ihtml .= " selected='selected'";
								$ihtml .= ">" . __('No');
								$ihtml .= "</option>";
								$ihtml .= "</select>";
								break;
					case '4':	// Option
								$options = explode("\n", $meta->metaoptions);
								if(!empty($options)) {
									$ihtml .= "<select name='meta[" . $meta->id . "]' id='' class='metavalue'>";
									$ihtml .= "<option value=''";
									if(!empty($searched[$this->make_url($meta->metaname)]) && $searched[$this->make_url($meta->metaname)] == '') $ihtml .= " selected='selected'";
									$ihtml .= "></option>";
									foreach($options as $opt) {
										if(!empty($opt)) {
											$ihtml .= "<option value='" . esc_attr(trim($opt)) . "'";
											if(!empty($searched[$this->make_url($meta->metaname)]) && $searched[$this->make_url($meta->metaname)] == esc_attr(trim($opt))) $ihtml .= " selected='selected'";
											$ihtml .= ">" . esc_html(trim($opt));
											$ihtml .= "</option>";
										}
									}
									$ihtml .= "</select>";
								} else {
									$ihtml .= "<input type='text' name='meta[" . $meta->id . "]' id='' class='metavalue text' value='";
									if(!empty($searched[$this->make_url($meta->metaname)])) $ihtml .= esc_attr(stripslashes($searched[$this->make_url($meta->metaname)]));
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
					$html .= $groupheading[$key];
					$html .= implode("\n", $group);
				}
			}

			if(!empty($html)) {
				$html = "<ul>" . $html . "</ul>";
			}
		}

		return $html;

	}

	function repopulate($value, $default, $repopulate = true) {

		if($repopulate && !empty($value)) {
			return $value;
		} else {
			return $default;
		}

	}

	function do_propertyadvancedsearchbox_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"allowextend"			=>	'yes',
							"buttontext"			=>	'Search',
							"showonlymeta"			=>	'',
							"excludemeta"			=>	'',
							"repopulate"			=>	'yes'
						);

		extract(shortcode_atts($defaults, $atts));

		if($repopulate == 'yes') {
			$repopulate = true;
		} else {
			$repopulate = false;
		}

		// Build up the details for re-populating
		$search = explode('/', $wp_query->query_vars['search']);
		foreach($search as $key => $s) {
			$stemp = explode(':', $s);
			$search[$key] = array( $stemp[0] => $stemp[1]);
		}

		$locations = array();
		$tags = array();
		$metas = array();
		$keyword = '';

		foreach($search as $s) {
			switch(key($s)) {

				case 'incountry':	$locations[0] = current($s);
									break;

				case 'inregion':	$locations[1] = current($s);
									break;

				case 'intown':		$locations[2] = current($s);
									break;

				case 'tag':			$tags[] = current($s);
									break;

				case 'keyword':		$keyword = current($s);
									break;

				case 'searchfor':	$searchfor = current($s);
									break;

				default:			// could be a meta value or a plugin based extension
									if(!has_filter('staypress_process_full_search_urlkey_' . key($s))) {
										$metas[key($s)] = current($s);
									}
									break;

			}
		}

		$searchtext = $this->unmake_url(urldecode($searchfor));

		if($property == 'define'  && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$html = '';

		if(!empty($holder)) {
			$html .= "<{$holder} class='{$holderclass}'>";
		}
		if(!empty($item)) {
			$html .= "<{$item} class='{$itemclass}'>";
		}
		$html .= $prefix;
		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}

		$html .= "<form action='" . sp_get_permalink('search') . "' method='get' class='sp_advshortcodesearchform'>";
		$html .= "<input type='hidden' name='searchmadeby' value='advshortcodesearch' />";
		$html .= "<input type='text' name='sp_advshortcodesearchfor' class='sp_advshortcodesearchfor' value='" . $this->repopulate($searchtext, __($this->propertyoptions['propertysearchtext'],'property'), $repopulate) . "' />";
		$html .= "<input type='submit' name='sp_advshortcodesearch' class='sp_advshortcodesearch' value='" . __($buttontext,'property') . "' />";

		if($allowextend == 'yes') {
			$html .= "<div style='clear:both;'></div>";
			$html = apply_filters( 'staypress_preextend_shortadv_search_form', $html, 'sp_shortadv_', $repopulate, $search);
			if(!empty($showonlymeta)) {
				$showonlymeta = explode(",", $showonlymeta );
			}
			if(!empty($excludemeta)) {
				$excludemeta = explode(",", $excludemeta );
			}
			$html .= $this->show_meta_searchform( $showonlymeta , $excludemeta, $metas, $repopulate );
			$html = apply_filters( 'staypress_extend_shortadv_search_form', $html, 'sp_shortadv_', $repopulate, $search);
		}

		$html .= "</form>";
		if(!empty($wrapwith)) {
			$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
		}
		$html .= $postfix;
		if(!empty($item)) {
			$html .= "</{$item}>";
		}
		if(!empty($holder)) {
			$html .= "</{$holder}>";
		}

		return $html;
	}

	function do_propertycontactname_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$contacts = $this->property->public_get_propertycontacts( $property );

		$html = '';


		if(!empty($contacts)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}

			$contact = array_shift($contacts);

			$html .= esc_html($contact->post_title);

			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertycontactemail_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$contacts = $this->property->public_get_propertycontacts( $property );

		$html = '';


		if(!empty($contacts)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}

			$contact = array_shift($contacts);
			$contactmetadata = get_post_custom($contact->ID);

			$html .= esc_html(array_shift($contactmetadata['contact_email']));

			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertycontacttel_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$contacts = $this->property->public_get_propertycontacts( $property );

		$html = '';


		if(!empty($contacts)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}

			$contact = array_shift($contacts);
			$contactmetadata = get_post_custom($contact->ID);

			$html .= esc_html(array_shift($contactmetadata['contact_tel']));

			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertycontactnotes_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	''
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$contacts = $this->property->public_get_propertycontacts( $property );

		$html = '';


		if(!empty($contacts)) {
			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}

			$contact = array_shift($contacts);

			$html .= esc_html($contact->post_content);

			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertyenquiryform_shortcode($atts, $content = null, $code = "") {

		global $wp_query;

		$defaults = array(	"property"				=>	"define",
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"ajax"					=>	'true',
							"title"					=>	'false'
						);

		extract(shortcode_atts($defaults, $atts));

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) {
			$property = (int) STAYPRESS_ON_PROPERTY_PAGE;
		} elseif($property == 'define' && $this->inshortcodelist == true && !empty($this->shortcodeproperty)) {
			$property = (int) $this->shortcodeproperty;
		} elseif($property == 'post') {
			$property = get_the_id();
		}

		$formoption = SPPCommon::get_option('property_enquiry_options', array());

		$html = '';

		if(!empty($formoption['enquiry_form_gf_id'])) {
			// Add in hash url link
			$html .= "<a name='" . __('propertyenquiry','property') . "'></a>";

			if(!empty($holder)) {
				$html .= "<{$holder} class='{$holderclass}'>";
			}
			if(!empty($item)) {
				$html .= "<{$item} class='{$itemclass}'>";
			}
			$html .= $prefix;

			if(!empty($wrapwith)) {
				$html .= "<{$wrapwith} class='{$wrapwithclass}'>";
			}

			if(class_exists('RGFormsModel')) {
				$form = RGFormsModel::get_form($formoption['enquiry_form_gf_id']);
				if(!empty($form) && $form->is_active == 1) {
					$html .= do_shortcode("[gravityform id=" . $formoption['enquiry_form_gf_id'] . " title={$title} ajax={$ajax}]");
				} else {
					$html .= __('Your selected enquiry form could not be found.','property');
				}
			} else {
				// No Gravity Forms
				$html .= __('Gravity Forms not found.','property');
			}

			if(!empty($wrapwith)) {
				$html .= "</{$wrapwith}>";
			}

			$html .= $postfix;
			if(!empty($item)) {
				$html .= "</{$item}>";
			}
			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}
		}

		return $html;
	}

	function do_propertylist_shortcode($atts, $content = null, $code = "") {

		/*
		// List shortcode helpers

		*/


		global $wp_query, $post;

		$defaults = array(	"show"					=>	'25',
							"page"					=>	'wp_query',
							"holder"				=>	'',
							"holderclass"			=>	'',
							"item"					=>	'',
							"itemclass"				=>	'',
							"postfix"				=>	'',
							"prefix"				=>	'',
							"wrapwith"				=>	'',
							"wrapwithclass"			=>	'',
							"showpagination"		=>	'yes',
							"paginationholder"		=>	'div',
							"paginationclass"		=>	'pagination'
						);

		extract(shortcode_atts($defaults, $atts));

		$html = '';

		if($page == 'wp_query') {
			$page = $wp_query->query_vars['type'];
		}

		// Backup the original wp_query and post
		$backup_query = $wp_query;
		$backup_post = $post;

		$wp_query = new WP_Query();

		$wp_query->query_vars['post_type'] = 'property';
		$wp_query->query_vars['orderby'] = 'post_modified';
		$wp_query->query_vars['order'] = 'DESC';
		$wp_query->query_vars['paged'] = $backup_query->query_vars['paged'];

		switch($page) {

			case 'list':			$wp_query->query_vars['post_status'] = 'publish';

									$positive_ids = array();
									$negative_ids = array();

									$positive_ids = apply_filters( 'staypress_process_list_positive', $positive_ids, $backup_query );
									$negative_ids = apply_filters( 'staypress_process_list_negative', $negative_ids, $backup_query );

									$positive_ids = array_diff($positive_ids, $negative_ids);

									$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

									$wp_query->query_vars['post__in'] = $positive_ids;
									// reset incorrectly set wp_query variables
									$wp_query->is_singular = false;
									$wp_query->is_home = false;
									$wp_query->is_archive = true;

									add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
									break;


			case 'tag':				global $sp_tags;

									$wp_query->query_vars['post_status'] = 'publish';
									$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

									$tag = $backup_query->query_vars['with'];
									if(!empty($tag)) {
										$sp_tags = explode('/', $tag);
										$positive_ids = $this->property->get_properties_with_tags( $sp_tags );
										$wp_query->query_vars['tag'] = '';
									}

									$positive_ids = apply_filters( 'staypress_process_tag_positive', $positive_ids, $backup_query );
									$negative_ids = apply_filters( 'staypress_process_tag_negative', array(), $backup_query );
									$positive_ids = array_diff($positive_ids, $negative_ids);

									if(empty($positive_ids)) $positive_ids = array(0);

									$wp_query->query_vars['post__in'] = $positive_ids;

									// reset incorrectly set wp_query variables
									$wp_query->is_singular = false;
									$wp_query->is_home = false;
									$wp_query->is_archive = true;
									$wp_query->is_category = false;
									$wp_query->is_tag = false;
									$wp_query->is_tax = false;

									add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
									break;

			case 'dest':
									$wp_query->query_vars['post_status'] = 'publish';
									$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

									if(!empty($backup_query->query_vars['name'])) unset($wp_query->query_vars['name']);
									$this->locations = explode('/', $backup_query->query_vars['dest']);

									switch(count($this->locations)) {
										case 1:	// country
												$positive_ids = $this->property->get_country_ids($this->locations[0]);
												break;
										case 2: // region
												$positive_ids = $this->property->get_region_ids($this->locations[0], $this->locations[1]);
												break;
										case 3: // town
												$positive_ids = $this->property->get_town_ids($this->locations[0], $this->locations[1], $this->locations[2]);
												break;
									}

									$positive_ids = apply_filters( 'staypress_process_dest_positive', $positive_ids, $backup_query );
									$negative_ids = apply_filters( 'staypress_process_dest_negative', array(), $backup_query );
									$positive_ids = array_diff($positive_ids, $negative_ids);

									if(empty($positive_ids)) $positive_ids = array(0);

									$wp_query->query_vars['post__in'] = $positive_ids;
									// reset incorrectly set wp_query variables
									$wp_query->is_singular = false;
									$wp_query->is_home = false;
									$wp_query->is_archive = true;

									add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
									break;

			case 'near':
									$wp_query->query_vars['post_status'] = 'publish';
									$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

									if(isset($_GET['radius'])) {
										$radius = (int) $_GET['radius'];
									} else {
										$radius = 100;
									}
									//$wp_query->query_vars['paged'] = $this->paged;

									// Need to add in a bounds calculation to these two following functions
									$near = $backup_query->query_vars['near'];
									if(strpos($near, ',') === false) {
										// we want properties near an id
										$near = (int) $near;
										if(!defined('STAYPRESS_ON_PROPERTY_NEAR')) define('STAYPRESS_ON_PROPERTY_NEAR', $near );
										$nearby = $this->get_properties_near_property( $near, $radius, STAYPRESS_PROPERTY_PER_PAGE );

									} else {
										// we want properties near a location
										$near = explode(',', $near);
										if(count($near) >= 2) {
											$nearby = $this->get_properties_near( $near[0], $near[1], $radius, STAYPRESS_PROPERTY_PER_PAGE );
										} else {
											$nearby = array();
										}
									}

									$positive_ids = array();
									if(!empty($nearby)) {
										foreach($nearby as $key => $property ) {
											if($property->post_id != $near) {
												// we don't want our current property listed
												$positive_ids[] = $property->post_id;
											}
										}
									}

									$positive_ids = apply_filters( 'staypress_process_near_positive', $positive_ids, $backup_query );
									$negative_ids = apply_filters( 'staypress_process_near_negative', array(), $backup_query );
									$positive_ids = array_diff($positive_ids, $negative_ids);

									if(empty($positive_ids)) $positive_ids = array(0);

									$wp_query->query_vars['post__in'] = $positive_ids;
									// reset incorrectly set wp_query variables
									$wp_query->is_singular = false;
									$wp_query->is_home = false;
									$wp_query->is_archive = true;

									add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
									break;

			case 'available':
									$wp_query->query_vars['post_status'] = 'publish';
									$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

									$negative_ids = apply_filters( 'staypress_process_unavailable_properties', $backup_query );
									if(empty($negative_ids)) $negative_ids = array(0);
									$wp_query->query_vars['post__not_in'] = $negative_ids;
									// reset incorrectly set wp_query variables
									$wp_query->is_singular = false;
									$wp_query->is_home = false;
									$wp_query->is_archive = true;

									add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
									break;

			case 'search':			//echo "<pre>";
									//print_r($backup_query);
									//echo "</pre>";
									$searchfrom = addslashes($_REQUEST['searchmadeby']);
									$wp_query->query_vars['post_status'] = 'publish';

									// default is no results
									$positive_ids = array(0);
									$negative_ids = array(0);

									if(has_filter('staypress_process_full_search_positive')) {
										$positive_ids = apply_filters('staypress_process_full_search_positive', $positive_ids, $backup_query);
										$negative_ids = apply_filters( 'staypress_process_full_search_negative', $negative_ids, $backup_query );
									} elseif(has_filter('staypress_process_' . $searchfrom . '_search_positive')) {
										$positive_ids = apply_filters('staypress_process_' . $searchfrom . '_search_positive', $positive_ids, $backup_query);
										$negative_ids = apply_filters( 'staypress_process_' . $searchfrom . '_search_negative', $negative_ids, $backup_query );
									} else {
										$positive_ids = apply_filters( 'staypress_process_search_positive', $positive_ids, $backup_query );
										$negative_ids = apply_filters( 'staypress_process_search_negative', $negative_ids, $backup_query );
									}

									$positive_ids = array_diff( (array) $positive_ids, (array) $negative_ids );

									if(empty($positive_ids)) {
										$positive_ids = array(0);
									}

									$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

									$wp_query->query_vars['post__in'] = $positive_ids;
									// reset incorrectly set wp_query variables
									$wp_query->is_singular = false;
									$wp_query->is_home = false;
									$wp_query->is_search = true;

									add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );

									break;

			case 'map':				$wp_query->query_vars['post_status'] = 'publish';
									break;

			default:				// Everything else
									$wp_query->query_vars['post_status'] = 'publish';
									$wp_query->query_vars['posts_per_page'] = STAYPRESS_PROPERTY_PER_PAGE;

									do_action('staypress_process_page_type_' . $wp_query->query_vars['type'], $backup_query);

									add_action( 'the_posts', array(&$this, 'unset_single_flag'), 999 );
									break;


		}

		$wp_query->get_posts();

		if(have_posts()) {
			// Get the template
			if(empty($content) && file_exists( apply_filters('staypress_propertylist_template', SPPCommon::property_dir('includes/propertylist.template.php') )) ) {
				ob_start();
				include_once( apply_filters('staypress_propertylist_template', SPPCommon::property_dir('includes/propertylist.template.php') ) );
				$content = ob_get_contents();
				ob_end_clean();
			}

			if($showpagination == 'yes') {
				// show some pagination here
				$html .= $this->build_list_pagination( $paginationholder, $paginationclass, $backup_query->query_vars['paged'], $wp_query->max_num_pages );
			}

			$this->inshortcodelist = true;
			while(have_posts()) {
				the_post();

				$this->shortcodeproperty = $post->ID;
				$html .= do_shortcode($content);
			}
			$this->inshortcodelist = false;

			if($showpagination == 'yes') {
				// show some pagination here
				$html .= $this->build_list_pagination( $paginationholder, $paginationclass, $backup_query->query_vars['paged'], $wp_query->max_num_pages );
			}
		}

		// Restore the original wp_query and post
		$wp_query = $backup_query;
		$post = $backup_post;

		return $html;

	}

	// Search - handles search results and redirects to correct locations
	function handle_search_redirect() {

		global $wpdb;

		$this->property = new property_model($wpdb, false);

		// Check the search criteria and see if any meta information has been added - if so then
		// we need to resort to straight searching and bypass the quick find stuff.
		switch(addslashes($_REQUEST['searchmadeby'])) {
			case 'quicksearchwidget':	$search = $_REQUEST['sp_quicksearchfor'];
										break;
			case 'advsearchwidget':		$search = $_REQUEST['sp_advsearchfor'];
										break;
			case 'shortcodesearch':		$search = $_REQUEST['sp_shortcodesearchfor'];
										break;
			case 'advshortcodesearch':	$search = $_REQUEST['sp_advshortcodesearchfor'];
										break;
		}

		$metasearch = array();

		if(!empty($_GET['meta'])) {
			foreach((array) $_GET['meta'] as $metakey => $metadata) {
				if(!empty($metadata)) {
					$metasearch[$metakey] = $metadata;
				}
			}
		}

		$tagsearch = apply_filters('staypress_advsearch_extendshortcode_tags', array());

		if(__($this->propertyoptions['propertysearchtext'],'property') == $search) {
			// no text put into the search box, so blank out the standard placeholder text
			$search = '';
		}

		// Check criteria for property ID or reference - overrides meta information serch
		$property = $this->property->public_quickfind_propertyreforid($search, 'publish');
		if($property !== false && !empty($property)) {
			// if it exists - redirect to correct permalink
			$url = sp_get_permalink( 'property', $property );

			$url = apply_filters( 'staypress_search_redirect_url', $url );
			wp_safe_redirect( $url );
			exit;
		}

		if(empty($metasearch) && empty($tagsearch)) {
			// Check criteria for nearby locations
			$dest = $this->property->public_quickfind_location($search);
			if($dest !== false && !empty($dest)) {
				// if it exists - redirect to correct permalink
				$page_prefix = SPPCommon::get_option('property_rewrite_prefix', '');
				if(!empty($page_prefix)) $page_prefix = trailingslashit($page_prefix);

				if( !empty($dest->town) ) {
					$url = $page_prefix . __('in', 'property') . '/' . $this->make_url($dest->country);
					$url .= '/' . $this->make_url($dest->region);
					$url .= '/' . $this->make_url($dest->town);

				} elseif( !empty($dest->region) ) {
					$url = $page_prefix . __('in', 'property') . '/' . $this->make_url($dest->country);
					$url .= '/' . $this->make_url($dest->region);
				} elseif( !empty($dest->country) ) {
					$url = $page_prefix . __('in', 'property') . '/' . $this->make_url($dest->country);
				} else {
					return false;
				}

				$url = apply_filters( 'staypress_search_redirect_url', $url );
				wp_safe_redirect( trailingslashit(get_option('home')) . $url );
				exit;
			}

			// Check criteria for keyword
			$searchingfor = array_map(array(&$this,'make_url'), array_map('trim', explode(',', $search)));
			$keyworded = $this->property->get_properties_with_tags((array) $searchingfor);

			if(empty($keyworded) || (count($keyworded) == 1 && $keyworded[0] == '0')) {
			} else {
				// There are properties with those keywords to redirect
				$url = sp_get_permalink( 'with', false, implode('/', $searchingfor) );

				$url = apply_filters( 'staypress_search_redirect_url', $url );
				wp_safe_redirect( $url );
				exit;
			}

			// Add in final keyword bit
			$extend = 'keyword:' . urlencode($search) . "/searchfor:" . urlencode($search);
			$url = sp_get_permalink( 'search', false, $extend );

			$url = apply_filters( 'staypress_search_redirect_url', $url );
			wp_safe_redirect( $url );
			exit;


		} else {

			$urlextend = array();
			// More advanced serching - need to build a meta information url and then process later
			// Check criteria for nearby locations
			$dest = $this->property->public_quickfind_location($search);
			if($dest !== false && !empty($dest)) {
				// if it exists - redirect to correct permalink
				if( !empty($dest->town) ) {
					$urlextend[] = 'intown:' . $this->make_url($dest->town);
					$urlextend[] = 'inregion:' . $this->make_url($dest->region);
					$urlextend[] = 'incountry:' . $this->make_url($dest->country);
				} elseif( !empty($dest->region) ) {
					$urlextend[] = 'inregion:' . $this->make_url($dest->region);
					$urlextend[] = 'incountry:' . $this->make_url($dest->country);
				} elseif( !empty($dest->country) ) {
					$urlextend[] = 'incountry:' . $this->make_url($dest->country);
				}
			}

			// Check criteria for keyword
			if(empty($urlextend)) {
				$searchingfor = array_map(array(&$this,'make_url'), array_map('trim', explode(',', $search)));
				$keyworded = $this->property->get_properties_with_tags((array) $searchingfor);

				if(empty($keyworded) || (count($keyworded) == 1 && $keyworded[0] == '0')) {
				} else {
					// There are properties with those keywords to redirect
					foreach($searchingfor as $sf) {
						$urlextend[] = 'tag:' . $sf;
					}
				}
			}

			if(!empty($tagsearch)) {
				foreach($tagsearch as $tag) {
					$urlextend[] = 'tag:' . $tag;
				}
			}

			if(empty($urlextend)) {
				$urlextend[] = 'keyword:' . urlencode($search);
			}

			if(!empty($metasearch)) {
				foreach( (array) $metasearch as $key => $value ) {
					$fac = $this->property->get_metaforfac($key);
					if(!empty($fac)) {
						$urlextend[] = $this->make_url($fac->metaname) . ':' . urlencode($value);
					}
				}
			}

			$urlextend[] = "searchfor:" . urlencode($search);

			$url = sp_get_permalink( 'search', false, implode('/', $urlextend) );

			$url = apply_filters( 'staypress_search_redirect_url', $url );
			wp_safe_redirect( $url );
			exit;

		}

	}

	function get_fullsearch_results_postive( $positive_ids, $wp_query ) {

		$passedin_ids = $positive_ids;

		// blank out the listing - WILL need to change this
		$this->processlist = false;

		$search = explode('/', $wp_query->query_vars['search']);
		foreach($search as $key => $s) {
			$stemp = explode(':', $s);
			$search[$key] = array( $stemp[0] => $stemp[1]);
		}

		$locations = array();
		$tags = array();
		$metas = array();
		$keyword = '';

		foreach($search as $s) {
			switch(key($s)) {

				case 'incountry':	$locations[0] = current($s);
									break;

				case 'inregion':	$locations[1] = current($s);
									break;

				case 'intown':		$locations[2] = current($s);
									break;

				case 'tag':			$tags[] = current($s);
									break;

				case 'keyword':		$keyword = current($s);
									break;

				case 'searchfor':	$searchfor = current($s);
									break;

				default:			// could be a meta value or a plugin based extension
									if(!has_filter('staypress_process_full_search_urlkey_' . key($s))) {
										$metas[key($s)] = current($s);
									} else {
										$passedin_ids = apply_filters( 'staypress_process_full_search_urlkey_' . key($s), $passedin_ids, current($s), $search );
									}
									break;

			}
		}

		// empty the results arrays
		$positive_ids = array();
		$tags_ids = array();
		$meta_ids = array();
		$key_ids = array();

		// check for areas first
		if(!empty($locations)) {
			switch(count($locations)) {
				case 1:	// country
						$positive_ids = $this->property->get_country_ids($locations[0]);
						break;
				case 2: // region
						$positive_ids = $this->property->get_region_ids($locations[0], $locations[1]);
						break;
				case 3: // town
						$positive_ids = $this->property->get_town_ids($locations[0], $locations[1], $locations[2]);
						break;
			}
		}

		// then check for tags
		if(!empty($tags)) {
			$tags_ids = $this->property->get_properties_with_tags((array) $tags);
		}

		// then check for metas
		if(!empty($metas)) {
			$meta_ids = $this->property->get_properties_for_metas((array) $metas);
		}

		// Finally check for keywords
		if(!empty($keyword)) {
			$page = $wp_query->query_vars['paged'];
			$key_ids = $this->property->get_properties_for_keyword( $keyword, $page );
		}

		// Do the merge - nothing passed in so we fill with the first array containing information
		if(empty($passedin_ids) || (count($passedin_ids) == 1 && $passedin_ids[0] == '0')) {
			if(!empty($positive_ids)) {
				$passedin_ids = $positive_ids;
			} elseif(!empty($tags_ids)) {
				$passedin_ids = $tags_ids;
			} elseif(!empty($meta_ids)) {
				$passedin_ids = $meta_ids;
			} elseif(!empty($key_ids)) {
				$passedin_ids = $key_ids;
			}
		}

		// Cycle through and remove those ids that aren't in all arrays
		if(!empty($positive_ids)) {
			$passedin_ids = array_intersect( $passedin_ids, $positive_ids );
		}
		if(!empty($tags_ids)) {
			$passedin_ids = array_intersect( $passedin_ids, $tags_ids );
		}
		if(!empty($meta_ids)) {
			$passedin_ids = array_intersect( $passedin_ids, $meta_ids );
		}
		if(!empty($key_ids)) {
			$passedin_ids = array_intersect( $passedin_ids, $key_ids );
		}

		$this->processlist = true;

		$passedin_ids = array_unique($passedin_ids);

		if(!empty($passedin_ids)) {
			return $passedin_ids;
		} else {
			return array(0);
		}

	}

	// Pagination
	function build_list_pagination( $holder = 'div', $class = 'pagination', $paged = 1, $max_num_pages = 1 ) {
		global $sp_nopagination;

		if($sp_nopagination === true ) return;

		if(empty($paged)) {
			$paged = 1;
		} else {
			$paged = $paged;
		}

		$html = '';
		if((int) $max_num_pages > 1) {
			// we can draw the pages

			if(!empty($holder)) {
				$html .= "<{$holder} class='{$class}'>";
			}

			// hack to create the main link for this pages
			$mainlink = get_pagenum_link(1);

			$list_navigation = paginate_links( array(
				'base' => trailingslashit($mainlink) . '%_%',
				'format' => 'page/%#%',
				'total' => $max_num_pages,
				'current' => $paged,
				'prev_next' => true
			));

			$html .= $list_navigation;

			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}

		}
		return $html;
	}

	function build_pagination( $holder = 'div', $class = 'pagination', $wp_query ) {
		global $sp_nopagination;

		if($sp_nopagination === true ) return;

		if(empty($wp_query->query_vars['paged'])) {
			$paged = 1;
		} else {
			$paged = $wp_query->query_vars['paged'];
		}

		if((int) $wp_query->max_num_pages > 1) {
			// we can draw the pages
			$html = '';

			if(!empty($holder)) {
				$html .= "<{$holder} class='{$class}'>";
			}

			// hack to create the main link for this pages
			$mainlink = get_pagenum_link(1);

			$list_navigation = paginate_links( array(
				'base' => trailingslashit($mainlink) . '%_%',
				'format' => 'page/%#%',
				'total' => $wp_query->max_num_pages,
				'current' => $paged,
				'prev_next' => true
			));

			$html .= $list_navigation;

			if(!empty($holder)) {
				$html .= "</{$holder}>";
			}

			return $html;
		}

	}

}

// helper functions for the public interface
/**********************************************************
* Image functions
**********************************************************/
function sp_have_images($property_id, $type = 'thumb') {
	global $sp_property;

	return $sp_property->have_images( $property_id, $type );
}

function sp_get_image($property_id, $type = 'thumb') {
	global $sp_property;

	return $sp_property->get_image($property_id, $type);
}

function sp_reset_images($property_id) {
	global $sp_property;

	$sp_property->reset_images($property_id);
}

function sp_get_image_url($property_id, $type = 'thumb') {
	global $sp_property;

	return $sp_property->get_image_url($property_id, $type);
}

// Function is depcreciated
function sp_extend_post( $id = 0 ) {
	global $sp_property;

	return $sp_property->extend_post($id);
}

function sp_get_properties_near( $lat, $lng, $radiuskm, $number = STAYPRESS_PROPERTY_PER_PAGE ) {
	global $sp_property;

	return $sp_property->get_properties_near( $lat, $lng, $radiuskm, $number );
}

function sp_get_properties_near_property($id, $radiuskm, $number = STAYPRESS_PROPERTY_PER_PAGE ) {
	global $sp_property;

	return $sp_property->get_properties_near_property( $id, $radiuskm, $number );
}

function sp_get_town($id = 0) {
	global $sp_property;

	return $sp_property->get_town($id);
}

function sp_get_region($id = 0) {
	global $sp_property;

	return $sp_property->get_region($id);
}

function sp_get_country($id = 0) {
	global $sp_property;

	return $sp_property->get_country($id);
}

function sp_get_contact($property_id) {
	global $sp_property;

	return $sp_property->get_contact($property_id);
}

function sp_get_owner($property_id) {
	global $sp_property;

	return $sp_property->get_owner($property_id);
}

function sp_get_properties_in_country( $country ) {
	global $sp_property;

	return $sp_property->get_properties_in_country( $country );
}

function sp_get_properties_in_region( $country, $region ) {
	global $sp_property;

	return $sp_property->get_properties_in_region( $country, $region );
}

function sp_get_properties_in_town( $country, $region, $town ) {
	global $sp_property;

	return $sp_property->get_properties_in_town( $country, $region, $town );
}

function sp_get_permalink($type = 'property', $post = false, $extend = '') {
	global $sp_property;

	return $sp_property->get_permalink( $type, $post, $extend );
}

function sp_get_property_meta( $property_id, $key, $default ) {
	global $sp_property;

	return $sp_property->get_property_meta($property_id, $key, $default);
}

function sp_use_drilldown_post( $key = '', $drilldown = array(), $return = 0 ) {
	global $sp_property;

	return $sp_property->use_drilldown_post($key, $drilldown, $return);
}

function sp_have_prices($property_id) {
	global $sp_property;

	return $sp_property->have_prices($property_id);
}

function sp_get_price($property_id) {
	global $sp_property;

	return $sp_property->get_price($property_id);
}

function sp_have_period_prices($property_id, $period = 1, $period_type = 'm') {
	global $sp_property;

	return $sp_property->have_period_prices($property_id, $period, $period_type);
}

function sp_get_period_price( $property_id, $period = 1, $period_type = 'm' ) {
	global $sp_property;

	return $sp_property->get_period_price( $property_id, $period, $period_type );
}

function sp_show_price_heading($args = array()) {
	global $sp_property;

	$sp_property->show_price_heading($args);
}

function sp_get_monthly_price_for_date( $property_id, $date ) {
	global $sp_property;

	return $sp_property->get_monthly_price_for_date( $property_id, $date );
}

function sp_get_weekly_price_for_date( $property_id, $date ) {
	global $sp_property;

	return $sp_property->get_weekly_price_for_date( $property_id, $date );
}

function sp_get_daily_price_for_date( $property_id, $date ) {
	global $sp_property;

	return $sp_property->get_daily_price_for_date( $property_id, $date );
}

function sp_get_lowest_monthly_price( $property_id, $currency ) {
	global $sp_property;

	return $sp_property->get_lowest_monthly_price( $property_id, $currency );
}

function sp_get_lowest_weekly_price( $property_id, $currency ) {
	global $sp_property;

	return $sp_property->get_lowest_weekly_price( $property_id, $currency );
}

function sp_get_lowest_daily_price( $property_id, $currency ) {
	global $sp_property;

	return $sp_property->get_lowest_daily_price( $property_id, $currency );
}

function sp_get_period_price_for_date( $property_id, $date, $period, $period_type ) {
	global $sp_property;

	return $sp_property->get_period_price_for_date( $property_id, $date, $period, $period_type );
}

function sp_get_lowest_period_price( $property_id, $period, $period_type, $currency ) {
	global $sp_property;

	return $sp_property->get_lowest_period_price( $property_id, $period, $period_type, $currency );
}

function sp_get_lowest_period_price_any_currency( $property_id, $period, $period_type ) {
	global $sp_property;

	return $sp_property->get_lowest_period_price_any_currency( $property_id, $period, $period_type );
}

function sp_have_price_periods($property_id) {
	global $sp_property;

	return $sp_property->have_price_periods($property_id);
}

function sp_get_price_period($property_id) {
	global $sp_property;

	return $sp_property->get_price_period($property_id);
}

function sp_build_pagination( $holder = 'div', $class = 'pagination' ) {
	global $sp_property, $wp_query;

	return $sp_property->build_pagination( $holder, $class, $wp_query );
}


?>