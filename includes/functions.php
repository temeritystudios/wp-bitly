<?php
/**
 * @package   wp-bitly
 * @author    Temerity Studios <info@temeritystudios.com>
 * @author    Chip Bennett
 * @license   GPL-2.0+
 * @link      http://wordpress.org/plugins/wp-bitly
 */


/**
 * Write to a WP Bitly debug log file
 *
 * @since 2.2.3
 * @param   string $towrite The data we want to add to the logfile
 */
function wpbitly_debug_log($towrite, $message, $bypass = true)
{

    $wpbitly = wpbitly();

    if (!$wpbitly->getOption('debug') || !$bypass) {
        return;
    }


    $log = fopen(WPBITLY_LOG, 'a');

    fwrite($log, '# [ ' . date('F j, Y, g:i a') . " ]\n");
    fwrite($log, '# [ ' . $message . " ]\n\n");
    fwrite($log, (is_array($towrite) ? print_r($towrite, true) : var_export($towrite, 1)));
    fwrite($log, "\n\n\n");

    fclose($log);

}


/**
 * Retrieve the requested API endpoint.
 *
 * @since 2.0
 * @param   string $api_call Which endpoint do we need?
 * @return  string           Returns the URL for our requested API endpoint
 */
function wpbitly_api($api_call)
{

    $api_links = [
        'shorten' => 'shorten?access_token=%1$s&longUrl=%2$s',
        'expand' => 'expand?access_token=%1$s&shortUrl=%2$s',
        'link/clicks' => 'link/clicks?access_token=%1$s&link=%2$s',
        'link/refer' => 'link/referring_domains?access_token=%1$s&link=%2$s',
        'user/info' => 'user/info?access_token=%1$s',
    ];

    if (!array_key_exists($api_call, $api_links)) {
        trigger_error(__('WP Bitly Error: No such API endpoint.', 'wp-bitly'));
    }

    return WPBITLY_BITLY_API . $api_links[$api_call];
}


/**
 * WP Bitly wrapper for wp_remote_get that verifies a successful response.
 *
 * @since   2.1
 * @param   string $url The API endpoint we're contacting
 * @return  bool|array      False on failure, array on success
 */

function wpbitly_get($url)
{

    $the = wp_remote_get($url, ['timeout' => '30',]);

    if (is_array($the) && '200' == $the['response']['code']) {
        return json_decode($the['body'], true);
    }
}


/**
 * Generates the shortlink for the post specified by $post_id.
 *
 * @since   0.1
 * @param   int $post_id The post ID we need a shortlink for.
 * @return  bool|string          Returns the shortlink on success.
 */

function wpbitly_generate_shortlink($post_id)
{

    $wpbitly = wpbitly();

    // Avoid creating shortlinks during an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // or for revisions
    if (wp_is_post_revision($post_id)) {
        return;
    }

    // Token hasn't been verified, bail
    if (!$wpbitly->isAuthorized()) {
        return;
    }


    // Verify this is a post we want to generate short links for
    if (!in_array(get_post_type($post_id), $wpbitly->getOption('post_types')) ||
        !in_array(get_post_status($post_id), ['publish', 'future', 'private'])) {
        return;
    }


    // We made it this far? Let's get a shortlink
    $permalink = get_permalink($post_id);
    $shortlink = get_post_meta($post_id, '_wpbitly', true);
    $token = $wpbitly->getOption('oauth_token');

    if (!empty($shortlink)) {
        $url = sprintf(wpbitly_api('expand'), $token, $shortlink);
        $response = wpbitly_get($url);

        wpbitly_debug_log($response, '/expand/');

        if ($permalink == $response['data']['expand'][0]['long_url']) {
            return $shortlink;
        }
    }

    $url = sprintf(wpbitly_api('shorten'), $token, urlencode($permalink));
    $response = wpbitly_get($url);

    wpbitly_debug_log($response, '/shorten/');

    if (is_array($response)) {
        $shortlink = $response['data']['url'];
        update_post_meta($post_id, '_wpbitly', $shortlink);
    }

    return $shortlink;
}


/**
 * Short circuits the `pre_get_shortlink` filter.
 *
 * @since   0.1
 * @param   bool $shortlink False is passed in by default.
 * @param   int $post_id Current $post->ID, or 0 for the current post.
 * @return  string            A shortlink
 */
function wpbitly_get_shortlink($original, $post_id)
{

    $wpbitly = wpbitly();

    // Verify this is a post we want to generate short links for
    if (!in_array(get_post_type($post_id), $wpbitly->getOption('post_types'))) {
        return $original;
    }

    if (0 == $post_id) {
        $post = get_post();
        $post_id = $post->ID;
    }

    $shortlink = get_post_meta($post_id, '_wpbitly', true);

    if (!$shortlink) {
        $shortlink = wpbitly_generate_shortlink($post_id);
    }

    return ($shortlink) ? $shortlink : $original;
}


/**
 * This is our shortcode handler, which could also be called directly.
 *
 * @since   0.1
 * @param   array $atts Default shortcode attributes.
 */
function wpbitly_shortlink($atts = [])
{

    $post = get_post();

    $defaults = [
        'text' => '',
        'title' => '',
        'before' => '',
        'after' => '',
        'post_id' => $post->ID, // Use the current post by default, or pass an ID
    ];

    extract(shortcode_atts($defaults, $atts));

    $permalink = get_permalink($post_id);
    $shortlink = wpbitly_get_shortlink($permalink, $post_id);

    if (empty($text)) {
        $text = $shortlink;
    }

    if (empty($title)) {
        $title = the_title_attribute(['echo' => false]);
    }

    $output = '';

    if (!empty($shortlink)) {
        $output = apply_filters('the_shortlink', '<a rel="shortlink" href="' . esc_url($shortlink) . '" title="' . $title . '">' . $text . '</a>', $shortlink, $text, $title);
        $output = $before . $output . $after;
    }

    return $output;
}
