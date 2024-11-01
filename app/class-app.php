<?php

namespace UPWPForms;

defined( 'ABSPATH' ) || exit();


class App {

	/**
	 * Google API Client
	 *
	 * @var \Exception|false|\IGDGoogle_Client|mixed
	 */
	public $client;

	/**
	 * Google Drive API Service
	 *
	 * @var \IGDGoogle_Service_Drive
	 */
	public $service;

	/**
	 * @var null
	 */
	protected static $instance = null;

	public $account_id = null;

	public $file_fields = 'capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),shared,sharedWithMeTime,description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey';
	public $list_fields = 'files(capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),shared,sharedWithMeTime,description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey),nextPageToken';


	/**
	 * @throws \Exception
	 */
	public function __construct( $account_id = null ) {

		if ( empty( $account_id ) && ! empty( Account::get_active_account()['id'] ) ) {
			$account_id = Account::get_active_account()['id'];
		}

		$this->account_id = $account_id;

		$this->client = Client::instance( $account_id )->get_client();

		if ( ! class_exists( 'IGDGoogle_Service_Drive' ) ) {
			require_once UPWPFORMS_PATH . '/vendors/Google-sdk/src/Google/Service/Drive.php';
		}

		$this->service = new \IGDGoogle_Service_Drive( $this->client );
	}

	/**
	 * Get files
	 *
	 * @param array $query
	 * @param null $folder
	 *
	 * @return array
	 */
	public function get_files( $query = [], $folder_id = null ) {

		$active_account = Account::get_active_account();

		$folder_id = empty( $folder_id ) ? $active_account['root_id'] : $folder_id;

		$default_query = array(
			'pageSize'                  =>  500,
			'orderBy'                   => "folder,name",
			'q'                         => "trashed=false and '$folder_id' in parents and mimeType = 'application/vnd.google-apps.folder'",
			'fields'                    => 'nextPageToken, files(id, name, iconLink, mimeType, modifiedTime, createdTime, starred, shared, ownedByMe, parents, webViewLink, webContentLink, thumbnailLink, capabilities)',
			'supportsAllDrives'         => true,
			'includeItemsFromAllDrives' => true,
		);

		$query = wp_parse_args( $query, $default_query );

		// If is search or no cache exits get the files directly from server

		$next_page_token = null;
		$items           = [];

		do {
			$params = $query;

			if ( ! empty( $next_page_token ) ) {
				$params['pageToken'] = $next_page_token;
			}

			try {
				$response = $this->service->files->listFiles( $params );
				$items    = array_merge( $items, $response->getFiles() );

					$next_page_token = $response->getNextPageToken();
			} catch ( \Exception $e ) {

				error_log( 'Integrate Google Drive: ' . sprintf( 'API Error On Line %s: %s', __LINE__, $e->getMessage() ) );

				return [ 'error' => sprintf( '<strong>%s</strong> - %s', __( 'Server error', 'upload-fields-for-wpforms' ), __( 'Couldn\'t connect to the Google drive API server.', 'upload-fields-for-wpforms' ) ) ];
			}
		} while ( ! empty( $next_page_token ) );


		if ( empty( $items ) ) {
			return [];
		}

		$files = [];

		//Filter computer files
		if ( 'computers' == $folder_id ) {
			$computer_files = [];

			foreach ( $items as $item ) {
				if ( empty( $item->getParents() ) ) {
					$computer_files[] = $item;
				}
			}

			$items = $computer_files;
		}

		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				$files[] = $item->toSimpleObject();
			}
		}


		return $files;
	}

	public function get_computers_files() {
		$args['q'] = "'me' in owners and mimeType='application/vnd.google-apps.folder' and trashed=false";

		return $this->get_files( $args, 'computers' );
	}


	public function get_starred_files() {
		$args['q'] = "starred=true";

		return $this->get_files( $args, 'starred' );
	}

	public function get_shared_files() {
		$args['q'] = "sharedWithMe=true";

		return $this->get_files( $args, 'shared' );
	}

	public function get_search_files( $query ) {

		$params = array(
			'orderBy' => "", // Order by not supported in fullText search
		);

		$files = [];

		$params['q'] = "fullText contains '{$query}' and trashed = false";
		$items       = $this->get_files( $params, '', true );
		if ( ! empty( $items ) ) {
			$files = array_merge( $files, $items );
		}

		return $files;

	}

	public function get_shared_drives() {

		$shared_drives = [];
		$params        = [
			'fields'   => '*',
			'pageSize' => 50,
		];

		$next_page_token = null;

		// Get all files in folder
		while ( $next_page_token || null === $next_page_token ) {
			try {
				if ( null !== $next_page_token ) {
					$params['pageToken'] = $next_page_token;
				}

				$more_drives     = $this->service->drives->listDrives( $params );
				$shared_drives   = array_merge( $shared_drives, $more_drives->getDrives() );
				$next_page_token = ( null !== $more_drives->getNextPageToken() ) ? $more_drives->getNextPageToken() : false;
			} catch ( \Exception $ex ) {
				error_log( $ex->getMessage() );

				return [];
			}
		}

		$files = [];

		if ( ! empty( $shared_drives ) ) {

			foreach ( $shared_drives as $drive ) {
				$drive = $drive->toSimpleObject();

				$file = [
					'id'            => $drive->id,
					'name'          => $drive->name,
					'iconLink'      => $drive->backgroundImageLink,
					'thumbnailLink' => $drive->backgroundImageLink,
					'created'       => $drive->createdTime,
					'hidden'        => $drive->hidden,
					'shared-drives' => true,
					'accountId'     => $this->account_id,
					'type'          => 'application/vnd.google-apps.folder',
					'parents'       => [ 'shared-drives' ],
				];

				$file['permissions'] = $drive->capabilities;

				$files[] = $file;
			}
		}

		return $files;

	}

	/**
	 * Get file item by file id
	 *
	 * @param $id
	 *
	 * @return array|false|mixed|void
	 */
	public function get_file_by_id( $id ) {

		// If no cache file then get file from server

		$item = $this->service->files->get( $id, [
			'supportsAllDrives' => true,
			'fields'            => '*'
		] );

		// Skip errors if folder is not found
		if ( ! is_object( $item ) || ! method_exists( $item, 'getId' ) ) {
			return;
		}

		return $item;
	}

	public function get_file_by_name( $name, $parent_folder = '' ) {
		$folder_id = isset( $parent_folder['id'] ) ? $parent_folder['id'] : $parent_folder;

		$args = [
			'fields'   => $this->list_fields,
			'pageSize' => 1,
			'q'        => "name = '{$name}' and trashed = false ",
		];

		if ( ! empty( $folder_id ) ) {
			$args['q'] .= " and '{$folder_id}' in parents";
		}

		try {
			$response = $this->service->files->listFiles( $args );

			if ( ! method_exists( $response, 'getFiles' ) ) {
				return false;
			}

			$files = $response->getFiles();
		} catch ( \Exception $e ) {
			return false;
		}

		if ( empty( $files ) ) {
			return false;
		}

		return $files[0]->toSimpleObject();

	}

	public function move_file( $files, $newParentId ) {

		try {

			$emptyFileMetadata = new \IGDGoogle_Service_Drive_DriveFile();

			if ( ! empty( $files ) ) {
				foreach ( $files as $file ) {

					$previousParents = join( ',', $file['parents'] );

					// Move the file to the new folder
					$this->service->files->update( $file['id'], $emptyFileMetadata, array(
						'addParents'    => $newParentId,
						'removeParents' => $previousParents,
						'fields'        => '*'
					) );

				}
			}

		} catch ( \Exception $e ) {
			return "An error occurred: " . $e->getMessage();
		}
	}

	public function get_resume_url( $data ) {
		$name      = ! empty( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$size      = ! empty( $data['size'] ) ? sanitize_text_field( $data['size'] ) : '';
		$type      = ! empty( $data['type'] ) ? sanitize_text_field( $data['type'] ) : '';
		$folder_id = ! empty( $data['folder_id'] ) ? sanitize_text_field( $data['folder_id'] ) : '';

		$file = new \IGDGoogle_Service_Drive_DriveFile();
		$file->setName( $name );
		$file->setMimeType( $type );
		$file->setParents( [ $folder_id ] );

		$this->client->setDefer( true );

		$request = $this->service->files->create( $file, [
			'fields'            => '*',
			'supportsAllDrives' => true
		] );

		$request_headers           = $request->getRequestHeaders();
		$request_headers['Origin'] = $_SERVER['HTTP_ORIGIN'];
		$request->setRequestHeaders( $request_headers );

		$chunkSizeBytes = 5 * 1024 * 1024;
		$media          = new \IGDGoogle_Http_MediaFileUpload(
			$this->client,
			$request,
			$type,
			null,
			true,
			$chunkSizeBytes
		);

		$media->setFileSize( $size );

		try {
			return $media->getResumeUri();
		} catch ( \Exception $exception ) {
			return [
				'error' => $exception->getMessage(),
			];
		}
	}

	public function new_folder( $folder_name, $parent_id ) {

		if ( empty( $parent_id ) ) {
			$parent_id = $this->get_root_id();
		}

		$params = [
			'fields'            => $this->file_fields,
			'supportsAllDrives' => true,
		];

		$request = $this->service->files->create( new \IGDGoogle_Service_Drive_DriveFile( [
			'name'     => $folder_name,
			'parents'  => [ $parent_id ],
			'mimeType' => 'application/vnd.google-apps.folder'
		] ), $params );

		// add new folder to cache
		$item = igd_file_map( $request, $this->account_id );

		// Insert log
		do_action( 'igd_insert_log', 'folder', $item['id'], $this->account_id );

		return $item;
	}

	public function get_root_id() {
		if ( ! empty( $this->account_id ) ) {
			$account = Account::get_accounts( $this->account_id );

			return $account['root_id'];
		}

		return 'root';

	}

	/**
	 * @return App|null
	 */
	public static function instance( $account_id = null ) {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self( $account_id );
		}

		return self::$instance;
	}
}