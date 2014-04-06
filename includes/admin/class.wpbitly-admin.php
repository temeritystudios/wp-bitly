<?php
/**
 * WP Bit.ly Administration
 *
 * @package   wp-bitly
 * @author    Mark Waterous <mark@watero.us
 * @license   GPL-2.0+
 */

class wpbitly_admin
{

    protected static $_instance = null;


    public static function get_in()
    {

        if ( !isset( self::$_instance ) && !( self::$_instance instanceof wpbitly_admin ) )
        {
            self::$_instance = new self;
            self::$_instance->action_filters();
        }

        return self::$_instance;
    }


    public function action_filters()
    {

        $wpbitly = wp_bitly();

        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // @TODO This is annoying. Disabled until further notice, pun intended.
        if ( empty( $wpbitly->options['oauth_token'] ) && 1 == 0 )
            add_action( 'admin_notices', array( $this, 'display_notice' ) );

        if  ( array_key_exists( 'post_types', $wpbitly->options ) && is_array( $wpbitly->options['post_types'] ) )
            foreach ( $wpbitly->options['post_types'] as $post_type )
                add_action( 'add_meta_boxes_' . $post_type, array( $this, 'add_metaboxes_yo' ) );

    }


    public function display_notice()
    {

        // @TODO use get_current_screen here.

        $prologue = __( 'WP Bit.Ly is almost ready!', 'wp-bitly' );
        $link = '<a href="options-writing.php">' . __( 'settings page', 'wp-bitly' ) . '</a>';
        $epilogue = sprintf( __( 'Please visit the %s to configure WP Bit.ly', 'wp-bitly' ), $link );

        $message = apply_filters( 'wpbitly_setup_notice', '<div id="message" class="updated"><p>' . $prologue . ' ' . $epilogue . '</p></div>' );

        echo $message;

    }


    public function register_settings()
    {

        register_setting( 'writing', 'wpbitly-options', array( $this, 'validate_settings' ) );
        add_settings_section( 'wpbitly_settings', 'WP Bit.ly Options', 'wpbitly_settings_section', 'writing' );

        function wpbitly_settings_section() {
            echo apply_filters( 'wpbitly_settings_section', '<p>'.__( 'You will need a Bit.ly account to use this plugin. Click the link below for your OAuth Token, and if necessary create a new account.', 'wp-bitly' ).'</p>' );
        }


        add_settings_field( 'oauth_token', '<label for="oauth_token">' . __( 'Bit.ly OAuth Token' , 'wpbitly' ) . '</label>', 'settings_field_oauth', 'writing', 'wpbitly_settings' );
        function settings_field_oauth()
        {

            $wpbitly = wp_bitly();

            $url = apply_filters( 'wpbitly_oauth_url', 'https://bitly.com/a/wordpress_oauth_app' );

            $output = '<input type="text" size="80" name="wpbitly-options[oauth_token]" value="' . esc_attr( $wpbitly->options['oauth_token'] ) . '" />'
                    . '<p>' . __( 'Please provide your', 'wp-bitly' ) . ' <a href="'.$url.'" target="_blank" style="text-decoration: none;"> ' . __( 'OAuth Token', 'wp-bitly' ) . '</a></p>';

            echo $output;

        }


        add_settings_field( 'post_types', '<label for="post_types">' . __( 'Post Types' , 'wp-bitly' ) . '</label>', 'settings_field_post_types', 'writing', 'wpbitly_settings' );
        function settings_field_post_types()
        {

            $wpbitly = wp_bitly();

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


    public function validate_settings( $input )
    {

        $input['oauth_token'] = wp_filter_nohtml_kses( $input['oauth_token'] );

        if ( !isset( $input['post_types'] ) )
        {
            $input['post_types'] = array();
        }
        else
        {
            $post_types = apply_filters( 'wpbitly_valid_post_types', get_post_types( array( 'public' => true ) ) );

            foreach ( $input['post_types'] as $pt )
            {
                if ( ! in_array( $pt, $post_types ) )
                    unset( $input['post_types'][$pt] );
            }
        }

        return $input;

    }


    public function add_metaboxes_yo( $post )
    {

        $shortlink = wp_get_shortlink();

        if ( empty( $shortlink ) )
            return;

        add_meta_box( 'wpbitly-meta', 'WP Bit.ly', array( $this, 'display_metabox' ), $post->post_type, 'side', 'default', array( $shortlink ) );

    }


    public function display_metabox( $post, $args )
    {

        $wpbitly = wp_bitly();
        $bapi = wpbitly_api();

        $shortlink = $args['args'][0];

        echo '<label class="screen-reader-text" for="new-tag-post_tag">' . __( 'Bit.ly Statistics', 'wp-bitly' ) . '</label>';

        $url = sprintf( $bapi['base'] . $bapi['link']['clicks'], $wpbitly->options['oauth_token'], $shortlink );
        $response = wpbitly_curl( $url );

        if ( is_array( $response ) && $response['status_code'] == 200 )
            $clicks = $response['data']['link_clicks'];


        $url = sprintf( $bapi['base'] . $bapi['link']['refer'], $wpbitly->options['oauth_token'], $shortlink );
        $response = wpbitly_curl( $url );

        if ( is_array( $response ) && $response['status_code'] == 200 )
            $refer = $response['data']['referring_domains'];

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
            echo '<p class="error">' . __( 'There was a problem retrieving information about your link!', 'wp-bitly' ) . '</p>';
        }

    }


}

wpbitly_admin::get_in();
