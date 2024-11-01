<?php

namespace UPWPForms;

defined( 'ABSPATH' ) || exit;

class Ajax {

	private static $instance = null;

	public function __construct() {
		add_action( 'wp_ajax_wpforms_settings_provider_disconnect_google-drive', array( $this, 'disconnect_account' ) );

		add_action( 'wp_ajax_upwpforms_get_files', array( $this, 'get_files' ) );
		add_action( 'wp_ajax_nopriv_upwpforms_get_files', array( $this, 'get_files' ) );

		// Switch Account
		add_action( 'wp_ajax_upwpforms_switch_account', [ $this, 'switch_account' ] );
		add_action( 'wp_ajax_nopriv_upwpforms_switch_account', [ $this, 'switch_account' ] );

	}

	public function switch_account() {
		$id = ! empty( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';
		wp_send_json_success( Account::set_active_account( $id ) );
	}

	public function get_files() {

		$active_account = Account::get_active_account();

		if ( empty( $active_account ) ) {
			wp_send_json_error( __( 'No active account found', 'upload-fields-for-wpforms' ) );
		}

		$folder = ! empty( $_POST['folder'] ) ? $_POST['folder'] : null;

		$folder_id  = ! empty( $folder['id'] ) ? $folder['id'] : $active_account['root_id'];
		$account_id = ! empty( $folder['accountId'] ) ? $folder['accountId'] : $active_account['id'];

		$args = [];
		$app  = App::instance( $account_id );

		if ( 'computers' == $folder_id ) {
			$files = $app->get_computers_files();
		} elseif ( 'shared-drives' == $folder_id ) {
			$files = $app->get_shared_drives();
		} elseif ( 'shared' == $folder_id ) {
			$files = $app->get_shared_files();
		}elseif ( 'starred' == $folder_id ) {
			$files = $app->get_starred_files();
		} elseif ( ! empty( $folder_id['search'] ) ) {
			$files   = $app->get_search_files( $folder_id['search'] );
		} else {
			$files = $app->get_files( $args, $folder_id );
		}

		if ( ! empty( $files['error'] ) ) {
			wp_send_json_error( $files );
		}

		$data = [
			'files' => $files,
		];

		if ( empty( $folder['id']['search'] ) ) {
			$data['breadcrumbs'] = upwpforms_get_breadcrumb( $folder );
		}

		wp_send_json_success( $data );
	}

	public function disconnect_account() {
		$key = ! empty( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		if ( empty( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'upload-fields-for-wpforms' ) ) );
		}

		Account::delete_account( $key );

		wp_send_json_success();
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

}

Ajax::instance();