<?php
namespace Slind\ContactForm\Services;

use Slind\ContactForm\Admin\Settings;

defined( 'ABSPATH' ) || exit;

final class SendGridService {
	public function send( $to, $subject, $text_body, $reply_to = '', $html_body = '' ) {
		$key = Settings::get( 'sendgrid_key' ); $from = Settings::get( 'from_email' );
		if ( ! $key || ! is_email( $from ) || ! is_email( $to ) ) { return new \WP_Error( 'slind_sendgrid_configuration', __( 'SendGrid is not fully configured.', 'slind-contact-form' ) ); }
		$content = array( array( 'type' => 'text/plain', 'value' => $text_body ) ); if ( $html_body ) { $content[] = array( 'type' => 'text/html', 'value' => $html_body ); }
		$data = array( 'personalizations' => array( array( 'to' => array( array( 'email' => $to ) ), 'subject' => $subject ) ), 'from' => array( 'email' => $from, 'name' => Settings::get( 'from_name', get_bloginfo( 'name' ) ) ), 'content' => $content );
		if ( is_email( $reply_to ) ) { $data['reply_to'] = array( 'email' => $reply_to ); }
		$response = wp_remote_post( 'https://api.sendgrid.com/v3/mail/send', array( 'timeout' => 15, 'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $data ) ) );
		if ( is_wp_error( $response ) ) { return $response; }
		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300 ? true : new \WP_Error( 'slind_sendgrid_failed', sprintf( __( 'SendGrid returned HTTP %d.', 'slind-contact-form' ), $code ) );
	}
}
