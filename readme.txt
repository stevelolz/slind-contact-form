=== SLIND Forms ===
Contributors: slind
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 2.4.0

Reusable native WordPress forms with a sortable visual builder, stored entries, accessible validation, and direct SendGrid delivery.

== Architecture and storage ==

Forms are private-admin custom posts using the slind_form post type. Structured field definitions and form delivery settings are stored as post metadata. Existing version 1 pipe-delimited definitions are read as a fallback and are not deleted.

Entries use the private slind_form_entry post type with sanitized values, field labels, entry status, and delivery status stored as post metadata. This keeps the data WordPress-native, capability-protected, queryable, and compatible with standard backup/export tooling.

== Usage ==

1. Open SLIND Forms > Add New and build the form.
2. Configure its recipient, subject, reply-to field, messages, and button label.
3. Add the SendGrid API key and verified From address under SLIND Forms > Settings.
4. Publish and embed with [slind_form id="123"].

The legacy [slind_contact_form] shortcode and version 1 field definitions remain supported.

== Fields ==

Available types are section, text, email, telephone, number, URL, textarea, select, radio, checkbox group, single checkbox, hidden, and date. Sections are structural items: they open accessible fieldsets, collect no data, and preserve grouping in entry details and admin notifications. Add a type to Slind\ContactForm\Support\FieldParser::TYPES, extend its sanitizer when needed, and add the corresponding rendering branch in Slind\ContactForm\Frontend\Shortcode to introduce another type.

== Delivery and submission flow ==

Configure the SendGrid API key, verified From Email, From Name, and optional reCAPTCHA v3 keys under SLIND Forms > Settings. A submission is validated and sanitized, saved as an entry, delivered directly through SendGrid, and then updated with the delivery result. Email failure never removes an entry.

Each form has independent Admin Notification and User Notification settings. Admin messages dynamically include every submitted field. Optional user confirmations are sent to a selected email-type field using a responsive HTML template with a plain-text alternative. User notifications are disabled by default for existing forms.

The global Email Logo URL accepts an absolute public URL for confirmation-email branding. A PNG is recommended. When it is empty, the plugin falls back to the WordPress custom logo, an active-theme assets/svg/logo.svg file, or the site name as text.

== Privacy and spam ==

Submissions are stored in WordPress before notification delivery. The plugin includes a nonce, honeypot, and privacy-preserving salted rate-limit key. It does not permanently store visitor IP addresses or user agents.

When both reCAPTCHA v3 keys are configured, the plugin additionally verifies the slind_form_submit action and requires a score of at least 0.5.

== Extension hooks ==

WordPress HTTP hooks can customize or observe SendGrid and reCAPTCHA requests. Theme authors can style the semantic slind-form classes and override the --slind-form-primary, --slind-form-secondary, --slind-form-background, and --slind-form-text CSS custom properties.

The plugin version constant and slind_forms_capability_version option provide the upgrade/versioning foundation. Version 2 grants the manage_slind_forms capability to administrators without altering stored forms or entries.

== reCAPTCHA Testing ==

Enable reCAPTCHA Testing under SLIND Forms > Settings, save, submit a form, and refresh Settings to inspect the latest verification results. Testing preserves normal verification behavior. Results include UTC time, key configuration presence, HTTP status, success, score, action, hostname, and Google error codes. Missing keys/tokens and transport failures are also recorded. No secrets, tokens, IPs, or form contents are logged. Only the latest 10 results are retained, expiring 24 hours after the last result; disabling testing and saving clears them. Results are visible only to users who can manage SLIND Forms. Requests rejected before verification or never received by the server produce no result.
