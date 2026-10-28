# CLAUDE.md: XEVION FaceMatch

Project notes for Claude Code. They cover what the app is, how to run it, how it is deployed, and the decisions made so far, in date order. Never put passwords, keys or tokens in this file: the repository is public.

## What this is

XEVION FaceMatch is a photo face-off site. Visitors create a match from 2 to 20 photos, pick a category question such as "Who has the best smile?", share a public or invite-only link, and others vote head-to-head. There are no visitor accounts. Creators manage a match through a secret management link. Administrators sign in at `/admin/`.

- Plain PHP 8.1+ with MySQL or MariaDB. There is no framework, no Composer and no build step.
- The original product brief is the "MASTER DEVELOPMENT PROMPT" the owner pasted at the start. The app already implemented it when the work in this file began.
- Repository: https://github.com/cyb3ralpha/xevion, branch `main`. Vercel deploys from it.

## Layout

| Path | Purpose |
|---|---|
| `index.php` | Public front controller and route table |
| `admin/index.php` | Admin front controller |
| `install/index.php` | One-time web installer (classic hosting); on Vercel it is optional |
| `api/index.php` | Vercel entry point; dispatches to the three controllers above |
| `app/bootstrap.php` | Loads config (file or environment), helpers, error handling, auto-setup |
| `app/env_config.php` | Settings from environment variables (Vercel) |
| `app/auto_setup.php` | Vercel first-run setup: tables, app secret, first admin |
| `app/uploads.php` | Image validation, GD re-encoding or no-GD metadata stripping, file or DB storage |
| `app/security.php` | Sessions (file or DB), CSRF, rate limits, abuse blocks, housekeeping |
| `app/controllers/public.php`, `admin.php` | Page handlers |
| `templates/` | PHP templates; `partials/icons.php` is the SVG icon sprite |
| `assets/css/app.css` | All public styles; the "Visual & motion layer" section holds the animations |
| `assets/js/app.js` | Progressive enhancement: reveals, ticker, create form, photo resizing, arena |
| `database/schema.sql` | Idempotent schema (`CREATE TABLE IF NOT EXISTS`) |
| `tests/run.php` | 90 engine checks against the configured database; cleans up after itself |
| `docs/` | `DEPLOYMENT.md`, `VERCEL.md`, `SECURITY.md`, `ADMIN.md`, `VOTING.md` |

## Local development (XAMPP on Windows)

- Site: http://localhost/xevion/ and admin http://localhost/xevion/admin/
- Database `facematch`, user `facematch`. Credentials are in `config/config.php`, which is git-ignored.
- XAMPP ships with `gd` and `sodium` disabled. Both were enabled in `C:\xampp\php\php.ini`; the original is backed up as `php.ini.bak-before-xevion`. Restart Apache after changing php.ini.
- Apache was started from the XAMPP Control Panel. Restarting it from a shell leaves the panel out of sync.
- PHP CLI: `C:\xampp\php\php.exe`. MySQL CLI: `C:\xampp\mysql\bin\mysql.exe -u root`.
- Git was installed with winget (`C:\Program Files\Git`). Pushing works from the Bash tool or the VS Code terminal.

Commands:

```bash
php tests/run.php                       # engine tests; must stay at 90 passed
php -l path/to/file.php                 # lint after editing PHP
php -S localhost:8090 api/index.php     # Vercel-style local run (see docs/VERCEL.md)
```

## Vercel deployment

- `vercel.json` uses the community runtime `vercel-php@0.8.0` (PHP 8.4). Routes send `/assets/*` and `robots.txt` to static files and everything else to `api/index.php`, so source files are never served.
- With no `config/config.php`, settings come from environment variables. Required: `DATABASE_URL` (or `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`; provider names like `MYSQL_URL` and `MYSQLHOST` also work), `ADMIN_EMAIL`, `ADMIN_PASSWORD` (12+ characters). Optional: `APP_SECRET`, `CRON_SECRET`, `BASE_URL`, `DB_SSL`, `SETUP_KEY`.
- First request runs `auto_setup_boot()`: applies the schema, stores a generated app secret in `app_meta` when `APP_SECRET` is unset, and creates the super admin from the environment. Bump `SCHEMA_VERSION` in `app/auto_setup.php` whenever `database/schema.sql` changes.
- On Vercel, sessions live in the `sessions` table and photos in `media_files`. Logs go to stderr.
- The Vercel PHP runtime has no GD or Imagick. The browser resizes photos to JPEG before upload, which also keeps requests under Vercel's 4.5 MB limit. PHP then strips EXIF, XMP, comments and PNG text chunks. Thumbnails reuse the full photo there.
- Vercel Cron calls `/api/cron` daily with `Authorization: Bearer <CRON_SECRET>`; without the secret it returns 404.
- Vercel cannot host MySQL; the owner must supply an external database (TiDB Cloud Serverless, Aiven, Railway, ...). Without one the site shows "Database not connected".
- The README has a "Deploy with Vercel" button that asks for the three required variables.

## Conventions

- All SQL goes through prepared statements via `q()`, `q_one()`, `q_all()`, `q_val()`.
- Escape all output with `e()`. Every POST form includes `csrf_field()`.
- Server-side validation is the source of truth; JavaScript only improves usability.
- Content Security Policy allows only same-origin scripts, so there are no inline scripts. Inline `style` attributes are allowed.
- Icons: call `icon('name')` with a symbol from `templates/partials/icons.php`; categories map through `category_icon()`. Do not use emojis in the UI; the owner asked for line icons instead.
- Animations must respect `prefers-reduced-motion` (see the end of `app.css`). Scroll reveals use `data-reveal` and only apply when the `js` class is on `<html>`.
- Never show invented numbers (fake users, votes or stats). Use only real database data.
- Keep pages free of horizontal scroll at 390px wide.

## Testing the UI

Headless Chrome will not lay out narrower than 512px with `--window-size`. For real phone-width checks, drive Chrome through the DevTools protocol with `Emulation.setDeviceMetricsOverride`. A PowerShell helper for this lived in the session scratchpad (`cdp-shot.ps1`). When a full-page capture looks blank below the fold, it is because `data-reveal` sections only appear on scroll; add the `in` class before capturing.

## History of changes (October 2026)

1. **Oct 25: got it running.** Enabled `gd` and `sodium` in XAMPP, created the database and user, ran the CLI installer, restarted Apache, verified routes and all 90 tests. Removed the unused Python browser tests (`tests/e2e`) and an unused logo derivative. Kept the original logo files and font licences on purpose.
2. **Oct 25: admin tweaks.** Removed the "Forgot your password?" note from the admin login, the "Recent admin activity" panel from the dashboard, and the "View site" link from the admin sidebar. Activity is still recorded in the audit log.
3. **Oct 25: GitHub.** Installed Git and pushed to `cyb3ralpha/xevion`. Windows Application Control briefly blocked Git's HTTPS helper; it worked later. Credentials go through Git Credential Manager.
4. **Oct 25: Vercel support.** Added `vercel.json`, `api/index.php`, `api/php.ini`, `.vercelignore`, environment config, MySQL TLS, DB-backed sessions and media, the no-GD upload path with browser-side resizing, the cron endpoint and `docs/VERCEL.md`. Verified with a local simulation without GD.
5. **Oct 25: automatic setup.** Removed the manual install step on Vercel: `app/auto_setup.php`, the `app_meta` table, provider variable aliases, and clear "Database not connected" and "Cannot reach the database" pages.
6. **Oct 27: friendlier UI.** Removed the "advertise with us" placeholder bar, its setting and the footer link. Paid campaigns created in the admin still show, labelled "Advertisement". Category tiles show their question and link empty categories to the create form with the category preselected. Two-per-row layouts on phones. Fixed a 20px sideways scroll on the home page caused by the hero slide-in animation.
7. **Oct 27: Vercel readiness check.** Audited for PHP 8.4 issues and disk writes, read the `vercel-php` launcher source, added the "Deploy with Vercel" button, and ran a full end-to-end in Vercel mode.
8. **Oct 28: animated redesign.** New hero with a drifting aurora, gradient headline, rotating category question, floating category labels, animated vote meter and shiny buttons. Line icons replaced emojis everywhere. Card and tile hover motion, scroll reveals, a sticky header that gains a shadow on scroll, and a closing call-to-action band that replaces the old empty-state box. Removed clutter: the "A XEVION product" eyebrow, the preview caption, the Explore category dropdown that duplicated the chips, and "(0)" chip counts. Fixed stretched contender and winner photos.

9. **Oct 28: Home link.** Added a "Home" link with a house icon as the first item in the main navigation. It is highlighted on the home page.

## Known limits and open items

- The live Vercel deployment could not be inspected: the owner's Vercel project is not visible to the connected Vercel account.
- Tests were run on PHP 8.2 locally; Vercel runs PHP 8.4. A static scan found no 8.4 issues.
- Anonymous voting cannot guarantee one person, one vote; see `docs/SECURITY.md`.
