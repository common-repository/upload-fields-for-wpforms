<?php

namespace UPWPForms;
defined( 'ABSPATH' ) || exit();

final class Main {


	/**
	 * The single instance of the class.
	 *
	 * @var Main
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Main constructor.
	 */
	public function __construct() {

		if ( ! $this->check_environment() ) {
			return;
		}

		$this->init_auto_loader();
		$this->includes();
		$this->init_hooks();

		// Add Group
		add_filter( 'wpforms_builder_fields_buttons', [ $this, 'add_field_group' ], 8 );

		do_action( 'upwpforms_loaded' );
	}

	private function check_environment() {
		$environment = true;

		if ( ! class_exists( 'WPForms\WPForms' ) ) {
			$environment = false;

			if ( is_admin() ) {
				add_action( 'admin_notices', [ $this, 'wpforms_not_loaded_notice' ] );
			}
		}

		return $environment;
	}

	public function is_plugin_installed( $basename ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$installed_plugins = get_plugins();

		return isset( $installed_plugins[ $basename ] );
	}


	/**
	 * Include required core files used in admin and on the frontend.
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public function includes() {
		include_once UPWPFORMS_INCLUDES . '/class-hooks.php';
		include_once UPWPFORMS_INCLUDES . '/class-ajax.php';
		include_once UPWPFORMS_INCLUDES . '/class-enqueue.php';
		include_once UPWPFORMS_INCLUDES . '/functions.php';

		// Form Fields
		include_once UPWPFORMS_INCLUDES . '/fields/file-upload.php';
		include_once UPWPFORMS_INCLUDES . '/fields/image-upload.php';
		include_once UPWPFORMS_INCLUDES . '/fields/google-drive-upload.php';

		if ( is_admin() ) {
			include_once UPWPFORMS_INCLUDES . '/class-settings.php';
		}
	}

	public function init_auto_loader() {

		// Only loads the app files
		spl_autoload_register( function ( $class_name ) {

			if ( false !== strpos( $class_name, 'UPWPForms' ) ) {
				$classes_dir = UPWPFORMS_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

				$file_name = strtolower( str_replace( [ 'UPWPForms\\', '_' ], [ '', '-' ], $class_name ) );

				$file_name = "class-$file_name.php";

				$file = $classes_dir . $file_name;

				if ( file_exists( $file ) ) {
					require_once $file;
				}
			}
		} );
	}

	/**
	 * Hook into actions and filters.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {

		add_action( 'admin_notices', [ $this, 'print_notices' ], 15 );

		// Localize our plugin
		add_action( 'init', [ $this, 'localization_setup' ] );

		// Plugin action links
		add_filter( 'plugin_action_links_' . plugin_basename( UPWPFORMS_FILE ), [ $this, 'plugin_action_links' ] );
	}

	public function plugin_action_links( $links ) {
		$links[] = '<a href="'.admin_url('admin.php?page=wpforms-settings&view=integrations&wpforms-integration=google-drive').'" >' . __( 'Settings', 'upload-fields-for-wpforms' ) . '</a>';

		return $links;
	}

	public function add_field_group( $fields ) {
		$tmp = [
			'upload_fields' => [
				'group_name' => __( 'Upload Fields', 'upload-fields-for-wpforms' ),
				'fields'     => [],
			],
		];

		return array_slice( $fields, 0, 1, true ) + $tmp + array_slice( $fields, 1, count( $fields ) - 1, true );
	}


	/**
	 * Initialize plugin for localization
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public function localization_setup() {
		load_plugin_textdomain( 'upload-fields-for-wpforms', false, dirname( plugin_basename( UPWPFORMS_FILE ) ) . '/languages/' );
	}


	public function add_notice( $class, $message ) {

		$notices = get_option( sanitize_key( 'upwpforms_notices' ), [] );
		if ( is_string( $message ) && is_string( $class ) && ! wp_list_filter( $notices, array( 'message' => $message ) ) ) {

			$notices[] = array(
				'message' => $message,
				'class'   => $class,
			);

			update_option( sanitize_key( 'upwpforms_notices' ), $notices );
		}

	}

	/**
	 * Prince admin notice
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public function print_notices() {
		$notices = get_option( sanitize_key( 'upwpforms_notices' ), [] );

		foreach ( $notices as $notice ) { ?>
            <div class="notice notice-large is-dismissible upwpforms-admin-notice notice-<?php echo esc_attr( $notice['class'] ); ?>">
				<?php echo $notice['message']; ?>
            </div>
			<?php
			update_option( sanitize_key( 'upwpforms_notices' ), [] );
		}
	}

	public function wpforms_not_loaded_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$wpforms      = 'wpforms/wpforms.php';
		$wpforms_lite = 'wpforms-lite/wpforms.php';

		$is_wpforms_installed      = $this->is_plugin_installed( $wpforms );
		$is_wpforms_lite_installed = $this->is_plugin_installed( $wpforms_lite );

		if ( $is_wpforms_installed ) {
			$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $wpforms . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $wpforms );

			$message = sprintf( __( '%1$sUpload Fields for WPForms%2$s requires %1$sWPForms%2$s plugin to be active. Please activate WPForms to continue.', 'upload-fields-for-wpforms' ), "<strong>", "</strong>" );

			$button_text = __( 'Activate WPForms', 'upload-fields-for-wpforms' );
		} elseif ( ! $is_wpforms_installed && $is_wpforms_lite_installed ) {
			$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $wpforms_lite . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $wpforms_lite );

			$message = sprintf( __( '%1$sUpload Fields for WPForms%2$s requires %1$sWPForms%2$s plugin to be active. Please activate WPForms to continue.', 'upload-fields-for-wpforms' ), "<strong>", "</strong>" );

			$button_text = __( 'Activate WPForms', 'upload-fields-for-wpforms' );
		} else {

			$activation_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=wpforms-lite' ), 'install-plugin_wpforms-lite' );

			$message     = sprintf( __( '%1$sUpload Fields for WPForms%2$s requires %1$sWPForms%2$s plugin to be installed and activated. Please install WPForms to continue.', 'upload-fields-for-wpforms' ), '<strong>', '</strong>' );
			$button_text = __( 'Install WPForms', 'upload-fields-for-wpforms' );
		}

		$button = '<p><a href="' . esc_url( $activation_url ) . '" class="button-primary">' . esc_html( $button_text ) . '</a></p>';

		printf( '<div class="error"><p>%1$s</p>%2$s</div>',  $message , $button );
	}


	/**
	 * Main Instance.
	 *
	 * Ensures only one instance of UPWPFORMS is loaded or can be loaded.
	 *
	 * @return Main - Main instance.
	 * @since 1.0.0
	 * @static
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

//kickoff upwpforms
if ( ! function_exists( 'upwpforms' ) ) {
	function upwpforms() {
		return Main::instance();
	}
}

upwpforms();