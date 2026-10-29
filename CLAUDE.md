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
| `api/index.php` | Vercel entry point; dispatches to the two controllers above |
| `app/bootstrap.php` | Loads `config/config.php` plus platform defaults, helpers, error handling, auto-setup |
| `app/auto_setup.php` | First-run setup on every host: tables, app secret, first admin |
| `app/uploads.php` | Image validation, GD re-encoding or no-GD metadata stripping, file or DB storage |
| `app/security.php` | Sessions (file or DB), CSRF, rate limits, abuse blocks, housekeeping |
| `app/controllers/public.php`, `admin.php` | Page handlers |
| `templates/` | PHP templates; `partials/icons.php` is the SVG icon sprite |
| `assets/css/app.css` | All public styles; the "Visual & motion layer" section holds the animations |
| `assets/js/app.js` | Progressive enhancement: reveals, ticker, create form, photo resizing, arena |
| `media/brand/`, `media/uploads/` | Brand images (originals in `media/brand/originals/`); database-mode uploads (git-ignored, never served) |
| `database/schema.sql` | Idempotent schema (`CREATE TABLE IF NOT EXISTS`) |
| `docs/` | `DEPLOYMENT.md`, `VERCEL.md`, `SECURITY.md`, `ADMIN.md`, `VOTING.md`; original logo files in `docs/brand/` |
| `router.php` | Router for PHP's built-in server (`php -S localhost:8000 router.php`) |

## Local development (XAMPP on Windows)

- Site: http://localhost/xevion/ and admin http://localhost/xevion/admin/
- Database `facematch`, user `facematch`. Credentials are in `config/config.php`, which is git-ignored.
- XAMPP ships with `gd` and `sodium` disabled. Both were enabled in `C:\xampp\php\php.ini`; the original is backed up as `php.ini.bak-before-xevion`. Restart Apache after changing php.ini.
- Apache was started from the XAMPP Control Panel. Restarting it from a shell leaves the panel out of sync.
- PHP CLI: `C:\xampp\php\php.exe`. MySQL CLI: `C:\xampp\mysql\bin\mysql.exe -u root`.
- Git was installed with winget (`C:\Program Files\Git`). Pushing works from the Bash tool or the VS Code terminal.

Commands:

```bash
php -l path/to/file.php                 # lint after editing PHP
php -S localhost:8090 api/index.php     # Vercel-style local run (see docs/VERCEL.md)
```

## Vercel deployment

- `vercel.json` uses the community runtime `vercel-php@0.8.0` (PHP 8.4). Routes send `/assets/*`, `/media/brand/*` and `robots.txt` to static files and everything else to `api/index.php`, so source files are never served.
- All configuration, including the database, comes only from `config/config.php` (owner's decision, Oct 28). It is git-ignored; to deploy on Vercel the owner either makes the repo private and commits it, or deploys with the Vercel CLI (`.vercelignore` deliberately does not exclude it). The only environment variables read are Vercel's own `VERCEL` and `VERCEL_PROJECT_PRODUCTION_URL`, for platform defaults (`platform_defaults()` in `app/bootstrap.php`), plus `CRON_SECRET`, which Vercel Cron sends and must equal `cron_secret`.
- First request runs `auto_setup_boot()`: applies the schema, stores a generated app secret in `app_meta` when `app_secret` is empty, and creates the super admin from `admin_email`/`admin_password`. Bump `SCHEMA_VERSION` in `app/auto_setup.php` whenever `database/schema.sql` changes.
- Sessions always live in the `sessions` table on every host. On Vercel, photos go to `media_files` and logs to stderr automatically.
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

10. **Oct 28: rollback incident.** Production (www.xevion.pk, xevion-delta.vercel.app) had been rolled back to commit c1bd9a6, which has no vercel.json, so Vercel served index.php as a download. The rollback also disabled automatic production updates. Fix is in the Vercel dashboard: promote the newest deployment. Added a troubleshooting section to docs/VERCEL.md.

11. **Oct 28: clean structure.** The owner deleted `cli/`, `install/`, `tests/`, `config/config.sample.php` and `storage/sessions/`. To keep setup working everywhere, the automatic first-visit setup now runs on every host: ordinary hosting reads `admin_email` and `admin_password` from `config/config.php`, Vercel reads `ADMIN_EMAIL` and `ADMIN_PASSWORD`. Removed `app/installer.php`, the `/install` routes and `SETUP_KEY`; moved the unused original logo files to `docs/brand/`; removed the duplicate `docs/CLUADE.md`; rewrote the README with a structure overview and updated all guides. Maintenance on ordinary hosting is now a cron job calling `/api/cron`. There is no automated test suite any more; verify changes with HTTP smoke tests.

12. **Oct 28: database sessions and config-file-only settings.** Sessions are now always stored in the database (no file sessions anywhere). Environment-variable configuration (`app/env_config.php`) was removed: the database and all settings come only from `config/config.php`. `docs/VERCEL.md` explains how to get that file onto Vercel safely; the README's env-var deploy button was removed.

13. **Oct 28: preview mode without a database.** The owner wanted the site to work on Vercel with no database. `config/config.php` is now optional; `db_enabled()` (helpers.php) is false without `db.host`/`db.name`. Then: no auto-setup, PHP's default sessions, empty listings, no ads or housekeeping, and `page_unavailable()` ("… is coming soon", HTTP 503) for create, report, contact, voting APIs and the admin. Match, media and cron routes return 404. With a database everything works as before. An upload-ready InfinityFree package with that host's database was also left on the owner's Desktop (`xevion-infinityfree`), since the InfinityFree database is unreachable from Vercel.

14. **Oct 28: device mode instead of preview mode.** The owner wants the site fully usable on Vercel with no database. Without `db_enabled()`: `/create` renders the normal form with `data-local`, and `app.js` saves the match (photos as JPEG data URLs) in IndexedDB (`xevion-facematch`/`matches`). `/play#id=…` is a client-side arena with pairwise rounds, standings and "Next voter". `/play#s=…` is a share link: deflate-raw + base64url JSON with 240×300 photos in the URL fragment, imported once per link (id = SHA-256 of the payload). Explore and categories list device matches (`local_list.php`). Admin, report, contact and the server vote APIs answer `page_unavailable()`. Votes are per device and are never aggregated. With a database, `/play` is 404 and everything is server-side as before. Also fixed: `.arena-wrap` no longer removes the container's side padding on phones.

15. **Oct 28: master-prompt pass (no database, Vercel).** Brand images moved to `media/brand/` (`brand_img()` helper; originals in `media/brand/originals/`); database-mode uploads moved from `storage/media/` to `media/uploads/` (`uploads_path()`, git-ignored, blocked in `.htaccess`/`router.php`, existing local photos moved). Browser mode gained private matches (AES-GCM link encrypted with a random access code via PBKDF2 210k; lock screen on `/play`), file-signature checks on chosen photos, dimension limits on create, and full validation of shared payloads (size cap, decoded image check). README rewritten around the two modes and limits; SECURITY.md has a browser-mode section. Verified: PHP lint of all 59 files, secret scan of tracked files, Chrome tests of fake-image/SVG rejection, private link with wrong and right codes, brand images, and XAMPP regression (moved photos still served). No package manager exists, so there is nothing to install, build or audit.

16. **Oct 28: live review and admin without a database.** Browsed www.xevion.pk with a DevTools script that records JS exceptions, console errors and failed requests: no errors, no broken images. Corrections: Contact and Report became real pages (`local_contact.php`, using `CONTACT_EMAIL`), Privacy describes browser mode, the home "Share the link" step matches the mode, the play page shows its match ID with a Report link. Added `app/admin_lite.php` + `templates/admin/lite.php`: sign-in from `ADMIN_EMAIL` + `ADMIN_PASSWORD_HASH`/`ADMIN_PASSWORD` env vars, HMAC-signed cookie session, double-submit CSRF + Origin check (host and port), Status, Moderation (decode a share link without saving; `BLOCKED_MATCHES` env list enforced on /play and in lists) and device matches (delete, JSON backup and validated restore). Fixed during testing: the Origin check compared host only, so browser sign-in failed when a port was present. `.env.example` added.

17. **Oct 29: fixes after owner testing.** Photos with an empty MIME type (some phones and apps) were rejected before the byte check ran; now only a wrong non-empty type is refused and the file signature decides. iPhone HEIC files get a specific message. Admin sign-in now tolerates a plain password placed in `ADMIN_PASSWORD_HASH` and quoted env values, and the error explains what must match (and that Vercel needs a redeploy after changes).

## Known limits and open items

- The live Vercel deployment could not be inspected: the owner's Vercel project is not visible to the connected Vercel account.
- Tests were run on PHP 8.2 locally; Vercel runs PHP 8.4. A static scan found no 8.4 issues.
- Anonymous voting cannot guarantee one person, one vote; see `docs/SECURITY.md`.
