=== UTM Traffic Recorder ===
Contributors: galih
Tags: utm, analytics, traffic, campaign, tracking
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.6.4
License: GPLv2 or later

Records pageviews, UTM parameters, and WhatsApp CTA clicks in your WordPress database.
Panduan penggunaan lengkap berbahasa Indonesia tersedia di DOKUMENTASI.md dalam paket plugin.

== Installation ==
1. Upload the `utm-traffic-recorder` folder or its ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open Traffic Recorder in the WordPress admin menu.

== Admin menus ==
* Dashboard Trafik & UTM: full-width responsive traffic dashboard with distinct browser ID and session totals, WhatsApp click totals, top UTM sources, and recent click details.
* Dashboard Chat WA: WhatsApp click totals, visitor sessions, top five accounts or numbers ranked by unique clicking sessions, session IDs, and UTM details per click.
* UTM Manager: browser IDs with their session and pageview counts (five rows by default, configurable from 1 to 500), top source, medium, campaign, content, and term rankings by distinct sessions, full pageview details including available referrer URLs and match type, browser ID filter, CSV export, and an optional Clean URL toggle.
* CTA WA: manage multiple WhatsApp accounts, avatars, numbers, titles, active days, page availability, custom colors, and shortcodes. Drag and drop account rows or use the up/down controls to arrange their priority. Toggle each account's appearance in the floating chat list directly in the table; the shortcode remains available. One floating WhatsApp launcher opens the account list with a configurable greeting, left/right position, separate background/text/accent colors, and button size from 80% to 140% with a live preview. This menu controls floating display, existing-link enhancement, WhatsApp UTM source mode and format, and an optional no-UTM fallback with its own label and current-page or custom URL.
* Settings: choose which UTM columns are recorded in visit and click reports, turn click tracking for existing api.whatsapp.com/send and wa.me links on or off, and turn Clean URL on or off. Both `utm_matchtype` and `utm_match_type` are accepted into the `utm_match_type` field.

Click a data table's column heading to sort the visible rows. On narrow screens, use the sort selector above the table. Sorting paginated reports applies to the current page only. Account table sorting is temporary; priority dragging resumes when the original order is restored.

The recent WhatsApp clicks and full click history use dedicated column widths. On medium screens the table scrolls horizontally; on phones it becomes labeled rows.

Each account has a shortcode such as `[utr_whatsapp id="1"]`. Active days and selected pages apply to both shortcode and the floating account list. Selected-page mode has a separate Home/front page checkbox, supporting static front pages and post-index homepages; previously selected WordPress front-page and posts-page IDs are still recognized. Existing WhatsApp links can be enhanced independently. Supported links include `api.whatsapp.com/send?phone=...`, `wa.me/send?phone=...`, and `wa.me/<number>`. Their recipient and pre-filled message are preserved. If a link has no `text` message, the matching account message or configured default message is used. A new tagged URL updates the active UTM. Standard anchor links in the page are updated, including links inserted later by JavaScript.

When tracking of existing links is enabled, outgoing WhatsApp links matching a configured account number are attributed to that account in the click report. If such a link has no pre-filled text, its account message is used. Account messages can contain line breaks and paragraphs; the source is appended after a blank line. The message editor includes a new-line button. The floating WhatsApp logo is rendered inline without a separate image request. Floating avatars use a 42px responsive image size, matching image preloads, and show a fallback while loading; supported themes render the widget at `wp_body_open`, with a footer fallback. The account list includes the "UTM Trafik Manager" copyright link to https://www.mtalkblog.com/p/utm-traffic-recorder-dokumentasi.html. The packaged WhatsApp icon is from Simple Icons (https://github.com/simple-icons/simple-icons).

The default WhatsApp message source uses the active UTM source and the first eight characters of the session ID, matching the session column in UTM Manager. For example: `Sumber: GADS+a1b2c3d4`. The CTA WA menu can change the label, uppercase/titlecase/lowercase formatting, selected UTM values, session/browser ID prefixes, and separator. When no UTM is available, an independent toggle controls whether a fallback source line is appended. Its label and link can be customized; by default it uses `Sumber: ` followed by the current page URL. The fallback line does not append session or browser IDs. New WhatsApp click records store the source line actually appended to the link, even if the format is changed later. Click records keep each enabled UTM field, destination account or number, and page path for 90 days. Logged-in visitors are excluded from tracking. The plugin does not store the base message text, visitor IP address, or user agent in click records.

The CTA WA message choices and the Settings report columns are independent. A UTM value can appear in the saved message source line while its structured click-report column is empty because that column is disabled in Settings. Both admin screens identify this configuration. The localStorage/sessionStorage switch for message text is now located with the other CTA WA format controls; structured click-report UTM columns continue to use the latest localStorage attribution.

Plugin author: Galih Enggarnanto — https://www.mtalkblog.com

== How it works ==
The browser's localStorage holds one persistent browser ID and all supported current UTM values under `utr_visitor_v1`. A first-party `utr_vid_v1` cookie contains only the browser ID as a fallback if localStorage cannot be read; the plugin does not place UTM values in cookies. SessionStorage holds the tab session ID, landing path, referrer, and all current session UTM values under `utr_session_v2`. A new tagged URL replaces the complete UTM set, clearing values absent from that URL. Settings selects which UTM columns are sent to WordPress reports; it does not remove values needed by the WhatsApp message from browser storage. One browser ID can have multiple session IDs. The older `utr_wa_utm_v1` and `utr_wa_session_utm_v1` snapshots are migrated into the corresponding primary keys and removed on the next public page load. Each pageview and WhatsApp click sends the browser and session IDs and only enabled report UTM fields to WordPress. The WhatsApp message source can use either persistent localStorage UTM (default) or active session UTM. Page URLs are stored as paths only, while referrer URLs can include a path and query string when the browser provides them. IP addresses and user agents are not stored. Logged-in visits are excluded. Database records are removed after 90 days by WP-Cron; localStorage and the cookie remain until cleared by the visitor or cookie expiry. The admin report uses the site's WordPress time zone and can be filtered by date, UTM source, and browser ID. CSV export includes the filtered records with UTC timestamps.

Example: https://example.com/?utm_source=newsletter&utm_medium=email&utm_campaign=october

The browser snapshots are only rewritten when their values change, and stored UTM values are limited to 191 characters. The tracker verifies storage writes and uses the current page's in-memory UTM when a write fails. WhatsApp messages otherwise read the current localStorage snapshot when clicked, including updates made in another tab. Browser storage or cookies may be disabled or cleared by the visitor; in that case persistent attribution cannot be guaranteed.

When the UTM URL toggle is enabled, an early inline head script removes supported UTM parameters immediately, including both match type spellings and case variants. An external head script provides a fallback for sites that block inline scripts. Other query parameters and the URL fragment stay in place. The incoming UTM values are captured before cleanup and remain available to traffic recording and WhatsApp source messages. Shared campaign links must continue to include UTM parameters so visits can be attributed. This client-side feature requires JavaScript and cannot remove the original UTM URL from the initial HTTP request or guarantee it never appears briefly.

== Notes ==
Tracking requires JavaScript and the WordPress REST API. Browser privacy tools may block requests. A browser tab is treated as one visit, so opening another tab can count as another visit. WP-Cron cleanup runs when WordPress receives traffic.
Since 2.4.2, pageview and WhatsApp click endpoints reject requests unless both browser and session IDs are valid UUIDs. Clear page and script caches after upgrading so old tracking scripts do not keep sending incomplete requests. Existing rows with a missing browser ID cannot be reconstructed without a reliable original identifier.
