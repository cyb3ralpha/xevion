# XEVION FaceMatch

A photo face-off site by XEVION. Someone picks a question such as "Who has the best style?", adds 2 to 20 photos and shares a link; friends vote on two photos at a time and see the standings. It is a voting game, not face recognition: nothing analyses or identifies faces.

Plain PHP 8.1+, no framework, no Composer or npm packages and no build step.

## Two ways it runs

| | Without a database (Vercel default) | With a MySQL database (`config/config.php`) |
|---|---|---|
| Create, vote, results | In the visitor's browser | On the server |
| Photos | Resized in the browser, kept in browser storage and inside share links. Never uploaded | Uploaded, re-encoded and stored in `media/uploads/` (or MySQL on Vercel) |
| Sharing | Short `/s/…` links when Vercel Blob is connected, otherwise a link that carries the match; **private** matches are encrypted and need an access code | Public listing after review, or unguessable invite links with optional code |
| Results | Per device, never combined across people | Combined for everyone, with duplicate-vote limits |
| Admin dashboard | Status, moderation by share link (block list), matches in the admin's browser, backups | Full: matches, photos, reports, ads, administrators, settings |
| Explore, reports, ads | Explore lists the device's own matches; reports go by email | Full |

Nothing is configured to get the first column: deploy the repository to Vercel as-is. To switch on its admin dashboard, set the variables in [.env.example](.env.example) in Vercel. For the second column see **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** and **[docs/VERCEL.md](docs/VERCEL.md)**.

## Run it locally

- **XAMPP:** put the folder in `htdocs` and open `http://localhost/<folder>/`. Enable `extension=gd` and `extension=sodium` in `php.ini`.
- **Built-in server:** `php -S localhost:8000 router.php`

Without `config/config.php` the site runs in browser mode. With it, the first visit creates the tables and the admin account.

## Deploy on Vercel

Import the GitHub repository into Vercel and keep the defaults; `vercel.json` sets up the PHP runtime and routes. The site works without environment variables; the admin dashboard needs `ADMIN_EMAIL` and `ADMIN_PASSWORD` (see [.env.example](.env.example)). Details, the optional database and troubleshooting: **[docs/VERCEL.md](docs/VERCEL.md)**.

## Structure

```
├── index.php, admin/index.php   Front controllers (public site, admin)
├── api/                         Vercel entry point, PHP settings, short-link function (share.js)
├── package.json                 Only for api/share.js (@vercel/blob)
├── app/                         Application code (never served)
├── assets/                      CSS, JavaScript, fonts
├── config/                      config.php for database mode (git-ignored)
├── database/schema.sql          Schema, applied automatically
├── docs/                        Deployment, Vercel, security, admin and voting guides
├── media/brand/                 XEVION logos and icons (originals/ keeps the supplied files unchanged)
├── media/uploads/               Uploaded photos in database mode (git-ignored, never served directly)
├── storage/                     Logs and the setup marker (never served)
├── templates/                   Page templates and the icon sprite
├── router.php                   Router for PHP's built-in server
└── vercel.json                  Vercel runtime, routes and cron
```

## Security and limitations

See **[docs/SECURITY.md](docs/SECURITY.md)**. In short: uploads are checked by content, re-encoded and never trusted by name; all output is escaped under a strict Content-Security-Policy; private links are encrypted. Browser mode cannot provide shared or tamper-proof vote counts, moderation or deletion of copies that friends have opened, and it does not claim to. No software is free of vulnerabilities; report issues as described in the security guide.

[CLAUDE.md](CLAUDE.md) holds working notes and the change history.

## Brand

Colours come from the XEVION logo: navy `#0C0727`, ribbon blue `#188CF1`, fold blue `#0659A5`, deep fold `#044A92`, defined once at the top of `assets/css/app.css`. Fonts (Outfit, Inter) are self-hosted under the SIL Open Font License; icons are an original line set in `templates/partials/icons.php`.