<?php
/*
Plugin Name:    Phanes Care Initiative
Plugin URI:     https://phanes.co/
Description:    Phanes Donorschoose is a simple WordPress plugin to show donorschoose projects on your website using simple shortcode. It is really easy to setup and use.
Version:        1.0.0

Text Domain:    pds
Domain Path:    /lang
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PHANESDS_VERSION', '1.0.0' );
define( 'PHANESDS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PHANESDS_URL', plugin_dir_url( __FILE__ ) );
define( 'PHANESDS_BASE', plugin_basename( __FILE__ ) );

/**
 * Get donorschoose settings page url
 * @return string Settings page url
 */
function phanes_donorschoose_page_url() {
	return add_query_arg( array( 'page' => 'phanes-ds' ), admin_url( 'options-general.php' ) );
}

/**
 * Initialize and loadup donorschoose class
 * @return void
 */
function phanes_donorschoose_init() {
	require_once PHANESDS_PATH . '/src/class-phanes-donorschoose.php';

	new Phanes_Donorschoose;
}
add_action( 'plugins_loaded', 'phanes_donorschoose_init' );

/**
 * Redirect to settings page on plugin activation
 * @param  string $plugin Plugin base name
 * @return void
 */
function phanes_donorschoose_redirect( $plugin ) {
	if ( $plugin == PHANESDS_BASE) {
		wp_redirect( phanes_donorschoose_page_url() );
		exit;
	}
}
add_action( 'activated_plugin', 'phanes_donorschoose_redirect' );
