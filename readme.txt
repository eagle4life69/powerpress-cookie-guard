=== PowerPress Cookie Guard ===
Contributors: eagle4life69
Tags: powerpress, cookies, podcast, compatibility, http 400
Requires at least: 5.0
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Temporary compatibility guard for PowerPress pp_event_* cookie accumulation.

== Description ==

PowerPress Cookie Guard is a temporary compatibility plugin created in response to PowerPress 11.17.9 creating per-feed/per-post pp_event_* session cookies. On a busy podcast site or a long-running browser session, these cookies can accumulate until the browser's Cookie request header exceeds the web server's request-header limit, producing an HTTP 400 error such as:

"Size of a request header field exceeds server limit."

The guard does not modify PowerPress and does not touch WordPress authentication cookies. It only considers cookies whose names begin with pp_event_.

To avoid suppressing a newly-created PowerPress success/error notice, the plugin preserves the newest six PowerPress event cookies and expires older event cookies when WordPress is able to receive a request.

Important: If the browser has already accumulated enough cookies for the web server to reject the request before PHP/WordPress runs, clear the site's cookies once. After that, this plugin is intended to prevent the PowerPress event-cookie collection from growing back to that point.

The Plugins screen displays a status notice with the current PowerPress event-cookie count, cleanup total, and last cleanup time.

== Installation ==

1. Install the plugin ZIP in WordPress or copy the powerpress-cookie-guard folder to wp-content/plugins/.
2. Activate PowerPress Cookie Guard.
3. If the site is already returning the HTTP 400 header-size error in the current browser, clear the site's cookies once and log back in.
4. Use WordPress/PowerPress normally. The plugin will automatically keep the pp_event_* collection bounded.

== GitHub Updates ==

This plugin includes a native GitHub Release updater. WordPress checks the latest published release at:

https://github.com/eagle4life69/powerpress-cookie-guard/releases/latest

Published releases appear in WordPress's normal plugin update system and can participate in WordPress automatic plugin updates when Auto-updates is enabled for the plugin.

Development commits on the main branch are not treated as production updates. Publish a GitHub Release with a version tag such as v1.0.1 after updating the plugin Version, PPCG_VERSION, and Stable tag.

== Changelog ==

= 1.0.0 =
* Initial release.
* Detects only PowerPress pp_event_* cookies.
* Keeps the newest six PowerPress event cookies so recent PowerPress notices can still be consumed.
* Expires older pp_event_* cookies before they can accumulate indefinitely.
* Does not modify or delete WordPress authentication cookies.
* Adds cleanup counters and status information to the WordPress Plugins screen.
* Includes native GitHub Release update support.
* Uses GitHub Releases rather than development commits as the production update channel.

== Technical Notes ==

PowerPress 11.17.9 creates cookies with names similar to:

pp_event_podcast_12345_success
pp_event_podcast_12345_add_notice
pp_event_members_12345_add_notice

The post ID is included in the cookie name and the cookies use the site-wide / path. Different posts can therefore produce separate cookies that are sent together in the Cookie request header.

This plugin is intentionally narrow in scope. It does not attempt to fix or replace PowerPress's notice system. It only bounds the number of pp_event_* cookies retained by the browser while the upstream issue is investigated.
