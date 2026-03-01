=== Lockfront ===
Contributors: appscreo
Tags: maintenance mode, coming soon, password protection, development, site lock
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Password-protect your WordPress site while it is under development. Clean, fast, zero dependencies.

== Description ==

Lockfront places a stylish password gate in front of your entire site while you build it. Once the correct password is entered a secure cookie is set so the visitor is not asked again.

= Features =

* Enable / disable protection toggle
* Configurable site password with show/hide button
* Unlock duration in days (or browser-session only)
* Brute-force protection – configurable attempt limit and rolling time window
* Login log – every attempt recorded in a dedicated DB table
* IP whitelist – per-IP and CIDR notation supported
* Bypass for logged-in administrators
* Optionally allow the REST API and RSS feeds through
* URL key bypass – a permanent secret key=value pair in the URL
* Temporary bypass links – time-limited, use-limited, DB-backed one-time tokens
* Fully customisable password page:
  * Headline, sub-headline, button label, placeholder, error message, footer text
  * Form placement: centred, left, or right
  * Background: solid colour, two-colour gradient with direction control, or image
  * Image overlay colour and opacity
  * Card: background, max width, border radius, drop shadow
  * Typography: Google Fonts picker with live admin preview, CSS fallback
  * Custom logo (replaces the lock icon)
  * Input field colours and border radius
  * Button colours and border radius
  * Error message colour
* Live preview of the password page directly in the admin

== Installation ==

1. Upload the `lockfront` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Go to **Lockfront → Settings**
4. Enter a password under the **General** tab and enable protection
5. Save Changes

== Changelog ==

= 1.0.0 =
* Initial release
