<?php
/**
 * @package   wp-bitly
 * @author    Mark Waterous <mark@watero.us
 * @license   GPL-2.0+
 */


function wpbitly_api()
{
    return array(
        'base'      => 'https://api-ssl.bitly.com',
        'shorten'   => '/v3/shorten?access_token=%1$s&longUrl=%2$s',
        'expand'    => '/v3/expand?access_token=%1$s&shortUrl=%2$s',
        'link'      => array(
            'clicks'    => '/v3/link/clicks?access_token=%1$s&link=%2$s',
            'refer'     => '/v3/link/referring_domains?access_token=%1$s&link=%2$s',
        ),
        'user'      => array(
            'info'  => '/v3/user/info?access_token=%1$s',
        ),
    );
}


/**
 * WP Bit.ly wrapper for cURL - this method relies on the ability to use cURL
 * or file_get_contents. If cURL is not available and allow_url_fopen is set
 * to false this method will fail and the plugin will not be able to generate
 * shortlinks.
 */

function wpbitly_curl( $url )
{

    if ( ! isset( $url ) )
        return false;

    if ( function_exists( 'curl_init' ) )
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url );
        $result = curl_exec($ch);
        curl_close($ch);

    }
    else
    {
        $result = file_get_contents( $url );
    }

    if ( ! empty( $result ) )
        return json_decode( $result, true );

    return false;

}


function wpbitly_good_response( $response )
{
    if ( !is_array( $response ) )
        return false;

    return ( isset( $response['status_code'] ) && $response['status_code'] == 200 ) ? true : false;
}

/**
 * Generates the shortlink for the post specified by $post_id.
 */

function wpbitly_generate_shortlink( $post_id )
{

    $wpbitly = wp_bitly();
    $bapi = wpbitly_api();

    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || empty( $wpbitly->options['oauth_token'] ) )
        return false;

    // Do we need to generate a shortlink for this post? (save_post is fired when revisions, auto-drafts, et al are saved)
    if ( $parent = wp_is_post_revision( $post_id ) )
        $post_id = $parent;

    $post = get_post( $post_id );

    if ( 'publish' != $post->post_status && 'future' != $post->post_status )
        return false;


    // Link to be generated
    $permalink = get_permalink( $post_id );
    $shortlink = get_post_meta( $post_id, '_wpbitly', true );


    if ( !empty( $shortlink ) )
    {
        $url = sprintf( $bapi['base'] . $bapi['expand'], $wpbitly->options['oauth_token'], $shortlink );
        $response = wpbitly_curl( $url );

        // If we have a shortlink for this post already, we've sent it to the Bit.ly expand API to verify that it will actually forward to this posts permalink
        if ( wpbitly_good_response( $response ) && $response['data']['expand'][0]['long_url'] == $permalink )
            return $shortlink;

    }

    // Submit to Bit.ly API and look for a response
    $url = sprintf( $bapi['base'] . $bapi['shorten'], $wpbitly->options['oauth_token'], urlencode( $permalink ) );
    $response = wpbitly_curl( $url );

    // Success?
    if ( wpbitly_good_response( $response ) )
    {
        $shortlink = $response['data']['url'];
        update_post_meta( $post_id, '_wpbitly', $shortlink );
    }

    return $shortlink;

}


/**
 * Return the wpbitly_get_shortlink method to the built in WordPress pre_get_shortlink
 * filter for internal use.
 */

function wpbitly_get_shortlink( $shortlink, $id = '' )
{

    // Look for the post ID passed by wp_get_shortlink() first
    if ( empty( $id ) )
    {
        global $post;
        $id = ( isset( $post ) ? $post->ID : null );
    }

    // Fall back in case we still don't have a post ID
    if ( empty( $id ) )
        return $shortlink;


    $shortlink = get_post_meta( $id, '_wpbitly', true );

    if ( empty( $shortlink ) )
        $shortlink = wpbitly_generate_shortlink( $id );

    return $shortlink;

}


function wpbitly_shortcode( $atts )
{
    global $post;

    $defaults = array(
        'text' => '',
        'title' => '',
        'before' => '',
        'after'  => '',
    );

    extract( shortcode_atts( $defaults, $atts ) );

    return the_shortlink( $text, $title, $before, $after );

}

