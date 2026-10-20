<?php
/** Stored forms and entries are intentionally retained on uninstall. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Entries and forms are intentionally retained. Remove only plugin configuration.
delete_option( 'slind_forms_settings' );
delete_option( 'slind_forms_capability_version' );

delete_transient( 'slind_recaptcha_diagnostics' );
delete_transient( 'slind_forms_github_release' );
