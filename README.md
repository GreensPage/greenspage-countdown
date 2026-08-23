# Greenspage Countdown

> Launches deserve more than a date.

A WordPress countdown block that is quick to configure and honest about what happens when time runs out.

**Product page:** https://greenspage.com/plugins/countdown/

## Features

- Fixed-date and evergreen (per-visitor) countdowns
- Country-aware date formatting and proper timezone handling
- Boxes or inline layouts, configurable units and labels
- Optional progress bar
- Four expiry behaviours: sit at zero, hide, swap in a message, or redirect
- Bundled **Launch Hero + Countdown** block pattern
- Re-themeable launch pattern via CSS custom properties on `.gp-launch-hero`
- Accessibility-conscious output: live region announcements, unit labels, and reduced-motion support

## Install

Download the latest `greenspage-countdown.zip` from the
[Releases](https://github.com/greenspage/greenspage-countdown/releases/latest) page,
then in WordPress go to **Plugins → Add Plugin → Upload Plugin**.

Once installed, the plugin checks this repository's releases and offers updates
through the normal WordPress updates screen — no account or token required.

## Requirements

- WordPress 6.3+
- PHP 7.4+

## Releasing (maintainers)

Releases are automated. Bump the version in `greenspage-countdown.php` and
`readme.txt`, commit, then tag:

```bash
git tag v1.1.1
git push origin v1.1.1
```

The release workflow builds `greenspage-countdown.zip` (excluding dev tooling in
`.distignore`) and attaches it to the GitHub release. The plugin's updater and
the site Download button both resolve to that asset.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
