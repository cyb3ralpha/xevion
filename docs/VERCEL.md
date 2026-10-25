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

In **Project → Settings → Environment Variables** add:

| Variable | Required | Value |
|---|---|---|
| `DB_HOST` | yes | Database host |
| `DB_PORT` | no | Default `3306` |
| `DB_NAME` | yes | Database name |
| `DB_USER` | yes | Database user |
| `DB_PASSWORD` | yes | Database password |
| `DATABASE_URL` | alternative | `mysql://user:pass@host:port/dbname` instead of the five above |
| `DB_SSL` | no | Default `true` on Vercel; set `false` only if your database has no TLS |
| `DB_SSL_CA` | no | Path to a CA bundle; the system bundle is used by default |
| `APP_SECRET` | yes | 64 random hex characters, e.g. `php -r "echo bin2hex(random_bytes(32));"`. Never change it after launch: it signs links and encrypts stored codes |
| `SETUP_KEY` | yes | Any random string of 16+ characters; the installer asks for it once |
| `CRON_SECRET` | recommended | Random string of 16+ characters; Vercel Cron sends it to `/api/cron` |
| `BASE_URL` | recommended | Your public address, e.g. `https://facematch.example.com`. Defaults to the Vercel production domain |

Mark `DB_PASSWORD`, `APP_SECRET`, `SETUP_KEY` and `CRON_SECRET` as sensitive. Redeploy after adding or changing variables.

## 4. Run the installer once

Open `https://<your-domain>/install`, enter the `SETUP_KEY` value and create the super administrator. The installer creates the tables and then disables itself, returning 404 once a super administrator exists. Sign in at `/admin/`.

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
