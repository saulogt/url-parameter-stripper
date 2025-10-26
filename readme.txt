=== URL Parameter Stripper ===
Contributors: saulogt
Tags: url, sanitize, parameters, privacy, tracking
Requires at least: 4.5
Tested up to: 6.8.3
Requires PHP: 5.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Removes specified substrings or query parameters from URLs before they’re stored (posts, comments, options, meta).

== Description ==
- Strip tracking params like utm_* / gclid / fbclid
- Apply to post content/excerpt, comments, user profile URL, options/meta
- Configure patterns under **Settings → URL Stripper**

== Installation ==
1. Upload to `/wp-content/plugins/url-parameter-stripper`
2. Activate the plugin
3. Go to **Settings → URL Stripper** to configure

== Frequently Asked Questions ==
= Does it modify existing content? =
Only on save/update. To retroactively sanitize, re-save content or run a small WP-CLI script.

== Changelog ==

= 1.0.0 =
Initial release