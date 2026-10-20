<?php
namespace Slind\ContactForm;

use Slind\ContactForm\Admin\FormEditor;
use Slind\ContactForm\Admin\Lists;
use Slind\ContactForm\Admin\Settings;
use Slind\ContactForm\Frontend\Handler;
use Slind\ContactForm\Frontend\Shortcode;
use Slind\ContactForm\PostTypes\Registrar;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static $instance;
	private $booted = false;
	public static function instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }
	public function boot() {
		if ( $this->booted ) { return; } $this->booted = true;
		load_plugin_textdomain( 'slind-contact-form', false, dirname( plugin_basename( SLIND_CONTACT_FORM_FILE ) ) . '/languages' );
		\Slind\ContactForm\Updates\GitHubUpdater::register_hooks();
		Registrar::register_hooks(); FormEditor::register_hooks(); Lists::register_hooks(); Settings::register_hooks(); Shortcode::register_hooks(); Handler::register_hooks();
	}
}
