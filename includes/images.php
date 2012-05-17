<?php
function has_post_mainimage( $post_id = NULL ) {
	global $id;
	$post_id = ( NULL === $post_id ) ? $id : $post_id;
	return !! get_post_mainimage_id( $post_id );
}

/**
 * Retrieve Post Thumbnail ID.
 *
 * @since 2.9.0
 *
 * @param int $post_id Optional. Post ID.
 * @return int
 */
function get_post_mainimage_id( $post_id = NULL ) {
	global $id;
	$post_id = ( NULL === $post_id ) ? $id : $post_id;
	return get_post_meta( $post_id, '_mainimage_id', true );
}

/**
 * Display Post Thumbnail.
 *
 * @since 2.9.0
 *
 * @param int $size Optional. Image size.  Defaults to 'post-thumbnail', which theme sets using set_post_thumbnail_size( $width, $height, $crop_flag );.
 * @param string|array $attr Optional. Query string or array of attributes.
 */
function the_post_mainimage( $size = 'post-medium', $attr = '' ) {
	echo get_the_post_mainimage( NULL, $size, $attr );
}

/**
 * Retrieve Post Thumbnail.
 *
 * @since 2.9.0
 *
 * @param int $post_id Optional. Post ID.
 * @param string $size Optional. Image size.  Defaults to 'thumbnail'.
 * @param string|array $attr Optional. Query string or array of attributes.
  */
function get_the_post_mainimage( $post_id = NULL, $size = 'post-medium', $attr = '' ) {
	global $id;
	$post_id = ( NULL === $post_id ) ? $id : $post_id;
	$post_mainimage_id = get_post_mainimage_id( $post_id );
	$size = apply_filters( 'post_medium_size', $size );
	if ( $post_mainimage_id ) {
		do_action( 'begin_fetch_post_mainimage_html', $post_id, $post_mainimage_id, $size ); // for "Just In Time" filtering of all of wp_get_attachment_image()'s filters
		$html = wp_get_attachment_image( $post_mainimage_id, $size, false, $attr );
		do_action( 'end_fetch_post_mainimage_html', $post_id, $post_mainimage_id, $size );
	} else {
		$html = '';
	}
	return apply_filters( 'post_mainimage_html', $html, $post_id, $post_mainimage_id, $size, $attr );
}

?>