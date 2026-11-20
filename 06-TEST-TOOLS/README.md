# dev-tools

Scripts used to test SEO Boster on a local WordPress (see DEVELOPER-GUIDE.md section 6).

| File | Use |
|------|-----|
| `wp-config.sample.php` | wp-config for a local SQLite WordPress at http://127.0.0.1:8899. Salts are dummy values: local testing only. |
| `install-wordpress.php` | Installs WordPress (admin / testpass123, local only). Put it next to the `wp` folder and run `php install-wordpress.php`. |
| `fake-ai.php` | Must-use plugin that answers Anthropic API calls with valid page JSON, so no AI credits are used. Breaks the first answer for each page on purpose to test the "fix" call. |
| `example-job-test.php` | Creates a small job and runs it step by step, then tests auto-pause on a repeated 404 error. |
| `example-menu-test.php` | Builds the Areas We Serve menu, hub page, regroups by service, removes the items. |

All scripts expect to sit next to the `wp` folder (they load `__DIR__ . '/wp/wp-load.php'`).
