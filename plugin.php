<?php
/**
 * Plugin Name: Upload Fields for WPForms
 * Plugin URI:  https://softlabbd.com/upload-fields-for-wpforms/
 * Description: Upload Fields for WPForms provides a 3 new file upload fields for WPForms.
 * Version:     1.0.2
 * Author:      SoftLab
 * Author URI:  https://softlabbd.com/
 * Text Domain: upload-fields-for-wpforms
 * Domain Path: /languages/
 *
 */

// Don't call the file directly

if ( ! defined( 'ABSPATH' ) ) {
	wp_die( __( 'You can\'t access this page', 'upload-fields-for-wpforms' ) );
}

/** define constants */
define( 'UPWPFORMS_VERSION', '1.0.2' );
define( 'UPWPFORMS_FILE', __FILE__ );
define( 'UPWPFORMS_PATH', dirname( UPWPFORMS_FILE ) );
define( 'UPWPFORMS_INCLUDES', UPWPFORMS_PATH . '/includes' );
define( 'UPWPFORMS_URL', plugins_url( '', UPWPFORMS_FILE ) );
define( 'UPWPFORMS_ASSETS', UPWPFORMS_URL . '/assets' );

/*
 * The code that runs during plugin activation
 *
 * @since 1.0.0
 */
register_activation_hook( UPWPFORMS_FILE, function () {
	if ( ! class_exists( 'UPWPForms\Install' ) ) {
		require_once UPWPFORMS_INCLUDES . '/class-install.php';
	}

	UPWPForms\Install::activate();
} );


/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 *
 * @since 1.0.0
 */
add_action( 'wpforms_loaded', function () {
	include_once UPWPFORMS_INCLUDES . '/base.php';
} );


