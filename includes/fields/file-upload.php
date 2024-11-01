<?php

namespace UPWPForms;

defined( 'ABSPATH' ) || exit;

class File_Upload extends \WPForms_Field {

	public function init() {

		if ( ! class_exists( 'UPWPForms\Uploader' ) ) {
			include_once UPWPFORMS_INCLUDES . '/class-uploader.php';
		}

		// Define field type information.
		$this->name  = __( 'File Upload', 'upload-fields-for-wpforms' );
		$this->type  = 'upwpforms_file_upload';
		$this->group = 'upload_fields';
		$this->icon  = 'fa-lg fa-upload';
		$this->order = 1;

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

		if ( empty( $field['value'] ) || $this->type !== $field['type'] ) {
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

		// Render HTML
		ob_start();

		foreach ( $uploaded_files as $file ) { ?>
            <div style="display: flex; align-items: center; margin-bottom: 5px; padding: 5px; border: 1px solid #ddd;background-color: #FAFAFA;border-radius:3px;">
				<?php $this->file_icon_html( $file ); ?>
                <a rel="noopener noreferrer"
                   style="display:block;width: 100%;text-decoration: none; color: #ff7f50;vertical-align: middle;text-overflow: ellipsis;overflow: hidden;white-space: nowrap;"
                   href="<?php echo esc_url( $file['value'] ); ?>"
                   target="_blank"><?php echo esc_html( $file['file_original'] ); ?></a>
            </div>
			<?php
		}

		if ( count( $uploaded_files ) < count( $field['value_raw'] ) ) {
			echo esc_html( '...' );
		}

		//Remove any newlines
		return trim( preg_replace( '/\s+/', ' ', ob_get_clean() ) );
	}

	public function file_icon_html( $file ) {

		$src       = $file['value'];
		$ext_types = wp_get_ext_types();

		if ( ! in_array( $file['ext'], $ext_types['image'], true ) ) {

			$src = wp_mime_type_icon( wp_ext2type( $file['ext'] ) );
		} elseif ( ! empty( $file['attachment_id'] ) ) {

			$image = wp_get_attachment_image_src( $file['attachment_id'], [ 16, 16 ], true );
			$src   = $image ? $image[0] : $src;
		}

		printf( '<span class="file-icon"><img src="%s" style="margin-right:5px;height:auto;width:16px;max-width:16px;vertical-align: middle;" width="16" height="16"  /></span>', esc_url( $src ) );
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
		$is_required = ! empty( $field['required'] ) ? 'required' : '';

		$max_size      = ! empty( $field['max_size'] ) ? $field['max_size'] . ' MB' : '';
		$max_files     = ! empty( $field['max_files'] ) ? $field['max_files'] : 1;
		$extensions    = ! empty( $field['extensions'] ) ? $field['extensions'] : '';
		$media_library = ! empty( $field['media_library'] ) ? 1 : '';
		$form_id       = ! empty( $form_data['id'] ) ? $form_data['id'] : '';

		?>
        <div class="upwpforms-uploader upwpforms-file-upload"
             data-max-size="<?php echo esc_attr( $max_size ); ?>"
             data-max-files="<?php echo esc_attr( $max_files ); ?>"
             data-max-post-size="<?php echo esc_attr( wpforms_max_upload() ); ?>"
             data-extensions="<?php echo esc_attr( $extensions ); ?>"
             data-media-library="<?php echo esc_attr( $media_library ); ?>"
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
                <span class="max-files-label <?php echo esc_attr( $max_files < 2 ? 'hidden' : '' ); ?>"><?php printf( __( "Upload upto %s Files.", 'upload-fields-for-wpforms' ), '<span class="number">' . $max_files . '</span>' ); ?></span>
                <span class="max-size-label <?php echo esc_attr( empty( $max_size ) ? 'hidden' : '' ); ?>"><?php printf( __( "Max File Size: %s", 'upload-fields-for-wpforms' ), '<span class="size">' . $max_size . '</span>' ); ?></span>
            </div>
        </div>
		<?php
		$form_id  = ! empty( $form_data['id'] ) ? $form_data['id'] : 0;
		$field_id = sprintf( 'wpforms-%d-field_%d', $form_id, $field['id'] );

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

	// Field options panel inside the builder
	public function field_options( $field ) {

		// Options open markup.
		$this->field_option( 'basic-options', $field, [ 'markup' => 'open', ] );

		// Label
		$this->field_option( 'label', $field );

		// Description.
		$this->field_option( 'description', $field );

		// Allowed extensions.
		$lbl = $this->field_element( 'label', $field, array(
			'slug'    => 'extensions',
			'value'   => esc_html__( 'Allowed File Extensions', 'upload-fields-for-wpforms' ),
			'tooltip' => esc_html__( 'Enter the comma seperated extensions you would like to allow to upload.', 'upload-fields-for-wpforms' ),
		), false );

		$fld = $this->field_element( 'text', $field, array(
			'slug'  => 'extensions',
			'value' => ! empty( $field['extensions'] ) ? $field['extensions'] : '',
		), false );

		$this->field_element( 'row', $field, array(
			'slug'    => 'extensions',
			'content' => $lbl . $fld,
		) );

		// Max file size.
		$lbl = $this->field_element( 'label', $field, array(
			'slug'    => 'max_size',
			'value'   => esc_html__( 'Max File Size (MB)', 'upload-fields-for-wpforms' ),
			/* translators: %s - max upload size. */
			'tooltip' => sprintf( esc_html__( 'Enter the max size of each file, in megabytes, to allow. If left blank, the value defaults to the maximum size the server allows which is %s.', 'upload-fields-for-wpforms' ), wpforms_max_upload() ),
		), false );

		$fld = $this->field_element( 'text', $field, array(
			'slug'  => 'max_size',
			'type'  => 'number',
			'attrs' => array(
				'min'     => 1,
				'max'     => 512,
				'step'    => 1,
				'pattern' => '[0-9]',
			),
			'value' => ! empty( $field['max_size'] ) ? abs( $field['max_size'] ) : '',
		), false );

		$this->field_element( 'row', $field, array(
			'slug'    => 'max_size',
			'content' => $lbl . $fld,
		) );

		// Max Files
		$lbl = $this->field_element( 'label', $field, array(
			'slug'    => 'max_files',
			'value'   => esc_html__( 'Max File Uploads', 'upload-fields-for-wpforms' ),
			'tooltip' => esc_html__( 'Enter the max number of files to allow. If left blank, the value defaults to 1.', 'upload-fields-for-wpforms' ),
		), false );

		$fld = $this->field_element( 'text', $field, array(
			'slug'  => 'max_files',
			'type'  => 'number',
			'attrs' => array(
				'min'     => 1,
				'max'     => 100,
				'step'    => 1,
				'pattern' => '[0-9]',
			),
			'value' => ! empty( $field['max_files'] ) ? absint( $field['max_files'] ) : 1,
		), false );

		$this->field_element( 'row', $field, [
			'slug'    => 'max_files',
			'content' => $lbl . $fld,
		] );


		// Required toggle.
		$this->field_option( 'required', $field );

		// Options close markup.
		$this->field_option( 'basic-options', $field, [ 'markup' => 'close', ] );

		// Advanced field options

		// Options open markup.
		$this->field_option( 'advanced-options', $field, [ 'markup' => 'open', ] );

		// Media Library toggle.
		$fld = $this->field_element( 'toggle', $field, [
			'slug'    => 'media_library',
			'value'   => ! empty( $field['media_library'] ) ? 1 : '',
			'desc'    => esc_html__( 'Save files in Media Library', 'upload-fields-for-wpforms' ),
			'tooltip' => esc_html__( 'Check this option to store the uploaded files in the WordPress Media Library', 'upload-fields-for-wpforms' ),
		], false );

		$this->field_element( 'row', $field, [
			'slug'    => 'media_library',
			'content' => $fld,
		] );

		// Hide label.
		$this->field_option( 'label_hide', $field );

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Options close markup.
		$this->field_option( 'advanced-options', $field, [ 'markup' => 'close', ] );
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

new File_Upload();