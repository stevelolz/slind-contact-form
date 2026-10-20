# SLIND Forms

WordPress form builder with stored entries, SendGrid notifications, reCAPTCHA v3 verification, and optional CAPTCHA diagnostics.

## Install

Download **slind-contact-form.zip** from a published GitHub release and upload it under WordPress → Plugins → Add New → Upload Plugin. Keep the directory name `slind-contact-form`. Sites running versions before 2.4.0 need this manual update once.

## Publish updates using GitHub Desktop

1. Make and test changes. Update the version in the plugin header, `SLIND_CONTACT_FORM_VERSION`, and the stable tag in `readme.txt` together.
2. Commit and push with GitHub Desktop.
3. On GitHub, open Releases → Draft a new release. Create a matching tag such as `v2.4.0` targeting the pushed commit, add release notes, and publish a standard release.
4. The release workflow builds and attaches **slind-contact-form.zip**. Check that the workflow succeeds and the asset appears. WordPress uses this asset, not GitHub's automatic source archives.
5. On each site, use Dashboard → Updates → Check again. Newer releases appear through normal WordPress plugin updates. WordPress schedules checks; updates are not instantly pushed. The updater caches GitHub responses for five minutes.

Sites can opt into WordPress auto-updates from the Plugins page. The updater does not turn them on. Forms, entries, and settings remain in the database during normal updates. Back up sites before upgrading.

Only published stable releases with a numeric `major.minor.patch` tag (optionally prefixed with `v`) and the named ZIP asset are offered. Drafts and prereleases are ignored. A missing asset, GitHub outage, or rate limit produces no update offer. No GitHub credentials are stored on sites. Update checks contact GitHub's public API.

The release workflow needs GitHub Actions enabled with permission to write release assets. No personal access token is needed. The workflow's token is provided by GitHub.
