<?php
/**
 * @package   wp-bitly
 * @author    Mark Waterous <mark@watero.us>
 * @license   GPL-2.0+
 * @link      http://wordpress.org/plugins/wp-bitly
 * @copyright 2014 Mark Waterous
 */

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	die;


function wpbitly_uninstall()
{
    // Delete associated options
    delete_option( 'wpbitly-options' );

    // Grab all posts with an attached shortlink
    $posts = get_posts( 'numberposts=-1&post_type=any&meta_key=_wpbitly' );

    // And remove our meta information from them
    // @TODO benchmark this against deleting it with a quick SQL query. Probably quicker, any conflict?
    foreach ( $posts as $post )
        delete_post_meta( $post->ID, '_wpbitly' );

}

wpbitly_uninstall();
