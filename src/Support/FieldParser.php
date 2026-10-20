<?php
namespace Slind\ContactForm\Support;

defined( 'ABSPATH' ) || exit;

final class FieldParser {
	const TYPES = array( 'section', 'text', 'email', 'tel', 'number', 'url', 'textarea', 'select', 'radio', 'checkboxes', 'checkbox', 'hidden', 'date' );
	public static function parse( $definition ) {
		if ( is_array( $definition ) ) { return self::sanitize_fields( $definition ); }
		$fields = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $definition ) as $line ) {
			$line = trim( $line ); if ( '' === $line || '#' === substr( $line, 0, 1 ) ) { continue; }
			$parts = array_map( 'trim', explode( '|', $line ) );
			$type = isset( $parts[0] ) ? sanitize_key( $parts[0] ) : 'text';
			$name = isset( $parts[1] ) ? sanitize_key( $parts[1] ) : '';
			if ( ! in_array( $type, self::TYPES, true ) || ! $name ) { continue; }
			$fields[] = array( 'type' => $type, 'name' => $name, 'label' => isset( $parts[2] ) && $parts[2] ? sanitize_text_field( $parts[2] ) : ucwords( str_replace( '_', ' ', $name ) ), 'required' => isset( $parts[3] ) && in_array( strtolower( $parts[3] ), array( 'required', 'yes', 'true', '1' ), true ), 'options' => isset( $parts[4] ) ? array_filter( array_map( 'sanitize_text_field', explode( ',', $parts[4] ) ) ) : array(), 'placeholder' => '', 'class' => '', 'help' => '', 'default' => '' );
		}
		return $fields;
	}
	public static function get( $form_id ) {
		$structured = get_post_meta( $form_id, '_slind_fields_v2', true );
		return self::parse( is_array( $structured ) && $structured ? $structured : get_post_meta( $form_id, '_slind_fields', true ) );
	}
	public static function sanitize_fields( array $fields ) {
		$clean = array(); $used = array();
		foreach ( $fields as $field ) {
			$type = sanitize_key( isset( $field['type'] ) ? $field['type'] : 'text' );
			$name = sanitize_key( isset( $field['name'] ) ? $field['name'] : '' );
			$is_section = 'section' === $type; $label = sanitize_text_field( isset( $field['label'] ) ? $field['label'] : $name );
			if ( $is_section && ! $name ) { $name = sanitize_key( sanitize_title( $label ?: 'section' ) ); }
			if ( ! in_array( $type, self::TYPES, true ) || ! $name ) { continue; }
			if ( isset( $used[ $name ] ) ) { if ( ! $is_section ) { continue; } $base = $name; $suffix = 2; while ( isset( $used[ $name ] ) ) { $name = $base . '_' . $suffix; $suffix++; } }
			$used[ $name ] = true;
			$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : preg_split( '/\r\n|\r|\n/', isset( $field['options'] ) ? (string) $field['options'] : '' );
			$clean[] = array( 'type' => $type, 'name' => $name, 'label' => $is_section && ! $label ? __( 'Section', 'slind-contact-form' ) : $label, 'placeholder' => $is_section ? '' : sanitize_text_field( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ), 'required' => $is_section ? false : ! empty( $field['required'] ), 'class' => sanitize_html_class( isset( $field['class'] ) ? $field['class'] : '' ), 'help' => sanitize_text_field( isset( $field['help'] ) ? $field['help'] : '' ), 'default' => $is_section ? '' : sanitize_text_field( isset( $field['default'] ) ? $field['default'] : '' ), 'options' => $is_section ? array() : array_values( array_filter( array_map( 'sanitize_text_field', $options ), 'strlen' ) ) );
		}
		return $clean;
	}
}
