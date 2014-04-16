<?php
/**
 * WP Bit.ly Administration
 *
 * @package     wp-bitly
 * @subpackage  admin
 * @author      Mark Waterous <mark@watero.us
 * @license     GPL-2.0+\
 * @since       2.0
 */

/**
 * Class wpbitly_admin
 * This handles everything we do on the dashboard side.
 *
 * @since 2.0
 */
class wpbitly_admin
{

    /**
     * @var $_instance An instance of ones own instance
     */
    protected static $_instance = null;


    /**
     * This creates and returns a single instance of wpbitly_admin.
     *
     * @since   2.0
     * @static
     * @uses    _wpbitly::action_filters() To set up any necessary WordPress hooks.
     * @return  wpbitly_admin
     */
    public static function get_in()
    {

        if ( !isset( self::$_instance ) && !( self::$_instance instanceof wpbitly_admin ) )
        {
            self::$_instance = new self;
            self::$_instance->action_filters();
        }

        return self::$_instance;
    }


    /**
     * Hook any necessary WordPress actions or filters that we'll be needing for the admin.
     *
     * @since   2.0
     * @uses    wpbitly()
     */
    public function action_filters()
    {

        $wpbitly = wpbitly();

        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Display a notice on our plugins page giving people a nudge in the right direction.
        if ( empty( $wpbitly->options['oauth_token'] ) )
            add_action( 'admin_notices', array( $this, 'display_notice' ) );

        // Initialize our meta boxes for post types that are or can generate shortlinks with bit.ly
        if  ( array_key_exists( 'post_types', $wpbitly->options ) && is_array( $wpbitly->options['post_types'] ) )
        {
            foreach ( $wpbitly->options['post_types'] as $post_type )
                add_action( 'add_meta_boxes_' . $post_type, array( $this, 'add_metaboxes_yo' ) );
        }

    }


    /**
     * Display a simple and unobtrusive notice on the plugins page after activation (and
     * up until they add their oauth_token).
     *
     * @since   2.0
     */
    public function display_notice()
    {

        $screen = get_current_screen();

        // If we're not on the plugins page, let's just go!
        if ( $screen->base != 'plugins' )
            return;

        $prologue = __( 'WP Bit.Ly is almost ready!', 'wp-bitly' );
        $link = '<a href="options-writing.php">' . __( 'settings page', 'wp-bitly' ) . '</a>';
        $epilogue = sprintf( __( 'Please visit the %s to configure WP Bit.ly', 'wp-bitly' ), $link );

        $message = apply_filters( 'wpbitly_setup_notice', '<div id="message" class="updated"><p>' . $prologue . ' ' . $epilogue . '</p></div>' );

        echo $message;

    }


    /**
     * Add our options array to the WordPress whitelist, append them to the existing Writing
     * options page, and handle all the callbacks.
     *
     * @since   2.0
     * @uses    _f_settings_section()          Internal callback for add_settings_section()
     * @uses    _f_settings_field_oauth()      Internal callback for add_settings_field()
     * @uses    _f_settings_field_post_types() Internal callback for add_settings_field()
     */
    public function register_settings()
    {

        register_setting( 'writing', 'wpbitly-options', array( $this, 'validate_settings' ) );

        add_settings_section( 'wpbitly_settings', 'WP Bit.ly Options', '_f_settings_section', 'writing' );
        /**
         * @ignore
         */
        function _f_settings_section() {
            echo apply_filters( 'wpbitly_settings_section', '<p>'.__( 'You will need a Bit.ly account to use this plugin. Click the link below for your OAuth Token, and if necessary create a new account.', 'wp-bitly' ).'</p>' );
        }


        add_settings_field( 'oauth_token', '<label for="oauth_token">' . __( 'Bit.ly OAuth Token' , 'wpbitly' ) . '</label>', '_f_settings_field_oauth', 'writing', 'wpbitly_settings' );
        /**
         * @ignore
         */
        function _f_settings_field_oauth()
        {

            $wpbitly = wpbitly();

            $url = apply_filters( 'wpbitly_oauth_url', 'https://bitly.com/a/wordpress_oauth_app' );

            $auth_css = $wpbitly->options['authorized'] ? '' : ' style="border-color: #c00; background-color: #ffecec;" ';
            $output = '<input type="text" size="80" name="wpbitly-options[oauth_token]" value="' . esc_attr( $wpbitly->options['oauth_token'] ) . '"' . $auth_css . ' />'
                    . '<p>' . __( 'Please provide your', 'wp-bitly' ) . ' <a href="'.$url.'" target="_blank" style="text-decoration: none;"> ' . __( 'OAuth Token', 'wp-bitly' ) . '</a></p>';

            echo $output;

        }


        add_settings_field( 'post_types', '<label for="post_types">' . __( 'Post Types' , 'wp-bitly' ) . '</label>', '_f_settings_field_post_types', 'writing', 'wpbitly_settings' );
        /**
         * @ignore
         */
        function _f_settings_field_post_types()
        {

            $wpbitly = wpbitly();

            $post_types = apply_filters( 'wpbitly_allowed_post_types', get_post_types( array( 'public' => true ) ) );
            $output = '';

            foreach ( $post_types as $label )
            {
                $output .= '<label for "' . $label . '>'
                    . '<input type="checkbox" name="wpbitly-options[post_types][]" value="' . $label . '" ' . checked( in_array( $label, $wpbitly->options['post_types'] ), true, false ) . '>'
                    . '<span>' . $label . '</span><br />';
            }

            $output .= '<p>' . __( 'Check each post type you want to generate short links for.', 'wp-bitly' ) . '</p>';

            echo $output;

        }

    }


    /**
     * Validate user settings. This will also authorize their OAuth token if it has
     * changed.
     *
     * @since   2.0
     * @uses    wpbitly()
     * @param   array   $input  WordPress sanitized data array
     * @return  array           WP Bit.ly sanitized data
     */
    public function validate_settings( $input )
    {

        $wpbitly = wpbitly();

        // Validate the OAuth token, but only if it's necessary.
        if ( $input['oauth_token'] != $wpbitly->options['oauth_token'] )
        { // Verify the provided OAuth Token
            $input['oauth_token'] = wp_filter_nohtml_kses( $input['oauth_token'] );

            $url = sprintf( wpbitly_api( 'user/info' ), $input['oauth_token'] );
            $response = wpbitly_get( $url );

            $input['authorized'] = ( isset( $response['data']['member_since'] ) ) ? true : false;

        }

        // Nothing checked? Return an array.
        if ( !isset( $input['post_types'] ) )
        {
            $input['post_types'] = array();
        }
        else
        {
            // Otherwise make sure we're seeing valid post types.
            $post_types = apply_filters( 'wpbitly_allowed_post_types', get_post_types( array( 'public' => true ) ) );

            foreach ( $input['post_types'] as $key => $pt )
            {
                if ( ! in_array( $pt, $post_types ) )
                    unset( $input['post_types'][$key] );
            }

        }

        return $input;

    }


    /**
     * Add a fun little statistics metabox to any posts/pages that WP Bit.ly
     * generates a link for. There's potential here to include more information.
     *
     * @since   2.0
     * @TODO    Should the user can turn this on or off? You heard me.
     * @param   object  $post   The post object passed by WordPress
     */
    public function add_metaboxes_yo( $post )
    {

        $shortlink = wp_get_shortlink();

        // No shortlink?!
        if ( empty( $shortlink ) )
            return;

        add_meta_box( 'wpbitly-meta', 'WP Bit.ly', array( $this, 'display_metabox' ), $post->post_type, 'side', 'default', array( $shortlink ) );

    }


    /**
     * Handles the display of the metabox. It's big enough to warrant it's own method.
     *
     * @since   2.0
     * @uses    wpbitly()
     * @param   object  $post   WordPress passed $post object
     * @param   array   $args   Passed by our call to add_meta_box(), just the $shortlink in this case.
     */
    public function display_metabox( $post, $args )
    {

        $wpbitly = wpbitly();
        $shortlink = $args['args'][0];

        { // Look for a clicks response
            $url = sprintf( wpbitly_api( 'link/clicks' ), $wpbitly->options['oauth_token'], $shortlink );
            $response = wpbitly_get( $url );

            if (  is_array( $response ) )
                $clicks = $response['data']['link_clicks'];
        }

        { // Look for referring domains metadata
            $url = sprintf( wpbitly_api( 'link/refer' ), $wpbitly->options['oauth_token'], $shortlink );
            $response = wpbitly_get( $url );

            if ( is_array( $response ) )
                $refer = $response['data']['referring_domains'];
        }


        echo '<label class="screen-reader-text" for="new-tag-post_tag">' . __( 'Bit.ly Statistics', 'wp-bitly' ) . '</label>';

        if ( isset( $clicks ) && isset( $refer ) )
        {

            echo '<p>' . __( 'Global click through:', 'wp-bitly' ) . ' <strong>' . $clicks . '</strong></p>';

            if ( !empty( $refer ) )
            {
                echo '<h4 style="padding-bottom: 3px; border-bottom: 4px solid #eee;">' . __( 'Your link was shared on', 'wp-bitly' ) . '</h4>';
                foreach ( $refer as $domain )
                {
                    if ( isset( $domain['url'] ) )
                        printf( '<a href="%1$s" target="_blank" title="%2$s">%2$s</a> (%3$d)<br />', $domain['url'], $domain['domain'], $domain['clicks'] );
                    else
                        printf( '<strong>%1$s</strong> (%2$d)<br />', $domain['domain'], $domain['clicks'] );
                }
            }

        }
        else
        {
            echo '<p class="error">' . __( 'There was a problem retrieving information about your link. There may be no statistics yet.', 'wp-bitly' ) . '</p>';
        }

    }


}

// Get... in!
wpbitly_admin::get_in();
