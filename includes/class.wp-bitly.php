<?php
/**
 * WP Bit.ly
 *
 * Some code in the following class is liberally borrowed from the WordPress Plugin Boilerplate,
 * which can be found at http://
 *
 * @package   wp-bitly
 * @author    Mark Waterous <mark@watero.us
 * @license   GPL-2.0+
 */


/**
 * The primary controller class for everything wonderful that WP Bit.ly does.
 * We're not sure entirely what that means yet; if you figure it out, please
 * let us know and we'll say something snazzy about it here.
 *
 * @TODO: Update the class phpdoc description to say something snazzy.
 *
 * @package wp-bitly
 * @author  Mark Waterous <mark@watero.us
 */
class wp_bitly
{

    public $url = array(
        'shorten'  => 'http://api.bit.ly/v3/shorten?login=%s&apiKey=%s&uri=%s&format=json',
        'expand'   => 'http://api.bit.ly/v3/expand?shortUrl=%s&login=%s&apiKey=%s&format=json',
        'validate' => 'http://api.bit.ly/v3/validate?x_login=%s&x_apiKey=%s&login=wpbitly&apiKey=%s&format=json',
        'clicks'   => 'http://api.bit.ly/v3/clicks?shortUrl=%s&login=%s&apiKey=%s&format=json',
    );

    public function __construct()
    {

        // Load plugin text domain
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

        // Activate plugin when new blog is added
        add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

        // Automatic generation is disabled if the API information is invalid
        if ( ! $wpbitly_options['wpbitly_invalid'] )
            add_action( 'save_post', 'wpbitly_generate_shortlink', 10, 1 );

        // Settings menu on plugins page.
        add_filter( 'plugin_action_links', 'wpbitly_filter_plugin_actions', 10, 2 );

        // One guess?
        add_shortcode( 'wpbitly', array( $this, 'shortcode' ) );
        add_filter( 'get_shortlink', array( $this, 'get_shortlink' ), 10, 3 );

    }




    /**
     * Fired for each blog when the plugin is deactivated.
     *
     * @since    2.0.0
     */
    private static function single_uninstall()
    {
        // Delete associated options
        delete_option( 'wpbitly_options' );

        // Grab all posts
        $posts = get_posts( 'numberposts=-1&post_type=any&meta_key=_wpbitly' );

        // And remove our meta information from them
        foreach ( $posts as $post )
            delete_post_meta( $post->ID, '_wpbitly' );

    }


    /**
     * Load the plugin text domain for translation.
     *
     * @since    2.0.0
     */
    public function load_plugin_textdomain()
    {
        $domain = 'wp-bitly';
        $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

        load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
    }


    /**
     * Add a link to the settings directly from the Plugins page.
     *
     * @param $links array  The array of links displayed by the plugins page
     * @param $file  string The current plugin being filtered.
     */

    public function filter_plugin_actions( $links, $file )
    {

        if ( $file == plugin_basename( __FILE__ ) )
        {
            $settings_link = '<a href="' . admin_url( 'options-writing.php' ) . '">' . __( 'Settings', 'wp-bitly' ) . '</a>';
            array_unshift( $links, $settings_link );
        }

        return $links;

    }


    /**
     * WP Bit.ly wrapper for cURL - this method relies on the ability to use cURL
     * or file_get_contents. If cURL is not available and allow_url_fopen is set
     * to false this method will fail and the plugin will not be able to generate
     * shortlinks.
     */

    private function _curl( $url )
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


    /**
     * Generates the shortlink for the post specified by $post_id.
     */

    private function _generate_shortlink( $post_id )
    {
        global $wpbitly;

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


        if ( $shortlink != false )
        {
            $url = sprintf( $this->url['expand'], $shortlink, $wpbitly->options['oauth_token'] );
            $bitly_response = $this->_curl( $url );

            // If we have a shortlink for this post already, we've sent it to the Bit.ly expand API to verify that it will actually forward to this posts permalink
            if ( is_array( $bitly_response ) && $bitly_response['status_code'] == 200 && $bitly_response['data']['expand'][0]['long_url'] == $permalink )
                return $shortlink;

        }

        // Submit to Bit.ly API and look for a response
        $url = sprintf( $this->url['shorten'], $wpbitly->options['oauth_token'], urlencode( $permalink ) );
        $bitly_response = $this->_curl( $url );

        // Success?
        if ( is_array( $bitly_response ) && $bitly_response['status_code'] == 200 )
        {
            $shortlink = $bitly_response['data']['url'];
            update_post_meta( $post_id, '_wpbitly', $shortlink );
        }

        return $shortlink;

    }


    /**
     * Return the wpbitly_get_shortlink method to the built in WordPress pre_get_shortlink
     * filter for internal use.
     */

    public function get_shortlink( $shortlink, $id )
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

        if ( $shortlink == false )
            $shortlink = $this->_generate_shortlink( $id );

        return $shortlink;

    }


    function shortcode( $atts )
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


}
