<?php
namespace Slind\ContactForm\Support;

defined( 'ABSPATH' ) || exit;

final class EntryTitle {
	/**
	 * Find the submitted full name using field names and saved labels.
	 *
	 * @param array $payload Submitted values.
	 * @param array $fields Saved field definitions.
	 * @param array $labels Saved field labels keyed by field name.
	 * @return string
	 */
	public static function full_name( array $payload, array $fields = array(), array $labels = array() ) {
		foreach ( $fields as $field ) {
			$name  = isset( $field['name'] ) ? $field['name'] : '';
			$label = isset( $field['label'] ) ? $field['label'] : '';
			if ( $name && self::is_full_name_key( $name, $label ) && ! empty( $payload[ $name ] ) ) {
				return self::string_value( $payload[ $name ] );
			}
		}

		foreach ( $labels as $name => $label ) {
			if ( self::is_full_name_key( $name, $label ) && ! empty( $payload[ $name ] ) ) {
				return self::string_value( $payload[ $name ] );
			}
		}

		foreach ( $payload as $name => $value ) {
			if ( self::is_full_name_key( $name ) && ! empty( $value ) ) {
				return self::string_value( $value );
			}
		}

		$first_name = self::value_for_normalized_key( $payload, 'first_name' );
		$last_name  = self::value_for_normalized_key( $payload, 'last_name' );
		return trim( $first_name . ' ' . $last_name );
	}

	/**
	 * Build an entry title without changing the entry storage structure.
	 *
	 * @param string $name      Submitted full name.
	 * @param int    $timestamp Unix timestamp.
	 * @param string $fallback  Fallback label when no name is available.
	 * @return string
	 */
	public static function format( $name, $timestamp, $fallback = '' ) {
		$label = $name ? $name : $fallback;
		$date  = wp_date( get_option( 'date_format' ), $timestamp, wp_timezone() );
		return $label ? sprintf( '%1$s — %2$s', $label, $date ) : $date;
	}

	/**
	 * Derive the display title for an existing entry.
	 *
	 * @param int    $entry_id Entry post ID.
	 * @param string $fallback Existing post title.
	 * @return string
	 */
	public static function for_entry( $entry_id, $fallback = '' ) {
		$payload = (array) get_post_meta( $entry_id, '_slind_payload', true );
		$fields  = (array) get_post_meta( $entry_id, '_slind_fields_snapshot', true );
		$labels  = (array) get_post_meta( $entry_id, '_slind_labels', true );
		$post    = get_post( $entry_id );

		if ( ! $post ) {
			return $fallback;
		}

		$name      = self::full_name( $payload, $fields, $labels );
		$form_id   = absint( get_post_meta( $entry_id, '_slind_form_id', true ) );
		$form_name = $form_id ? get_the_title( $form_id ) : $fallback;
		$date      = get_post_datetime( $post );

		return self::format( $name, $date ? $date->getTimestamp() : time(), $form_name );
	}

	private static function is_full_name_key( $name, $label = '' ) {
		$names = array( self::normalize( $name ), self::normalize( $label ) );
		return (bool) array_intersect( $names, array( 'full_name', 'fullname', 'name', 'your_name' ) );
	}

	private static function normalize( $value ) {
		return trim( preg_replace( '/_+/', '_', str_replace( '-', '_', sanitize_key( $value ) ) ), '_' );
	}

	private static function value_for_normalized_key( array $payload, $wanted ) {
		foreach ( $payload as $name => $value ) {
			if ( $wanted === self::normalize( $name ) && ! empty( $value ) ) {
				return self::string_value( $value );
			}
		}
		return '';
	}

	private static function string_value( $value ) {
		return sanitize_text_field( is_array( $value ) ? implode( ' ', $value ) : $value );
	}
}
