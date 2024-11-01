<?php

namespace UPWPForms;

class Uploader {

	private static $instance = null;

	private $type = 'upwpforms_file_upload';
	public $form_data;
	public $form_id;
	public $field_id;
	public $field_data;

	private $denylist = array(
		'ade',
		'adp',
		'app',
		'asp',
		'bas',
		'bat',
		'cer',
		'cgi',
		'chm',
		'cmd',
		'com',
		'cpl',
		'crt',
		'csh',
		'csr',
		'dll',
		'drv',
		'exe',
		'fxp',
		'flv',
		'hlp',
		'hta',
		'htaccess',
		'htm',
		'html',
		'htpasswd',
		'inf',
		'ins',
		'isp',
		'jar',
		'js',
		'jse',
		'jsp',
		'ksh',
		'lnk',
		'mdb',
		'mde',
		'mdt',
		'mdw',
		'msc',
		'msi',
		'msp',
		'mst',
		'ops',
		'pcd',
		'php',
		'pif',
		'pl',
		'prg',
		'ps1',
		'ps2',
		'py',
		'rb',
		'reg',
		'scr',
		'sct',
		'sh',
		'shb',
		'shs',
		'sys',
		'swf',
		'tmp',
		'torrent',
		'url',
		'vb',
		'vbe',
		'vbs',
		'vbscript',
		'wsc',
		'wsf',
		'wsf',
		'wsh',
		'dfxp',
		'onetmp'
	);

	public function __construct() {
		add_action( 'wp_ajax_upwpforms_upload_file', [ $this, 'upload_file' ] );
		add_action( 'wp_ajax_nopriv_upwpforms_upload_file', [ $this, 'upload_file' ] );

		add_filter( 'wpforms_process_after_filter', [ $this, 'upload_complete' ], 10, 3 );
	}

	public function upload_complete( $fields, $entry, $form_data ) {
		if ( ! empty( wpforms()->get( 'process' )->errors[ $form_data['id'] ] ) ) {
			return $fields;
		}

		$this->form_data = $form_data;

		foreach ( $fields as $field_id => $field ) {
			if ( empty( $field['type'] )
			     || ! in_array( $field['type'], [ 'upwpforms_file_upload', 'image_upload' ] ) ) {
				continue;
			}

			$this->form_id    = absint( $form_data['id'] );
			$this->field_id   = $field_id;
			$this->field_data = ! empty( $this->form_data['fields'][ $field_id ] ) ? $this->form_data['fields'][ $field_id ] : [];
			$is_visible       = ! isset( wpforms()->get( 'process' )->fields[ $field_id ]['visible'] ) || ! empty( wpforms()->get( 'process' )->fields[ $field_id ]['visible'] );

			$fields[ $field_id ]['visible'] = $is_visible;

			if ( ! $is_visible ) {
				continue;
			}

			$files = $this->sanitize_files_input();

			if ( empty( $files ) ) {
				$fields[ $field_id ] = $field;
				continue;
			}

			$processed_field = $field;

			wpforms_create_upload_dir_htaccess_file();

			$upload_dir = wpforms_upload_dir();

			if ( empty( $upload_dir['error'] ) ) {
				wpforms_create_index_html_file( $upload_dir['path'] );
			}

			$data = [];

			foreach ( $files as $file ) {
				$data[] = $this->process_file( $file );
			}

			$data                         = array_filter( $data );
			$processed_field['value_raw'] = $data;
			$processed_field['value']     = wpforms_chain( $data )
				->map(
					static function ( $file ) {

						return $file['value'];
					}
				)
				->implode( "\r\n" )
				->value();

			$fields[ $field_id ] = $processed_field;
		}

		return $fields;
	}

	public function process_file( $file ) {

		$file['tmp_name'] = trailingslashit( $this->get_tmp_dir() ) . $file['file'];
		$file['type']     = 'application/octet-stream';

		if ( is_file( $file['tmp_name'] ) ) {
			$filetype     = wp_check_filetype( $file['tmp_name'] );
			$file['type'] = $filetype['type'];
			$file['size'] = filesize( $file['tmp_name'] );
		}

		$file_name     = sanitize_file_name( $file['name'] );
		$file_ext      = pathinfo( $file_name, PATHINFO_EXTENSION );
		$file_base     = $this->get_file_basename( $file_name, $file_ext );
		$file_name_new = sprintf( '%s-%s.%s', $file_base, wp_hash( wp_rand() . microtime() . $this->form_data['id'] . $this->field_id ), strtolower( $file_ext ) );

		$file_details = [
			'file_name'     => $file_name,
			'file_name_new' => $file_name_new,
			'file_ext'      => $file_ext,
		];

		$is_media_integrated = ! empty( $this->field_data['media_library'] ) && '1' === $this->field_data['media_library'];

		if ( $is_media_integrated ) {
			$uploaded_file = $this->process_media_storage( $file_details, $file );
		} else {
			$uploaded_file = $this->process_wpforms_storage( $file_details, $file );
		}

		if ( empty( $uploaded_file ) ) {
			return [];
		}

		$uploaded_file['file']           = $file['file'];
		$uploaded_file['file_user_name'] = $file['file_user_name'];
		$uploaded_file['type']           = $file['type'];

		return $this->generate_file_data( $uploaded_file );
	}

	private function process_wpforms_storage( $file_details, $file ) {

		$form_id          = $this->form_data['id'];
		$upload_dir       = wpforms_upload_dir();
		$upload_path      = $upload_dir['path'];
		$form_directory   = $this->get_form_directory( $form_id, $this->form_data['created'] );
		$upload_path_form = $this->get_form_upload_path( $upload_path, $form_directory );
		$file_new         = trailingslashit( $upload_path_form ) . $file_details['file_name_new'];
		$file_url         = trailingslashit( $upload_dir['url'] ) . trailingslashit( $form_directory ) . $file_details['file_name_new'];

		wpforms_create_upload_dir_htaccess_file();
		wpforms_create_index_html_file( $upload_path );
		wpforms_create_index_html_file( $upload_path_form );

		$move_new_file = @rename( $file['tmp_name'], $file_new );

		if ( $move_new_file === false ) {
			wpforms_log(
				'Upload Error, could not upload file',
				$file_url,
				[
					'type'    => [ 'entry', 'error' ],
					'form_id' => $form_id,
				]
			);

			return [];
		}

		$this->set_file_fs_permissions( $file_new );

		$file_details['attachment_id'] = '0';
		$file_details['upload_path']   = $upload_path_form;
		$file_details['file_url']      = $file_url;

		return $file_details;
	}

	public function get_form_directory( $form_id, $date_created ) {

		return absint( $form_id ) . '-' . md5( $form_id . $date_created );
	}

	private function get_form_upload_path( $upload_path, $form_directory ) {

		$upload_path_form = wp_normalize_path( trailingslashit( $upload_path ) . $form_directory );

		if ( ! file_exists( $upload_path_form ) ) {
			wp_mkdir_p( $upload_path_form );
		}

		return $upload_path_form;
	}

	private function process_media_storage( $file_details, $file ) {

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file_args = [
			'error'    => '',
			'tmp_name' => $file['tmp_name'],
			'name'     => $file_details['file_name_new'],
			'type'     => $file['type'],
			'size'     => $file['size'],
		];

		$upload = wp_handle_sideload( $file_args, [ 'test_form' => false ] );

		if ( empty( $upload['file'] ) ) {
			return [];
		}

		$attachment_id = $this->insert_attachment( $file, $upload['file'], $this->form_data['fields'][ $this->field_id ] );

		if ( $attachment_id === 0 ) {
			return [];
		}

		$file_details['attachment_id'] = $attachment_id;
		$file_details['file_url']      = wp_get_attachment_url( $attachment_id );
		$file_details['file_name_new'] = wp_basename( $file_details['file_url'] );
		$file_details['upload_path']   = wp_normalize_path( trailingslashit( dirname( get_attached_file( $attachment_id ) ) ) );

		return $file_details;
	}

	private function insert_attachment( $file, $upload_file, $field_data ) {

		$attachment_id = wp_insert_attachment(
			[
				'post_title'     => $this->get_wp_media_file_title( $file, $field_data ),
				'post_content'   => $this->get_wp_media_file_desc( $file, $field_data ),
				'post_status'    => 'publish',
				'post_mime_type' => $file['type'],
			],
			$upload_file
		);

		if ( empty( $attachment_id ) || is_wp_error( $attachment_id ) ) {

			wpforms_log(
				"Upload Error, attachment wasn't created",
				$file['name'],
				[
					'type' => [ 'error' ],
				]
			);

			return 0;
		}

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $upload_file )
		);

		return $attachment_id;
	}

	private function get_wp_media_file_title( $file, $field_data ) {

		$field_type = $file['type'];

		/**
		 * Allow filtering attachment title used in WordPress Media Library for an uploaded file.
		 *
		 * @param string $desc Label text.
		 * @param array $file File data.
		 * @param array $field_data Field data.
		 *
		 * @since 1.0.0
		 *
		 */
		$title = apply_filters(
			"wpforms_field_{$field_type}_media_file_title",
			isset( $field_data['label'] ) ? $field_data['label'] : '',
			$file,
			$field_data
		);

		return wpforms_sanitize_text_deeply( $title );
	}

	private function get_wp_media_file_desc( $file, $field_data ) {

		$field_type = $file['type'];

		/**
		 * Allow filtering attachment description used in WordPress Media Library for an uploaded file.
		 *
		 * @param string $desc Description text.
		 * @param array $file File data.
		 * @param array $field_data Field data.
		 *
		 * @since 1.0.0
		 *
		 */
		$desc = apply_filters(
			"wpforms_field_{$field_type}_media_file_desc",
			isset( $field_data['description'] ) ? $field_data['description'] : '',
			$file,
			$field_data
		);

		return wp_kses_post_deep( $desc );
	}

	private function get_file_basename( $file_name, $file_ext ) {

		return mb_substr( wp_basename( $file_name, '.' . $file_ext ), 0, 64, 'UTF-8' );
	}

	private function sanitize_files_input() {

		$json_value = isset( $_POST['wpforms']['fields'][ $this->field_id ] ) ? sanitize_text_field( wp_unslash( $_POST['wpforms']['fields'][ $this->field_id ] ) ) : '';

		$files = json_decode( $json_value, true );

		if ( empty( $files ) || ! is_array( $files ) ) {
			return [];
		}

		return array_filter( array_map( [ $this, 'sanitize_file' ], $files ) );
	}

	private function sanitize_file( $file ) {

		if ( empty( $file['file'] ) || empty( $file['name'] ) ) {
			return [];
		}

		$sanitized_file = [];
		$rules          = [
			'name'           => 'sanitize_file_name',
			'file'           => 'sanitize_file_name',
			'url'            => 'esc_url_raw',
			'size'           => 'absint',
			'type'           => 'sanitize_text_field',
			'file_user_name' => 'sanitize_text_field',
		];

		foreach ( $rules as $rule => $callback ) {
			$file_attribute          = isset( $file[ $rule ] ) ? $file[ $rule ] : '';
			$sanitized_file[ $rule ] = $callback( $file_attribute );
		}

		return $sanitized_file;
	}

	public function upload_file() {
		$default_error = esc_html__( 'Something went wrong, please try again.', 'upload-fields-for-wpforms' );

		// Make sure we have required values from $_FILES.
		if ( empty( $_FILES['file']['name'] ) ) {
			wp_send_json_error( $default_error, 403 );
		}
		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( $default_error, 403 );
		}

		$error          = empty( $_FILES['file']['error'] ) ? 0 : (int) $_FILES['file']['error'];
		$name           = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) );
		$file_user_name = sanitize_text_field( wp_unslash( $_FILES['file']['name'] ) );
		$path           = $_FILES['file']['tmp_name'];
		$extension      = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$errors         = wpforms_chain( array() )
			->array_merge( (array) $this->validate_basic( $error ) )
			->array_merge( (array) $this->validate_size() )
			->array_merge( (array) $this->validate_extension( $extension ) )
			->array_merge( (array) $this->validate_wp_filetype_and_ext( $path, $name ) )
			->array_filter()
			->value();

		if ( count( $errors ) ) {
			$errors = array_unique( $errors );
			wp_send_json_error( implode( ',', $errors ), 400 );
		}

		$tmp_dir  = $this->get_tmp_dir();
		$tmp_name = $this->get_tmp_file_name( $extension );
		$tmp_path = wp_normalize_path( $tmp_dir . '/' . $tmp_name );
		$tmp      = $this->move_file( $path, $tmp_path );

		if ( ! $tmp ) {
			wp_send_json_error( $default_error, 400 );
		}

		$this->clean_tmp_files();

		wp_send_json_success(
			array(
				'name'           => $name,
				'file'           => pathinfo( $tmp, PATHINFO_FILENAME ) . '.' . pathinfo( $tmp, PATHINFO_EXTENSION ),
				'file_user_name' => $file_user_name,
				'url'            => $this->get_tmp_file_url( $tmp_name ),
				'size'           => filesize( $tmp ),
				'type'           => $this->get_mime_type( $tmp ),
			)
		);
	}

	public function get_tmp_file_url( $tmp_name ) {
		$upload_dir = wpforms_upload_dir();
		$upload_url = $upload_dir['url'];
		$upload_url = trailingslashit( $upload_url );
		$upload_url = $upload_url . 'tmp/' . $tmp_name;

		return $upload_url;
	}

	public function get_mime_type( $tmp ) {
		$mime_type = wp_check_filetype( $tmp );
		$mime_type = $mime_type['type'];

		return $mime_type;
	}

	protected function validate_basic( $error ) {

		if ( $error === 0 || $error === 4 ) {
			return false;
		}

		$errors = [
			false,
			esc_html__( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'upload-fields-for-wpforms' ),
			esc_html__( 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.', 'upload-fields-for-wpforms' ),
			esc_html__( 'The uploaded file was only partially uploaded.', 'upload-fields-for-wpforms' ),
			esc_html__( 'No file was uploaded.', 'upload-fields-for-wpforms' ),
			'',
			esc_html__( 'Missing a temporary folder.', 'upload-fields-for-wpforms' ),
			esc_html__( 'Failed to write file to disk.', 'upload-fields-for-wpforms' ),
			esc_html__( 'File upload stopped by extension.', 'upload-fields-for-wpforms' ),
		];

		if ( array_key_exists( $error, $errors ) ) {
			return sprintf( /* translators: %s - error text. */
				esc_html__( 'File upload error. %s', 'upload-fields-for-wpforms' ),
				$errors[ $error ]
			);
		}

		return false;
	}

	protected function validate_size( $sizes = null ) {

		if ( $sizes === null && ! empty( $_FILES ) ) {
			$sizes = [];

			foreach ( $_FILES as $file ) {
				$sizes[] = $file['size'];
			}
		}

		if ( ! is_array( $sizes ) ) {
			return false;
		}

		$max_size = min( wp_max_upload_size(), $this->max_file_size() );

		foreach ( $sizes as $size ) {
			if ( $size > $max_size ) {
				return sprintf( /* translators: $s - allowed file size in MB. */
					esc_html__( 'File exceeds max size allowed (%s).', 'upload-fields-for-wpforms' ),
					size_format( $max_size )
				);
			}
		}

		return false;
	}

	public function max_file_size() {

		if ( ! empty( $this->field_data['max_size'] ) ) {

			// Strip any suffix provided (eg M, MB etc), which leaves us with the raw MB value.
			$max_size = preg_replace( '/[^0-9.]/', '', $this->field_data['max_size'] );

			return wpforms_size_to_bytes( $max_size . 'M' );
		}

		return wpforms_max_upload( true );
	}

	protected function validate_extension( $ext ) {

		// Make sure file has an extension first.
		if ( empty( $ext ) ) {
			return esc_html__( 'File must have an extension.', 'upload-fields-for-wpforms' );
		}

		// Validate extension against all allowed values.
		if ( ! in_array( $ext, $this->get_extensions(), true ) ) {
			return esc_html__( 'File type is not allowed.', 'upload-fields-for-wpforms' );
		}

		return false;
	}

	protected function get_extensions() {

		// Allowed file extensions by default.
		$default_extensions = $this->get_default_extensions();

		// Allowed file extensions.
		$extensions = ! empty( $this->field_data['extensions'] ) ? explode( ',', $this->field_data['extensions'] ) : $default_extensions;

		return wpforms_chain( $extensions )
			->map(
				static function ( $ext ) {

					return strtolower( preg_replace( '/[^A-Za-z0-9_-]/', '', $ext ) );
				}
			)
			->array_filter()
			->array_intersect( $default_extensions )
			->value();
	}

	protected function get_default_extensions() {

		return wpforms_chain( get_allowed_mime_types() )
			->array_keys()
			->implode( '|' )
			->explode( '|' )
			->array_diff( $this->denylist )
			->value();
	}

	protected function validate_wp_filetype_and_ext( $path, $name ) {

		$wp_filetype = wp_check_filetype_and_ext( $path, $name );

		$ext             = empty( $wp_filetype['ext'] ) ? '' : $wp_filetype['ext'];
		$type            = empty( $wp_filetype['type'] ) ? '' : $wp_filetype['type'];
		$proper_filename = empty( $wp_filetype['proper_filename'] ) ? '' : $wp_filetype['proper_filename'];

		if ( $proper_filename || ! $ext || ! $type ) {
			return esc_html__( 'File type is not allowed.', 'upload-fields-for-wpforms' );
		}

		return false;
	}

	public function get_tmp_dir() {

		$upload_dir = wpforms_upload_dir();
		$tmp_root   = $upload_dir['path'] . '/tmp';

		if ( ! file_exists( $tmp_root ) || ! wp_is_writable( $tmp_root ) ) {
			wp_mkdir_p( $tmp_root );
		}

		// Check if the index.html exists in the directory, if not - create it.
		wpforms_create_index_html_file( $tmp_root );

		return $tmp_root;
	}

	protected function get_tmp_file_name( $extension ) {

		return wp_hash( wp_rand() . microtime() . $this->form_id . $this->field_id ) . '.' . $extension;
	}

	protected function move_file( $path_from, $path_to ) {

		$this->create_dir( dirname( $path_to ) );

		if ( false === move_uploaded_file( $path_from, $path_to ) ) {
			wpforms_log(
				'Upload Error, could not upload file',
				$path_from,
				array(
					'type' => array( 'entry', 'error' ),
				)
			);

			return false;
		}

		$this->set_file_fs_permissions( $path_to );

		return $path_to;
	}

	public function set_file_fs_permissions( $path ) {

		$stat = stat( dirname( $path ) );

		@chmod( $path, $stat['mode'] & 0000666 );
	}

	protected function create_dir( $path ) {

		if ( ! file_exists( $path ) ) {
			wp_mkdir_p( $path );
		}

		// Check if the index.html exists in the path, if not - create it.
		wpforms_create_index_html_file( $path );

		return $path;
	}

	protected function clean_tmp_files() {

		$files = glob( trailingslashit( $this->get_tmp_dir() ) . '*' );

		if ( ! is_array( $files ) || empty( $files ) ) {
			return;
		}

		$lifespan = (int) apply_filters( 'wpforms_field_' . $this->type . '_clean_tmp_files_lifespan', DAY_IN_SECONDS );

		foreach ( $files as $file ) {
			if ( $file === 'index.html' || ! is_file( $file ) ) {
				continue;
			}

			// In some cases filemtime() can return false, in that case - pretend this is a new file and do nothing.
			$modified = (int) filemtime( $file );

			if ( empty( $modified ) ) {
				$modified = time();
			}

			if ( ( time() - $modified ) >= $lifespan ) {
				@unlink( $file );
			}
		}
	}

	protected function generate_file_data( $file ) {

		return [
			'name'           => sanitize_text_field( $file['file_name'] ),
			'value'          => esc_url_raw( $file['file_url'] ),
			'file'           => $file['file_name_new'],
			'file_original'  => $file['file_name'],
			'file_user_name' => sanitize_text_field( $file['file_user_name'] ),
			'ext'            => wpforms_chain( $file['file'] )->explode( '.' )->pop()->value(),
			'attachment_id'  => isset( $file['attachment_id'] ) ? absint( $file['attachment_id'] ) : 0,
			'id'             => $this->field_id,
			'type'           => $file['type'],
		];
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

}

Uploader::instance();