<?php
namespace Slind\ContactForm\Admin;

use Slind\ContactForm\PostTypes\Registrar;
use Slind\ContactForm\Support\EntryTitle;

defined( 'ABSPATH' ) || exit;

final class Lists {
	public static function register_hooks() {
		add_filter( 'manage_' . Registrar::FORM . '_posts_columns', array( __CLASS__, 'form_columns' ) ); add_action( 'manage_' . Registrar::FORM . '_posts_custom_column', array( __CLASS__, 'form_column' ), 10, 2 );
		add_filter( 'manage_' . Registrar::ENTRY . '_posts_columns', array( __CLASS__, 'entry_columns' ) ); add_action( 'manage_' . Registrar::ENTRY . '_posts_custom_column', array( __CLASS__, 'entry_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) ); add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ) );
		add_filter( 'post_class', array( __CLASS__, 'row_class' ), 10, 3 ); add_action( 'admin_head-edit.php', array( __CLASS__, 'styles' ) );
		add_filter( 'the_title', array( __CLASS__, 'entry_title' ), 10, 2 );
	}
	public static function form_columns( $columns ) { $columns['shortcode'] = __( 'Shortcode', 'slind-contact-form' ); return $columns; }
	public static function form_column( $column, $id ) { if ( 'shortcode' === $column ) { echo '<code>[slind_form id=&quot;' . esc_attr( $id ) . '&quot;]</code>'; } }
	public static function entry_columns( $columns ) { return array( 'cb' => $columns['cb'], 'title' => __( 'Entry ID', 'slind-contact-form' ), 'form' => __( 'Form', 'slind-contact-form' ), 'name' => __( 'Name', 'slind-contact-form' ), 'email' => __( 'Email', 'slind-contact-form' ), 'entry_status' => __( 'Status', 'slind-contact-form' ), 'admin_delivery' => __( 'Admin Email', 'slind-contact-form' ), 'user_delivery' => __( 'User Email', 'slind-contact-form' ), 'date' => $columns['date'] ); }
	public static function entry_column( $column, $id ) {
		$payload = (array) get_post_meta( $id, '_slind_payload', true );
		if ( 'form' === $column ) { echo esc_html( get_the_title( absint( get_post_meta( $id, '_slind_form_id', true ) ) ) ); }
		if ( 'name' === $column ) { echo esc_html( EntryTitle::full_name( $payload, (array) get_post_meta( $id, '_slind_fields_snapshot', true ), (array) get_post_meta( $id, '_slind_labels', true ) ) ?: '—' ); }
		if ( 'email' === $column ) { echo esc_html( self::find_email( $payload ) ); }
		if ( 'entry_status' === $column ) { echo esc_html( ucfirst( get_post_meta( $id, '_slind_entry_status', true ) ?: 'New' ) ); }
		if ( 'admin_delivery' === $column ) { $status = get_post_meta( $id, '_slind_admin_delivery_status', true ) ?: get_post_meta( $id, '_slind_delivery_status', true ); $message = get_post_meta( $id, '_slind_admin_delivery_message', true ) ?: get_post_meta( $id, '_slind_delivery_message', true ); echo '<span title="' . esc_attr( $message ) . '">' . esc_html( ucfirst( $status ?: 'unknown' ) ) . '</span>'; }
		if ( 'user_delivery' === $column ) { $status = get_post_meta( $id, '_slind_user_delivery_status', true ); echo '<span title="' . esc_attr( get_post_meta( $id, '_slind_user_delivery_message', true ) ) . '">' . esc_html( ucfirst( $status ?: 'not recorded' ) ) . '</span>'; }
	}
	public static function entry_title( $title, $post_id ) {
		if ( is_admin() && Registrar::ENTRY === get_post_type( $post_id ) ) {
			return EntryTitle::for_entry( $post_id, $title );
		}
		return $title;
	}
	private static function find_email( $payload ) { foreach ( $payload as $value ) { if ( ! is_array( $value ) && is_email( $value ) ) { return $value; } } return '—'; }
	public static function filters( $post_type ) {
		if ( Registrar::ENTRY !== $post_type ) { return; } $selected_form = isset( $_GET['slind_form_filter'] ) ? absint( $_GET['slind_form_filter'] ) : 0; $selected_status = isset( $_GET['slind_status_filter'] ) ? sanitize_key( $_GET['slind_status_filter'] ) : '';
		wp_dropdown_pages( array( 'post_type' => Registrar::FORM, 'name' => 'slind_form_filter', 'selected' => $selected_form, 'show_option_all' => __( 'All forms', 'slind-contact-form' ), 'sort_column' => 'post_title', 'post_status' => array( 'publish', 'draft' ) ) );
		echo '<select name="slind_status_filter"><option value="">' . esc_html__( 'All statuses', 'slind-contact-form' ) . '</option>'; foreach ( array( 'new', 'read', 'archived' ) as $status ) { printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $status ), selected( $selected_status, $status, false ), esc_html( ucfirst( $status ) ) ); } echo '</select>';
	}
	public static function filter_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || Registrar::ENTRY !== $query->get( 'post_type' ) ) { return; } $meta = array();
		if ( ! empty( $_GET['slind_form_filter'] ) ) { $meta[] = array( 'key' => '_slind_form_id', 'value' => absint( $_GET['slind_form_filter'] ) ); }
		if ( ! empty( $_GET['slind_status_filter'] ) ) { $meta[] = array( 'key' => '_slind_entry_status', 'value' => sanitize_key( $_GET['slind_status_filter'] ) ); }
		if ( $meta ) { $query->set( 'meta_query', $meta ); }
	}
	public static function row_class( $classes, $class, $post_id ) { if ( Registrar::ENTRY === get_post_type( $post_id ) && 'new' === get_post_meta( $post_id, '_slind_entry_status', true ) ) { $classes[] = 'slind-entry-new'; } return $classes; }
	public static function styles() { $screen = get_current_screen(); if ( $screen && Registrar::ENTRY === $screen->post_type ) { echo '<style>.wp-list-table tr.slind-entry-new td,.wp-list-table tr.slind-entry-new th{background:#fff8d7}.wp-list-table tr.slind-entry-new .row-title{font-weight:700}</style>'; } }
}
