<?php

class property_model {

	var $build = 2;

	var $db;

	var $staypress_prefix = 'sp_';			// Added near the front of table names e.g. wp_sp_property

	var $tables = array('property', 'metadesc', 'metagroupdesc', 'property_meta', 'property_price', 'property_price_line', 'contact', 'queue');

	// Tables pointers
	var $property;
	var $property_meta;
	var $property_price;
	var $property_price_line;

	// Property meta information
	var $metadesc;
	var $metagroupdesc;

	// Contact table
	var $contact;

	// Plugin update queue
	var $queue;

	// The current account and blog to access
	var $user_id = 0;
	var $blog_id = 0;

	// full user record
	var $user;

	var $property_id = false;

	var $showdeleted = false;

	// Timezone dates
	var $timeoffset = 0;

	// The cache
	var $cache = array();

	// numbers

	var $max_num_pages = 0;
	var $found_properties = 0;


	function __construct($wpdb, $installed_build = false) {

		// Grab local pointer the database class
		$this->db =& $wpdb;

		// Set the table prefixes
		$this->set_prefix();

		if($installed_build !== false && $this->build > $installed_build) {
			// A build was passed and it is lower than our current one.
			$this->update_tables_from($installed_build);
		}

		$this->blog_id = $this->db->blogid;

	}

	function __destruct() {

	}

	function property_model($wpdb) {
		$this->__construct();
	}

	function update_tables_from($installed_build = false) {

		include_once(SPPCommon::property_dir('includes/upgrade.php'));

		sp_upgradeproperty($installed_build);

	}

	function set_prefix($site_id = false) {

		foreach($this->tables as $table) {
			if(isset($this->db->base_prefix) && defined('STAYPRESS_GLOBAL_TABLES') && STAYPRESS_GLOBAL_TABLES == true ) {
				// Use the base_prefix - WPMU
				$this->$table = $this->db->base_prefix . $this->staypress_prefix . $table;
			} else {
				$this->$table = $this->db->prefix . $this->staypress_prefix . $table;
			}
		}

	}

	function set_userid($user_id) {
		$this->user_id = $user_id;

		$this->user = new WP_User( (int) $user_id );
	}

	function set_blogid($blog_id) {
		$this->blog_id = $blog_id;
	}

	function show_deleted($show) {
		$this->showdeleted = $show;
	}

	// Quick and easy List properties

	function get_foundrows() {
		return $this->db->get_var( "SELECT FOUND_ROWS();" );
	}

	function get_totalproperties($includedeleted = false) {

		return $this->found_posts;

	}

	function count_posts($type = 'post', $perm = '') {

		$cache_key = $type;

		$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$this->db->posts} WHERE post_type = %s";
		if ( 'readable' == $perm && is_user_logged_in() ) {
			$post_type_object = get_post_type_object($type);
			if ( !current_user_can("read_private_properties") ) {
				$cache_key .= '_' . $perm . '_' . $user->ID;
				$query .= " AND (post_status != 'private' OR ( post_author = '$user->ID' AND post_status = 'private' ))";
			}
			if ( !current_user_can("edit_others_properties") ) {
				$cache_key .= '_' . $perm . '_' . $user->ID;
				$query .= " AND post_author = '{$this->user_id}'";
			}
		}
		$query .= ' GROUP BY post_status';

		$count = wp_cache_get($cache_key, 'counts');
		if ( false !== $count )
			return $count;

		$count = $this->db->get_results( $this->db->prepare( $query, $type ), ARRAY_A );

		$stats = array();
		foreach ( get_post_stati() as $state )
			$stats[$state] = 0;

		foreach ( (array) $count as $row )
			$stats[$row['post_status']] = $row['num_posts'];

		$stats = (object) $stats;
		wp_cache_set($cache_key, $stats, 'counts');

		return $stats;
	}

	function properties_exist() {
		// Function for "empty state" checking

		$num_posts = $this->count_posts( STAYPRESS_PROPERTY_POST_TYPE, 'readable' );

		$total_posts = array_sum( (array) $num_posts ) - $num_posts->trash;

		if($total_posts > 0) {
			return true;
		} else {
			return false;
		}

	}

	function add_to_property_to_join( $join, $query ) {

		if($query->query_vars['post_type'] == STAYPRESS_PROPERTY_POST_TYPE) {
			$sql = " LEFT JOIN {$this->property} ON {$this->db->posts}.ID = {$this->property}.post_id";

			$join .= $sql;
		}

		return $join;
	}

	function add_to_property_to_fields( $fields, $query ) {

		if($query->query_vars['post_type'] == STAYPRESS_PROPERTY_POST_TYPE) {
			$myfields = array(	"{$this->property}.reference",
								"{$this->property}.latitude",
								"{$this->property}.longitude",
								"{$this->property}.country",
								"{$this->property}.town",
								"{$this->property}.region"
							);

			if(!empty($fields)) {
				$fields .= ', ' . implode(', ', $myfields);
			} else {
				$fields = implode(', ', $myfields);
			}
		}

		return $fields;
	}

	function get_lastid() {
		return $this->db->get_var( "SELECT LAST_INSERT_ID();" );
	}

	function search_propertylist($query = '', $startat = 0, $show = STAYPRESS_PROPERTY_PER_PAGE, $type = 'all', $includethumbimages = false) {

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		// Need to redo this search
		if(!empty($query)) {

			// Check for a "smart" query lookup of id
			if(strpos(strtolower($query), 'id:') !== false) {
				$post__in = array( (int) str_replace('id:', '', strtolower($query)) );
			} else {
				$n = '%';
				$query = addslashes_gpc($query);
				$sql = "SELECT post_id FROM {$this->property} WHERE (reference LIKE '{$n}{$query}{$n}' OR title LIKE '{$n}{$query}{$n}' OR description LIKE '{$n}{$query}{$n}' OR country LIKE '{$n}{$query}{$n}' OR region LIKE '{$n}{$query}{$n}' OR town LIKE '{$n}{$query}{$n}')";
				$sql .= $this->db->prepare(" AND blog_id = %d", $this->blog_id );

				$results = $this->db->get_col( $sql );

				if(empty($results)) {
					return false;
				} else {
					$post__in = $results;
				}
			}

		}

		$args = array(
			'posts_per_page' => $show,
			'offset' => $startat,
			'orderby' => 'post_modified',
			'order' => 'DESC',
			'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
			'post_status' => $type,
			'post__in' => $post__in
		);

		if(!$this->user->has_cap( 'edit_others_properties' )) {
			$args['author'] = $this->user->ID;
		}

		$get_properties = new WP_Query;
		$propertylist = $get_properties->query($args);

		$this->max_num_pages = $get_properties->max_num_pages;
		$this->found_posts = $get_properties->found_posts;

		return $propertylist;

	}

	function filter_propertylist($filter = false, $startat = 0, $show = STAYPRESS_PROPERTY_PER_PAGE, $type = 'all', $includethumbimages = false) {

		$include = array();

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		// Build the filter - check for deleted
		if(isset($filter['deleted'])) {
			$post_status = $type . ',trash';
		} else {
			$post_status = $type;
		}

		// Only those with the correct meta
		if(!empty($filter['meta'])) {

			$filtersql .= "select property_id FROM {$this->property_meta} WHERE ";
			$inmeta = array();
			foreach($filter['meta'] as $key => $value) {
				$inmeta[] = "(meta_id = $key AND meta_value = '" . $value . "')";
			}

			$filtersql .= implode(' OR ', $inmeta);
			$filtersql .= " GROUP BY property_id HAVING count(*) = " . count($filter['meta']);

			$filtersql = apply_filters('staypress_propertyfilter_select', $filtersql, $filter);

			$filterlist = $this->db->get_col( $filtersql );

			if($filterlist) {
				if(empty($include)) {
					$include = $filterlist;
				} else {
					$include = array_unique(array_intersect($include, $filterlist));
				}

			} else {
				return false;
			}

		}

		if(!empty($filter['tag'])) {

			// This SQL comes from wp_query (query.php) and is changed to alter the behaviour of tag__and until it's fixed / changed

			$tsql = "SELECT p.ID FROM {$this->db->posts} p INNER JOIN {$this->db->term_relationships} tr ON (p.ID = tr.object_id) INNER JOIN {$this->db->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id) INNER JOIN {$this->db->terms} t ON (tt.term_id = t.term_id)";
			$tsql .= " WHERE t.term_id IN ('" . implode("', '", $filter['tag']) . "')";
			$tsql .= " GROUP BY p.ID HAVING count(p.ID) = " . count($filter['tag']);

			$tag_list = $this->db->get_col($tsql);

			if($tag_list) {
				if(empty($include)) {
					$include = $tag_list;
				} else {
					//$include = array_unique(array_merge($include, $tag_list));
					$include = array_unique(array_intersect($include, $tag_list));
				}

			} else {
				return false;
			}

		}

		if(!empty($filter['meta']) || !empty($filter['tag'])) {
			if(empty($include)) {
				// we've done a filter and nothing has been found, so we need to add in a 0 element to stop
				// all the properties being returned.
				$include = array('0');
			}
		}

		$args = array(
			'posts_per_page' => $show,
			'offset' => $startat,
			'orderby' => 'post_modified',
			'order' => 'DESC',
			'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
			'post_status' => $post_status
		);

		if($include !== false) {
			$args['post__in'] = $include;
		}

		if(!$this->user->has_cap( 'edit_others_properties' )) {
			$args['author'] = $this->user->ID;
		}

		$get_properties = new WP_Query;
		$propertylist = $get_properties->query($args);

		$this->max_num_pages = $get_properties->max_num_pages;
		$this->found_posts = $get_properties->found_posts;

		return $propertylist;

	}

	function get_properties_with_tags( $tags = array(), $type = 'publish' ) {

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		if(!empty($tags)) {
			$tags = array_map('mysql_real_escape_string', $tags);

			// This SQL comes from wp_query (query.php) and is changed to alter the behaviour of tag__and until it's fixed / changed

			$tsql = "SELECT p.ID FROM {$this->db->posts} p INNER JOIN {$this->db->term_relationships} tr ON (p.ID = tr.object_id) INNER JOIN {$this->db->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id) INNER JOIN {$this->db->terms} t ON (tt.term_id = t.term_id)";
			$tsql .= " WHERE t.slug IN ('" . implode("', '", $tags) . "')";
			$tsql .= " GROUP BY p.ID HAVING count(p.ID) = " . count($tags);

			$tag_list = $this->db->get_col($tsql);

			if(!empty($tag_list)) {
				$include = $tag_list;
			} else {
				$include = array(0);
			}

			return $include;

		}

	}

	function get_extendedpropertylist($startat = 0, $show = STAYPRESS_PROPERTY_PER_PAGE, $type = 'all', $includethumbimages = false) {

		$properties = $this->get_propertylist($startat, $show, $type, $includethumbimages);

		if(!empty($properties)) {

			$ids = array();
			foreach($properties as $key => $property) {
				$ids[$key] = $property->ID;
			}

			$sql = "SELECT post_id, reference FROM {$this->property} WHERE post_id IN (" . implode(",", $ids) . ")";

			$results = $this->db->get_results($sql);
			if(!empty($results)) {
				foreach($results as $p) {
					$k = array_search($p->post_id, $ids);
					if($k !== false) {
						$properties[$k]->reference = $p->reference;
					}
				}
			}

			return $properties;

		} else {
			return false;
		}


	}

	function property_has_children( $property_id ) {

		$sql = $this->db->prepare( "SELECT count(*) as children FROM {$this->db->posts} WHERE post_type = %s AND post_parent = %d", STAYPRESS_PROPERTY_POST_TYPE, $property_id );

		$children = $this->db->get_var( $sql );

		if($children == 0) {
			return false;
		} else {
			return true;
		}

	}

	function get_childpropertylist( $parent_id , $type = 'all' ) {

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		$args = array(
			'posts_per_page' => 25,
			'offset' => 0,
			'orderby' => 'post_modified',
			'order' => 'DESC',
			'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
			'post_status' => $type,
			'post_parent' => $parent_id
		);

		if(!$this->user->has_cap( 'edit_others_properties' )) {
			$args['author'] = $this->user->ID;
		}

		$get_properties = new WP_Query;
		$propertylist = $get_properties->query($args);

		return $propertylist;

	}

	function get_propertylist($startat = 0, $show = STAYPRESS_PROPERTY_PER_PAGE, $type = 'all', $includethumbimages = false) {

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		$args = array(
			'posts_per_page' => $show,
			'offset' => $startat,
			'orderby' => 'post_modified',
			'order' => 'DESC',
			'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
			'post_status' => $type,
			'post_parent' => 0
		);

		if(!$this->user->has_cap( 'edit_others_properties' )) {
			$args['author'] = $this->user->ID;
		}

		$get_properties = new WP_Query;
		$propertylist = $get_properties->query($args);

		$this->max_num_pages = $get_properties->max_num_pages;
		$this->found_posts = $get_properties->found_posts;

		return $propertylist;

	}

	function get_properties_for_keyword( $keyword, $page = 0, $type = 'publish' ) {

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		if($page <= 1) {
			$startat = 0;
		} else {
			$startat = ($page - 1) * STAYPRESS_PROPERTY_PER_PAGE;
		}

		if(!empty($keyword)) {

			//$sql = $this->db->prepare();

			$args = array(
				'posts_per_page' => STAYPRESS_PROPERTY_PER_PAGE,
				'offset' => $startat,
				'orderby' => 'post_modified',
				'order' => 'DESC',
				'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
				'post_status' => $type,
				's' => $keyword
			);

			$get_properties = new WP_Query;
			$propertylist = $get_properties->query($args);

			if(!empty($propertylist)) {
				$properties = array();
				foreach($propertylist as $property) {
					$properties[] = $property->ID;
				}
			} else {
				$properties = array(0);
			}

			$this->max_num_pages = $get_properties->max_num_pages;
			$this->found_posts = $get_properties->found_posts;

			$processlist = true;

			return $properties;

		} else {

			$processlist = true;

			return array(0);
		}

	}

	// Public side searching
	function public_quickfind_propertyreforid($query = '', $type = 'publish') {
		// Only returns a single property

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		if(!empty($query)) {

			if(is_numeric($query)) {
				// possibly an id
				$property = $this->get_property($query, true, false);

				if(!is_wp_error($property) && in_array($property->post_status, explode(',', $type))) {
					return $property;
				} else {
					return false;
				}

			} elseif(strpos($query, ' ') === false) {
				// possibly a reference
				$id = $this->get_postid_for_reference( $query );

				if($id !== false) {
					$property = $this->get_property($id, true, false);

					if(!is_wp_error($property) && in_array($property->post_status, explode(',', $type))) {
						return $property;
					} else {
						return false;
					}

				} else {
					return false;
				}

			} else {
				// there is a space in there so pass this on to the next
				return false;
			}
		} else {
			return false;
		}

	}

	function public_get_owner_id($property_id) {

		$property = get_post($property_id);

		if($property) {

			if(in_array($property->post_status, array('publish', 'private'))) {
				return $property->post_author;
			}

		}

		return false;

	}

	function public_quickfind_property($query = '', $startat = 0, $show = STAYPRESS_PROPERTY_PER_PAGE, $type = 'all') {

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		// Need to redo this search
		if(!empty($query)) {
			$args = array(
				'posts_per_page' => $show,
				'offset' => $startat,
				'orderby' => 'post_modified',
				'order' => 'DESC',
				'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
				'post_status' => $type,
				's' => $query
			);

			$get_properties = new WP_Query;
			$propertylist = $get_properties->query($args);

			$this->max_num_pages = $get_properties->max_num_pages;
			$this->found_posts = $get_properties->found_posts;

			$processlist = true;

			return $propertylist;

		} else {

			$processlist = true;

			return false;
		}

	}

	function public_quickfind_destination($query = '', $type = 'publish') {
		// Only returns the first destination

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		if(!empty($query)) {

			$args = array(
				'posts_per_page' => $show,
				'offset' => $startat,
				'orderby' => 'post_modified',
				'order' => 'DESC',
				'post_type' => STAYPRESS_DESTINATION_POST_TYPE,
				'post_status' => $type,
				's' => $query
			);

			$get_dest = new WP_Query;
			$dest = $get_dest->query($args);

			if(!empty($dest)) {
				return $dest[0];
			} else {
				return false;
			}
		} else {
			return false;
		}

	}

	function public_quickfind_location($query = '') {
		// Only returns a single location

		if(!empty($query)) {

			$find = array_map('strtolower', array_map('trim', explode(',', $query)));

			// check if we have multiple criteria entered so treat first as town, second as region, etc..
			switch(count($find)) {

				case 1:		$sql = $this->db->prepare( "SELECT country, region, town FROM {$this->property} WHERE LOWER(country) = %s OR LOWER(region) = %s OR LOWER(town) = %s ORDER BY property_modified DESC LIMIT 0, 1", $find[0], $find[0], $find[0] );
							break;

				case 2:		$sql = $this->db->prepare( "SELECT country, region, town FROM {$this->property} WHERE (LOWER(country) = %s AND LOWER(region) = %s) OR (LOWER(region) = %s AND LOWER(town) = %s) OR (LOWER(country) = %s AND LOWER(town) = %s) ORDER BY property_modified DESC LIMIT 0, 1", $find[1], $find[0], $find[1], $find[0], $find[1], $find[0] );
							break;

				case 3:		$sql = $this->db->prepare( "SELECT country, region, town FROM {$this->property} WHERE (LOWER(country) = %s AND LOWER(region) = %s AND LOWER(town) = %s) ORDER BY property_modified DESC LIMIT 0, 1", $find[2], $find[1], $find[0] );
							break;

				default:	// Same as one above.
							$sql = $this->db->prepare( "SELECT country, region, town FROM {$this->property} WHERE LOWER(country) = %s OR LOWER(region) = %s OR LOWER(town) = %s ORDER BY property_modified DESC LIMIT 0, 1", $find[0], $find[0], $find[0] );
							break;
			}

			$results = $this->db->get_row( $sql );

			if(!empty($results)) {
				// Check if the town was entered - and blank if not
				if(!in_array(strtolower($results->town), $find)) {
					// no so blank out town
					unset($results->town);

					if(!in_array(strtolower($results->region), $find)) {
						// no so blank out town
						unset($results->region);
					}
				}

				return $results;
			} else {
				return false;
			}

		} else {
			return false;
		}

	}

	function public_get_propertycontacts($property_id = false, $type = 'publish,private') {

		$args = array(
			'post_type' => STAYPRESS_CONTACT_POST_TYPE,
			'post_status' => $type,
			'meta_key' => '_property_id',
			'orderby' => 'post_modified',
			'order' => 'DESC',
			'meta_value' => $property_id
		);

		$get_contacts = new WP_Query;
		$contactlist = $get_contacts->query($args);

		return $contactlist;

	}


	function get_reference($property) {

		if(!empty($property->reference)) {
			$ref = $property->reference;
		} else {
			$ref = $this->db->get_var( $this->db->prepare( "SELECT p.reference FROM {$this->property} AS p WHERE p.post_id = %d LIMIT 0,1", $property->ID ));
		}

		return $ref;

	}

	function get_postid_for_reference( $reference = false, $include = 'publish,private' ) {

		if($reference) {
			$include = array_map('trim', explode(",", $include));
			$id = $this->db->get_var( $this->db->prepare( "SELECT p.post_id FROM {$this->property} AS p WHERE LOWER(p.reference) = %s AND status IN ('" . implode("','", $include ) . "') LIMIT 0,1", strtolower($reference) ));

			return $id;
		} else {
			return false;
		}

	}

	function get_postid_for_title( $reference = false, $include = 'publish,private' ) {

		if($reference) {
			$include = array_map('trim', explode(",", $include));
			$id = $this->db->get_var( $this->db->prepare( "SELECT p.post_id FROM {$this->property} AS p WHERE LOWER(p.reference) = %s AND status IN ('" . implode("','", $include ) . "') LIMIT 0,1", strtolower($reference) ));

			return $id;
		} else {
			return false;
		}

	}

	// Individual property getters and setters - extended is now depreciated in favour of the_post filter
	function get_property($id = false, $extended = true, $enforcepermissions = true) {

		$property = get_post($id);

		if($property) {

			if($enforcepermissions && $property->post_author != $this->user_id && !current_user_can( 'edit_others_properties' )) {
				return new WP_Error('nopermissions', __('You do not have permissions to access this property.','property'));
			}

			// For extending the post automatically
			do_action_ref_array('the_post', array(&$property));

			return $property;

		} else {
			return new WP_Error('notfound', __('The property could not be found.','property'));
		}

	}

	function extend_property($property) {

		if($property->post_type == STAYPRESS_PROPERTY_POST_TYPE && !isset($property->reference)) {

			$propertyext = $this->db->get_row( $this->db->prepare( "SELECT p.* FROM {$this->property} AS p WHERE p.post_id = %d LIMIT 0,1", $property->ID ));
			if(!empty($propertyext)) {

				$property->ext_id = $propertyext->id;

				$property->reference = $propertyext->reference;
				$property->latitude = $propertyext->latitude;
				$property->longitude = $propertyext->longitude;
				$property->country = $propertyext->country;
				$property->town = $propertyext->town;
				$property->region = $propertyext->region;

			}
		}

		return $property;
	}

	function delete_property($id = false) {

		$property = $this->get_property($id);

		if(is_wp_error($property)) {
			// No property or not this users property and user can't edit others properties
			return $property;
		}

		if(current_user_can('delete_property')) {

			$sql = $this->db->prepare( "UPDATE {$this->property} AS p SET status = 'trash' WHERE p.post_id = %d", $id );
			$results = $this->db->query( $sql );

			$sql = $this->db->prepare( "UPDATE {$this->db->posts} AS p SET post_status = 'trash' WHERE p.ID = %d", $id);
			$results = $this->db->query( $sql );

			if($results) {
				return $id;
			} else {
				return new WP_Error('notdeleted', __('The property could not be deleted.','property'));
			}

		} else {
			return new WP_Error('nopermissions', __('You do not have permissions to delete this property.','property'));
		}

	}

	function get_country_ids( $country, $type = 'publish' ) {

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS pp.ID FROM {$this->property} as p INNER JOIN {$this->db->posts} as pp ON p.post_id = pp.ID WHERE REPLACE(LOWER(country), ' ', '-') = %s", $country );

		$results = $this->db->get_col( $sql );

		if(!empty($results)) {
			return $results;
		} else {
			return array(0);
		}

	}

	function get_region_ids( $country, $region, $type = 'publish' ) {

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS post_id FROM {$this->property} WHERE REPLACE(LOWER(country), ' ', '-') = %s AND REPLACE(LOWER(region), ' ', '-') = %s", $country, $region );

		$results = $this->db->get_col( $sql );

		if(!empty($results)) {
			return $results;
		} else {
			return array(0);
		}

	}

	function get_town_ids( $country, $region, $town, $type = 'publish' ) {

		if($type == 'all') {
			$type = 'publish,draft,pending,private';
		}

		$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS post_id FROM {$this->property} WHERE REPLACE(LOWER(country), ' ', '-') = %s AND REPLACE(LOWER(region), ' ', '-') = %s AND REPLACE(LOWER(town), ' ', '-') = %s", $country, $region, $town );

		$results = $this->db->get_col( $sql );

		if(!empty($results)) {
			return $results;
		} else {
			return array(0);
		}

	}

	function get_posts_for_meta_key( $key, $values = array(), $excludestatus = array('trash', 'draft', 'private') ) {

		if(!is_array($values) || empty($values)) {
			return false;
		}
		// escape the values
		$values = array_map( 'mysql_real_escape_string', $values );

		$sql = $this->db->prepare( "SELECT pm.post_id FROM {$this->db->postmeta} AS pm INNER JOIN {$this->db->posts} AS p ON pm.post_id = p.ID WHERE p.post_status NOT IN ('" . implode("','", $excludestatus) . "') AND pm.meta_key = %s AND pm.meta_value IN ('" . implode("','", $values ) . "') ORDER BY CHAR_LENGTH(meta_value) DESC LIMIT 0, %d", $key, count($values) );

		return $this->db->get_col( $sql );
	}

	function get_properties_for_metas( $metas, $excludestatus = array('trash', 'draft', 'private') ) {

		$filtersql .= "SELECT pm.property_id FROM {$this->property_meta} AS pm INNER JOIN {$this->metadesc} AS m ON pm.meta_id = m.id INNER JOIN {$this->db->posts} AS p ON pm.property_id = p.ID  WHERE ";
		$filtersql .= $this->db->prepare("(m.blog_id IN (0, %d)) AND ", $this->blog_id);
		$filtersql .= "p.post_status NOT IN ('" . implode("','", $excludestatus) . "') AND ";
		$inmeta = array();
		foreach($metas as $key => $value) {
			$inmeta[] = $this->db->prepare("(REPLACE(LOWER(m.metaname), ' ', '-') = %s AND pm.meta_value = %s)", $key, $value);
		}

		$filtersql .= implode(' OR ', $inmeta);
		$filtersql .= " GROUP BY pm.property_id HAVING count(*) = " . count($metas);

		$filtersql = apply_filters('staypress_propertyfilter_select', $filtersql, $filter);

		$filterlist = $this->db->get_col( $filtersql );

		if(!empty($filterlist)) {
			return $filterlist;
		} else {
			return array(0);
		}

	}

	function set_status($id, $to) {

		if(current_user_can('edit_property', $id)) {
			$sql = $this->db->prepare( "UPDATE {$this->property} AS p SET status = %s WHERE p.post_id = %d", $to, $id );
			$results = $this->db->query( $sql );

			$sql = $this->db->prepare( "UPDATE {$this->db->posts} AS p SET post_status = %s WHERE AND p.ID = %d", $to, $id);
			$results = $this->db->query( $sql );
		} else {
			return false;
		}

	}

	function set_property_mainimage($post_id, $image_id) {

		$post_id = (int) $post_id;
		$image_id = (int) $image_id;

		update_post_meta( $post_id, '_mainimage_id', $image_id );

	}

	function has_property_mainimage($post_id) {
		return !! $this->get_property_mainimage_id( $post_id );
	}

	function get_property_mainimage_id($post_id) {
		return get_post_meta( $post_id, '_mainimage_id', true );
	}

	function get_property_mainimage_url($post_id) {
		if($this->has_property_mainimage($post_id)) {
			return wp_get_attachment_url($this->get_property_mainimage_id($post_id));
		} else {
			return false;
		}
	}

	function set_property_thumbnail($post_id, $image_id) {

		$post_id = (int) $post_id;
		$image_id = (int) $image_id;

		update_post_meta( $post_id, '_thumbnail_id', $image_id );

	}

	function has_property_thumbnail($post_id) {
		return !! $this->get_property_thumbnail_id( $post_id );
	}

	function get_property_thumbnail_id($post_id) {
		return get_post_meta( $post_id, '_thumbnail_id', true );
	}

	function get_property_thumbnail_url($post_id) {
		if($this->has_property_thumbnail($post_id)) {
			return wp_get_attachment_thumb_url($this->get_property_thumbnail_id($post_id));
		} else {
			return false;
		}
	}

	function get_property_images($post_id = false, $type = 'thumb', $limit = false) {

		$args = array( 	'post_parent' => $post_id,
						'post_type'	=> 'attachment',
						'orderby' => 'menu_order',
						'order' => 'ASC'
					);

		$children = get_children($args);

		return $children;
	}

	function get_price_periods($property_id = false) {

		if(!$property_id) $property_id = $this->property_id;

		$sql = $this->db->prepare( "SELECT DISTINCT ppl.price_period, ppl.price_period_type FROM {$this->property_price_line} AS ppl INNER JOIN {$this->property_price} AS pp ON pp.property_id = ppl.property_id AND pp.price_row = ppl.price_row WHERE pp.property_id = %d AND pp.blog_id = %d ORDER BY ppl.price_period_type ASC, ppl.price_period ASC", $property_id , $this->blog_id);

		$results = $this->db->get_results($sql);

		return $results;

	}

	function get_propertyprices($property_id = false) {

		if(!$property_id) $property_id = $this->property_id;

		$sql = $this->db->prepare( "SELECT * FROM {$this->property_price} WHERE property_id = %d AND blog_id = %d ORDER BY price_month ASC, price_day ASC", $property_id , $this->blog_id);

		$results = $this->db->get_results($sql);

		return $results;

	}

	function get_propertypricelines( $property_id = false, $row = 1 ) {

		if(!$property_id) $property_id = $this->property_id;

		$sql = $this->db->prepare( "SELECT * FROM {$this->property_price_line} WHERE property_id = %d AND price_row = %d", $property_id , $row);

		$results = $this->db->get_results($sql);

		return $results;

	}

	function get_full_propertyprices( $property_id = false ) {

		if(!$property_id) $property_id = $this->property_id;

		$sql  = "SELECT pr.property_id, pr.price_row, pr.price_day, pr.price_month, prl.price_amount, prl.price_period, prl.price_period_type, prl.price_currency ";
		$sql .= " FROM {$this->property_price} AS pr INNER JOIN {$this->property_price_line} AS prl ON pr.property_id = prl.property_id AND pr.price_row = prl.price_row";
		$sql .= $this->db->prepare( " WHERE pr.property_id = %d AND pr.blog_id = %d ORDER BY pr.price_month ASC, pr.price_day ASC", $property_id , $this->blog_id);

		$results = $this->db->get_results($sql);

		return $results;

	}

	function get_period_propertyprices( $property_id = false, $period = 1, $period_type = 'm' ) {

		if(!$property_id) $property_id = $this->property_id;

		$sql  = "SELECT pr.property_id, pr.price_row, pr.price_day, pr.price_month, prl.price_amount, prl.price_period, prl.price_period_type, prl.price_currency ";
		$sql .= " FROM {$this->property_price} AS pr INNER JOIN {$this->property_price_line} AS prl ON pr.property_id = prl.property_id AND pr.price_row = prl.price_row";
		$sql .= $this->db->prepare( " WHERE pr.property_id = %d AND pr.blog_id = %d AND prl.price_period = %d AND prl.price_period_type = %s ORDER BY pr.price_month ASC, pr.price_day ASC", $property_id , $this->blog_id, $period, $period_type);

		$results = $this->db->get_results($sql);

		return $results;

	}

	function get_propertycontacts($property_id = false, $type = 'publish,draft,pending,private') {

		$args = array(
			'post_type' => STAYPRESS_CONTACT_POST_TYPE,
			'post_status' => $type,
			'meta_key' => '_property_id',
			'orderby' => 'post_modified',
			'order' => 'DESC',
			'meta_value' => $property_id
		);

		if(!$this->user->has_cap( 'edit_others_contacts' )) {
			$args['author'] = $this->user->ID;
		}

		$get_contacts = new WP_Query;
		$contactlist = $get_contacts->query($args);

		return $contactlist;

	}

	function get_propertymeta($property_id = false) {

		if(!$property_id) $property_id = $this->property_id;

		$sql = $this->db->prepare( "SELECT * FROM {$this->property_meta} WHERE property_id = %d AND blog_id = %d ORDER BY meta_id ASC", $property_id, $this->blog_id );

		$results = $this->db->get_results($sql);

		return $results;

	}

	function insert_contact($contact = false) {

		//function get_post_custom($post_id = 0) {

		$post = array(
		'post_title' => $contact['name'],
		'post_content' => $contact['notes'],
		'post_name' => sanitize_title($contact['name']),
		'post_status' => 'private', // You can also make this pending, or whatever you want, really.
		'post_author' => $this->user_id,
		'post_category' => array(get_option('default_category')),
		'post_type' => STAYPRESS_CONTACT_POST_TYPE,
		'comment_status' => 'closed'
		);

		// update the post
		$contact_id = wp_insert_post($post);

		if(!is_wp_error($contact_id)) {
			update_metadata('post', $contact_id, 'contact_name', $contact['name']);
			update_metadata('post', $contact_id, 'contact_email', $contact['email']);
			update_metadata('post', $contact_id, 'contact_tel', $contact['tel']);
			update_metadata('post', $contact_id, '_property_id', $contact['property_id']);
		}

		return $contact_id;

	}

	function update_contact($contact_id = false, $contact = false) {

		$post = array(
		'post_title' => $contact['name'],
		'post_content' => $contact['notes'],
		'post_name' => sanitize_title($contact['name']),
		'post_status' => 'private', // You can also make this pending, or whatever you want, really.
		'post_author' => $this->user_id,
		'post_category' => array(get_option('default_category')),
		'post_type' => STAYPRESS_CONTACT_POST_TYPE,
		'comment_status' => 'closed'
		);

		$post['ID'] = $contact_id;

		// update the post
		$new_id = wp_update_post($post);

		if(!is_wp_error($new_id)) {
			update_metadata('post', $contact_id, 'contact_name', $contact['name']);
			update_metadata('post', $contact_id, 'contact_email', $contact['email']);
			update_metadata('post', $contact_id, 'contact_tel', $contact['tel']);
			update_metadata('post', $contact_id, '_property_id', $contact['property_id']);
		}

		return $contact_id;

	}

	function update_prices($property_id = false, $prices = false, $pricelines = false) {

		if(!$property_id) $property_id = $this->property_id;

		$sql = $this->db->prepare( "DELETE FROM {$this->property_price} WHERE property_id = %d AND blog_id = %d", $property_id, $this->blog_id );
		$result = $this->db->query( $sql );

		$sql = $this->db->prepare( "DELETE FROM {$this->property_price_line} WHERE property_id = %d", $property_id );
		$result = $this->db->query( $sql );

		if(!empty($prices)) {

			$sqlin = array();
			$row = 1;
			// Add the new prices here
			foreach($prices as $key => $price) {
				$sqlin[] = "($property_id, $key, '" . mysql_real_escape_string($price['day']) . "','" . mysql_real_escape_string($price['month']) . "', " . $this->blog_id . ")";
				$row++;
			}
			if(!empty($sqlin)) {
				$sql = "INSERT INTO {$this->property_price} VALUES " . implode(',', $sqlin);
				$this->db->query($sql);
			}

		}

		if(!empty($pricelines)) {
			$sqlin = array();
			foreach($pricelines as $key => $priceline) {
				$sqlin[] = $this->db->prepare("(%d, %d, %f, %d, %s, %s)", $priceline['property_id'], $priceline['price_row'], $priceline['price_amount'], $priceline['price_period'], $priceline['price_period_type'], $priceline['price_currency']   );
				$row++;
			}
			if(!empty($sqlin)) {
				$sql = "INSERT INTO {$this->property_price_line} VALUES " . implode(',', $sqlin);
				$this->db->query($sql);
			}
		}

	}

	function update_propertymeta($property_id = false, $meta = false) {

		if(!$property_id) $property_id = $this->property_id;

		$sql = $this->db->prepare( "DELETE FROM {$this->property_meta} WHERE property_id = %d AND blog_id = %d", $property_id, $this->blog_id );

		$result = $this->db->query( $sql );

		if(!empty($meta)) {

			$sqlin = array();
			$row = 1;
			// Add the new meta here
			foreach($meta as $key => $value) {

				$sqlin[] = "($property_id, $key, '" . mysql_real_escape_string($value) . "', " . $this->blog_id . ")";
				$row++;
			}
			if(!empty($sqlin)) {
				$sql = "INSERT INTO {$this->property_meta} VALUES " . implode(',', $sqlin);
				$this->db->query($sql);
			}

		}

	}

	function move_images($old_ID, $new_ID, $images = false) {

		$old_ID = (int) $old_ID;
		$new_ID = (int) $new_ID;

		$children = $this->db->get_col( $this->db->prepare("
			SELECT post_id
			FROM {$this->db->postmeta}
			WHERE meta_key = '_wp_attachment_temp_parent'
			AND meta_value = %d", $old_ID) );

		foreach ( $children as $child_id ) {

			$meta = get_post_meta($child_id, '_wp_attachment_metadata', true);
			$file = get_post_meta($child_id, '_wp_attached_file', true);

			if(!empty($meta)) {
				if(isset($meta['file'])) {
					$meta['file'] = str_replace('/' . $old_ID . '/', '/' . $new_ID . '/', $meta['file']);
				}
			}
			if(!empty($file)) {
				$file = str_replace('/' . $old_ID . '/', '/' . $new_ID . '/', $file);
			}

			// now to write the information back in, but manually as WP won't let me use its functions.
			$this->db->update( $this->db->postmeta, array('meta_value' => maybe_serialize($meta)), array('meta_key' => '_wp_attachment_metadata', 'post_id' => $child_id));
			$this->db->update( $this->db->postmeta, array('meta_value' => $file), array('meta_key' => '_wp_attached_file', 'post_id' => $child_id));

			// Update the post details so that it links back up
			$sql = $this->db->prepare( "UPDATE {$this->db->posts} SET post_parent = %d, guid = REPLACE(guid, %s, %s) WHERE ID = %d AND post_parent = 0 AND post_type = 'attachment' AND post_status = 'inherit'", $new_ID, $old_ID, $new_ID, $child_id );

			$this->db->query( $sql );

			delete_post_meta($child_id, '_wp_attachment_temp_parent');

			wp_cache_delete($child_id, 'post_meta');
		}

		return true;
	}

	function arrange_imageorder($property_id, $imgarray = false) {

		if($imgarray) {
			$order = 1;
			$sql = '';
			foreach($imgarray as $key => $img) {
				if(is_numeric($img)) {
					$this->db->update( $this->db->posts, array('menu_order' => $order), array('ID' => $img));
				}
				$order++;
			}
		}

	}

	function sanitize_slug($slug) {
		$slug = strip_tags($slug);
		// Preserve escaped octets.
		$slug = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $slug);
		// Remove percent signs that are not part of an octet.
		$slug = str_replace('%', '', $slug);
		// Restore octets.
		$slug = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $slug);

		$slug = strtolower($slug);
		$slug = preg_replace('/&.+?;/', '', $slug); // kill entities
		$slug = str_replace('.', '-', $slug);
		$slug = preg_replace('/[^%a-z0-9 _-]/', '', $slug);
		$slug = preg_replace('/\s+/', '-', $slug);
		$slug = preg_replace('|-+|', '-', $slug);
		$slug = trim($slug, '-');

		return $slug;
	}

	function validate_property($id = false, $property = array()) {

		$rules = array( 'title' 	=> 	'required' );

		$rules = apply_filters('staypress_property_validation_rules', $rules);

		if(!empty($property)) {

			foreach((array) $rules as $key => $validation) {

				switch($validation) {

					case 'required':	if(empty($property[$key])) {
											return new WP_Error('required-' . $key,sprintf(__('The %s field is a required one.','property'), ucfirst($key) ));
										}
										break;

				}

			}

		}

	}

	function update_property($id = false, $property = array()) {

		if(!empty($id)) {

			$existingproperty = $this->get_property($id);

			if(is_wp_error($existingproperty)) {
				// No property or not this users property and user can't edit others properties
				return $existingproperty;
			}

			// Insert the property as a post first - so we can get the id for later use
			$post = array(
			'ID' => $id,
			'post_title' => $property['title'],
			'post_excerpt' => $property['extract'],
			'post_content' => $property['description'],
			'post_name' => sanitize_title($property['title']),
			'post_status' => $property['status'], // You can also make this pending, or whatever you want, really.
			//'post_author' => $this->user_id, // leave the post_author as the creator of the property
			'post_category' => array(get_option('default_category')),
			'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
			'comment_status' => 'closed',
			'tags_input'	=> (isset($property['tags_input']) ? $property['tags_input'] : ''),
			'tax_input' => $property['tax_input'],
			'post_parent' => (isset($property['post_parent']) ? $property['post_parent'] : 0)
			);

			// update the post
			$property['post_id'] = wp_update_post($post);

			// set the blog id
			$property['blog_id'] = $this->blog_id;
			// remove the post ID before adding it to the external database
			unset($post['ID']);

			// remove the property tag information as we don't store it in the external tables
			unset($property['tags_imput']);
			unset($property['tax_input']);
			unset($property['post_parent']);

			if(!empty($property['listimageid']) && is_numeric($property['listimageid'])) {
				$this->set_property_thumbnail($property['post_id'], $property['listimageid']);
			}

			if(!empty($property['mainimageid']) && is_numeric($property['mainimageid'])) {
				$this->set_property_mainimage($property['post_id'], $property['mainimageid']);
			}

			$sql = "UPDATE {$this->property} ";

			$insql = array();
			foreach($property as $key => $value) {
				$insql[] = "$key = '" . mysql_real_escape_string($value) . "'";
			}

			$insql[] = "property_modified = '" . $this->current_time('mysql') . "'";
			$insql[] = "property_modified_gmt = '" . $this->current_time('mysql',1) . "'";

			if(!empty($insql)) {
				$sql .= "SET " . implode(',', $insql);
			}

			$sql .= " WHERE post_id = " . addslashes($id);

			$result = $this->db->query($sql);

			if($result) {
				return $id;
			} else {
				return new WP_Error('notsaved', __('The property could not be updated.','property') );
			}

		} else {
			return new WP_Error('noproperty', __('The property details do not exist.','property') );
		}

	}

	function insert_property($id = false, $property = array()) {

		if($id < 0) {

			// Insert the property as a post first - so we can get the id for later use
			$post = array(
			'post_title' => $property['title'],
			'post_excerpt' => $property['extract'],
			'post_content' => $property['description'],
			'post_name' => sanitize_title($property['title']),
			'post_status' => $property['status'], // You can also make this pending, or whatever you want, really.
			'post_author' => $this->user_id,
			'post_category' => array(get_option('default_category')),
			'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
			'comment_status' => 'closed',
			'tags_input'	=> (isset($property['tags_input']) ? $property['tags_input'] : ''),
			'tax_input' => $property['tax_input'],
			'post_parent' => (isset($property['post_parent']) ? $property['post_parent'] : 0)
			);

			// update the post
			$property['post_id'] = wp_insert_post($post);

			// set the blog id
			$property['blog_id'] = $this->blog_id;

			// remove the property tag information as we don't store it in the external tables
			unset($property['tags_imput']);
			unset($property['tax_input']);
			unset($property['post_parent']);

			if(!empty($property['listimageid']) && is_numeric($property['listimageid'])) {
				$this->set_property_thumbnail($property['post_id'], $property['listimageid']);
			}

			$sql = "INSERT INTO {$this->property} ";

			$insql = array();

			foreach($property as $key => $value) {
				$insql[$key] = "'" . mysql_real_escape_string($value) . "'";
			}

			$insql['property_modified'] = "'" . $this->current_time('mysql') . "'";
			$insql['property_modified_gmt'] = "'" . $this->current_time('mysql',1) . "'";

			if(!empty($insql)) {
				$sql .= "(" . implode(',', array_keys($insql)) . ") VALUES (";
				$sql .= implode(',', array_values($insql));
				$sql .= ")";
			}

			$result = $this->db->query($sql);

			if($result) {
				$newid = $this->get_lastid();

				return $property['post_id'];

			} else {
				return false;
			}

		} else {
			return false;
		}

	}

	// Searching getters

	function get_metadesc($filter = '', $startat = 0, $limit = 20) {

		if(empty($filter)) {
			$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS m.*, mg.groupname FROM {$this->metadesc} AS m LEFT JOIN {$this->metagroupdesc} AS mg ON m.metagroup_id = mg.id WHERE m.blog_id IN (0, %d) AND mg.blog_id IN (0, %d)  ORDER BY mg.grouporder, mg.groupname, m.showorder LIMIT %d, %d", $this->blog_id, $this->blog_id, $startat, $limit );
		} else {
			$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS m.*, mg.groupname FROM {$this->metadesc} AS m LEFT JOIN {$this->metagroupdesc} AS mg ON m.metagroup_id = mg.id WHERE m.blog_id IN (0, %d) AND mg.blog_id IN (0, %d) AND (m.metaname LIKE CONCAT('%%', %s, '%%') OR mg.groupname LIKE CONCAT('%%', %s, '%%')) ORDER BY mg.grouporder, mg.groupname, m.showorder LIMIT %d, %d", $this->blog_id, $this->blog_id, $filter, $filter, $startat, $limit );
		}

		$results = $this->db->get_results($sql);

		return $results;

	}

	function add_metagroup($groupname) {

		$rows = $this->db->get_var( $this->db->prepare("SELECT count(*) FROM {$this->metagroupdesc} WHERE groupname = %s AND blog_id IN (0, %d)", $groupname, $this->blog_id) );

		if($rows > 0) {
			return false;
		} else {
			return $this->db->insert($this->metagroupdesc, array('groupname' => $groupname, 'blog_id' => $this->blog_id));
		}

	}

	function update_metagroup($groupid, $groupname) {

		$rows = $this->db->get_var( $this->db->prepare("SELECT count(*) FROM {$this->metagroupdesc} WHERE id = %d AND blog_id IN (0, %d)", $groupid, $this->blog_id) );

		if($rows > 0) {
			$rows = $this->db->get_var( $this->db->prepare("SELECT count(*) FROM {$this->metagroupdesc} WHERE groupname = %s AND blog_id IN (0, %d)", $groupname, $this->blog_id) );
			if($rows > 0) {
				return false;
			} else {
				return $this->db->update($this->metagroupdesc, array('groupname' => $groupname), array('id' => $groupid ,'blog_id' => $this->blog_id));
			}
		} else {
			return $this->add_metagroup($groupname);
		}

	}

	function delete_metagroup($fac_ID) {
		$group = $this->get_metagroupforfac($fac_ID);

		if(!empty($group)) {
			if($group->blog_id == $this->blog_id) {
				// can delete
				$sql = $this->db->prepare( "DELETE FROM {$this->metagroupdesc} WHERE id = %d AND blog_id = %d", $group->id, $this->blog_id);
				$sql2 = $this->db->prepare( "DELETE FROM {$this->metadesc} WHERE metagroup_id = %d AND blog_id = %d", $group->id, $this->blog_id);

				$this->db->query($sql2);

				return $this->db->query($sql);
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	function get_metagroups() {

		$sql = $this->db->prepare( "SELECT mg.id, mg.groupname FROM {$this->metagroupdesc} AS mg WHERE mg.blog_id IN (0, %d)", $this->blog_id );

		$results = $this->db->get_results($sql);

		return $results;
	}

	function get_metagroupforfac($fac_ID) {

		$sql = $this->db->prepare( "SELECT mg.id, mg.groupname, mg.blog_id FROM {$this->metagroupdesc} AS mg INNER JOIN {$this->metadesc} AS m ON m.metagroup_id = mg.id WHERE m.id = %d AND mg.blog_id IN (0, %d)", $fac_ID, $this->blog_id );

		$results = $this->db->get_row($sql);

		return $results;
	}

	function get_metaforfac($fac_ID) {

		$sql = $this->db->prepare( "SELECT * FROM {$this->metadesc} AS m WHERE m.id = %d AND m.blog_id IN (0, %d)", $fac_ID, $this->blog_id );

		$results = $this->db->get_row($sql);

		return $results;
	}

	function get_meta($fac_ID) {
		return $this->get_metaforfac($fac_ID);
	}

	function add_meta($fac) {

		$rows = $this->db->get_var( $this->db->prepare("SELECT count(*) FROM {$this->metadesc} WHERE metaname = %s AND metagroup_id = %d  AND blog_id IN (0, %d)", $fac['metaname'], $fac['metagroup_id'], $this->blog_id) );

		if($rows > 0) {
			return false;
		} else {
			$fac['blog_id'] = $this->blog_id;
			return $this->db->insert($this->metadesc, $fac);
		}

	}

	function update_meta($fac_ID, $fac) {

		$rows = $this->db->get_var( $this->db->prepare("SELECT count(*) FROM {$this->metadesc} WHERE id = %d AND blog_id IN (0, %d)", $fac_ID, $this->blog_id) );

		if($rows > 0) {
			$rows = $this->db->get_var( $this->db->prepare("SELECT count(*) FROM {$this->metadesc} WHERE metaname = %s AND id != %d AND metagroup_id = %d AND blog_id IN (0, %d)", $fac['metaname'], $fac_ID, $fac['metagroup_id'], $this->blog_id) );

			if($rows > 0) {
				return false;
			} else {
				return $this->db->update($this->metadesc, $fac, array('id' => $fac_ID ,'blog_id' => $this->blog_id));
			}
		} else {
			return $this->add_metagroup($fac);
		}

	}

	function delete_meta($fac_ID) {

		$meta = $this->get_meta($fac_ID);

		if(!empty($meta)) {
			if($meta->blog_id == $this->blog_id) {
				// can delete
				$sql = $this->db->prepare( "DELETE FROM {$this->metadesc} WHERE id = %d AND blog_id = %d", $meta->id, $this->blog_id);

				return $this->db->query($sql);
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	function get_meta_value( $property_id = false, $metakey = false, $default = false ) {

		if($property_id && $metakey) {

			$sql = $this->db->prepare( "SELECT pm.meta_value FROM {$this->property_meta} AS pm, {$this->metadesc} AS md WHERE pm.meta_id = md.id AND (pm.blog_id = %d OR pm.blog_id = 0) and (md.blog_id = %d OR md.blog_id = 0) AND pm.property_id = %d AND  md.metaname = %s LIMIT 0, 1", $this->blog_id, $this->blog_id, $property_id, $metakey);

			$result = $this->db->get_var( $sql );

			if(!empty($result)) {
				return $result;
			} else {
				return $default;
			}

		} else {
			return $default;
		}

	}

	// Geo functions

	function get_withinbounds($tright, $bleft, $startat = 0, $number = STAYPRESS_PROPERTY_PER_PAGE, $formapping = false, $justid = false) {

		if($formapping && !$justid) {
			$fields = "p.post_id AS ID, p.latitude, p.longitude, p.country, p.town, p.region, posts.post_title, p.reference, posts.post_excerpt, posts.post_name";
		} else {
			$fields = "p.post_id";
		}

		$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS {$fields} FROM {$this->property} AS p INNER JOIN {$this->db->posts} AS posts ON p.post_id = posts.ID WHERE (latitude <= %f AND longitude <= %f) AND (latitude >= %f AND longitude >= %f) AND status = 'publish' ORDER BY dateadded DESC LIMIT %d , %d;",
		 						mysql_real_escape_string($tright['lat']),
								mysql_real_escape_string($tright['lng']),
								mysql_real_escape_string($bleft['lat']),
								mysql_real_escape_string($bleft['lng']),
								$startat,
								$number
								);

		if($formapping) {
			$propertylist = $this->db->get_results($sql);
		} else {
			$ranged = $this->db->get_col($sql);

			$args = array(
				'posts_per_page' => $number,
				'offset' => 0,
				'orderby' => 'post_modified',
				'order' => 'DESC',
				'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
				'post_status' => 'publish',
				'post__in' => $ranged
			);

			$get_properties = new WP_Query;
			$propertylist = $get_properties->query($args);

		}

		if(!empty($propertylist)) {
			return $propertylist;
		} else {
			return false;
		}

	}

	function get_withinrange($point, $radial, $unit = 'km', $startat = 0, $number = STAYPRESS_PROPERTY_PER_PAGE, $formapping = false, $justid = false) {

		switch($unit) {
			case 'km':		$distance = 6371; break;
			case 'mile':	$distance = 3959; break;
			default: 		$distance = 6371; break;
		}

		if($formapping && !$justid) {
			$fields = "p.post_id AS ID, p.latitude, p.longitude, p.country, p.town, p.region, posts.post_title, p.reference, posts.post_excerpt, posts.post_name";
		} else {
			$fields = "p.post_id";
		}

		// lat, lng, lat

		$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS {$fields}, ( %d * acos( cos( radians(%f) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(%f) ) + sin( radians(%f) ) * sin( radians( latitude ) ) ) ) AS distance FROM {$this->property} AS p INNER JOIN {$this->db->posts} AS posts ON p.post_id = posts.ID WHERE status = 'publish' HAVING distance < %f ORDER BY distance LIMIT %d , %d;",
								$distance,
								mysql_real_escape_string($point['lat']),
								mysql_real_escape_string($point['lng']),
								mysql_real_escape_string($point['lat']),
								mysql_real_escape_string($radial),
								$startat,
								$number
								);

		if($formapping) {
			$propertylist = $this->db->get_results($sql);
		} else {
			$ranged = $this->db->get_col($sql);

			$args = array(
				'posts_per_page' => $number,
				'offset' => 0,
				'orderby' => 'post_modified',
				'order' => 'DESC',
				'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
				'post_status' => 'publish',
				'post__in' => $ranged
			);

			$get_properties = new WP_Query;
			$propertylist = $get_properties->query($args);

		}

		if(!empty($propertylist)) {
			return $propertylist;
		} else {
			return false;
		}

	}

	function get_withinrangeandbounds($point, $tright, $bleft, $radial, $unit = 'km', $startat = 0, $number = STAYPRESS_PROPERTY_PER_PAGE, $formapping = false, $justid = false) {

		switch($unit) {
			case 'km':		$distance = 6371; break;
			case 'mile':	$distance = 3959; break;
			default: 		$distance = 6371; break;
		}

		if($formapping && !$justid) {
			$fields = "p.post_id AS ID, p.latitude, p.longitude, p.country, p.town, p.region, posts.post_title, p.reference, posts.post_excerpt, posts.post_name";
		} else {
			$fields = "p.post_id";
		}

		// lat, lng, lat
		$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS {$fields}, ( %d * acos( cos( radians(%f) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(%f) ) + sin( radians(%f) ) * sin( radians( latitude ) ) ) ) AS distance FROM {$this->property} AS p INNER JOIN {$this->db->posts} AS posts ON p.post_id = posts.ID WHERE (latitude <= %f AND longitude <= %f) AND (latitude >= %f AND longitude >= %f) AND status = 'publish' HAVING distance < %f ORDER BY distance LIMIT %d , %d; ",
								$distance,
								mysql_real_escape_string($point['lat']),
								mysql_real_escape_string($point['lng']),
								mysql_real_escape_string($point['lat']),
								mysql_real_escape_string($tright['lat']),
								mysql_real_escape_string($tright['lng']),
								mysql_real_escape_string($bleft['lat']),
								mysql_real_escape_string($bleft['lng']),
								mysql_real_escape_string($radial),
								$startat,
								$number
								);

		if($formapping) {
			$propertylist = $this->db->get_results($sql);
		} else {
			$ranged = $this->db->get_col($sql);

			$args = array(
				'posts_per_page' => $number,
				'offset' => 0,
				'orderby' => 'post_modified',
				'order' => 'DESC',
				'post_type' => STAYPRESS_PROPERTY_POST_TYPE,
				'post_status' => 'publish',
				'post__in' => $ranged
			);

			$get_properties = new WP_Query;
			$propertylist = $get_properties->query($args);

		}

		if(!empty($propertylist)) {
			return $propertylist;
		} else {
			return false;
		}

	}

	// getBoundingBox
	// based on an original hacked out by ben brown <ben@xoxco.com>
	// http://xoxco.com/clickable/php-getboundingbox

	// given a latitude and longitude in degrees (40.123123,-72.234234) and a distance
	// calculates a bounding box with corners $distance away from the point specified.
	// returns $min_lat,$max_lat,$min_lon,$max_lon
	function getBoundingBox($point, $distance, $unit = 'km') {

		switch($unit) {
			case 'km':		$radius = 6371; break;
			case 'mile':	$radius = 3959; break;
			default: 		$radius = 6371; break;
		}

		// bearings
		$due_north = 0;
		$due_south = 180;
		$due_east = 90;
		$due_west = 270;

		// convert latitude and longitude into radians
		$lat_r = deg2rad($point['lat']);
		$lon_r = deg2rad($point['lng']);

		// find the northmost, southmost, eastmost and westmost corners $distance_in_miles away
		// original formula from
		// http://www.movable-type.co.uk/scripts/latlong.html

		$northmost  = asin(sin($lat_r) * cos($distance/$radius) + cos($lat_r) * sin ($distance/$radius) * cos($due_north));
		$southmost  = asin(sin($lat_r) * cos($distance/$radius) + cos($lat_r) * sin ($distance/$radius) * cos($due_south));

		$eastmost = $lon_r + atan2(sin($due_east)*sin($distance/$radius)*cos($lat_r),cos($distance/$radius)-sin($lat_r)*sin($lat_r));
		$westmost = $lon_r + atan2(sin($due_west)*sin($distance/$radius)*cos($lat_r),cos($distance/$radius)-sin($lat_r)*sin($lat_r));


		$northmost = rad2deg($northmost);
		$southmost = rad2deg($southmost);
		$eastmost = rad2deg($eastmost);
		$westmost = rad2deg($westmost);

		// sort the lat and long so that we can use them for a between query
		if ($northmost > $southmost) {
			$lat1 = $southmost;
			$lat2 = $northmost;

		} else {
			$lat1 = $northmost;
			$lat2 = $southmost;
		}


		if ($eastmost > $westmost) {
			$lon1 = $westmost;
			$lon2 = $eastmost;

		} else {
			$lon1 = $eastmost;
			$lon2 = $westmost;
		}

		return array($lat1,$lat2,$lon1,$lon2);
	}

	// Helper functions

	function translate_status($status) {

		$statuslist = $this->get_statuslist();

		$statuslist['trash'] = __('Deleted', 'property');

		return $statuslist[$status];

	}

	function sanitize_int($in) {
		return ( substr(preg_replace('/[^0-9]/', '', strval($in) ), 0, 20) );
	}

	function sanitize_str($in) {
		return mysql_real_escape_string($in);
	}

	function get_statuslist() {
		return get_post_statuses();
	}

	function set_timezone($offset = 0) {
		$this->timeoffset = $offset;
	}

	// Maybe use this version or alter it
	function current_time( $type, $gmt = 0 ) {
		switch ( $type ) {
			case 'mysql':
				return ( $gmt ) ? gmdate( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s', ( time() + ( $this->timeoffset * 3600 ) ) );
				break;
			case 'timestamp':
				return ( $gmt ) ? time() : time() + ( $this->timeoffset * 3600 );
				break;
		}
	}

}

?>