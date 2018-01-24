<?php
/**
 * WP Bitly Administration
 *
 * @package     wp-bitly
 * @subpackage  admin
 * @author    Temerity Studios <info@temeritystudios.com>
 * @author    Chip Bennett
 * @license   GPL-2.0+
 * @link      http://wordpress.org/plugins/wp-bitly
 */

/**
 * Class WPBitlyAdmin
 * This handles everything we do on the dashboard side.
 *
 * @since 2.0
 */
class WPBitlyAdmin
{

    /**
     * @var $_instance An instance of ones own instance
     */
    protected static $_instance = null;


    /**
     * This creates and returns a single instance of WPBitlyAdmin
     *
     * @since   2.0
     * @static
     * @uses    WPBitlyAdmin::defineHooks() To set up any necessary WordPress hooks.
     * @return  WPBitlyAdmin
     */
    public static function getIn()
    {

        if (!isset(self::$_instance) && !(self::$_instance instanceof WPBitlyAdmin)) {
            self::$_instance = new self;
            self::$_instance->defineHooks();
        }

        return self::$_instance;
    }


    /**
     * Hook any necessary WordPress actions or filters that we'll be needing for the admin.
     *
     * @since   2.0
     * @uses    wpbitly()
     */
    public function defineHooks()
    {

        $wpbitly = wpbitly();

        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_print_styles', [$this, 'enqueueStyles']);

        if (!$wpbitly->isAuthorized()) {
            add_action('admin_notices', [$this, 'displayNotice']);
        }


        $post_types = $wpbitly->getOption('post_types');

        if (is_array($post_types)) {
            foreach ($post_types as $post_type) {
                add_action('add_meta_boxes_' . $post_type, [$this, 'addMetaboxes']);
            }
        }

    }


    /**
     * Load our admin stylesheet if we're on the right page
     *
     * @since  2.4.1
     */
    public function enqueueStyles()
    {

        $screen = get_current_screen();

        if ('options-writing' == $screen->base) {
            wp_enqueue_style('wpbitly-admin', WPBITLY_URL . '/dist/css/admin.min.css');
        }

    }

    /**
     * Display a simple and unobtrusive notice on the plugins page after activation (and
     * up until they add their oauth_token).
     *
     * @since   2.0
     */
    public function displayNotice()
    {

        $screen = get_current_screen();

        if ($screen->base != 'plugins') {
            return;
        }

        $prologue = __('WP Bitly is almost ready!', 'wp-bitly');
        $link = sprintf('<a href="%s">', admin_url('options-writing.php')) . __('settings page', 'wp-bitly') . '</a>';
        $epilogue = sprintf(__('Please visit the %s to configure WP Bitly', 'wp-bitly'), $link);

        $message = apply_filters('wpbitly_setup_notice', sprintf('<div id="message" class="updated"><p>%s %s</p></div>', $prologue, $epilogue));

        echo $message;

    }


    /**
     * Add our options array to the WordPress whitelist, append them to the existing Writing
     * options page, and handle all the callbacks.
     *
     * @since   2.0
     */
    public function registerSettings()
    {

        register_setting('writing', 'wpbitly-options', [$this, 'validateSettings']);

        add_settings_section('wpbitly_settings', 'WP Bitly Options', '_f_settings_section', 'writing');
        function _f_settings_section()
        {
            echo apply_filters('wpbitly_settings_section', '<p>' . __('You will need a Bitly account to use this plugin.', 'wp-bitly') . '</p>');
        }


        add_settings_field('oauth_token', '<label for="oauth_token">' . __('Bitly OAuth Token', 'wpbitly') . '</label>', '_f_settings_field_oauth', 'writing', 'wpbitly_settings');
        function _f_settings_field_oauth()
        {

            $wpbitly = wpbitly();
            $auth = $wpbitly->isAuthorized();

            if (!$auth && isset($_GET['access_token']) && isset($_GET['login'])) {

                $wpbitly->setOption('oauth_token', esc_attr($_GET['access_token']));
                $wpbitly->setOption('oauth_login', esc_attr($_GET['login']));

                $wpbitly->authorize();
                $auth = true;

            }

            if ($auth && isset($_GET['disconnect']) && 'bitly' == $_GET['disconnect']) {
                $wpbitly->setOption('oauth_token', '');
                $wpbitly->setOption('oauth_login', '');

                $wpbitly->authorize(false);
                $auth = false;
            }


            if ($auth) {

                $url = add_query_arg($wp->request);
                $output = sprintf('<a href="%s" class="button button-secondary confirm-disconnect">%s</a>', add_query_arg('disconnect', 'bitly', strtok($url, '?')), __('Disconnect', 'wp-bitly'));
                $output .= '<script>jQuery(function(n){n(".confirm-disconnect").click(function(){return window.confirm("Are you sure you want to disconnect your Bitly account?")})});</script>';

            } else {
                $redirect = strtok(home_url(add_query_arg($wp->request)), '?');

                $url = WPBITLY_REDIRECT . '?auth=bitly&state=' . urlencode(base64_encode($redirect));
                $image = WPBITLY_URL . '/dist/images/b_logo.png';

                $output = sprintf('<a href="%s" class="btn"><span class="btn-content">%s</span><span class="icon"><img src="%s"></span></a>', $url, __('Authorize', 'wp-bitly'), $image);
            }

            echo $output;

        }


        add_settings_field('post_types', '<label for="post_types">' . __('Post Types', 'wp-bitly') . '</label>', '_f_settings_field_post_types', 'writing', 'wpbitly_settings');
        function _f_settings_field_post_types()
        {

            $wpbitly = wpbitly();

            $post_types = apply_filters('wpbitly_allowed_post_types', get_post_types(['public' => true]));
            $output = '<fieldset><legend class="screen-reader-text"><span>Post Types</span></legend>';

            $current_post_types = $wpbitly->getOption('post_types');
            foreach ($post_types as $label) {
                $output .= '<label for "' . $label . '>' . '<input type="checkbox" name="wpbitly-options[post_types][]" value="' . $label . '" ' . checked(in_array($label, $current_post_types), true,
                        false) . '>' . $label . '</label><br>';
            }

            $output .= '<p class="description">' . __('Automatically generate shortlinks for the selected post types.', 'wp-bitly') . '</p>';
            $output .= '</fieldset>';

            echo $output;

        }


        //add_settings_field('debug', '<label for="debug">' . __('Debug WP Bitly', 'wp-bitly') . '</label>', '_f_settings_field_debug', 'writing', 'wpbitly_settings');
        function _f_settings_field_debug()
        {

            $wpbitly = wpbitly();

            $output = '<fieldset><legend class="screen-reader-text"><span>Debug WP Bitly</span></legend>' . '<label title="debug"><input type="checkbox" id="debug" name="wpbitly-options[debug]" value="1" ' . checked($wpbitly->getOption('debug'),
                    1, 0) . '><span> ' . __("Let's debug!", 'wpbitly') . '</span></label><br>' . '<p class="description">' . __("If you're having issues generating shortlinks, turn this on and create a thread in the",
                    'wpbitly') . ' ' . '<a href="http://wordpress.org/support/plugin/wp-bitly" title="' . __('WP Bitly support forums on WordPress.org', 'wpbitly') . '">' . __('support forums',
                    'wpbitly') . '</a>.</p>' . '</fieldset>';

            echo $output;

        }

    }


    /**
     * Validate user settings.
     *
     * @since   2.0
     * @param   array $input WordPress sanitized data array
     * @return  array           WP Bitly sanitized data
     */
    public function validateSettings($input)
    {

        $input['debug'] = ('1' == $input['debug']) ? true : false;

        if (!isset($input['post_types'])) {
            $input['post_types'] = [];
        } else {
            $post_types = apply_filters('wpbitly_allowed_post_types', get_post_types(['public' => true]));

            foreach ($input['post_types'] as $key => $pt) {
                if (!in_array($pt, $post_types)) {
                    unset($input['post_types'][$key]);
                }
            }

        }

        return $input;

    }


    /**
     * Add a fun little statistics metabox to any posts/pages that WP Bitly
     * generates a link for. There's potential here to include more information.
     *
     * @since   2.0
     * @param   object $post The post object passed by WordPress
     */
    public function addMetaboxes($post)
    {

        $shortlink = get_post_meta($post->ID, '_wpbitly', true);
        
        if (!$shortlink) {
            return;
        }

        add_meta_box('wpbitly-meta', 'WP Bitly', [
            $this,
            'displayMetabox'
        ], $post->post_type, 'side', 'default', [$shortlink]);
    }


    /**
     * Handles the display of the metabox.
     *
     * @since   2.0
     * @param   object $post WordPress passed $post object
     * @param   array $args Passed by our call to add_meta_box(), just the $shortlink in this case.
     */
    public function displayMetabox($post, $args)
    {

        $wpbitly = wpbitly();
        $shortlink = $args['args'][0];


        // Look for a clicks response
        $url = sprintf(wpbitly_api('link/clicks'), $wpbitly->getOption('oauth_token'), $shortlink);
        $response = wpbitly_get($url);

        if (is_array($response)) {
            $clicks = $response['data']['link_clicks'];
        }


        // Look for referring domains metadata
        $url = sprintf(wpbitly_api('link/refer'), $wpbitly->getOption('oauth_token'), $shortlink);
        $response = wpbitly_get($url);

        if (is_array($response)) {
            $refer = $response['data']['referring_domains'];
        }


        echo '<label class="screen-reader-text" for="new-tag-post_tag">' . __('Bitly Statistics', 'wp-bitly') . '</label>';

        if (isset($clicks) && isset($refer)) {

            echo '<p>' . __('Global click through:', 'wp-bitly') . ' <strong>' . $clicks . '</strong></p>';

            if (!empty($refer)) {
                echo '<h4 style="padding-bottom: 3px; border-bottom: 4px solid #eee;">' . __('Your link was shared on', 'wp-bitly') . '</h4>';
                foreach ($refer as $domain) {
                    if (isset($domain['url'])) {
                        printf('<a href="%1$s" target="_blank" title="%2$s">%2$s</a> (%3$d)<br>', $domain['url'], $domain['domain'], $domain['clicks']);
                    } else {
                        printf('<strong>%1$s</strong> (%2$d)<br>', $domain['domain'], $domain['clicks']);
                    }
                }
            }

        } else {
            echo '<p class="error">' . __('There was a problem retrieving information about your link. There may be no statistics yet.', 'wp-bitly') . '</p>';
        }

    }

}


WPBitlyAdmin::getIn();
