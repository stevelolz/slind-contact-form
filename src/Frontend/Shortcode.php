<?php
namespace Slind\ContactForm\Frontend;

use Slind\ContactForm\PostTypes\Registrar;
use Slind\ContactForm\Support\FieldParser;
use Slind\ContactForm\Admin\Settings;

defined( 'ABSPATH' ) || exit;

final class Shortcode {
	public static function register_hooks() { add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) ); add_shortcode( 'slind_form', array( __CLASS__, 'render' ) ); add_shortcode( 'slind_contact_form', array( __CLASS__, 'render' ) ); }
	public static function assets() { wp_register_style( 'slind-contact-form', SLIND_CONTACT_FORM_URL . 'assets/css/form.css', array(), SLIND_CONTACT_FORM_VERSION ); wp_register_script( 'slind-form-recaptcha', SLIND_CONTACT_FORM_URL . 'assets/js/recaptcha.js', array( 'slind-google-recaptcha' ), SLIND_CONTACT_FORM_VERSION, true ); }
	public static function render( $attributes ) {
		$attributes = shortcode_atts( array( 'id' => 0, 'button' => '' ), $attributes, 'slind_form' ); $form_id = absint( $attributes['id'] ); $form = get_post( $form_id );
		if ( ! $form || Registrar::FORM !== $form->post_type || 'publish' !== $form->post_status ) { return current_user_can( Registrar::MANAGE_CAP ) ? '<p>' . esc_html__( 'SLIND Forms: publish a valid Form and check the shortcode ID.', 'slind-contact-form' ) . '</p>' : ''; }
		$fields = FieldParser::get( $form_id ); if ( ! $fields ) { return ''; } wp_enqueue_style( 'slind-contact-form' ); self::colors(); $site_key = Settings::get( 'recaptcha_site_key' ); if ( $site_key && Settings::get( 'recaptcha_secret_key' ) ) { wp_register_script( 'slind-google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ), array(), null, true ); wp_enqueue_script( 'slind-form-recaptcha' ); wp_localize_script( 'slind-form-recaptcha', 'slindRecaptcha', array( 'siteKey' => $site_key, 'action' => 'slind_form_submit' ) ); }
		$result = isset( $_GET['slind_form_result'], $_GET['slind_form'] ) && $form_id === absint( $_GET['slind_form'] ) ? sanitize_key( wp_unslash( $_GET['slind_form_result'] ) ) : ''; $state = array( 'errors' => array(), 'values' => array() );
		if ( 'error' === $result && isset( $_GET['slind_form_token'] ) ) { $token = sanitize_key( wp_unslash( $_GET['slind_form_token'] ) ); $stored = get_transient( 'slind_form_error_' . $token ); if ( is_array( $stored ) ) { $state = array_merge( $state, $stored ); delete_transient( 'slind_form_error_' . $token ); } }
		$button = $attributes['button'] ?: ( get_post_meta( $form_id, '_slind_submit_label', true ) ?: __( 'Submit', 'slind-contact-form' ) );
		ob_start(); ?><div class="slind-form-wrap" id="slind-form-<?php echo esc_attr( $form_id ); ?>"><?php if ( 'success' === $result ) : ?><div class="slind-form-notice slind-form-notice--success" role="status"><?php echo esc_html( get_post_meta( $form_id, '_slind_success', true ) ?: __( 'Thank you. Your submission has been received.', 'slind-contact-form' ) ); ?></div><?php elseif ( 'error' === $result ) : ?><div class="slind-form-notice slind-form-notice--error" role="alert"><?php esc_html_e( 'Please correct the highlighted fields and try again.', 'slind-contact-form' ); ?></div><?php endif; ?><form class="slind-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate><input type="hidden" name="action" value="slind_submit_form"><input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>"><input type="hidden" name="slind_recaptcha_token" value=""><?php wp_nonce_field( 'slind_submit_' . $form_id, 'slind_form_nonce' ); ?><div class="slind-form__trap" aria-hidden="true"><label>Website<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label></div><?php self::fields( $fields, $form_id, $state ); ?><button class="slind-form__submit" type="submit"><?php echo esc_html( $button ); ?></button></form></div><?php return ob_get_clean();
	}
	private static function fields( $fields, $form_id, $state ) {
		$section_open = false;
		foreach ( $fields as $field ) {
			if ( 'section' === $field['type'] ) {
				if ( $section_open ) { echo '</div></fieldset>'; }
				$id = 'slind-' . $form_id . '-' . $field['name']; $class = 'slind-form__section' . ( $field['class'] ? ' ' . $field['class'] : '' );
				echo '<fieldset class="' . esc_attr( $class ) . '" id="' . esc_attr( $id ) . '"><legend class="slind-form__section-header"><span class="slind-form__section-title">' . esc_html( $field['label'] ) . '</span></legend>';
				if ( $field['help'] ) { echo '<p class="slind-form__section-description">' . esc_html( $field['help'] ) . '</p>'; }
				echo '<div class="slind-form__section-fields">'; $section_open = true; continue;
			}
			self::field( $field, $form_id, $state );
		}
		if ( $section_open ) { echo '</div></fieldset>'; }
	}
	private static function field( $field, $form_id, $state ) {
		$id = 'slind-' . $form_id . '-' . $field['name']; $error = isset( $state['errors'][ $field['name'] ] ) ? $state['errors'][ $field['name'] ] : ''; $value = isset( $state['values'][ $field['name'] ] ) ? $state['values'][ $field['name'] ] : $field['default']; $described = array(); if ( $field['help'] ) { $described[] = $id . '-help'; } if ( $error ) { $described[] = $id . '-error'; } $aria = $described ? ' aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"' : ''; if ( $error ) { $aria .= ' aria-invalid="true"'; } $required = $field['required'] ? ' required' : ''; $class = 'slind-form__field slind-form__field--' . $field['type'] . ( $field['class'] ? ' ' . $field['class'] : '' ) . ( $error ? ' has-error' : '' );
		echo '<div class="' . esc_attr( $class ) . '">'; if ( 'hidden' === $field['type'] ) { echo '<input type="hidden" name="fields[' . esc_attr( $field['name'] ) . ']" value="' . esc_attr( $value ) . '"></div>'; return; }
		$legend = in_array( $field['type'], array( 'radio', 'checkboxes' ), true ); if ( $legend ) { echo '<fieldset><legend>' . esc_html( $field['label'] ) . self::required_mark( $field ) . '</legend>'; } elseif ( 'checkbox' !== $field['type'] ) { echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . self::required_mark( $field ) . '</label>'; }
		if ( 'textarea' === $field['type'] ) { echo '<textarea id="' . esc_attr( $id ) . '" name="fields[' . esc_attr( $field['name'] ) . ']" rows="6" placeholder="' . esc_attr( $field['placeholder'] ) . '"' . $required . $aria . '>' . esc_textarea( $value ) . '</textarea>'; }
		elseif ( 'select' === $field['type'] ) { echo '<select id="' . esc_attr( $id ) . '" name="fields[' . esc_attr( $field['name'] ) . ']"' . $required . $aria . '><option value="">' . esc_html( $field['placeholder'] ?: __( 'Select…', 'slind-contact-form' ) ) . '</option>'; foreach ( $field['options'] as $option ) { printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $option ), selected( $value, $option, false ) ); } echo '</select>'; }
		elseif ( in_array( $field['type'], array( 'radio', 'checkboxes' ), true ) ) { foreach ( $field['options'] as $index => $option ) { $checked = 'radio' === $field['type'] ? $value === $option : in_array( $option, (array) $value, true ); $name = 'fields[' . $field['name'] . ']' . ( 'checkboxes' === $field['type'] ? '[]' : '' ); echo '<label class="slind-form__choice"><input type="' . ( 'radio' === $field['type'] ? 'radio' : 'checkbox' ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $option ) . '"' . checked( $checked, true, false ) . ( 0 === $index ? $required . $aria : '' ) . '> <span>' . esc_html( $option ) . '</span></label>'; } echo '</fieldset>'; }
		elseif ( 'checkbox' === $field['type'] ) { echo '<label class="slind-form__choice" for="' . esc_attr( $id ) . '"><input id="' . esc_attr( $id ) . '" type="checkbox" name="fields[' . esc_attr( $field['name'] ) . ']" value="Yes"' . checked( $value, 'Yes', false ) . $required . $aria . '> <span>' . esc_html( $field['label'] ) . self::required_mark( $field ) . '</span></label>'; }
		else { echo '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $field['type'] ) . '" name="fields[' . esc_attr( $field['name'] ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '"' . $required . $aria . '>'; }
		if ( $field['help'] ) { echo '<small id="' . esc_attr( $id ) . '-help" class="slind-form__help">' . esc_html( $field['help'] ) . '</small>'; } if ( $error ) { echo '<small id="' . esc_attr( $id ) . '-error" class="slind-form__error">' . esc_html( $error ) . '</small>'; } echo '</div>';
	}
	private static function required_mark( $field ) { return $field['required'] ? ' <span class="slind-form__required" aria-hidden="true">*</span><span class="screen-reader-text"> ' . esc_html__( 'required', 'slind-contact-form' ) . '</span>' : ''; }
	private static function colors() {
		static $added = false; if ( $added ) { return; } $added = true;
		$button = sanitize_hex_color( Settings::get( 'button_background_color', Settings::BUTTON_BACKGROUND_DEFAULT ) ) ?: Settings::BUTTON_BACKGROUND_DEFAULT;
		$button_text = sanitize_hex_color( Settings::get( 'button_text_color', Settings::BUTTON_TEXT_DEFAULT ) ) ?: Settings::BUTTON_TEXT_DEFAULT;
		$success = sanitize_hex_color( Settings::get( 'success_notification_color', Settings::SUCCESS_DEFAULT ) ) ?: Settings::SUCCESS_DEFAULT;
		$error = sanitize_hex_color( Settings::get( 'error_notification_color', Settings::ERROR_DEFAULT ) ) ?: Settings::ERROR_DEFAULT;
		$css = sprintf( '.slind-form-wrap{--slind-forms-button-background:%1$s;--slind-forms-button-text:%2$s;--slind-forms-button-focus:%3$s;--slind-forms-success:%4$s;--slind-forms-success-text:%5$s;--slind-forms-error:%6$s;--slind-forms-error-text:%7$s}', $button, $button_text, self::contrast_color( $button ), $success, self::contrast_color( $success ), $error, self::contrast_color( $error ) );
		wp_add_inline_style( 'slind-contact-form', $css );
	}
	private static function contrast_color( $hex ) {
		$hex = ltrim( $hex, '#' ); $channels = array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
		foreach ( $channels as &$channel ) { $channel /= 255; $channel = $channel <= 0.04045 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 ); } unset( $channel );
		$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2]; return $luminance > 0.179 ? '#000000' : '#FFFFFF';
	}
}
