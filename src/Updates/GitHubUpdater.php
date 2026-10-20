<?php
namespace Slind\ContactForm\Updates;

defined( 'ABSPATH' ) || exit;

final class GitHubUpdater {
	const REPO = 'https://github.com/stevelolz/slind-contact-form';
	public static function register_hooks() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'information' ), 10, 3 );
	}
	private static function release() {
		$cached = get_transient( 'slind_forms_github_release' );
		if ( false !== $cached ) { return $cached; }
		$response = wp_remote_get( 'https://api.github.com/repos/stevelolz/slind-contact-form/releases/latest', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json' ) ) );
		$release = array();
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $data ) && empty( $data['draft'] ) && empty( $data['prerelease'] ) && isset( $data['tag_name'] ) && is_string( $data['tag_name'] ) && preg_match( '/^v?(\d+\.\d+\.\d+)$/', $data['tag_name'], $matches ) ) {
				foreach ( isset( $data['assets'] ) && is_array( $data['assets'] ) ? $data['assets'] : array() as $asset ) {
					if ( isset( $asset['name'], $asset['browser_download_url'] ) && 'slind-contact-form.zip' === $asset['name'] && is_string( $asset['browser_download_url'] ) && 0 === strpos( $asset['browser_download_url'], self::REPO . '/releases/download/' ) ) {
						$release = array( 'version' => $matches[1], 'package' => esc_url_raw( $asset['browser_download_url'] ) );
						break;
					}
				}
			}
		}
		// Short cache also limits retries when GitHub is unavailable or rate-limited.
		set_transient( 'slind_forms_github_release', $release, 5 * MINUTE_IN_SECONDS );
		return $release;
	}
	public static function check( $update, $plugin_data, $plugin_file, $locales ) {
		if ( plugin_basename( SLIND_CONTACT_FORM_FILE ) !== $plugin_file ) { return $update; }
		$release = self::release();
		if ( empty( $release ) ) { return $update; }
		return array( 'id' => self::REPO, 'slug' => 'slind-contact-form', 'version' => $release['version'], 'url' => self::REPO, 'package' => $release['package'], 'tested' => '', 'requires' => '6.4', 'requires_php' => '7.4', 'autoupdate' => false );
	}
	public static function information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'slind-contact-form' !== $args->slug ) { return $result; }
		$release = self::release();
		if ( empty( $release ) ) { return $result; }
		return (object) array( 'name' => 'SLIND Forms', 'slug' => 'slind-contact-form', 'version' => $release['version'], 'author' => 'slind.us', 'homepage' => self::REPO, 'requires' => '6.4', 'requires_php' => '7.4', 'download_link' => $release['package'], 'sections' => array( 'description' => 'Reusable WordPress forms with stored entries and SendGrid notifications.', 'changelog' => '<a href="' . esc_url( self::REPO . '/releases' ) . '">View release notes on GitHub</a>' ) );
	}
}
