<?php
namespace Slind\ContactForm\PostTypes;

defined( 'ABSPATH' ) || exit;

final class Registrar {
	const FORM = 'slind_form';
	const ENTRY = 'slind_form_entry';
	const MANAGE_CAP = 'manage_slind_forms';
	public static function register_hooks() { add_action( 'init', array( __CLASS__, 'register' ) ); add_action( 'admin_init', array( __CLASS__, 'ensure_capability' ) ); }
	public static function activate() {
		$role = get_role( 'administrator' );
		if ( $role ) { $role->add_cap( self::MANAGE_CAP ); }
		self::register(); flush_rewrite_rules();
	}
	public static function ensure_capability() {
		if ( '2.0.0' === get_option( 'slind_forms_capability_version' ) ) { return; }
		$role = get_role( 'administrator' ); if ( $role ) { $role->add_cap( self::MANAGE_CAP ); }
		update_option( 'slind_forms_capability_version', '2.0.0', false );
	}
	public static function register() {
		register_post_type( self::FORM, array(
			'labels' => self::labels( __( 'Form', 'slind-contact-form' ), __( 'SLIND Forms', 'slind-contact-form' ) ),
			'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_icon' => 'dashicons-feedback',
			'supports' => array( 'title' ), 'map_meta_cap' => false, 'capabilities' => self::capabilities(),
		) );
		register_post_type( self::ENTRY, array(
			'labels' => self::labels( __( 'Entry', 'slind-contact-form' ), __( 'Entries', 'slind-contact-form' ) ),
			'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=' . self::FORM,
			'supports' => array( 'title' ), 'map_meta_cap' => false, 'capabilities' => self::capabilities(),
		) );
	}
	private static function capabilities() { return array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ), self::MANAGE_CAP ); }
	private static function labels( $single, $plural ) { return array( 'name' => $plural, 'singular_name' => $single, 'add_new_item' => sprintf( __( 'Add New %s', 'slind-contact-form' ), $single ), 'edit_item' => sprintf( __( 'Edit %s', 'slind-contact-form' ), $single ), 'all_items' => sprintf( __( 'All %s', 'slind-contact-form' ), $plural ), 'search_items' => sprintf( __( 'Search %s', 'slind-contact-form' ), $plural ), 'not_found' => sprintf( __( 'No %s found.', 'slind-contact-form' ), strtolower( $plural ) ), 'menu_name' => $plural ); }
}
