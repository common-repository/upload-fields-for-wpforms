<?php

namespace UPWPForms;

defined( 'ABSPATH' ) || exit;

class Hooks {

	private static $instance = null;

	public function __construct() {
		add_action( 'admin_action_upwpforms-authorize', array( $this, 'handle_authorization' ) );
	}

	public function handle_authorization() {
		$client = Client::instance();

		$client->create_access_token();

		$redirect = admin_url( 'admin.php?page=wpforms-settings&view=integrations&wpforms-integration=google-drive' );

		echo '<script type="text/javascript">window.opener.parent.location.href = "' . $redirect . '"; window.close();</script>';
		die();
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

}

Hooks::instance();