<?php
namespace Slind\ContactForm\Admin;

use Slind\ContactForm\PostTypes\Registrar;

defined( 'ABSPATH' ) || exit;

final class Settings {
	const OPTION = 'slind_forms_settings';
	const BUTTON_BACKGROUND_DEFAULT = '#000000';
	const BUTTON_TEXT_DEFAULT = '#FFFFFF';
	const SUCCESS_DEFAULT = '#198754';
	const ERROR_DEFAULT = '#B42318';
	public static function register_hooks() { add_action( 'admin_menu', array( __CLASS__, 'menu' ) ); add_action( 'admin_init', array( __CLASS__, 'register' ) ); add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) ); }
	public static function menu() { add_submenu_page( 'edit.php?post_type=' . Registrar::FORM, __( 'SLIND Forms Settings', 'slind-contact-form' ), __( 'Settings', 'slind-contact-form' ), Registrar::MANAGE_CAP, 'slind-forms-settings', array( __CLASS__, 'page' ) ); }
	public static function register() { register_setting( 'slind_forms', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ), 'default' => array() ) ); }
	public static function get( $key, $fallback = '' ) { $values = get_option( self::OPTION, array() ); return isset( $values[ $key ] ) ? $values[ $key ] : $fallback; }
	public static function assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'slind-forms-settings' ) ) { return; }
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".slind-forms-color-field").wpColorPicker();});' );
	}
	public static function sanitize( $input ) {
		$current = get_option( self::OPTION, array() ); $clean = array();
		$key = isset( $input['sendgrid_key'] ) ? trim( sanitize_text_field( wp_unslash( $input['sendgrid_key'] ) ) ) : '';
		$clean['sendgrid_key'] = '' === $key || '••••••••••••••••' === $key ? ( isset( $current['sendgrid_key'] ) ? $current['sendgrid_key'] : '' ) : $key;
		$clean['from_email'] = isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '';
		$clean['from_name'] = isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '';
		$clean['email_logo_url'] = isset( $input['email_logo_url'] ) ? esc_url_raw( $input['email_logo_url'] ) : '';
		foreach ( self::color_defaults() as $name => $default ) {
			$color = isset( $input[ $name ] ) ? sanitize_hex_color( wp_unslash( $input[ $name ] ) ) : '';
			$clean[ $name ] = $color ?: $default;
		}
		$clean['recaptcha_site_key'] = isset( $input['recaptcha_site_key'] ) ? sanitize_text_field( $input['recaptcha_site_key'] ) : '';
		$secret = isset( $input['recaptcha_secret_key'] ) ? trim( sanitize_text_field( wp_unslash( $input['recaptcha_secret_key'] ) ) ) : '';
		$clean['recaptcha_secret_key'] = '' === $secret || '••••••••••••••••' === $secret ? ( isset( $current['recaptcha_secret_key'] ) ? $current['recaptcha_secret_key'] : '' ) : $secret;
		$clean['recaptcha_testing'] = empty( $input['recaptcha_testing'] ) ? 0 : 1;
		if ( ! $clean['recaptcha_testing'] ) { delete_transient( 'slind_recaptcha_diagnostics' ); }
		return $clean;
	}
	private static function color_defaults() { return array( 'button_background_color' => self::BUTTON_BACKGROUND_DEFAULT, 'button_text_color' => self::BUTTON_TEXT_DEFAULT, 'success_notification_color' => self::SUCCESS_DEFAULT, 'error_notification_color' => self::ERROR_DEFAULT ); }
	private static function color_field( $key, $label, $default ) {
		?><tr><th scope="row"><label for="slind-<?php echo esc_attr( str_replace( '_', '-', $key ) ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="slind-forms-color-field" id="slind-<?php echo esc_attr( str_replace( '_', '-', $key ) ); ?>" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( self::get( $key, $default ) ); ?>" data-default-color="<?php echo esc_attr( $default ); ?>"></td></tr><?php
	}
	private static function recaptcha_testing_fields() {
		?><h3><?php esc_html_e( 'reCAPTCHA Testing', 'slind-contact-form' ); ?></h3>
		<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_testing]" value="1" <?php checked( self::get( 'recaptcha_testing' ), 1 ); ?>> <?php esc_html_e( 'Record recent reCAPTCHA verification results', 'slind-contact-form' ); ?></label>
		<p class="description"><?php esc_html_e( 'Save settings, submit a form, then refresh this page. Stores the latest 10 results for up to 24 hours after the latest result. Disabling testing clears them. Secrets, tokens, visitor IPs, and form contents are never recorded. Testing does not bypass verification.', 'slind-contact-form' ); ?></p>
		<?php
		if ( ! self::get( 'recaptcha_testing' ) ) { return; }
		$results = get_transient( 'slind_recaptcha_diagnostics' );
		if ( ! is_array( $results ) || ! $results ) {
			echo '<p>' . esc_html__( 'No results yet. Requests stopped by nonce, honeypot, or rate-limit checks do not reach reCAPTCHA verification; neither do browser submissions that never reach the server.', 'slind-contact-form' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html__( 'Passed means CAPTCHA verification passed; it does not confirm form validation or email delivery. Times are UTC.', 'slind-contact-form' ) . '</p>';
		echo '<pre style="white-space:pre-wrap;overflow-wrap:anywhere;background:#fff;padding:16px;max-height:480px;overflow:auto">' . esc_html( wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
	}

	public static function page() {
		if ( ! current_user_can( Registrar::MANAGE_CAP ) ) { return; } $has_key = (bool) self::get( 'sendgrid_key' ); $has_secret = (bool) self::get( 'recaptcha_secret_key' );
		?><div class="wrap"><h1><?php esc_html_e( 'SLIND Forms Settings', 'slind-contact-form' ); ?></h1><form method="post" action="options.php"><?php settings_fields( 'slind_forms' ); ?><h2><?php esc_html_e( 'SendGrid', 'slind-contact-form' ); ?></h2><table class="form-table" role="presentation"><tr><th scope="row"><label for="slind-sendgrid-key"><?php esc_html_e( 'SendGrid API key', 'slind-contact-form' ); ?></label></th><td><input class="regular-text" id="slind-sendgrid-key" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION ); ?>[sendgrid_key]" value="<?php echo $has_key ? '••••••••••••••••' : ''; ?>"><p class="description"><?php esc_html_e( 'Stored in WordPress options and never displayed after saving.', 'slind-contact-form' ); ?></p></td></tr><tr><th scope="row"><label for="slind-from-email"><?php esc_html_e( 'From Email', 'slind-contact-form' ); ?></label></th><td><input class="regular-text" id="slind-from-email" type="email" name="<?php echo esc_attr( self::OPTION ); ?>[from_email]" value="<?php echo esc_attr( self::get( 'from_email' ) ); ?>"></td></tr><tr><th scope="row"><label for="slind-from-name"><?php esc_html_e( 'From Name', 'slind-contact-form' ); ?></label></th><td><input class="regular-text" id="slind-from-name" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[from_name]" value="<?php echo esc_attr( self::get( 'from_name', get_bloginfo( 'name' ) ) ); ?>"></td></tr></table><h2><?php esc_html_e( 'Email Branding', 'slind-contact-form' ); ?></h2><table class="form-table" role="presentation"><tr><th scope="row"><label for="slind-email-logo-url"><?php esc_html_e( 'Email Logo URL', 'slind-contact-form' ); ?></label></th><td><input class="regular-text code" id="slind-email-logo-url" type="url" name="<?php echo esc_attr( self::OPTION ); ?>[email_logo_url]" value="<?php echo esc_attr( self::get( 'email_logo_url' ) ); ?>" placeholder="https://example.com/logo.png"><p class="description"><?php esc_html_e( 'Use an absolute HTTPS URL. A PNG is recommended for the widest email-client support. The site custom logo or active theme logo is used as a fallback.', 'slind-contact-form' ); ?></p></td></tr></table><h2><?php esc_html_e( 'Form Colors', 'slind-contact-form' ); ?></h2><p><?php esc_html_e( 'These colors apply only to SLIND Forms submit buttons and notification bars.', 'slind-contact-form' ); ?></p><table class="form-table" role="presentation"><?php self::color_field( 'button_background_color', __( 'Button Background Color', 'slind-contact-form' ), self::BUTTON_BACKGROUND_DEFAULT ); self::color_field( 'button_text_color', __( 'Button Text Color', 'slind-contact-form' ), self::BUTTON_TEXT_DEFAULT ); self::color_field( 'success_notification_color', __( 'Success Notification Color', 'slind-contact-form' ), self::SUCCESS_DEFAULT ); self::color_field( 'error_notification_color', __( 'Failure / Error Notification Color', 'slind-contact-form' ), self::ERROR_DEFAULT ); ?></table><h2><?php esc_html_e( 'Google reCAPTCHA v3', 'slind-contact-form' ); ?></h2><p><?php esc_html_e( 'Both keys are required to enable reCAPTCHA. Forms continue using the built-in nonce, honeypot, and rate limit when these fields are empty.', 'slind-contact-form' ); ?></p><table class="form-table" role="presentation"><tr><th scope="row"><label for="slind-recaptcha-site-key"><?php esc_html_e( 'Site key', 'slind-contact-form' ); ?></label></th><td><input class="regular-text" id="slind-recaptcha-site-key" type="text" autocomplete="off" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_site_key]" value="<?php echo esc_attr( self::get( 'recaptcha_site_key' ) ); ?>"></td></tr><tr><th scope="row"><label for="slind-recaptcha-secret-key"><?php esc_html_e( 'Secret key', 'slind-contact-form' ); ?></label></th><td><input class="regular-text" id="slind-recaptcha-secret-key" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_secret_key]" value="<?php echo $has_secret ? '••••••••••••••••' : ''; ?>"><p class="description"><?php esc_html_e( 'The default minimum score is 0.5 and the expected action is slind_form_submit.', 'slind-contact-form' ); ?></p></td></tr></table><?php self::recaptcha_testing_fields(); submit_button(); ?></form></div><?php
	}
}
