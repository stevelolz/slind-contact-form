<?php
/**
 * Plugin Name: SLIND Forms
 * Plugin URI:  https://slind.us
 * Update URI:  https://github.com/stevelolz/slind-contact-form
 * Description: Reusable form builder, stored entries, and direct SendGrid notifications.
 * Version:     2.4.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      slind.us
 * Author URI:  https://slind.us
 * Text Domain: slind-contact-form
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'SLIND_CONTACT_FORM_VERSION', '2.4.0' );
define( 'SLIND_CONTACT_FORM_FILE', __FILE__ );
define( 'SLIND_CONTACT_FORM_PATH', plugin_dir_path( __FILE__ ) );
define( 'SLIND_CONTACT_FORM_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register( static function ( $class ) {
	$prefix = 'Slind\\ContactForm\\';
	if ( 0 !== strpos( $class, $prefix ) ) { return; }
	$file = SLIND_CONTACT_FORM_PATH . 'src/' . str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( $prefix ) ) ) . '.php';
	if ( is_readable( $file ) ) { require_once $file; }
} );

add_action( 'plugins_loaded', static function () { Slind\ContactForm\Plugin::instance()->boot(); } );
register_activation_hook( __FILE__, array( 'Slind\\ContactForm\\PostTypes\\Registrar', 'activate' ) );
