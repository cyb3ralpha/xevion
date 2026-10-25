# Deploying XEVION FaceMatch on Vercel

Vercel has no built-in PHP and no MySQL, and its servers have no permanent disk. The app handles this with an environment mode, which switches on automatically when Vercel runs it:

| Concern | Ordinary PHP hosting / XAMPP | Vercel |
|---|---|---|
| PHP | Apache + mod_php | Community runtime `vercel-php` (PHP 8.4), see `vercel.json` |
| Routing | `.htaccess` | `vercel.json` sends everything except `/assets/*` and `robots.txt` to `api/index.php` |
| Settings | `config/config.php` written by the installer | Environment variables |
| Sessions | `storage/sessions/` | `sessions` table |
| Photos and banners | `storage/media/` | `media_files` table |
| Logs | `storage/logs/` | stderr (Vercel → Logs) |
| Image processing | GD re-encodes every photo | The browser resizes to JPEG; PHP validates and strips all metadata (GD is not available on Vercel) |
| Maintenance | cron runs `cli/maintenance.php` | Vercel Cron calls `/api/cron` daily |

## 1. Create a MySQL database

Vercel does not host MySQL. Use any MySQL 8 / MariaDB service that accepts connections from the internet, for example TiDB Cloud Serverless, Aiven for MySQL, Railway or your own server. Create an empty database and a user with full rights on it, and note the host, port, database name, user and password.

Photos are stored in this database, roughly 100–300 KB each, so pick a plan with enough storage.

## 2. Import the project into Vercel

In Vercel choose **Add New → Project**, import the GitHub repository and keep the defaults. There is no framework preset or build command. `vercel.json` configures everything.

## 3. Set environment variables

In **Project → Settings → Environment Variables** add the database connection and an admin login. That is all that is required:

| Variable | Required | Value |
|---|---|---|
| `DATABASE_URL` | yes, or the five below | `mysql://user:password@host:3306/dbname` (`MYSQL_URL` is also accepted) |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | alternative | Separate values instead of a URL (`MYSQL_HOST`, `MYSQLHOST` and similar provider names also work) |
| `ADMIN_EMAIL` | yes | Email for the first super administrator |
| `ADMIN_PASSWORD` | yes | 12+ characters. Used only to create the account; change it later in the dashboard |
| `ADMIN_NAME` | no | Display name, default `Administrator` |
| `APP_SECRET` | no | 64 random hex characters. If unset, one is generated and kept in the database. If you set it, set it before the first visit and never change it |
| `CRON_SECRET` | recommended | Random string of 16+ characters; enables the daily maintenance call from Vercel Cron |
| `BASE_URL` | no | Public address for share links, e.g. `https://facematch.example.com`. Defaults to the Vercel production domain |
| `DB_SSL` | no | Default `true`; set `false` only if your database has no TLS |

Mark passwords and secrets as sensitive, then **redeploy** (Deployments → ⋯ → Redeploy) so the new variables take effect.

## 4. Open the site

There is no installation step. The first visit creates the tables, the application secret and the administrator account. Sign in at `/admin/` with `ADMIN_EMAIL` and `ADMIN_PASSWORD`.

If the variables are missing, the site shows **Database not connected** with the list above. If they are wrong, it shows **Cannot reach the database**, and the Vercel function logs give the MySQL error code.

Without `ADMIN_EMAIL`/`ADMIN_PASSWORD` you can still create the administrator at `/install`, using a `SETUP_KEY` variable as the one-time key.

Later deploys that add tables apply them automatically.
## Limits to know

- **Request size:** Vercel rejects requests over 4.5 MB. The create form resizes every photo in the browser and shrinks them further until the whole match fits. With JavaScript disabled, uploads larger than that fail.
- **Without JavaScript,** only JPG and PNG are accepted on Vercel, because WebP cannot be cleaned without GD.
- **Thumbnails** reuse the full photo on Vercel; pages crop it with CSS.
- **Vercel Cron on the Hobby plan** runs at most once a day. Housekeeping also runs on about 2% of normal requests, and closing times are always checked live, so a daily run is enough.
- **Cold starts:** the first request after a quiet period takes longer.
- `vercel-php` is a community runtime, not an official Vercel product.

## Local check of Vercel mode

Run PHP's built-in server with environment variables instead of `config/config.php`:

```bash
APP_CONFIG_FROM_ENV=1 VERCEL=1 DB_SSL=0 DB_HOST=127.0.0.1 DB_NAME=facematch_test DB_USER=... DB_PASSWORD=... \
APP_SECRET=... SETUP_KEY=... php -S localhost:8090 api/index.php
```

`APP_CONFIG_FROM_ENV=1` makes the environment win even when `config/config.php` exists.
