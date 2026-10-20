<?php
namespace Slind\ContactForm\Services;

use Slind\ContactForm\PostTypes\Registrar;
use Slind\ContactForm\Support\EntryTitle;
use Slind\ContactForm\Support\FieldParser;

defined( 'ABSPATH' ) || exit;

final class SubmissionService {
	public function submit( $form_id, array $raw ) {
		$fields = FieldParser::get( $form_id ); $payload = array(); $labels = array(); $errors = array();
		foreach ( $fields as $field ) {
			if ( 'section' === $field['type'] ) { continue; }
			$value = array_key_exists( $field['name'], $raw ) ? $raw[ $field['name'] ] : ''; $value = $this->sanitize( $field['type'], $value );
			if ( in_array( $field['type'], array( 'select', 'radio' ), true ) && '' !== $value && ! in_array( $value, $field['options'], true ) ) { $value = ''; }
			if ( 'checkboxes' === $field['type'] ) { $value = array_values( array_intersect( (array) $value, $field['options'] ) ); }
			if ( $field['required'] && ( '' === $value || array() === $value ) ) { $errors[ $field['name'] ] = sprintf( __( '%s is required.', 'slind-contact-form' ), $field['label'] ); }
			if ( 'email' === $field['type'] && $value && ! is_email( $value ) ) { $errors[ $field['name'] ] = __( 'Please enter a valid email address.', 'slind-contact-form' ); }
			if ( 'url' === $field['type'] && $value && ! wp_http_validate_url( $value ) ) { $errors[ $field['name'] ] = __( 'Please enter a valid URL.', 'slind-contact-form' ); }
			$payload[ $field['name'] ] = $value; $labels[ $field['name'] ] = $field['label'];
		}
		if ( $errors ) { return new \WP_Error( 'slind_validation', __( 'Please correct the highlighted fields.', 'slind-contact-form' ), array( 'errors' => $errors, 'values' => $payload ) ); }
		$entry_title = EntryTitle::format( EntryTitle::full_name( $payload, $fields, $labels ), current_datetime()->getTimestamp(), get_the_title( $form_id ) );
		$entry_id = wp_insert_post( array( 'post_type' => Registrar::ENTRY, 'post_status' => 'private', 'post_title' => $entry_title ), true ); if ( is_wp_error( $entry_id ) ) { return $entry_id; }
		update_post_meta( $entry_id, '_slind_form_id', absint( $form_id ) ); update_post_meta( $entry_id, '_slind_payload', $payload ); update_post_meta( $entry_id, '_slind_labels', $labels ); update_post_meta( $entry_id, '_slind_fields_snapshot', $fields ); update_post_meta( $entry_id, '_slind_entry_status', 'new' );
		$reply_to = $this->email_from_field( get_post_meta( $form_id, '_slind_reply_to', true ), $fields, $payload );
		$admin = $this->send_admin( $form_id, $entry_id, $fields, $payload, $labels, $reply_to ); $this->record_delivery( $entry_id, 'admin', $admin ); update_post_meta( $entry_id, '_slind_delivery_status', is_wp_error( $admin ) ? 'failed' : 'sent' ); update_post_meta( $entry_id, '_slind_delivery_message', is_wp_error( $admin ) ? $admin->get_error_message() : '' );
		if ( '1' === get_post_meta( $form_id, '_slind_user_notification_enabled', true ) ) { $user_email = $this->email_from_field( get_post_meta( $form_id, '_slind_user_email_field', true ), $fields, $payload, $reply_to ); $user = $user_email ? $this->send_user( $form_id, $user_email ) : new \WP_Error( 'slind_user_no_email', __( 'No valid user notification email address was submitted.', 'slind-contact-form' ) ); $this->record_delivery( $entry_id, 'user', $user, $user_email ? '' : 'no-email' ); } else { update_post_meta( $entry_id, '_slind_user_delivery_status', 'disabled' ); update_post_meta( $entry_id, '_slind_user_delivery_message', '' ); }
		return $entry_id;
	}
	private function sanitize( $type, $value ) {
		if ( is_array( $value ) ) { return array_map( 'sanitize_text_field', $value ); }
		switch ( $type ) { case 'email': return sanitize_email( $value ); case 'url': return esc_url_raw( $value ); case 'textarea': return sanitize_textarea_field( $value ); case 'number': return is_numeric( $value ) ? (string) $value : ''; case 'checkbox': return $value ? 'Yes' : ''; default: return sanitize_text_field( $value ); }
	}
	private function email_from_field( $preferred, array $fields, array $payload, $fallback = '' ) {
		$preferred = sanitize_key( $preferred ); if ( $preferred && isset( $payload[ $preferred ] ) && is_email( $payload[ $preferred ] ) ) { return $payload[ $preferred ]; }
		if ( is_email( $fallback ) ) { return $fallback; }
		foreach ( $fields as $field ) { if ( 'email' === $field['type'] && ! empty( $payload[ $field['name'] ] ) && is_email( $payload[ $field['name'] ] ) ) { return $payload[ $field['name'] ]; } } return '';
	}
	private function send_admin( $form_id, $entry_id, array $fields, array $payload, array $labels, $reply_to ) {
		$recipient = sanitize_email( get_post_meta( $form_id, '_slind_recipient', true ) ?: get_option( 'admin_email' ) ); $subject = get_post_meta( $form_id, '_slind_subject', true ) ?: __( 'New form submission: {form_title}', 'slind-contact-form' ); $subject = str_replace( '{form_title}', get_the_title( $form_id ), $subject ); $intro = get_post_meta( $form_id, '_slind_admin_message', true ) ?: sprintf( __( 'A new submission has been received for “%s”.', 'slind-contact-form' ), get_the_title( $form_id ) ); $lines = array( $intro, '' );
		foreach ( $fields as $field ) { if ( 'section' === $field['type'] ) { $lines[] = ''; $lines[] = strtoupper( $field['label'] ); if ( $field['help'] ) { $lines[] = $field['help']; } $lines[] = str_repeat( '-', max( 3, strlen( $field['label'] ) ) ); continue; } $key = $field['name']; if ( ! array_key_exists( $key, $payload ) ) { continue; } $value = $payload[ $key ]; $lines[] = ( isset( $labels[ $key ] ) ? $labels[ $key ] : $key ) . ':'; $lines[] = is_array( $value ) ? implode( ', ', $value ) : $value; $lines[] = ''; } $lines[] = sprintf( __( 'View entry: %s', 'slind-contact-form' ), admin_url( 'post.php?post=' . $entry_id . '&action=edit' ) );
		return ( new SendGridService() )->send( $recipient, sanitize_text_field( $subject ), implode( "\n", $lines ), $reply_to );
	}
	private function send_user( $form_id, $recipient ) {
		$subject = get_post_meta( $form_id, '_slind_user_subject', true ) ?: __( 'Thank you — we received your message', 'slind-contact-form' ); $message = get_post_meta( $form_id, '_slind_user_message', true ); $template = ( new EmailTemplateService() )->user_confirmation( $message );
		return ( new SendGridService() )->send( $recipient, sanitize_text_field( $subject ), $template['text'], '', $template['html'] );
	}
	private function record_delivery( $entry_id, $type, $result, $forced_status = '' ) {
		$status = $forced_status ?: ( is_wp_error( $result ) ? 'failed' : 'sent' ); update_post_meta( $entry_id, '_slind_' . $type . '_delivery_status', $status ); update_post_meta( $entry_id, '_slind_' . $type . '_delivery_message', is_wp_error( $result ) ? $result->get_error_message() : '' );
	}
}
