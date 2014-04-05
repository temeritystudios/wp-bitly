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

    protected static $instance = null;


    public static function get_instance()
    {

        if ( null == self::$instance )
        {
            self::$instance = new self;
            self::$instance->action_filters();
        }

        return self::$instance;
    }


    public function action_filters()
    {

        $wpbitly = wp_bitly();

        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // @TODO This is annoying. Disabled until further notice, pun possibly intended.
        if ( empty( $wpbitly->options['oauth_token'] ) && 1 == 0 )
            add_action( 'admin_notices', array( $this, 'display_notice' ) );

        foreach ( $wpbitly_options['post_types'] as $post_type )
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
            echo apply_filters( 'wpbitly_settings_section', '<p>'.__( 'Configure WP Bit.ly settings here.', 'wp-bitly' ).'</p>' );
        }


        add_settings_field( 'oauth_token', '<label for="oauth_token">' . __( 'Bit.ly OAuth Token' , 'wpbitly' ) . '</label>', 'settings_field_oauth', 'writing', 'wpbitly_settings' );
        function settings_field_oauth()
        {

            $wpbitly = wp_bitly();

            $url = apply_filters( 'wpbitly_oauth_url', 'https://bitly.com/a/wordpress_oauth_app' );

            $output = '<input type="text" size="80" name="wpbitly-options[oauth_token]" value="' . esc_attr( $wpbitly->options['oauth_token'] ) . '" />'
                    . '<p>' . __( 'Please provide your', 'wp-bitly' ) . ' <a href="'.$url.'" target="_blank"> ' . __( 'OAuth Token', 'wp-bitly' ) . '</a></p>';

            echo apply_filters( 'wpbitly_settings_field_oauth', $output );

        }


        add_settings_field( 'posttypes', '<label for="posttypes">' . __( 'Post Types' , 'wp-bitly' ) . '</label>', 'settings_field_posttypes', 'writing', 'wpbitly_settings' );
        function settings_field_posttypes()
        {

            $wpbitly = wp_bitly();

            $posttypes = apply_filters( 'wpbitly_allowed_posttypes', get_post_types( array( 'public' => true ) ) );
            $output = '';

            foreach ( $posttypes as $label )
            {
                $output .= '<label for "' . $label . '>'
                    . '<input type="checkbox" name="wpbitly-options[posttypes][]" value="' . $label . '" ' . checked( in_array( $label, $wpbitly->options['posttypes'] ), true, false ) . '>'
                    . '<span>' . $label . '</span><br />';
            }

            $output .= '<p>' . __( 'Check each post type you want to generate short links for.', 'wp-bitly' ) . '</p>';

            echo apply_filters( 'wpbitly_settings_field_posttypes', $output );

        }

    }


    public function validate_settings( $input )
    {

        $input['oauth_token'] = wp_filter_nohtml_kses( $input['oauth_token'] );

        // Post Types
        if ( !isset( $input['posttypes'] ) )
        {
            $input['posttypes'] = array();
        }
        else
        {
            $posttypes = $this->_get_posttypes();

            foreach ( $input['posttypes'] as $posttype )
            {
                if ( ! in_array( $posttype, $posttypes ) )
                    unset( $input['posttypes'][$posttype] );
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

        echo '<label class="screen-reader-text" for="new-tag-post_tag">WP Bit.ly</label>';
        echo '<p style="margin-top: 8px;"><input type="text" id="wpbitly-shortlink" name="_wpbitly" size="32" autocomplete="off" value="' . $shortlink . '" style="margin-right: 4px; color: #aaa;" /></p>';

        $url = sprintf( $bapi['clicks'], $shortlink, $wpbitly->options['oauth_token'] );
        $bitly_response = wpbitly_curl( $url );

        echo '<h4 style="margin-left: 4px; margin-right: 4px; padding-bottom: 3px; border-bottom: 4px solid #eee;">' . __( 'Shortlink Stats', 'wp-bitly' ) . '</h4>';

        if ( is_array( $bitly_response ) && $bitly_response['status_code'] == 200 )
        {
            echo '<p>' . __( 'Global Clicks:', 'wp-bitly ) . " <strong>{$bitly_response['data']['clicks'][0]['global_clicks']}</strong></p>";
            echo '<p>' . __( 'User Clicks:', 'wp-bitly ) . " <strong>{$bitly_response['data']['clicks'][0]['user_clicks']}</strong></p>";
        }
        else
        {
            echo '<p class="error" style="padding: 4px;">' . __( 'There was a problem retrieving stats!', 'wp-bitly' ) . '</p>';
        }

    }


}
