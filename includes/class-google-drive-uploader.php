<?php

namespace UPWPForms;

defined( 'ABSPATH' ) || exit();

class Google_Drive_Uploader {

	/**
	 * @var null
	 */
	protected static $instance = null;

	public $form_data;
	public $form_id;
	public $field_id;
	public $field_data;

	/**
	 * @throws \Exception
	 */
	public function __construct() {

		// Get upload direct url
		add_action( 'wp_ajax_upwpforms_get_google_drive_upload_url', [ $this, 'get_upload_url' ] );
		add_action( 'wp_ajax_nopriv_upwpforms_get_google_drive_upload_url', [ $this, 'get_upload_url' ] );

		add_filter( 'wpforms_process_after_filter', [ $this, 'upload_complete' ], 10, 3 );

		// May create entry folder
		add_action( 'wpforms_process_complete', [ $this, 'may_create_entry_folder' ], 10, 4 );

	}

	public function may_create_entry_folder( $fields, $entry, $form_data, $entry_id ) {
		$gd_fields = [];

		foreach ( $fields as $field ) {
			if ( $field['type'] == 'google_drive_upload' ) {
				$gd_fields[ $field['id'] ] = $field;
			}
		}

		if ( ! empty( $gd_fields ) ) {
			foreach ( $gd_fields as $field_id => $field ) {
				$files = $field['value_raw'];


				if ( empty( $files ) ) {
					continue;
				}

				$field_data = $form_data['fields'][ $field_id ];

				$create_entry_folder = ! empty( $field_data['create_entry_folder'] );

				if ( ! $create_entry_folder ) {
					continue;
				}

				$entry_folder_name_template = ! empty( $field_data['entry_folder_name_template'] ) ? $field_data['entry_folder_name_template'] : 'Entry {entry_id} - {form_name}';

				$tag_data = [
					'name' => $entry_folder_name_template,
					'form' => [
						'form_name' => $form_data['settings']['form_title'],
						'form_id'   => $form_data['id'],
						'entry_id'  => $entry_id,
					]
				];

				// Add user and post tags
				if ( upwpforms_contains_tags( 'user', $entry_folder_name_template ) ) {
					if ( is_user_logged_in() ) {
						$tag_data['user'] = get_userdata( get_current_user_id() );
					}
				}

				$extra_tags = [];

				foreach ( $fields as $tagField ) {
					$field_value = $tagField['value'];

					if ( is_array( $field_value ) ) {
						$field_value = implode( ', ', $field_value );
					}

					$extra_tags[ '{field_id_' . $tagField['id'] . '}' ] = $field_value;
				}

				$folder_name = upwpforms_replace_template_tags( $tag_data, $extra_tags );

				$upload_folder = ! empty( $field_data['upload_folder'] ) ? json_decode( $field_data['upload_folder'], 1 ) : [
					'id'        => 'root',
					'accountId' => '',
				];

				$merge_folders = isset( $field_data['merge_entry_folders'] ) ? filter_var( $field_data['merge_entry_folders'], FILTER_VALIDATE_BOOLEAN ) : false;

				$this->create_entry_folder_and_move( $files, $folder_name, $upload_folder, $merge_folders );
			}
		}
	}

	public function create_entry_folder_and_move( $files = [], $folder_name = '', $upload_folder = [], $merge_folders = false ) {
		$folder = [];

		// Check if folder is already exists
		if ( $merge_folders ) {
			$folder_exist = App::instance()->get_file_by_name( $folder_name, $upload_folder['id'] );

			if ( $folder_exist ) {
				$folder = $folder_exist;
			}

		}

		if ( empty( $folder ) ) {
			$folder = App::instance()->new_folder( $folder_name, $upload_folder['id'] );
		}

		App::instance()->move_file( $files, $folder['id'] );

		return $folder;
	}

	public function upload_complete( $fields, $entry, $form_data ) {

		if ( ! empty( wpforms()->get( 'process' )->errors[ $form_data['id'] ] ) ) {
			return $fields;
		}

		$this->form_data = $form_data;

		foreach ( $fields as $field_id => $field ) {
			if ( empty( $field['type'] ) || $field['type'] != 'google_drive_upload' ) {
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

			$files = ! empty( $field['value'] ) ? $field['value'] : [];

			$files = $this->sanitize_files_input( $files );

			if ( empty( $files ) ) {
				$fields[ $field_id ] = $field;
				continue;
			}

			$processed_field = $field;

			$data = [];

			foreach ( $files as $file ) {
				$data[] = $this->generate_file_data( $file );
			}

			$data                         = array_filter( $data );
			$processed_field['value_raw'] = $data;

			$processed_field['value']     = wpforms_chain( $data )
				->map(
					static function ( $file ) {
						return $file['webViewLink'];
					}
				)
				->implode( "\r\n" )
				->value();

			$fields[ $field_id ] = $processed_field;
		}

		return $fields;
	}

	protected function generate_file_data( $file ) {

		return [
			'id'          => sanitize_text_field( $file['id'] ),
			'name'        => sanitize_text_field( $file['name'] ),
			'size'        => absint( $file['size'] ),
			'iconLink'    => esc_url_raw( $file['iconLink'] ),
			'webViewLink' => esc_url_raw( $file['webViewLink'] ),
			'parents'     => ! empty( $file['parents'] ) ? $file['parents'] : [],
		];
	}

	private function sanitize_files_input( $files ) {
		$files = json_decode( $files, true );

		if ( empty( $files ) || ! is_array( $files ) ) {
			return [];
		}

		return array_filter( array_map( [ $this, 'sanitize_file' ], $files ) );
	}

	private function sanitize_file( $file ) {

		if ( empty( $file['name'] ) ) {
			return [];
		}

		$sanitized_file = [];
		$rules          = [
			'id'          => 'sanitize_text_field',
			'name'        => 'sanitize_file_name',
			'size'        => 'absint',
			'iconLink'    => 'esc_url_raw',
			'webViewLink' => 'esc_url_raw',
			'parents'     => 'wp_unslash',
		];

		foreach ( $rules as $rule => $callback ) {
			$file_attribute          = $file[ $rule ] ?? '';
			$sanitized_file[ $rule ] = $callback( $file_attribute );
		}

		return $sanitized_file;
	}

	public function get_upload_url() {
		$default_error = esc_html__( 'Something went wrong, please try again.', 'upload-fields-for-wpforms' );

		if ( empty( $_POST['name'] ) ) {
			wp_send_json_error( $default_error, 403 );
		}

		$extension = strtolower( pathinfo( $_POST['name'], PATHINFO_EXTENSION ) );

		$errors = wpforms_chain( array() )
			->array_merge( (array) $this->validate_size() )
			->array_merge( (array) $this->validate_extension( $extension ) )
			->array_filter()
			->array_unique()
			->value();

		if ( count( $errors ) ) {
			wp_send_json_error( implode( ',', $errors ), 400 );
		}

		$account_id = ! empty( $_POST['account_id'] ) ? sanitize_text_field( $_POST['account_id'] ) : '';

		$url = App::instance($account_id)->get_resume_url( $_POST );

		if ( ! $url ) {
			wp_send_json_error( $default_error, 400 );
		}

		wp_send_json_success( $url );
	}

	protected function validate_size() {
		$size     = ! empty( $_POST['size'] ) ? (int) $_POST['size'] : 0;
		$max_size = $this->max_file_size();

		if ( $size > $max_size ) {
			return sprintf( /* translators: $s - allowed file size in MB. */
				esc_html__( 'File exceeds max size allowed (%s).', 'upload-fields-for-wpforms' ),
				size_format( $max_size )
			);
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

		$extensions = ! empty( $this->field_data['extensions'] ) ? explode( ',', $this->field_data['extensions'] ) : [];

		if ( empty( $extensions ) ) {
			return false;
		}

		// Make sure file has an extension first.
		if ( empty( $ext ) ) {
			return esc_html__( 'File must have an extension.', 'upload-fields-for-wpforms' );
		}

		//remove spaces from extensions
		$extensions = array_map( 'trim', $extensions );

		// Validate extension against all allowed values.
		if ( ! in_array( $ext, $extensions, true ) ) {
			return esc_html__( 'File type is not allowed.', 'upload-fields-for-wpforms' );
		}

		return false;
	}

	/**
	 * @return Google_Drive_Uploader|null
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

Google_Drive_Uploader::instance();