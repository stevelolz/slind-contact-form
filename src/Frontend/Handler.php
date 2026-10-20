<?php
namespace Slind\ContactForm\Frontend;

use Slind\ContactForm\PostTypes\Registrar;
use Slind\ContactForm\Services\SubmissionService;
use Slind\ContactForm\Admin\Settings;

defined( 'ABSPATH' ) || exit;

final class Handler {
	public static function register_hooks() { add_action( 'admin_post_slind_submit_form', array( __CLASS__, 'handle' ) ); add_action( 'admin_post_nopriv_slind_submit_form', array( __CLASS__, 'handle' ) ); }
	public static function handle() {
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0; $form = get_post( $form_id ); if ( ! $form || Registrar::FORM !== $form->post_type || 'publish' !== $form->post_status ) { wp_die( esc_html__( 'Invalid form.', 'slind-contact-form' ), '', array( 'response' => 400 ) ); }
		$redirect = wp_validate_redirect( wp_get_referer(), home_url( '/' ) );
		if ( ! isset( $_POST['slind_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['slind_form_nonce'] ) ), 'slind_submit_' . $form_id ) ) { self::redirect( $redirect, $form_id, 'error' ); }
		if ( ! empty( $_POST['company_website'] ) || ! self::rate_limit( $form_id ) ) { self::redirect( $redirect, $form_id, 'success' ); }
		$captcha = self::verify_recaptcha(); if ( is_wp_error( $captcha ) ) { $token = wp_generate_password( 20, false ); set_transient( 'slind_form_error_' . $token, array( 'errors' => array(), 'values' => array() ), 10 * MINUTE_IN_SECONDS ); self::redirect( $redirect, $form_id, 'error', $token ); }
		$raw = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array(); $result = ( new SubmissionService() )->submit( $form_id, $raw );
		if ( is_wp_error( $result ) ) { $data = $result->get_error_data(); $token = wp_generate_password( 20, false ); set_transient( 'slind_form_error_' . $token, is_array( $data ) ? $data : array(), 10 * MINUTE_IN_SECONDS ); self::redirect( $redirect, $form_id, 'error', $token ); }
		self::redirect( $redirect, $form_id, 'success' );
	}
	private static function rate_limit( $form_id ) { $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; $key = 'slind_form_rate_' . md5( wp_salt( 'nonce' ) . $ip . $form_id ); $count = (int) get_transient( $key ); if ( $count >= 5 ) { return false; } set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS ); return true; }
	private static function verify_recaptcha() {
		$site = Settings::get( 'recaptcha_site_key' );
		$secret = Settings::get( 'recaptcha_secret_key' );
		$diagnostic = array( 'time_utc' => gmdate( 'c' ), 'site_key_configured' => (bool) $site, 'secret_key_configured' => (bool) $secret );
		if ( ! $site || ! $secret ) {
			self::record_recaptcha( $diagnostic + array( 'result' => 'skipped_missing_keys' ) );
			return true;
		}
		$token = isset( $_POST['slind_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['slind_recaptcha_token'] ) ) : '';
		if ( ! $token ) {
			self::record_recaptcha( $diagnostic + array( 'result' => 'missing_token' ) );
			return new \WP_Error( 'slind_recaptcha_missing', __( 'Spam verification could not be completed.', 'slind-contact-form' ) );
		}
		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array( 'timeout' => 10, 'body' => array( 'secret' => $secret, 'response' => $token ) ) );
		if ( is_wp_error( $response ) ) {
			// Do not record transport messages: they can contain request details.
			self::record_recaptcha( $diagnostic + array( 'result' => 'transport_error' ) );
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$failed = empty( $data['success'] ) || ! isset( $data['score'] ) || (float) $data['score'] < 0.5 || empty( $data['action'] ) || 'slind_form_submit' !== $data['action'];
		$diagnostic['http_status'] = (int) wp_remote_retrieve_response_code( $response );
		$diagnostic['result'] = $failed ? 'failed' : 'passed';
		$diagnostic['valid_json_object'] = is_array( $data );
		$diagnostic['success'] = ! empty( $data['success'] );
		$diagnostic['score'] = isset( $data['score'] ) && is_numeric( $data['score'] ) ? (float) $data['score'] : null;
		foreach ( array( 'action', 'hostname' ) as $field ) {
			$diagnostic[ $field ] = isset( $data[ $field ] ) && is_string( $data[ $field ] ) ? substr( sanitize_text_field( $data[ $field ] ), 0, 255 ) : '';
		}
		$diagnostic['error_codes'] = array();
		if ( isset( $data['error-codes'] ) && is_array( $data['error-codes'] ) ) {
			foreach ( array_slice( $data['error-codes'], 0, 10 ) as $code ) {
				if ( is_string( $code ) ) { $diagnostic['error_codes'][] = substr( sanitize_text_field( $code ), 0, 100 ); }
			}
		}
		self::record_recaptcha( $diagnostic );
		if ( $failed ) { return new \WP_Error( 'slind_recaptcha_failed', __( 'Spam verification failed.', 'slind-contact-form' ) ); }
		return true;
	}
	private static function record_recaptcha( $diagnostic ) {
		if ( ! Settings::get( 'recaptcha_testing' ) ) { return; }
		$results = get_transient( 'slind_recaptcha_diagnostics' );
		$results = is_array( $results ) ? $results : array();
		array_unshift( $results, $diagnostic );
		set_transient( 'slind_recaptcha_diagnostics', array_slice( $results, 0, 10 ), DAY_IN_SECONDS );
	}

	private static function redirect( $url, $form_id, $result, $token = '' ) { $url = remove_query_arg( array( 'slind_form_result', 'slind_form', 'slind_form_token' ), $url ); $args = array( 'slind_form_result' => $result, 'slind_form' => $form_id ); if ( $token ) { $args['slind_form_token'] = $token; } wp_safe_redirect( add_query_arg( $args, $url ) . '#slind-form-' . $form_id ); exit; }
}
