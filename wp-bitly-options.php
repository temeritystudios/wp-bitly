<?php

add_action( 'admin_init', 'wpbitly_options_init' );


function wpbitly_options_init() {
	register_setting( 'wpbitly_admin_options', 'wpbitly_options', 'wpbitly_options_validate' );
}


function wpbitly_options_validate( $options ) {
	global $wpbitly;

	$valid = FALSE;

	foreach ( $options as $key => $value )
		$options[$key] = trim( esc_attr( urlencode( $value ) ) );

	if ( ! empty( $options['bitly_username'] ) && ! empty( $options['bitly_api_key'] ) ) {

		$url = sprintf( $wpbitly->url['validate'], $options['bitly_username'], $options['bitly_api_key'] );

		$wpbitly_validate = wpbitly_curl( $url );

		if ( is_array( $wpbitly_validate ) && $wpbitly_validate['data']['valid'] == 1 )
			$valid = TRUE;

	}

	if ( ! in_array( $options['post_types'], array( 'post', 'page', 'any' ) ) )
		$options['post_types'] = 'any';

	if ( $valid === TRUE )
		delete_option( 'wpbitly_invalid' );
	else
		update_option( 'wpbitly_invalid', 1 );

	return $options;

}


class wpbitly_options {

	public $version;

	public $options;

	public $url = array( 'shorten'  => 'http://api.bit.ly/v3/shorten?login=%s&apiKey=%s&uri=%s&format=json',
						 'expand'   => 'http://api.bit.ly/v3/expand?shortUrl=%s&login=%s&apiKey=%s&format=json',
						 'validate' => 'http://api.bit.ly/v3/validate?x_login=%s&x_apiKey=%s&login=wpbitly&apiKey=R_bfef36d10128e7a2de09637a852c06c3&format=json',
						 'clicks'   => 'http://api.bit.ly/v3/clicks?shortUrl=%s&login=%s&apiKey=%s&format=json' );

	public function __construct() {
		$this->version = $this->_get_version();
		$this->options = $this->_refresh_options();
	}


	private function _get_version() {
		$version = get_option( 'wpbitly_version' );

		if ( $version == FALSE ) {
			update_option( 'wpbitly_version', WPBITLY_VERSION );
			return WPBITLY_VERSION;
		}

		return $version;
	}


	private function _refresh_options() {

		$options = get_option( 'wpbitly_options' );

		if ( $options == FALSE ) {

			$options['bitly_username'] = '';
			$options['bitly_api_key']  = '';
			$options['post_types']     = 'any';

			update_option( 'wpbitly_options', $options );

		}

		if ( empty( $options['bitly_username'] ) || empty( $options['bitly_api_key'] ) )
			add_action( 'admin_notices', array( $this, '_notice_setup' ) );

		if ( get_option( 'wpbitly_invalid' ) != FALSE && isset( $_GET['page'] ) && $_GET['page'] == 'wpbitly' )
			add_action( 'admin_notices', array( $this, '_notice_invalid' ) );

		return $options;

	}


	private function _notice_setup() {
		return $this->display_notice( '<strong>' . __( 'WP Bit.Ly is almost ready!', 'wpbitly' ) . '</strong> ' . sprintf( __( 'Please visit the %1 to configure WP Bit.ly', 'wpbitly' ), '<a href="options.php?page=wpbitly">' . __( 'Settings Page', 'wpbitly' ) . '</a>' ), 'error' );
	}


	public function _notice_invalid() {
		return $this->display_notice( '<strong>' . __( 'Invalid API Key;', 'wpbitly' ) . '</strong> ' . __( 'Your username and API key for bit.ly can\'t be validated. All features of WP Bit.ly are temporarily disabled.', 'wpbitly' ), 'error' );
	}


	public function display_notice( $string, $type = 'updated', $echo = TRUE ) {

		if ( $type != 'updated' )
			$type == 'error';

		$string = '<div id="message" class="' . $type . ' fade"><p>' . $string . '</p></div>';

		if ( $echo != TRUE )
			return $string;

		echo $string;

	}

}