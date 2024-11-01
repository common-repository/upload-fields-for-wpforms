<?php

namespace UPWPForms;

defined( 'ABSPATH' ) || exit;

/**
 * Class Install
 */
class Install {

	/**
	 * Plugin activation stuffs
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		self::create_default_data();
	}

	/**
	 * Create plugin settings default data
	 *
	 * @since 1.0.0
	 */
	private static function create_default_data() {

		$version      = get_option( 'upwpforms_version' );
		$install_time = get_option( 'upwpforms_install_time', '' );

		if ( empty( $version ) ) {
			update_option( 'upwpforms_version', UPWPFORMS_VERSION );
		}

		if ( ! empty( $install_time ) ) {
			$date_format = get_option( 'date_format' );
			$time_format = get_option( 'time_format' );
			update_option( 'upwpforms_install_time', date( $date_format . ' ' . $time_format ) );
		}
	}

}