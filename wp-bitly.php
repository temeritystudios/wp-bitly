<?php /*
Plugin Name: WP Bit.ly
Plugin URI: http://mark.watero.us/wordpress-plugins/wp-bitly/
Description: WP Bit.ly uses the Bit.ly API to generate short links for all your articles and pages. Visitors can use the link to email, share, or bookmark your pages quickly and easily.
Version: 0.1.0
Author: Mark Waterous
Author URI: http://mark.watero.us/

Copyright 2010 Mark Waterous (mark@watero.us)

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

define( 'WPBITLY_VERSION', '0.1.0' );

register_activation_hook( __FILE__, 'wpbitly_activate' );
register_deactivation_hook( __FILE__, 'wpbitly_deactivate' );

require_once( 'wp-bitly-options.php' );
require_once( 'wp-bitly-views.php' );

	// Load our controller class... it's helpful!
	$wpbitly = new wpbitly_options;


// If we're competing with WordPress.com stats... chances are people are already using wp.me
// but we'll remove the competitive headers just in case.
if ( function_exists( 'wpme_shortlink_header' ) ) {
	remove_action( 'wp',      'wpme_shortlink_header' );
	remove_action( 'wp_head', 'wpme_shortlink_wp_head' );
}

// And add our own
add_action( 'wp',      'wpbitly_shortlink_header' );
add_action( 'wp_head', 'wpbitly_shortlink_wp_head' );

// Automatic generation is disabled if the API information is invalid
if ( ! get_option( 'wpbitly_invalid' ) )
	add_action( 'save_post', 'wpbitly_generate_shortlink' );


// Settings menu on plugins page.
add_filter( 'plugin_action_links', 'wpbitly_filter_plugin_actions', 10, 2 );

// One guess?
add_shortcode( 'wpbitly', 'wpbitly_shortcode' );



/**
 * Activation routine sets up our initial options and versioning information.
 * It would also generate links for all posts and pages, except that we have
 * no way to accept installation/configuration information on activate! Yet!
 */

function wpbitly_activate() {

	update_option( 'wpbitly_version', WPBITLY_VERSION );

	$options['bitly_username'] = '';
	$options['bitly_api_key']  = '';
	$options['post_types']     = 'any';

	update_option( 'wpbitly_options', $options );

}


/**
 * The deactivation routine deletes all options related to WP Bit.ly
 * This would be better off in the uninstall hook, but nothing
 * here is mission critical or hard to reactivate.
 */

function wpbitly_deactivate() {

	// Delete associated options
	delete_option( 'wpbitly_version' );
	delete_option( 'wpbitly_options' );
	delete_option( 'wpbitly_invalid' );

	// Grab all posts
	$posts = get_posts( 'numberposts=-1&post_type=any' );

	// And remove our meta information from them
	foreach ( $posts as $post )
		delete_post_meta( $post->ID, '_wpbitly' );

}


/**
 * Borrowed from the Sociable plugin, this adds a 'Settings' option to the
 * entry on the WP Plugins page.
 *
 * @param $links array  The array of links displayed by the plugins page
 * @param $file  string The current plugin being filtered.
 */

function wpbitly_filter_plugin_actions( $links, $file ) {
	static $wpbitly_plugin;

	if ( ! isset( $wpbitly_plugin ) )
		$wpbitly_plugin = plugin_basename( __FILE__ );
	
	if ( $file == $wpbitly_plugin ) {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=wpbitly' ) . '">' . __( 'Settings', 'wpbitly' ) . '</a>';
		array_unshift( $links, $settings_link );
	}

	return $links;

}


/**
 * Generates the shortlink for any post specified by $pid. This parameter
 * should be passed automatically by any behind the scenes operations such
 * as mass generation or wp_insert_post
 *
 * @param $pid int  The WordPress post ID to be used.
 * @param $ret bool True if the link should be returned, false to update silently
 *
 * @todo If a link is found for a specific post, we should check it against Bit.ly's API to ensure it's still valid. If not, regenerate.
 */

function wpbitly_generate_shortlink( $pid, $ret = TRUE ) {
	global $wpbitly;


	if ( $parent = wp_is_post_revision( $pid ) )
		$pid = $parent;


	// Link to be generated
	$permalink = get_permalink( $pid );
	$wpbitly_link = get_post_meta( $pid, '_wpbitly', TRUE );


	if ( empty( $wpbitly->options['bitly_username'] ) || empty( $wpbitly->options['bitly_api_key'] ) || get_option( 'wpbitly_invalid' ) )
		return;

	if ( $wpbitly_link != FALSE ) {
		$url = sprintf( $wpbitly->expand, $wpbitly_link, $wpbitly->options['bitly_username'], $wpbitly->options['bitly_api_key'] );
		$bitly_response = wpbitly_curl( $url );

		if ( is_array( $bitly_response ) && $bitly_response['status_code'] == 200 && $bitly_response['data']['expand'][0]['long_url'] == $permalink )
			return;

		delete_post_meta( $pid, '_wpbitly' );
	}

	// Submit to Bit.ly API and look for a response
	$url = sprintf( $wpbitly->url['shorten'], $wpbitly->options['bitly_username'], $wpbitly->options['bitly_api_key'], urlencode( $permalink ) );
	$bitly_response = wpbitly_curl( $url );

	// Success?
	if ( is_array( $bitly_response ) && $bitly_response['status_code'] == 200 )
		update_post_meta( $pid, '_wpbitly', $bitly_response['data']['url'] );

}


/**
 * This function is used to return the Bit.ly shortlink for a specific post.
 * If $pid is not supplied, attempt to retrieve it from the global namespace.
 *
 * @param $pid int The WordPress post ID to be used.
 */

function wpbitly_get_shortlink( $pid ) {
	global $post;

	if ( empty( $pid ) && ! $pid = $post->ID )
		return FALSE;

	return get_post_meta( $pid, '_wpbitly', TRUE );

}


/**
 * Used internally by the shortcode function, this can be used directly by
 * a template to display the short link.
 *
 * @param $text string The text to display as the content of the link. Defaults to the link itself.
 * @param $echo bool   Whether to echo the result or return it. Defaults to true (echo).
 * @param $pid  int    The WordPress post ID to be used. Defaults to $post->ID if it can.
 */
 
function wpbitly_print( $text = '', $echo = TRUE, $pid = '' ) {
	global $post;

	// Attempt to get the post ID
	if ( empty( $pid ) && ! $pid = $post->ID )
		return;

	$wpbitly_link = wpbitly_get_shortlink( $pid );

	if ( empty( $text ) )
		$text = $wpbitly_link;

	$wpbitly_print = '<a href="' . $wpbitly_link . '" rel="shortlink" class="wpbitly shortlink">' . $text . '</a>';

	if ( $echo !== TRUE )
		return $wpbitly_print;

	echo $wpbitly_print;
	
}


/**
 * Shortcode for WP Bit.ly uses wpbitly_print() and accepts the same
 * arguments with the exception of echo which it has to do by default.
 */

function wpbitly_shortcode( $atts ) {
	global $post;

	extract( shortcode_atts( array( 'text' => '', 'pid' => $post->ID ), $atts ) );

	return wpbitly_print( $text, FALSE, $pid );

}


/**
 * Add the specification to the HTTP headers for our shortlink.
 */

function wpbitly_shortlink_header() {
	global $wp_query;

	if ( headers_sent() || ! is_singular() || ! is_front_page() )
		return;

	if ( ! $wpbitly_link = wpbitly_get_shortlink( $wp_query->get_queried_object_id() ) )
		return;

	header( 'Link: <' . $wpbitly_link . '>; rel=shortlink' );

}


/**
 * Add the specification to our HTML head for our shortlink.
 */

function wpbitly_shortlink_wp_head() {
	global $wp_query;

	if ( ! is_singular() || ! is_front_page() )
		return;

	if ( ! $wpbitly_link = wpbitly_get_shortlink( $wp_query->get_queried_object_id() ) )
		return;

	echo '<link rel="shortlink" href="' . $wpbitly_link . "\"/>\n";

}


/**
 * WP Bit.ly wrapper for cURL - if cURL is not installed, attempts to use
 * file_get_contents instead.
 */

function wpbitly_curl( $url ) {
	global $wpbitly;

	if ( ! isset( $url ) )
		return FALSE;

	if ( function_exists( 'curl_init' ) ) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url );
		$result = curl_exec($ch);
		curl_close($ch);

	}
	else {
		$result = file_get_contents( $url );
	}

	if ( ! empty( $result ) )
		return json_decode( $result, TRUE );

	return FALSE;

}
