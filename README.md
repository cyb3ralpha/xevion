# XEVION FaceMatch

Photo face-off platform by XEVION. Visitors create matches from 2–20 photos, share a public or invite-only link, and others vote head-to-head. Includes a role-based admin dashboard, moderation tools, and direct-sold advertising with first-party tracking.

Plain PHP 8.1+ and MySQL/MariaDB. No framework, no Node.js, no build step, no third-party services.

## Quick start (local, XAMPP or built-in server)

1. Create an empty MySQL database (utf8mb4) and a user with full rights on it.
2. Make `config/` and `storage/` writable by PHP.
3. Either:
   - **Web:** open `http://localhost/<folder>/install/`, paste the key from `storage/setup-key.txt`, fill in the form; or
   - **CLI:** `php cli/install.php` and answer the prompts.
4. Sign in at `/admin/`.

Without Apache you can run the built-in server: `php -S localhost:8000 router.php` (set the site URL to `http://localhost:8000` during install).

Production deployment, cron and hardening: **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.

## Deploy on Vercel

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fcyb3ralpha%2Fxevion&env=DATABASE_URL,ADMIN_EMAIL,ADMIN_PASSWORD&envDescription=A%20MySQL%20connection%20URL%20(mysql%3A%2F%2Fuser%3Apass%40host%3A3306%2Fdb)%20and%20the%20first%20admin%20login%20(password%2012%2B%20characters)&envLink=https%3A%2F%2Fgithub.com%2Fcyb3ralpha%2Fxevion%2Fblob%2Fmain%2Fdocs%2FVERCEL.md&project-name=xevion-facematch)

You need a MySQL database that accepts internet connections, such as TiDB Cloud Serverless, Aiven or Railway. Vercel asks for three values:

- `DATABASE_URL` is your database connection URL, `mysql://user:password@host:3306/dbname`.
- `ADMIN_EMAIL` and `ADMIN_PASSWORD` create the first administrator. The password needs 12 or more characters.

There is no install step. The first visit creates the tables and the admin account. Details and limits: **[docs/VERCEL.md](docs/VERCEL.md)**.

## What's inside

| Area | Where |
|---|---|
| Public pages: home, explore, categories, create, match, arena, results, report, contact, privacy, terms, errors | `index.php`, `app/controllers/public.php`, `templates/` |
| Voting engine and ranking | `app/voting.php` — see [docs/VOTING.md](docs/VOTING.md) |
| Access control (public / private / creator) | `app/competitions.php` |
| Uploads (re-encoding, metadata stripping) | `app/uploads.php` |
| Rate limits, sessions, CSRF, abuse blocks, retention | `app/security.php` |
| Advertising, impression/click tracking | `app/ads.php` |
| Admin dashboard and roles | `admin/`, `app/controllers/admin.php`, `app/admin_auth.php`, `templates/admin/` |
| Database schema | `database/schema.sql` |
| CLI: install, create admin, reset password, maintenance | `cli/` |
| Tests | `tests/run.php` (90 engine checks) |

Security model and privacy notes: **[docs/SECURITY.md](docs/SECURITY.md)**. Admin guide: **[docs/ADMIN.md](docs/ADMIN.md)**.

## Brand

Colours are sampled from the supplied logo files: navy `#0C0727` (logo background and wordmark ink), ribbon blue `#188CF1`, fold blue `#0659A5`, deep fold `#044A92`. They are defined once as CSS custom properties at the top of `assets/css/app.css` and reused by the admin stylesheet.

The original logo files are kept unchanged in `assets/img/brand/original-*`. The web versions (`wordmark-dark*.png`, `mark-dark*.png`, favicons) are the same artwork with only the empty background margin trimmed and scaled down; nothing is recoloured, redrawn or distorted. The site background uses the logo's exact navy so the dark-background versions sit seamlessly on the page.

The arena's VS element and the angled photo frames echo the two mirrored halves of the XEVION mark. Fonts (Outfit, Inter) are self-hosted under the SIL Open Font License.

## Running the tests

```bash
php tests/run.php    # engine, against the configured DB; cleans up after itself
```

Run it against a local or staging copy, not production — it creates (and then removes) real records.

## Local XAMPP notes

XAMPP ships with the `gd` and `sodium` extensions disabled. Enable `extension=gd` and `extension=sodium` in `C:\xampp\php\php.ini`, then restart Apache.
