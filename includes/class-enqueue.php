<?php

namespace UPWPForms;

defined( 'ABSPATH' ) || exit;

class Enqueue {

	private static $instance = null;

	public function __construct() {
		// Enqueue scripts and styles on wpforms admin page
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		// Enqueue scripts and styles on wpforms frontend page
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
	}

	public function frontend_scripts() {
		wp_register_style( 'upwpforms-frontend', UPWPFORMS_ASSETS . '/css/frontend.css', array( 'dashicons' ), UPWPFORMS_VERSION );

		wp_register_script( 'upwpforms-frontend', UPWPFORMS_ASSETS . '/js/frontend.js', [
			'jquery',
			'wp-plupload',
			'wp-util',
			'wp-i18n',
		], UPWPFORMS_VERSION, true );

		wp_localize_script( 'upwpforms-frontend', 'upwpforms', $this->get_localize_data() );
	}

	public function admin_scripts( $hook ) {

		wp_enqueue_style( 'upwpforms-admin', UPWPFORMS_URL . '/assets/css/admin.css', array(
			'wp-components',
		), UPWPFORMS_VERSION );
		wp_enqueue_script( 'upwpforms-admin', UPWPFORMS_URL . '/assets/js/admin.js', array(
			'jquery',
			'wp-util',
			'wp-i18n',
			'wp-api-fetch',
			'wp-components',
			'wp-element',
		), UPWPFORMS_VERSION, true );

		wp_localize_script( 'upwpforms-admin', 'upwpforms', $this->get_localize_data( $hook ) );

	}

	public function get_localize_data( $hook = '' ) {
		$data = [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'pluginUrl' => UPWPFORMS_URL,
			'nonce'     => wp_create_nonce( 'upwpforms' ),
		];

		if ( is_admin() ) {
			if ( '' == $hook ) {
				$auth_url = Client::instance()->get_auth_url();
			}
		}

		return $data;
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

}

Enqueue::instance();