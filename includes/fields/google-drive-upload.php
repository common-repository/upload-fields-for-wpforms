<?php

namespace UPWPForms;

defined( 'ABSPATH' ) || exit;

class Google_Drive_Upload extends \WPForms_Field {

	public function init() {

		if ( ! class_exists( 'UPWPForms\Google_Drive_Uploader' ) ) {
			require_once UPWPFORMS_INCLUDES . '/class-google-drive-uploader.php';
		}

		// Define field type information.
		$this->name  = __( 'Google Drive Upload', 'upload-fields-for-wpforms' );
		$this->type  = 'google_drive_upload';
		$this->group = 'upload_fields';
		$this->icon  = 'fa-cloud-upload fa-lg';
		$this->order = 3;

		add_action( 'wpforms_builder_enqueues', [ $this, 'builder_scripts' ], 9 );
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ] );

		// Display values in a proper way
		add_filter( 'wpforms_html_field_value', [ $this, 'html_field_value' ], 10, 4 );
		add_filter( 'wpforms_plaintext_field_value', [ $this, 'plain_field_value' ], 10, 3 );
        add_filter( 'wpforms_pro_admin_entries_export_ajax_get_data', [ $this, 'export_value' ], 10, 2 );
	}

	// Frontend - Field display on the form front-end.
	public function field_display( $field, $deprecated, $form_data ) {
		$this->render_uploader( $field, $form_data );
	}

	public function plain_field_value( $value, $field, $form_data ) {
		return $this->html_field_value( $value, $field, $form_data, false );
	}

	public function html_field_value( $value, $field, $form_data, $type ) {

		if ( $this->type !== $field['type'] ) {
			return $value;
		}

		// Reset $value as WPForms can truncate the content in e.g. the Entries table
		if ( isset( $field['value'] ) ) {
			$value = $field['value'];
		}

		$as_html = ( in_array( $type, [ 'entry-single', 'entry-table', 'email-html', 'smart-tag' ] ) );

		$uploaded_files = $type === 'entry-table' ? array_slice( $field['value_raw'], 0, 3, true ) : $field['value_raw'];

		if ( empty( $uploaded_files ) ) {
			return $value;
		}
		// Render TEXT only
		if ( ! $as_html ) {

			if ( count( $uploaded_files ) < count( $field['value_raw'] ) ) {
				$value .= '...';
			}

			return $value;
		}

		$upload_folder = json_decode( $form_data['fields'][ $field['id'] ]['upload_folder'], 1 );

		$folder_name     = ! empty( $upload_folder['name'] ) ? $upload_folder['name'] : __( 'My Drive', 'upload-fields-for-wpforms' );
		$folder_id       = ! empty( $upload_folder['id'] ) ? $upload_folder['id'] : Account::get_active_account()['root_id'];
		$folder_location = sprintf( '<a style="text-decoration: none; font-weight: bold; color: #ff7f50" href="https://drive.google.com/drive/folders/%1$s"><strong>%2$s</strong></a>', $folder_id, $folder_name );
		$heading         = sprintf( '<h4 style="margin-bottom: 7px;margin-top: 0;">%1$d file(s) uploaded to %2$s</h4>', count( $field['value_raw'] ), $folder_location );

		// Render HTML
		ob_start();

		echo $heading;

		foreach ( $uploaded_files as $file ) { ?>
            <div style="display: flex; align-items: center; margin-bottom: 5px; padding: 5px; border: 1px solid #ddd;background-color: #FAFAFA;border-radius:3px;">
                <img height="16" src="<?php echo esc_url( $file['iconLink'] ); ?>"
                     style="margin-right:5px;height:auto;width:16px;max-width:16px;vertical-align: middle;" width="16">
                <a style="display:block;width: 100%;text-decoration: none; color: #ff7f50;vertical-align: middle;text-overflow: ellipsis;overflow: hidden;white-space: nowrap;"
                   href="<?php echo esc_url( $file['webViewLink'] ); ?>"
                   target="_blank"><?php echo esc_html( $file['name'] ); ?></a>
            </div>
		<?php }

		if ( count( $uploaded_files ) < count( $field['value_raw'] ) ) {
			echo '<p>...</p>';
		}

		//Remove any newlines
		return trim( preg_replace( '/\s+/', ' ', ob_get_clean() ) );
	}

	public function export_value( $export_data, $request_data ) {
		foreach ( $export_data as $row_id => &$entry ) {
			if ( 0 === $row_id ) {
				continue; // Skip Headers
			}

			foreach ( $entry as $field_id => &$value ) {
				if ( $request_data['form_data']['fields'][ $field_id ]['type'] !== $this->type ) {
					continue; // Skip data that isn't related to this custom field
				}
				$value = $this->plain_field_value( $value, $request_data['form_data']['fields'][ $field_id ], $request_data['form_data'] );
			}
		}

		return $export_data;
	}

	public function render_uploader( $field, $form_data = null ) {
		$accounts = Account::get_accounts();

		if ( empty( $accounts ) ) {

			if ( ! $form_data ) { ?>
                <p><? esc_html_e( 'Please connect your Google Drive account first.', 'upload-fields-for-wpforms' ) ?></p>

                <a class="add-account-btn wpforms-btn wpforms-btn-blue wpforms-btn-sm"
                   href="<?php echo admin_url( 'admin.php?page=wpforms-settings&view=integrations&wpforms-integration=google-drive' ); ?>">
					<?php esc_html_e( 'Connect Google Drive', 'upload-fields-for-wpforms' ); ?>
                </a>
			<?php }

			return;
		}

		$is_required   = ! empty( $field['required'] ) ? 'required' : '';
		$upload_folder = ! empty( $field['upload_folder'] ) ? json_decode( $field['upload_folder'], true ) : [];
		$folder_id     = ! empty( $upload_folder['id'] ) ? $upload_folder['id'] : 'root';
		$account_id     = ! empty( $upload_folder['accountId'] ) ? $upload_folder['accountId'] : '';

		$max_size   = ! empty( $field['max_size'] ) ? $field['max_size'] . ' MB' : '';
		$max_files  = ! empty( $field['max_files'] ) ? $field['max_files'] : 1;
		$extensions = ! empty( $field['extensions'] ) ? $field['extensions'] : '';
		$form_id    = ! empty( $form_data['id'] ) ? $form_data['id'] : '';

		?>
        <div class="upwpforms-uploader google-drive-upload"
             data-folder-id="<?php echo esc_attr( $folder_id ); ?>"
             data-account-id="<?php echo esc_attr( $account_id ); ?>"
             data-max-size="<?php echo esc_attr( $max_size ); ?>"
             data-max-files="<?php echo esc_attr( $max_files ); ?>"
             data-max-post-size="<?php echo esc_attr( wpforms_max_upload() ); ?>"
             data-extensions="<?php echo esc_attr( $extensions ); ?>"
             data-field-id="<?php echo esc_attr( $field['id'] ); ?>"
             data-form-id="<?php echo esc_attr( $form_id ) ?>"
        >
            <div class="upwpforms-uploader-body">
                <span class="uploader-text"><?php _e( "Drag and drop files here or", 'upload-fields-for-wpforms' ) ?></span>

                <div class="uploader-buttons">
                    <button type="button" class="upwpforms-uploader-browse">
                        <span><?php _e( 'Browse Files', 'upload-fields-for-wpforms' ) ?></span>
                    </button>
                </div>

            </div>

            <div class="file-list"></div>

            <div class="uploader-hint">
                <span class="max-files-label <?php echo $max_files < 2 ? 'hidden' : ''; ?>"><?php printf( __( "Upload upto %s Files.", 'upload-fields-for-wpforms' ), '<span class="number">' . $max_files . '</span>' ); ?></span>
                <span class="max-size-label <?php echo empty( $max_size ) ? 'hidden' : ''; ?>"><?php echo __( "Max File Size: ", 'upload-fields-for-wpforms' ) . '<span class="size">' . $max_size . '</span>'; ?></span>
            </div>
        </div>
		<?php

		$form_id    = ! empty( $form_data['id'] ) ? $form_data['id'] : 0;
		$field_id   = sprintf( 'wpforms-%d-field_%d', $form_id, $field['id'] );
		$field_name = sprintf( 'wpforms[fields][%d]', $field['id'] );

		printf( '<input type="text" style="position:absolute!important;clip:rect(0,0,0,0)!important;height:1px!important;width:1px!important;border:0!important;overflow:hidden!important;padding:0!important;margin:0!important;" name="%s" id="%s" class="upload-file-list" %s>', $field_name, $field_id, $is_required );
	}

	public function frontend_scripts() {
		wp_enqueue_style( 'upwpforms-frontend' );
		wp_enqueue_script( 'upwpforms-frontend' );
	}

	/**
	 * Admin
	 * -----------------------------------------------------------------------------------------------------------------
	 * Format field value which is stored.
	 *
	 * @param int $field_id field ID
	 * @param mixed $field_submit field value that was submitted
	 * @param array $form_data form data and settings
	 */
	public function format( $field_id, $field_submit, $form_data ) {
		if ( $this->type !== $form_data['fields'][ $field_id ]['type'] ) {
			return;
		}

		$name = ! empty( $form_data['fields'][ $field_id ]['label'] ) ? sanitize_text_field( $form_data['fields'][ $field_id ]['label'] ) : '';

		wpforms()->process->fields[ $field_id ] = [
			'name'  => $name,
			'value' => $field_submit,
			'id'    => absint( $field_id ),
			'type'  => $this->type,
		];
	}

	// Enqueue scripts
	public function builder_scripts() {
		wp_enqueue_style( 'upwpforms-swal2', UPWPFORMS_ASSETS . '/vendor/sweetalert2/sweetalert2.min.css', [], UPWPFORMS_VERSION );
		wp_enqueue_style( 'upwpforms-builder', UPWPFORMS_ASSETS . '/css/builder.css', [], UPWPFORMS_VERSION );

		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'upwpforms-swal2', UPWPFORMS_ASSETS . '/vendor/sweetalert2/sweetalert2.min.js', [ 'jquery' ], UPWPFORMS_VERSION, true );
		wp_enqueue_script( 'upwpforms-builder', UPWPFORMS_ASSETS . '/js/builder.js', [
			'jquery',
			'wp-i18n',
			'wp-util',
		], UPWPFORMS_VERSION, true );

		wp_localize_script( 'upwpforms-builder', 'upwpforms', [
			'ajaxurl'       => admin_url( 'admin-ajax.php' ),
			'pluginUrl'     => UPWPFORMS_URL,
			'adminUrl'      => admin_url(),
			'nonce'         => wp_create_nonce( 'upwpforms' ),
			'accounts'      => Account::get_accounts(),
			'activeAccount' => Account::get_active_account(),
		] );

	}

	// Field options panel inside the builder
	public function field_options( $field ) {

		// Options open markup.
		$this->field_option( 'basic-options', $field, [ 'markup' => 'open', ] );

		// Label
		$this->field_option( 'label', $field );

		// Description.
		$this->field_option( 'description', $field );

		// Upload folder
		$lbl = $this->field_element(
			'label',
			$field,
			array(
				'slug'    => 'upload_folder',
				'value'   => esc_html__( 'Upload Folder', 'upload-fields-for-wpforms' ),
				'tooltip' => esc_html__( 'Select the upload folder where the files will be stored.', 'upload-fields-for-wpforms' ),
			),
			false
		);

		$upload_folder_data = ! empty( $field['upload_folder'] ) ? json_decode( $field['upload_folder'], true ) : [];

		ob_start(); ?>

        <div class="upwpforms-folder-item <?php echo ! empty( $upload_folder_data ) ? 'active' : ''; ?>">
            <img class="folder-item-icon"
                 src="<?php echo ! empty( $upload_folder_data ) ? $upload_folder_data['iconLink'] : UPWPFORMS_ASSETS . '/images/my-drive.svg'; ?>">
            <span class="folder-item-name"><?php echo ! empty( $upload_folder_data ) ? $upload_folder_data['name'] : 'My Drive'; ?></span>

            <button type="button" class="upwpforms-btn folder-item-remove">
                <i class="dashicons dashicons-trash"></i>
                <span><?php esc_html_e( 'Remove', 'upload-fields-for-wpforms' ); ?></span>
            </button>
        </div>

        <button type="button" class="upwpforms-btn upwpforms-select-folder-btn"
                data-id="<?php echo esc_attr( $field['id'] ); ?>">
			<?php ! empty( $upload_folder_data ) ? esc_html_e( 'Change Folder', 'upload-fields-for-wpforms' ) : esc_html_e( 'Select Upload Folder', 'upload-fields-for-wpforms' ); ?>
        </button>
		<?php

		$btn_container = ob_get_clean();

		$fld = $this->field_element(
			'text',
			$field,
			[
				'class' => 'upload_folder',
				'slug'  => 'upload_folder',
				'name'  => __( 'Upload Folder', 'upload-fields-for-wpforms' ),
				'type'  => 'hidden',
				'value' => ! empty( $field['upload_folder'] ) ? $field['upload_folder'] : '',
			],
			false
		);

		$this->field_element( 'row', $field, [
			'slug'    => 'upload_folder',
			'content' => $lbl . $btn_container . $fld,
		] );


		// Allowed extensions.
		$lbl = $this->field_element(
			'label',
			$field,
			array(
				'slug'    => 'extensions',
				'value'   => esc_html__( 'Allowed File Extensions', 'upload-fields-for-wpforms' ),
				'tooltip' => esc_html__( 'Enter the comma seperated extensions you would like to allow to upload.', 'upload-fields-for-wpforms' ),
			),
			false
		);

		$fld = $this->field_element(
			'text',
			$field,
			array(
				'slug'  => 'extensions',
				'value' => ! empty( $field['extensions'] ) ? $field['extensions'] : '',
			),
			false
		);

		$this->field_element(
			'row',
			$field,
			array(
				'slug'    => 'extensions',
				'content' => $lbl . $fld,
			)
		);

		// Max file size.
		$lbl = $this->field_element(
			'label',
			$field,
			array(
				'slug'    => 'max_size',
				'value'   => esc_html__( 'Max File Size (MB)', 'upload-fields-for-wpforms' ),
				/* translators: %s - max upload size. */
				'tooltip' => sprintf( esc_html__( 'Enter the max size of each file, in megabytes, to allow. If left blank, the value defaults to the maximum size the server allows which is %s.', 'upload-fields-for-wpforms' ), wpforms_max_upload() ),
			),
			false
		);

		$fld = $this->field_element(
			'text',
			$field,
			array(
				'slug'  => 'max_size',
				'type'  => 'number',
				'attrs' => array(
					'min'     => 1,
					'max'     => 512,
					'step'    => 1,
					'pattern' => '[0-9]',
				),
				'value' => ! empty( $field['max_size'] ) ? abs( $field['max_size'] ) : '',
			),
			false
		);

		$this->field_element(
			'row',
			$field,
			array(
				'slug'    => 'max_size',
				'content' => $lbl . $fld,
			)
		);

		// Max file number.
		$lbl = $this->field_element(
			'label',
			$field,
			array(
				'slug'    => 'max_files',
				'value'   => esc_html__( 'Max File Uploads', 'upload-fields-for-wpforms' ),
				'tooltip' => esc_html__( 'Enter the max number of files to allow. If left blank, the value defaults to 1.', 'upload-fields-for-wpforms' ),
			),
			false
		);

		$fld = $this->field_element(
			'text',
			$field,
			array(
				'slug'  => 'max_files',
				'type'  => 'number',
				'attrs' => array(
					'min'     => 1,
					'max'     => 100,
					'step'    => 1,
					'pattern' => '[0-9]',
				),
				'value' => ! empty( $field['max_files'] ) ? absint( $field['max_files'] ) : 1,
			),
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'max_files',
				'content' => $lbl . $fld,
			]
		);

		// Required toggle.
		$this->field_option( 'required', $field );

		// Options close markup.
		$this->field_option(
			'basic-options', $field, [ 'markup' => 'close', ]
		);

		// Advanced field options

		// Options open markup.
		$this->field_option(
			'advanced-options',
			$field,
			[ 'markup' => 'open', ]
		);

		// Create Entry Folder Toggle
		$fld = $this->field_element(
			'toggle',
			$field,
			array(
				'slug'    => 'create_entry_folder',
				'value'   => ! empty( $field['create_entry_folder'] ) ? $field['create_entry_folder'] : '',
				'desc'    => esc_html__( 'Create Entry Folder', 'upload-fields-for-wpforms' ),
				'tooltip' => esc_html__( 'Create a folder for each entry and upload files to that folder.', 'upload-fields-for-wpforms' ),
			),
			false
		);

		$this->field_element(
			'row',
			$field,
			array(
				'slug'    => 'create_entry_folder',
				'content' => $fld,
			)
		);

		// Entry Folder Name Template if create_entry_folder is enabled
		$lbl = $this->field_element(
			'label',
			$field,
			array(
				'slug'    => 'entry_folder_name_template',
				'value'   => esc_html__( 'Entry Folder Name Template', 'upload-fields-for-wpforms' ),
				'tooltip' => esc_html__( 'Enter the template for the entry folder name.', 'upload-fields-for-wpforms' ),
			),
			false
		);

		$fld = $this->field_element(
			'text',
			$field,
			array(
				'slug'  => 'entry_folder_name_template',
				'value' => ! empty( $field['entry_folder_name_template'] ) ? $field['entry_folder_name_template'] : 'Entry {entry_id} - {form_name}',
			),
			false
		);

		ob_start(); ?>
        <div class="upwpforms-tags">
            <p>
                <strong><?php esc_html_e( 'You can use the following placeholders:', 'upload-fields-for-wpforms' ); ?></strong>
                <br>

                <i>{form_id}, {form_name}, {entry_id}, {user_login}, {user_email}, {first_name}, {last_name},
                    {display_name}, {user_id}, {date}, {time}</i>
                <br>
                <br>
                <strong><?php esc_html_e( 'You can also use the form FIELD ID to rename the upload folder with the form field values.', 'upload-fields-for-wpforms' ); ?></strong>
                <br>
                <i><?php esc_html_e( 'For example: If you have a field with the ID 1, you can use the tag {field_id_1} to rename the upload folder.', 'upload-fields-for-wpforms' ); ?></i>
            </p>

        </div>
		<?php
		$template_tags = ob_get_clean();

		$this->field_element(
			'row',
			$field,
			array(
				'slug'    => 'entry_folder_name_template',
				'content' => $lbl . $fld . $template_tags,
			)
		);

		// Merge Entry Folders
		$fld = $this->field_element(
			'toggle',
			$field,
			array(
				'slug'    => 'merge_entry_folders',
				'value'   => ! empty( $field['merge_entry_folders'] ) ? $field['merge_entry_folders'] : '',
				'desc'    => esc_html__( 'Merge Entry Folders', 'upload-fields-for-wpforms' ),
				'tooltip' => esc_html__( 'Merge all the files uploaded in the entry folder into a single folder.', 'upload-fields-for-wpforms' ),
			),
			false
		);

		$this->field_element(
			'row',
			$field,
			array(
				'slug'    => 'merge_entry_folders',
				'content' => $fld,

			)
		);


		// Hide label.
		$this->field_option( 'label_hide', $field );

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Options close markup.
		$this->field_option(
			'advanced-options',
			$field,
			[ 'markup' => 'close', ]
		);
	}

	// Field preview inside the builder.
	public function field_preview( $field ) {

		// Label.
		$this->field_preview_option( 'label', $field );

		$this->render_uploader( $field );

		// Description.
		$this->field_preview_option( 'description', $field );
	}

}

new Google_Drive_Upload();