<?php
namespace Slind\ContactForm\Services;

use Slind\ContactForm\Admin\Settings;

defined( 'ABSPATH' ) || exit;

final class EmailTemplateService {
	public function user_confirmation( $message ) {
		$message = $message ?: __( "Thank you!\n\nWe've received your message and will be in touch soon.", 'slind-contact-form' );
		$site_name = get_bloginfo( 'name' ); $logo = $this->logo_url();
		$body = nl2br( esc_html( $message ) );
		$branding = $logo ? '<img src="' . esc_url( $logo ) . '" width="150" alt="' . esc_attr( $site_name ) . '" style="border:0;display:block;height:auto;max-width:150px;width:100%;">' : '<div style="color:#333333;font-family:Arial,sans-serif;font-size:24px;font-weight:bold;">' . esc_html( $site_name ) . '</div>';
		$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="background:#faf9f8;margin:0;padding:0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#faf9f8;width:100%;"><tr><td align="center" style="padding:32px 16px;"><table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background:#ffffff;border-collapse:separate;border-radius:12px;max-width:600px;width:100%;"><tr><td style="padding:32px 32px 16px;">' . $branding . '</td></tr><tr><td style="color:#333333;font-family:Arial,sans-serif;font-size:16px;line-height:1.65;padding:16px 32px 36px;">' . $body . '</td></tr></table></td></tr></table></body></html>';
		return array( 'text' => wp_strip_all_tags( $message ), 'html' => $html );
	}
	private function logo_url() {
		$configured = Settings::get( 'email_logo_url' ); if ( $configured ) { return esc_url_raw( $configured ); }
		$custom_logo_id = get_theme_mod( 'custom_logo' ); if ( $custom_logo_id ) { $logo = wp_get_attachment_image_url( $custom_logo_id, 'full' ); if ( $logo ) { return $logo; } }
		$theme_logo = trailingslashit( get_stylesheet_directory() ) . 'assets/svg/logo.svg'; if ( is_readable( $theme_logo ) ) { return trailingslashit( get_stylesheet_directory_uri() ) . 'assets/svg/logo.svg'; }
		return '';
	}
}
