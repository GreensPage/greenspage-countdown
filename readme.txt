=== Greenspage Countdown ===
Contributors: greenspage
Tags: countdown, timer, launch, evergreen, deadline
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later

A countdown block that is quick to set up and honest about what happens when it runs out.

== Description ==

One block, two modes:

* **Fixed date** — everyone sees the same deadline. Pick a country, pick a timezone from that country's short list, pick a date. No offset arithmetic, no daylight-saving surprises.
* **Evergreen** — each visitor gets their own clock, starting the first time they see the block, with an optional restart window.

It also does the thing most countdowns forget: it decides what happens at zero. Sit at zero, hide itself, swap in a message, or send the visitor somewhere.

= Why the country picker =

Choosing a country sets the date format and narrows the timezone list to that country's zones, so nobody scrolls four hundred entries to find Brisbane. "Other" still exposes the full list.

= Honest copy =

The intro line accepts `{date}`, `{time}` and `{weekday}` tokens, rendered server-side from the target date. Headline copy cannot drift out of sync with the clock, because it is generated from the same value.

= Notes =

* All counting happens in the browser, so full-page caching (WP Rocket, Cloudflare, and friends) stays correct.
* The frontend script is roughly 4KB, has no dependencies, and only loads on pages that actually use the block.
* The ticking digits are hidden from screen readers; a separate live region announces the remaining time in words, at most once every ten seconds.
* Evergreen state lives in the visitor's own browser (localStorage). Blocked storage degrades to a fresh timer for that pageview rather than an error.
* No options, no database tables, no tracking.

== Installation ==

1. Download the release asset named `greenspage-countdown.zip` — not GitHub's auto-generated "Source code" archives.
2. Upload it via Plugins → Add Plugin → Upload Plugin.
3. Activate.
4. Add the "Countdown" block to any page.

== Frequently Asked Questions ==

= Can I restyle it? =

Yes. Everything hangs off CSS custom properties on `.gp-cd`: `--gp-cd-accent`, `--gp-cd-num-size`, `--gp-cd-label-size`, `--gp-cd-radius`, `--gp-cd-box-bg`, `--gp-cd-box-shadow`, `--gp-cd-track`, `--gp-cd-gap`.

= Can I add or remove countries? =

Filter `gpcd_countries`. Each entry is `code => array( label, php_date_format, array_of_timezones )`.

= What happens if I hide the days unit? =

Those days roll into hours rather than disappearing, so a three-day countdown shows 71 hours, not zero.

= Where is the ready-made launch page? =

Insert the "Launch Hero + Countdown" pattern from the block inserter (Patterns -> Greenspage). It seeds a live date two Fridays out so it never shows a frozen clock; change it in the countdown block's sidebar.

== Changelog ==

= 1.1.2 =
* Maintenance: clarified installation instructions so the canonical `greenspage-countdown.zip` release asset is not confused with GitHub's auto-generated source archives.

= 1.1.1 =
* Fixed: the expiry message now uses the page's own text colour for reliable contrast, instead of the number accent colour. New --gp-cd-expired-color variable to override per block.
* Fixed: on the "swap in a message" expiry, the countdown boxes now hide correctly instead of remaining behind the message.
* Editor: the date field now states which timezone the clock time is read in, so short (sub-hour) countdowns are no longer thrown off by a stale timezone.
* Editor: first-use timezone suggestion — offers the WordPress site timezone (browser zone only as a fallback), applied only when you confirm it. No IP lookup, no network, no tracking.
* Editor: a target time that has already passed in the chosen timezone now shows a clear warning (with a one-click switch to the site timezone) instead of a silent row of zeroes.
* Editor: clarified that Country sets the date format while Timezone sets the actual moment zero is reached.
* Admin: the plugin's "Visit plugin site" link now opens the product page in a new tab (this plugin's row only).
* Added: automatic update notifications, delivered from Greenspage's GitHub releases. No account or token required.
* Plugin page moved to https://greenspage.com/plugins/countdown/.

= 1.1.0 =
* Added a bundled "Launch Hero + Countdown" block pattern (Inserter -> Patterns -> Greenspage). Drop it in, change the date, edit the copy.
* Pattern styling is theme-able via CSS custom properties on .gp-launch-hero.

= 1.0.0 =
* First release.
