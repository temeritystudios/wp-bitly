<?php
/**
 * WP Bit.ly
 *
 * This plugin can be used to generate short links for your websites posts, pages,
 * and any other custom post type that you would like it to interact with. Extremely
 * compact and easy to set up, give it your Bit.ly oAuth token and you're set!
 *
 * @package   wp-bitly
 * @author    Mark Waterous <mark@watero.us>
 * @author    Chip Bennett
 * @license   GPL-2.0+
 * @link      http://wordpress.org/plugins/wp-bitly
 * @copyright 2014 Mark Waterous
 *
 * @wordpress-plugin
 * Plugin Name:       WP Bit.ly
 * Plugin URI:        http://wordpress.org/plugins/wp-bitly
 * Description:       Whether you're sharing links on social media like Twitter, or sending someone a link from your smart phone, short links make life much easier. WP Bit.ly simplifies the process for you.
 * Version:           2.0
 * Author:            <a href="http://mark.watero.us/">Mark Waterous</a> & <a href="http://www.chipbennett.net/">Chip Bennett</a>
 * Text Domain:       wp-bitly
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/mwaterous/wp-bitly
 */


if ( ! defined( 'WPINC' ) )
    die;


define( 'WPBITLY_VERSION',  '2.0' );

define( 'WPBITLY_FILE', __FILE__ )
define( 'WPBITLY_DIR',  WP_PLUGIN_DIR.'/'.basename( dirname( __FILE__ ) ) );
define( 'WPBITLY_URL',  plugins_url().'/'.basename( dirname( __FILE__ ) ) );



/**
 * The primary controller class for everything wonderful that WP Bit.ly does.
 * We're not sure entirely what that means yet; if you figure it out, please
 * let us know and we'll say something snazzy about it here.
 *
 * @TODO: Update the class phpdoc description to say something snazzy.
 *
 * @package wp-bitly
 * @author  Mark Waterous <mark@watero.us>
 */
class wpbitly
{

    private static $_instance;

    public static $slug = 'wp-bitly';

    public $options;


    public static function get_instance()
    {

        if ( !isset( self::$_instance ) && !( self::$_instance instanceof wpbitly ) )
        {
            self::$_instance = new self;
            self::$_instance->populate_options();
            self::$_instance->include_files();
            self::$_instance->action_filters();
        }

        return self::$_instance;

    }


    public function populate_options()
    {
        $this->options = get_option( 'wpbitly-options' );
    }


    public function include_files()
    {

        if ( is_admin() )
            require_once( WPBITLY_DIR . '/includes/admin/class.wpbitly-admin.php' );

        require_once( WPBITLY_DIR . '/includes/functions.php' );

    }


    public function action_filters()
    {

        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

        $basename = plugin_basename( __FILE__ );
        add_filter( 'plugin_action_links_' . $basename, array( $this, 'add_action_links' ) );

        if ( isset( $this->options['authorized'] ) && $this->options['authorized'] )
        {
            add_action( 'save_post', 'wpbitly_generate_shortlink' );
            add_filter( 'get_shortlink', 'wpbitly_get_shortlink' );
        }

        add_shortcode( 'wpbitly', 'wpbitly_shortcode' );

        // @TODO This should happen during activation, and inform the user before arbitrarily choosing.
        if( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'shortlinks' ) )
            remove_filter( 'get_shortlink', 'wpme_get_shortlink_handler' );

    }


    public function add_action_links( $links )
    {

        return array_merge(
            array(
                'settings' => '<a href="' . admin_url( 'options-writing.php' ) . '">' . __( 'Settings', 'wp-bitly' ) . '</a>'
            ),
            $links
        );

    }


    public function load_plugin_textdomain()
    {

        $lang_dir = WPBITLY_DIR . '/languages/';
        $lang_dir = apply_filters( 'wpbitly_languages_directory', $lang_dir );

        $locale = apply_filters( 'plugin_locale', get_locale(), self::$slug );
        $mofile = $lang_dir . self::$slug . $locale . '.mo';

        if ( file_exists( $mofile ) )
            load_textdomain( self::$slug, $mofile );
        else
            load_plugin_textdomain( self::$slug, false, $lang_dir );

    }


}

function wp_bitly()
{
    return wpbitly::get_instance();
}

wp_bitly();
