<?php
/**
 * @package   wp-bitly
 * @author    Mark Waterous <mark@watero.us
 * @license   GPL-2.0+
 */

/**
 * What better way to store our api access call endpoints? I'm sure there is one, but this works for me.
 *
 * @since 2.0
 * @param   string  $api_call   Which endpoint do we need?
 * @return  string  Returns an empty string on failure, the full API URL on success
 */
function wpbitly_api( $api_call )
{

    $api_base   = 'https://api-ssl.bitly.com';
    $api_links  = array(
        'shorten'       => '/v3/shorten?access_token=%1$s&longUrl=%2$s',
        'expand'        => '/v3/expand?access_token=%1$s&shortUrl=%2$s',
        'link/clicks'   => '/v3/link/clicks?access_token=%1$s&link=%2$s',
        'link/refer'    => '/v3/link/referring_domains?access_token=%1$s&link=%2$s',
        'user/info'     => '/v3/user/info?access_token=%1$s',
    );

    return isset( $api_links[$api_call] ) ? ( $api_base . $api_links[$api_call] ) : '';

}


/**
 * WP Bit.ly wrapper for wp_remote_get. Why have I been using cURL when WordPress already does this?
 * Thanks to Otto, who while teaching someone else how to do it right unwittingly taught me the right
 * way as well.
 *
 * @since   2.1
 * @param   string  $url    The API endpoint we're contacting
 * @return  bool|array      False on failure, array on success
 */

function wpbitly_get( $url = '' )
{

    // Say $url phonetically. Is it Yer'l or Earl?
    if ( empty( $url ) )
        return false;

    $the = wp_remote_get( $url );

    if ( ! empty( $result ) && '200' == $the['response']['code'] )
        return json_decode( $the['body'], true );

    return false;

}


/**
 * Check our response for validity before proceeding.
 *
 * @since   2.0
 * @param   array   $response   This should be a json_decode()'d array
 * @return  bool
 */
function wpbitly_good_response( $response )
{
    if ( !is_array( $response ) )
        return false;

    return ( isset( $response['status_code'] ) && $response['status_code'] == 200 ) ? true : false;
}


/**
 * Generates the shortlink for the post specified by $post_id.
 *
 * @since   0.1
 * @param   int $post_id    The post ID we need a shortlink for.
 * @return  bool|string     Returns the shortlink on success.
 */

function wpbitly_generate_shortlink( $post_id )
{

    $wpbitly = wpbitly();

    // Don't waste cycles every time WordPress autosaves, or if we're missing a token.
    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || !$wpbitly->options['authorized'] )
        return false;

    { // Do we need to generate a shortlink for this post yet?
        if ( $parent = wp_is_post_revision( $post_id ) )
            $post_id = $parent;

        $post_status = get_post_status( $post_id );

        if ( !in_array( $post_status, array( 'publish', 'future', 'private') ) )
            return false;
    }

    // Link to be generated
    $permalink = get_permalink( $post_id );
    $shortlink = get_post_meta( $post_id, '_wpbitly', true );
$shortlink = '';
    if ( !empty( $shortlink ) )
    { // We shouldn't get here if there's already a shortlink, but if we did, let's verify it.
        $url = sprintf( wpbitly_api( 'expand' ), $wpbitly->options['oauth_token'], $shortlink );
        $response = wpbitly_get( $url );

        if ( wpbitly_good_response( $response ) && $permalink == $response['data']['expand'][0]['long_url'] )
            return $shortlink;
    }

    // Get Shorty.
    $url = sprintf( wpbitly_api( 'shorten' ), $wpbitly->options['oauth_token'], urlencode( $permalink ) ); md( $url );
    $response = wpbitly_get( $url );

    if ( wpbitly_good_response( $response ) )
    { // We caught something!!
        $shortlink = $response['data']['url'];
        update_post_meta( $post_id, '_wpbitly', $shortlink );
    }

    return $shortlink;

}


/**
 * Short circuits the `pre_get_shortlink` filter.
 *
 * @since   0.1
 * @param   bool    $shortlink  False is passed in by default.
 * @param   int     $post_id    Current $post->ID, or 0 for the current post.
 * @return  bool|string False on failure, shortlink on success.
 */

function wpbitly_get_shortlink( $shortlink, $post_id = '' )
{

    // Needs post id.
    if ( empty( $post_id ) || $post_id === 0 )
    {
        global $post;
        $post_id = ( isset( $post ) ? $post->ID : '' );
    }

    // No $post_id?
    if ( empty( $post_id ) )
        return $shortlink;


    { // We have a $post_id, lets get the shortlink.
        //$shortlink = get_post_meta( $post_id, '_wpbitly', true );

        //if ( empty( $shortlink ) )
            $shortlink = wpbitly_generate_shortlink( $post_id );
    }

    return $shortlink;

}


/**
 * This is not included in the wpbitly class on purpose, on the chance that someone somewhere
 * might decide they would like to use <?php echo wpbitly_shortcode; ?>
 *
 * @since   0.1
 * @param   array   $atts   I suppose we could let this accept the post->ID? Maybe later.
 */
function wpbitly_shortcode( $atts = array() )
{
    global $post;

    $defaults = array(
        'text'      => '',
        'title'     => '',
        'before'    => '',
        'after'     => '',
    );

    extract( shortcode_atts( $defaults, $atts ) );

    return the_shortlink( $text, $title, $before, $after );
}

add_action( 'wp', 'wpbitly_shortcode' );