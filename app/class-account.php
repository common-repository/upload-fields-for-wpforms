<?php

namespace UPWPForms;

defined( 'ABSPATH' ) || exit();


class Account {

	/**
	 * @param $id
	 *
	 * @return array|false|mixed|null
	 */
	public static function get_accounts( $id = null ) {
		$accounts = array_filter( (array) get_option( 'upwpforms_accounts' ) );

		if ( $id ) {
			return ! empty( $accounts[ $id ] ) ? $accounts[ $id ] : [];
		}

		return ! empty( $accounts ) ? $accounts : [];
	}

	/**
	 * Add new account or update previous account
	 *
	 * @param $data
	 */
	public static function update_account( $data ) {
		$accounts = self::get_accounts();

		$accounts[ $data['id'] ] = $data;

		update_option( 'upwpforms_accounts', $accounts );
	}

	public static function get_active_account() {
		$accounts = self::get_accounts();

		$cookie = isset( $_COOKIE['upwpforms_active_account'] ) ? $_COOKIE['upwpforms_active_account'] : null;

		if ( ! empty( $cookie ) ) {
			$cookie = str_replace( "\\\"", "\"", $cookie );

			$account = json_decode( $cookie, true );

			if ( ! empty( $account['id'] ) && empty( $accounts[ $account['id'] ] ) ) {
				setcookie( 'upwpforms_active_account', '', time() - 3600, '/' );
			} else {
				return $account;
			}
		}


		$account = @array_shift( $accounts );
		if ( ! empty( $account ) ) {
			return $account;
		}

		return [];
	}

	/**
	 * @param string $account_id
	 *
	 * @return bool
	 */
	public static function set_active_account( $account_id ) {
		$accounts = self::get_accounts();

		$account = [];

		if ( ! empty( $accounts[ $account_id ] ) ) {
			$account = $accounts[ $account_id ];

			setcookie( 'upwpforms_active_account', json_encode( $account ), time() + ( 86400 * 30 ), "/" ); // 86400 = 1 day
		} elseif ( ! empty( $accounts ) ) {
			$account = @array_shift( $accounts );

			setcookie( 'upwpforms_active_account', json_encode( $account ), time() + ( 86400 * 30 ), "/" ); // 86400 = 1 day
		} else {
			setcookie( 'upwpforms_active_account', '', time() - 3600, "/" );
		}

		return $account;
	}

	/**
	 * @param $account_id
	 *
	 * @return void
	 */
	public static function delete_account( $account_id ) {
		$accounts = self::get_accounts();

		$removed_account = $accounts[ $account_id ];

		//delete token
		$authorization = new Authorization( $removed_account );
		$authorization->remove_token();

		//remove account data from saved accounts
		unset( $accounts[ $account_id ] );

		$active_account = self::get_active_account();

		// Update active account
		if ( $account_id == $active_account['id'] ) {
			if ( count( $accounts ) ) {
				self::set_active_account( array_key_first( $accounts ) );
			}
		}

		//save updated accounts
		update_option( 'upwpforms_accounts', $accounts );
	}

	public static function get_root_id( $account_id = null ) {
		if ( ! $account_id ) {
			$active_account = self::get_active_account();

			if ( ! empty( $active_account ) ) {
				$account_id = $active_account['id'];
			}
		}

		$account = self::get_accounts( $account_id );

		return ! empty( $account['root_id'] ) ? $account['root_id'] : null;

	}

}

