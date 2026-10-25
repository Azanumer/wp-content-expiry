# Content Expiry (WordPress plugin)

Time-limited content — offers, event announcements, job posts, seasonal
pages — should not stay live forever. This plugin adds an expiry date to
posts and pages; on expiry the content is automatically moved to draft,
trash, or private.

## Features

- **Expiry meta box** on posts and pages: date/time + action (draft / trash / private).
- **Hourly sweep** via WP-Cron applies the action to expired published posts.
- **Post-list column** shows each expiry date, highlighted red when under 7 days away.
- **Safe re-publish**: expiry meta is cleared when an item expires, so
  re-publishing it does not instantly re-expire it.
- Malformed dates are ignored rather than stored.
- Works in the site's timezone (dates are stored and compared consistently).

## Install

1. Copy this folder to `wp-content/plugins/wp-content-expiry/`.
2. Activate **Content Expiry**.
3. Set an expiry date in the sidebar meta box when editing a post or page.

## Uninstall

Deactivation clears the hourly sweep; `uninstall.php` removes all expiry
postmeta (published content itself is untouched).

## License

MIT — see `LICENSE`.
