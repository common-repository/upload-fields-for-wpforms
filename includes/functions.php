<?php

use UPWPForms\Account;
use UPWPForms\App;

function upwpforms_get_breadcrumb( $folder ) {
	$active_account = Account::get_active_account();

	$account_id = ! empty( $folder['accountId'] ) ? $folder['accountId'] : $active_account['id'];

	if ( empty( $folder ) ) {
		return [];
	}

	$items = [ $folder['id'] => $folder['name'] ];

	if ( in_array( $folder['id'], [
		$active_account['root_id'],
		'computers',
		'shared-drives',
		'shared',
		'starred'
	] ) ) {
		return $items;
	}


	if ( ! isset( $folder['parents'] ) ) {
		$folder = App::instance( $account_id )->get_file_by_id( $folder['id'] );
	}

	if ( ! empty( $folder['parents'] ) ) {

		if ( in_array( 'shared-drives', $folder['parents'] ) ) {
			$items['shared-drives'] = __( 'Shared Drives', 'upload-fields-for-wpforms' );

			$items = array_reverse( $items );

			return $items;
		}

		$item  = App::instance( $account_id )->get_file_by_id( $folder['parents'][0] );
		$items = array_merge( upwpforms_get_breadcrumb( $item ), $items );
	}

	return $items;
}

function upwpforms_contains_tags( $type = '', $template = '' ) {
	// Define tags
	$user_tags = [
		'{user_login}',
		'{user_email}',
		'{first_name}',
		'{last_name}',
		'{display_name}',
		'{user_id}',
		'{user_role}',
	];


	if ( $type == 'user' ) {
		return array_reduce( $user_tags, function ( $carry, $item ) use ( $template ) {
				return $carry || strpos( $template, $item ) !== false;
			}, false );
	}

	return false;

}

function upwpforms_replace_template_tags( $data, $extra_tag_values = [] ) {

	$name_template = ! empty( $data['name'] ) ? $data['name'] : 'Entry {entry_id} - {form_name}';

	$date      = date( 'Y-m-d' );
	$time      = date( 'H:i' );

	$search = [
		'{date}',
		'{time}',
	];

	$replace = [
		$date,
		$time,
	];

	$name = str_replace( $search, $replace, $name_template );

	// Handle form data
	if ( ! empty( $data['form'] ) ) {
		$form = $data['form'];

		$search = array_merge( $search, [
			'{form_name}',
			'{form_id}',
			'{entry_id}',
		] );

		$replace = array_merge( $replace, [
			$form['form_name'],
			$form['form_id'],
			! empty( $form['entry_id'] ) ? $form['entry_id'] : '',
		] );

		$name = str_replace( $search, $replace, $name );

	}

	// Handle user data
	if ( ! empty( $data['user'] ) ) {
		$user = $data['user'];


		$user_login   = $user->user_login;
		$user_email   = $user->user_email;
		$display_name = $user->display_name;
		$first_name   = $user->first_name;
		$last_name    = $user->last_name;
		$user_role    = ! empty( $user->roles ) ? implode( ', ', $user->roles ) : '';

		$search = array_merge( $search, [
			'{user_id}',
			'{user_login}',
			'{user_email}',
			'{display_name}',
			'{first_name}',
			'{last_name}',
		] );

		$replace = array_merge( $replace, [
			$user->ID,
			$user_login,
			$user_email,
			$display_name,
			$first_name,
			$last_name,
			$user_role,
		] );

		$name = str_replace( $search, $replace, $name );
	}

	// Handle extra tag values
	if ( ! empty( $extra_tag_values ) ) {
		$name = str_replace( array_keys( $extra_tag_values ), array_values( $extra_tag_values ), $name );
	}

	return $name;
}