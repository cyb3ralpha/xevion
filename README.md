# XEVION FaceMatch

A photo face-off platform by XEVION. Visitors create a match from 2 to 20 photos, pick a question such as "Who has the best smile?", and share a public or invite-only link. Others vote head-to-head. The site includes a role-based admin dashboard, moderation tools, and direct-sold advertising with first-party tracking.

Built with plain PHP 8.1+ and MySQL or MariaDB. It has no framework, no Node.js, no build step and no third-party services.

## Configuration

Everything the site needs, including the database connection and the first admin login, goes in one file: `config/config.php`. The file is git-ignored so passwords never reach GitHub. Its format is in **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.

Sessions are always stored in the database, so nothing about a visitor's session is written to disk.

## Deploy on Vercel

Without any setup the site runs on Vercel **without a database**: visitors create matches, vote, see results and share links, with everything saved in their own browser. For shared results across visitors, plus admin and moderation, connect a database. You need a MySQL database that accepts connections from the internet, such as TiDB Cloud Serverless, Aiven or Railway. Create `config/config.php` with its details, then either make the repository private and commit the file, or deploy from your computer with the Vercel CLI. Step-by-step instructions, limits and troubleshooting are in **[docs/VERCEL.md](docs/VERCEL.md)**.
## Run on ordinary PHP hosting or locally

1. Create an empty MySQL database (utf8mb4) and a user with full rights on it.
2. Create `config/config.php` with the database details and the first admin login, as shown in **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.
3. Make `storage/` writable by PHP.
4. Open the site. The first visit creates the tables and the admin account. Sign in at `/admin/`.

Without Apache, use PHP's built-in server: `php -S localhost:8000 router.php`.

On XAMPP, enable `extension=gd` and `extension=sodium` in `C:\xampp\php\php.ini`, then restart Apache. Both are off by default.

## Project structure

```
├── index.php            Public front controller and routes
├── admin/index.php      Admin front controller
├── api/                 Vercel entry point and PHP settings
├── app/                 Application code (not web-accessible)
│   ├── controllers/     Public and admin page handlers
│   ├── auto_setup.php   First-run setup: tables, secret, first admin
│   ├── bootstrap.php    Configuration, error handling, loading order
│   ├── uploads.php      Photo validation, processing and storage
│   ├── voting.php       Pairing and ranking engine
│   └── …                Security, sessions, ads, settings, helpers
├── assets/              CSS, JavaScript, fonts and brand images
├── config/              config.php for ordinary hosting (git-ignored)
├── database/schema.sql  Database schema, applied automatically
├── docs/                Guides and the original logo files (docs/brand/)
├── storage/             Uploaded photos and logs on ordinary hosting
├── templates/           Page templates, partials and the icon sprite
├── router.php           Router for PHP's built-in server
└── vercel.json          Vercel routing, runtime and cron
```

## Documentation

| Guide | What it covers |
|---|---|
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Ordinary PHP hosting: configuration, permissions, HTTPS, cron, backups, Nginx |
| [docs/VERCEL.md](docs/VERCEL.md) | Vercel: database, getting the config file deployed, limits, troubleshooting |
| [docs/ADMIN.md](docs/ADMIN.md) | Using the admin dashboard and roles |
| [docs/SECURITY.md](docs/SECURITY.md) | Security model and privacy notes |
| [docs/VOTING.md](docs/VOTING.md) | How pairing and ranking work |
| [CLAUDE.md](CLAUDE.md) | Working notes and change history for Claude Code |

## Brand

Colours are sampled from the XEVION logo: navy `#0C0727`, ribbon blue `#188CF1`, fold blue `#0659A5` and deep fold `#044A92`. They are defined once as CSS custom properties at the top of `assets/css/app.css` and reused by the admin stylesheet.

The original logo files are kept unchanged in `docs/brand/`. The web versions in `assets/img/brand/` are the same artwork, trimmed and scaled; nothing is recoloured or redrawn. The fonts, Outfit and Inter, are self-hosted under the SIL Open Font License, and the icons are an original line set in `templates/partials/icons.php`.
