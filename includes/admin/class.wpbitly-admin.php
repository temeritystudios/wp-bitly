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

    public function __construct()
    {

        add_action( 'admin_init', array( $this, 'init_settings' ) );

        if ( empty( $wpbitly_options['oauth_token'] ) )
        {
            add_action( 'admin_notices', array( $this, 'notice_setup' ) );
        }

    }


    public static function get_instance()
    {

        if ( null == self::$instance )
        {
            self::$instance = new self;
        }

        return self::$instance;
    }



    public function check_settings()
    {

        // Display any necessary administrative notices
        if ( current_user_can( 'edit_posts' ) )
        {
            if ( empty( $this->options['bitly_username'] ) || empty( $this->options['bitly_api_key'] ) )
            {
                if ( ! isset( $_GET['page'] ) || $_GET['page'] != 'wpbitly' )
                {
                    add_action( 'admin_notices', array( $this, 'notice_setup' ) );
                }
            }

            if ( get_option( 'wpbitly_invalid' ) !== false && isset( $_GET['page'] ) && $_GET['page'] == 'wpbitly' )
            {
                add_action( 'admin_notices', array( $this, 'notice_invalid' ) );
            }
        }

    }


    public function notice_setup()
    {

        $screen = get_current_screen();

        if ( $screen->id != 'plugins' )
            return;


        $prologue = __( 'WP Bit.Ly is almost ready!', 'wp-bitly' );
        $link = '<a href="options-writing.php">' . __( 'settings page', 'wp-bitly' ) . '</a>';
        $epilogue = sprintf( __( 'Please visit the %s to configure WP Bit.ly', 'wp-bitly' ), $link );

        $message = apply_filters( 'wpbitly_setup_notice', '<div id="message" class="updated"><p>' . $prologue . ' ' . $epilogue . '</p></div>' );

        echo $message;

    }





    public function init_settings()
    {

        register_setting( 'writing', 'wpbitly_options', array( $this, 'validate_settings' ) );
        add_settings_section( 'wpbitly_settings', 'WP Bit.ly Options', 'wpbitly_settings_section', 'writing' );

        function wpbitly_settings_section() {
            echo apply_filters( 'wpbitly_settings_section', '<p>'.__( 'Configure WP Bit.ly settings here.', 'wp-bitly' ).'</p>' );
        }


        add_settings_field( 'oauth_token', '<label for="oauth_token">' . __( 'Bit.ly OAuth Token' , 'wpbitly' ) . '</label>', 'wpbitly_settings_field_oauth', 'writing', 'wpbitly_settings' );

        function wpbitly_settings_field_oauth()
        {

            //TEMPORARY
            $wpbitly_options = get_option('wpbitly_options');

            $url = apply_filters( 'wpbitly_oauth_url', 'https://bitly.com/a/wordpress_oauth_app' );

            $output = '<input type="text" size="80" name="wpbitly_options[oauth_token]" value="' . esc_attr( $wpbitly_options['oauth_token'] ) . '" />'
                    . '<p>' . __( 'Please provide your', 'wp-bitly' ) . ' <a href="'.$url.'" target="_blank"> ' . __( 'OAuth Token', 'wp-bitly' ) . '</a></p>';

            echo apply_filters( 'wpbitly_settings_field_oauth', $output );

        }


        add_settings_field( 'posttypes', '<label for="posttypes">' . __( 'Post Types' , 'wp-bitly' ) . '</label>', array( $this, 'settings_field_posttypes' ), 'writing', 'wpbitly_settings' );

    }

    public function settings_field_posttypes()
    {

        //TEMPORARY, CAUSE NO OPTIONS YET HAHAHAHAHAHA
        $wpbitly_options = get_option('wpbitly_options');

        $posttypes = $this->_get_posttypes();
        $output = '';

        foreach ( $posttypes as $label )
        {
            $output .= '<label for "' . $label . '>'
                     . '<input type="checkbox" name="wpbitly_options[posttypes][]" value="' . $label . '" ' . checked( in_array( $label, $wpbitly_options['posttypes'] ), true, false ) . '>'
                     . '<span>' . $label . '</span><br />';
        }

        $output .= '<p>' . __( 'Check each post type you want to generate short links for.', 'wp-bitly' ) . '</p>';

        echo apply_filters( 'wpbitly_settings_field_posttypes', $output );

    }

    private function _get_posttypes()
    {
        return apply_filters( 'wpbitly_allowed_posttypes', get_post_types( array( 'public' => true ) ) );
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
                {
                    unset( $input['posttypes'][$posttype] );
                }
            }
        }

        return $input;

    }



}